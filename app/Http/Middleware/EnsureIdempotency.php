<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\IdempotencyKey;
use App\Support\TenantContext;
use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * Stripe-style opt-in idempotency: when a state-changing request carries
 * an `Idempotency-Key` header, the first execution is recorded and any
 * replay returns the stored response instead of re-running the action
 * (e.g. a double-submitted payment, or a client retry after a network
 * drop).
 *
 * Behaviour:
 * - only POST/PUT/PATCH/DELETE with a non-empty key are tracked;
 * - the key is scoped per tenant ('platform' for public routes);
 * - the key is reserved first (unique scope+key), so concurrent
 *   same-key requests get 409 instead of double-executing;
 * - 5xx / streamed responses are not cached (a retry re-executes);
 * - keys expire after 24h and are lazily cleaned on lookup.
 */
final class EnsureIdempotency
{
    private const TTL_HOURS = 24;

    public function __construct(private readonly TenantContext $context) {}

    public function handle(Request $request, Closure $next): Response
    {
        $key = $request->header('Idempotency-Key');
        if (! $this->applies($request, $key)) {
            /** @var Response $response */
            $response = $next($request);

            return $response;
        }

        $scope = $this->context->id() ?? 'platform';

        $existing = IdempotencyKey::query()
            ->where('scope', $scope)
            ->where('key', $key)
            ->first();

        if ($existing !== null) {
            if ($existing->expires_at !== null && $existing->expires_at->isPast()) {
                $existing->delete();
            } elseif ($existing->response_status !== null) {
                return $this->replay($existing);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'A request with this Idempotency-Key is already in progress.',
                    'trace_id' => Context::get('trace_id') ?? Str::uuid()->toString(),
                ], 409);
            }
        }

        try {
            $record = IdempotencyKey::create([
                'scope' => $scope,
                'key' => $key,
                'method' => $request->method(),
                'path' => $request->path(),
                'expires_at' => now()->addHours(self::TTL_HOURS),
            ]);
        } catch (QueryException) {
            // A concurrent request reserved the same key first.
            $existing = IdempotencyKey::query()
                ->where('scope', $scope)
                ->where('key', $key)
                ->first();

            if ($existing !== null && $existing->response_status !== null) {
                return $this->replay($existing);
            }

            return response()->json([
                'success' => false,
                'message' => 'A request with this Idempotency-Key is already in progress.',
                'trace_id' => Context::get('trace_id') ?? Str::uuid()->toString(),
            ], 409);
        }

        try {
            /** @var Response $response */
            $response = $next($request);
        } catch (Throwable $e) {
            // Never leave a stuck reservation behind — allow the retry to re-execute.
            $record->delete();
            throw $e;
        }

        if ($this->cacheable($response)) {
            $record->update([
                'response_status' => $response->getStatusCode(),
                'response_body' => $response->getContent(),
            ]);
        } else {
            $record->delete();
        }

        return $response;
    }

    private function applies(Request $request, mixed $key): bool
    {
        return in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'], true)
            && is_string($key)
            && $key !== ''
            && mb_strlen($key) <= 255;
    }

    private function cacheable(Response $response): bool
    {
        return $response->getStatusCode() < 500
            && ! $response instanceof BinaryFileResponse
            && ! $response instanceof StreamedResponse;
    }

    private function replay(IdempotencyKey $record): Response
    {
        // Return a real JsonResponse so the outer force.json middleware
        // passes it through untouched (it rebuilds plain responses).
        return response()->json(
            json_decode($record->response_body ?? '', true),
            $record->response_status ?? 200,
        )->header('Idempotency-Replayed', 'true');
    }
}

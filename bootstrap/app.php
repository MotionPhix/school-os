<?php

declare(strict_types=1);

use App\Http\Middleware\EnsureEmailVerified;
use App\Http\Middleware\EnsureIdempotency;
use App\Http\Middleware\ForceJsonResponse;
use App\Http\Middleware\LogApiRequests;
use App\Http\Middleware\ResolveTenant;
use App\Http\Middleware\ResolveTenantContext;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response as HttpResponse;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prepend(ResolveTenantContext::class);

        // Reverse proxies (ngrok, nginx, Cloudflare) forward the original
        // scheme/host via X-Forwarded-*; trusting them keeps signed URLs
        // (email verification) and absolute URL generation consistent
        // behind any proxy.
        $middleware->trustProxies(at: '*');

        $middleware->alias([
            'force.json' => ForceJsonResponse::class,
            'log.api' => LogApiRequests::class,
            'verified' => EnsureEmailVerified::class,
            'resolve.tenant' => ResolveTenant::class,
            'idempotency' => EnsureIdempotency::class,
        ]);

        // There is no web login route — the SPA owns auth. API guests must
        // get a 401 JSON envelope, never a redirect to route('login')
        // (which would throw RouteNotFoundException → masked 500).
        $middleware->redirectGuestsTo(fn (Request $request): ?string => $request->is('api/*')
            ? null
            : route('login'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // API routes always render JSON. Without this, unauthenticated
        // requests that omit `Accept: application/json` take Laravel's web
        // fallback (`Authenticate::redirectTo` → route('login')) — and since
        // there is no web login route, a client would get a 500 instead of
        // the intended 401/403.
        $exceptions->shouldRenderJsonWhen(fn (Request $request): bool => $request->is('api/*'));

        // Standardized JSON error envelope for the API (handbook Ch. 20.7-20.8):
        //   { success:false, message, errors?, trace_id }
        // The trace_id matches the X-Trace-Id response header set by
        // ResolveTenantContext, so clients can correlate any error to logs.
        $exceptions->render(function (Throwable $e, Request $request): ?JsonResponse {
            if (! $request->is('api/*')) {
                return null;
            }

            $status = match (true) {
                $e instanceof ValidationException => 422,
                $e instanceof AuthenticationException => 401,
                $e instanceof ModelNotFoundException => 404,
                $e instanceof HttpExceptionInterface => $e->getStatusCode(),
                default => 500,
            };

            $message = match (true) {
                $e instanceof ValidationException => $e->getMessage(),
                $e instanceof AuthenticationException => 'Unauthenticated.',
                $e instanceof ModelNotFoundException => 'Not Found.',
                $e instanceof NotFoundHttpException => 'Not Found.',
                $e instanceof HttpExceptionInterface => $e->getMessage() !== ''
                    ? $e->getMessage()
                    : (HttpResponse::$statusTexts[$status] ?? 'Server Error.'),
                default => config('app.debug') ? $e->getMessage() : 'Server Error.',
            };

            $payload = [
                'success' => false,
                'message' => $message,
                'trace_id' => Context::get('trace_id') ?? Str::uuid()->toString(),
            ];

            if ($e instanceof ValidationException) {
                $payload['errors'] = $e->errors();
            }

            return response()->json($payload, $status);
        });
    })->create();

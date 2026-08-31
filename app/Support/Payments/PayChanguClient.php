<?php

declare(strict_types=1);

namespace App\Support\Payments;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Thin client for PayChangu standard checkout + transaction verification.
 *
 * Docs: https://developer.paychangu.com/docs/standard-checkout
 *       https://developer.paychangu.com/docs/transaction-verification
 *
 * Amounts are exchanged in MAJOR units (the PayChangu API takes e.g. 100
 * for MWK 100) — callers convert from minor units.
 */
class PayChanguClient
{
    private readonly string $baseUrl;

    private readonly string $secretKey;

    public function __construct(
        string $baseUrl = '',
        string $secretKey = '',
    ) {
        $configuredBase = config('billing.paychangu.base_url');
        $configuredKey = config('billing.paychangu.secret_key');

        $this->baseUrl = $baseUrl !== '' ? $baseUrl : (is_string($configuredBase) ? $configuredBase : 'https://api.paychangu.com');
        $this->secretKey = $secretKey !== '' ? $secretKey : (is_string($configuredKey) ? $configuredKey : '');
    }

    private function http(): PendingRequest
    {
        return Http::baseUrl($this->baseUrl)
            ->acceptJson()
            ->withToken($this->secretKey)
            ->timeout(20)
            ->throw(function (Response $response): never {
                $message = $response->json('message');
                $detail = is_string($message) ? $message : 'unknown error';

                throw new RuntimeException(sprintf('PayChangu API error (%d): %s', $response->status(), $detail));
            });
    }

    /**
     * Create a hosted checkout session. Returns [checkout_url, tx_ref].
     *
     * @param  array<string, mixed>  $params  amount (major units), currency, email, callback_url, return_url, tx_ref, customization
     * @return array{checkout_url: string, tx_ref: string}
     */
    public function createCheckout(array $params): array
    {
        $response = $this->http()->post('/payment', $params)->json();

        $data = is_array($response) && is_array($response['data'] ?? null) ? $response['data'] : null;
        $inner = is_array($data['data'] ?? null) ? $data['data'] : null;

        $checkoutUrl = is_string($data['checkout_url'] ?? null) ? $data['checkout_url'] : '';
        $txRef = is_string($inner['tx_ref'] ?? null) ? $inner['tx_ref'] : '';

        if ($checkoutUrl === '' || $txRef === '') {
            throw new RuntimeException('PayChangu checkout did not return a checkout_url/tx_ref.');
        }

        return ['checkout_url' => $checkoutUrl, 'tx_ref' => $txRef];
    }

    /**
     * Verify a transaction by reference (server-side, authoritative).
     *
     * @return array{status: string, amount_minor: int, currency: string}|null
     */
    public function verify(string $txRef): ?array
    {
        $response = $this->http()->get("/verify-payment/{$txRef}")->json();

        $data = is_array($response) && is_array($response['data'] ?? null) ? $response['data'] : null;
        if ($data === null) {
            throw new RuntimeException('PayChangu verification returned no data.');
        }

        $amount = $data['amount'] ?? 0;

        return [
            'status' => is_string($data['status'] ?? null) ? $data['status'] : 'unknown',
            'amount_minor' => (int) round((is_numeric($amount) ? (float) $amount : 0.0) * 100),
            'currency' => is_string($data['currency'] ?? null) ? $data['currency'] : 'MWK',
        ];
    }
}

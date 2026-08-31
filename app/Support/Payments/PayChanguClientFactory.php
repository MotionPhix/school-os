<?php

declare(strict_types=1);

namespace App\Support\Payments;

/**
 * Container-resolved factory so callers never `new` a client directly —
 * services stay testable (the factory can be mocked to return a fake
 * client) and key handling stays in one place.
 */
class PayChanguClientFactory
{
    public function make(string $secretKey): PayChanguClient
    {
        return new PayChanguClient($secretKey);
    }

    public function platform(): PayChanguClient
    {
        return new PayChanguClient;
    }
}

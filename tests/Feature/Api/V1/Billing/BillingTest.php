<?php

declare(strict_types=1);

use App\Models\PlatformInvoice;
use App\Models\PlatformPayment;
use App\Models\User;
use App\Support\Payments\PayChanguClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->tenant = makeTenant();
    bindTenant($this->tenant);

    $this->user = User::factory()->create();
    makeMember($this->user, $this->tenant, ['billing.payments.read', 'billing.payments.write']);
    Sanctum::actingAs($this->user);
});

it('returns the billing overview and issues the current-period invoice on first read', function (): void {
    $this->getJson('/api/v1/billing/overview')
        ->assertOk()
        ->assertJsonPath('data.period', now()->format('Y-m'))
        ->assertJsonPath('data.status', 'pending')
        ->assertJsonPath('data.amount_minor', (int) config('billing.monthly_fee_minor', 50000))
        ->assertJsonPath('data.currency', 'MWK')
        ->assertJsonPath('data.payments', []);

    expect(PlatformInvoice::count())->toBe(1);
});

it('starts a PayChangu checkout for the current invoice', function (): void {
    $this->mock(PayChanguClient::class)
        ->shouldReceive('createCheckout')
        ->once()
        ->andReturn(['checkout_url' => 'https://checkout.paychangu.com/abc', 'tx_ref' => 'scp-123']);

    $invoice = PlatformInvoice::factory()->create(['tenant_id' => $this->tenant->id, 'status' => 'pending']);

    $this->postJson("/api/v1/billing/invoices/{$invoice->id}/checkout")
        ->assertOk()
        ->assertJsonPath('data.checkout_url', 'https://checkout.paychangu.com/abc')
        ->assertJsonPath('data.tx_ref', 'scp-123');

    $payment = PlatformPayment::query()->first();
    expect($payment)->not->toBeNull()
        ->and($payment->tx_ref)->toBe('scp-123')
        ->and($payment->checkout_url)->toBe('https://checkout.paychangu.com/abc')
        ->and($payment->status)->toBe('pending');
});

it('reuses the pending payment on repeated checkouts', function (): void {
    $this->mock(PayChanguClient::class)
        ->shouldReceive('createCheckout')
        ->twice()
        ->andReturn(['checkout_url' => 'https://checkout.paychangu.com/abc', 'tx_ref' => 'scp-123']);

    $invoice = PlatformInvoice::factory()->create(['tenant_id' => $this->tenant->id, 'status' => 'pending']);

    $this->postJson("/api/v1/billing/invoices/{$invoice->id}/checkout")->assertOk();
    $this->postJson("/api/v1/billing/invoices/{$invoice->id}/checkout")->assertOk();

    expect(PlatformPayment::count())->toBe(1);
});

it('settles the invoice when verification succeeds', function (): void {
    $this->mock(PayChanguClient::class)
        ->shouldReceive('verify')
        ->once()
        ->andReturn(['status' => 'success', 'amount_minor' => 50000, 'currency' => 'MWK']);

    $invoice = PlatformInvoice::factory()->create(['tenant_id' => $this->tenant->id, 'status' => 'pending', 'amount_minor' => 50000]);
    $payment = PlatformPayment::create([
        'tenant_id' => $this->tenant->id,
        'platform_invoice_id' => $invoice->id,
        'tx_ref' => 'scp-123',
        'amount_minor' => 50000,
        'currency' => 'MWK',
        'status' => 'pending',
    ]);

    $this->postJson("/api/v1/billing/payments/{$payment->id}/refresh")
        ->assertOk()
        ->assertJsonPath('data.status', 'succeeded');

    expect($invoice->fresh()->status)->toBe('paid')
        ->and($invoice->fresh()->paid_at)->not->toBeNull();
});

it('marks the payment failed when verification fails and leaves the invoice open', function (): void {
    $this->mock(PayChanguClient::class)
        ->shouldReceive('verify')
        ->once()
        ->andReturn(['status' => 'failed', 'amount_minor' => 0, 'currency' => 'MWK']);

    $invoice = PlatformInvoice::factory()->create(['tenant_id' => $this->tenant->id, 'status' => 'pending']);
    $payment = PlatformPayment::create([
        'tenant_id' => $this->tenant->id,
        'platform_invoice_id' => $invoice->id,
        'tx_ref' => 'scp-456',
        'amount_minor' => 50000,
        'currency' => 'MWK',
        'status' => 'pending',
    ]);

    $this->postJson("/api/v1/billing/payments/{$payment->id}/refresh")
        ->assertOk()
        ->assertJsonPath('data.status', 'failed');

    expect($invoice->fresh()->status)->toBe('pending')
        ->and($invoice->fresh()->paid_at)->toBeNull();
});

it('settles the invoice through the public webhook (re-verified server-side)', function (): void {
    $this->mock(PayChanguClient::class)
        ->shouldReceive('verify')
        ->once()
        ->andReturn(['status' => 'success', 'amount_minor' => 50000, 'currency' => 'MWK']);

    $invoice = PlatformInvoice::factory()->create(['tenant_id' => $this->tenant->id, 'status' => 'pending']);
    PlatformPayment::create([
        'tenant_id' => $this->tenant->id,
        'platform_invoice_id' => $invoice->id,
        'tx_ref' => 'scp-789',
        'amount_minor' => 50000,
        'currency' => 'MWK',
        'status' => 'pending',
    ]);

    // No auth, no tenant context — like a real PayChangu IPN call.
    $this->postJson('/api/v1/billing/webhooks/paychangu', ['tx_ref' => 'scp-789'])
        ->assertOk()
        ->assertJsonPath('status', 'succeeded');

    expect($invoice->fresh()->status)->toBe('paid');
});

it('denies billing access without the billing permissions', function (): void {
    $other = User::factory()->create();
    makeMember($other, $this->tenant, ['finance.invoices.read']);
    Sanctum::actingAs($other);

    $this->getJson('/api/v1/billing/overview')->assertStatus(403);
});

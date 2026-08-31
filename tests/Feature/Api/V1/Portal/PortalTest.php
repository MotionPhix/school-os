<?php

declare(strict_types=1);

use App\Domains\Finance\Services\IssueInvoice;
use App\Enums\InvoiceStatus;
use App\Models\Campus;
use App\Models\Guardian;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\Payment;
use App\Models\PortalPaymentIntent;
use App\Models\Student;
use App\Models\TenantPaymentProvider;
use App\Models\User;
use App\Support\Payments\PayChanguClient;
use App\Support\Payments\PayChanguClientFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

/** Builds a tenant with a campus, an academic year and an issued invoice for the student. */
function portalSetup(string $label = 'Portal School'): array
{
    $tenant = makeTenant();
    bindTenant($tenant);

    $campus = Campus::create([
        'tenant_id' => $tenant->id,
        'name' => 'Main Campus',
        'code' => 'MAIN',
        'status' => 'operational',
        'address_line' => '1 Test Road',
        'city' => 'Lilongwe',
        'region' => 'Central',
        'timezone' => 'Africa/Blantyre',
    ]);

    $student = Student::create([
        'tenant_id' => $tenant->id,
        'campus_id' => $campus->id,
        'admission_number' => 'STD-'.Str::upper(Str::random(6)),
        'full_name' => 'Chisomo Banda',
        'avatar_initials' => 'CB',
        'date_of_birth' => '2013-04-01',
        'stage' => 'primary',
        'grade_label' => 'Grade 5',
        'status' => 'enrolled',
    ]);

    $guardian = Guardian::create([
        'tenant_id' => $tenant->id,
        'full_name' => 'Mrs. Banda',
        'primary_phone' => '+265990000000',
        'avatar_initials' => 'MB',
        'status' => 'active',
    ]);

    $user = User::factory()->create(['name' => 'Mrs. Banda']);
    $guardian->update(['user_id' => $user->id]);
    $guardian->students()->attach($student->id, ['tenant_id' => $tenant->id, 'relationship' => 'mother', 'is_primary' => true]);

    $invoice = Invoice::create([
        'tenant_id' => $tenant->id,
        'number' => 'INV-'.mb_strtoupper(Str::random(8)),
        'student_id' => $student->id,
        'student_name' => $student->full_name,
        'student_initials' => 'CB',
        'grade_label' => 'Grade 5',
        'guardian_name' => 'Mrs. Banda',
        'academic_year_label' => '2026',
        'term_label' => 'Term 1',
        'issued_on' => now()->toDateString(),
        'due_on' => now()->addDays(14)->toDateString(),
        'currency' => 'MWK',
        'subtotal_minor' => 10000,
        'discount_minor' => 0,
        'total_minor' => 10000,
        'balance_minor' => 10000,
    ]);
    InvoiceLine::create([
        'tenant_id' => $tenant->id,
        'invoice_id' => $invoice->id,
        'description' => 'Term 1 fees',
        'category' => 'tuition',
        'quantity' => 1,
        'unit_amount_minor' => 10000,
        'amount_minor' => 10000,
        'position' => 1,
    ]);
    app(IssueInvoice::class)->handle($invoice);

    return compact('tenant', 'student', 'guardian', 'user', 'invoice');
}

it('lets a guardian see their students with open balances', function (): void {
    $s = portalSetup();
    Sanctum::actingAs($s['user']);

    $this->getJson('/api/v1/portal/students')
        ->assertOk()
        ->assertJsonPath('data.0.id', $s['student']->id)
        ->assertJsonPath('data.0.full_name', 'Chisomo Banda')
        ->assertJsonPath('data.0.open_balance_minor', 10000);
});

it('returns the open invoices for one of the guardian students', function (): void {
    $s = portalSetup();
    Sanctum::actingAs($s['user']);

    $this->getJson("/api/v1/portal/students/{$s['student']->id}/invoices")
        ->assertOk()
        ->assertJsonPath('data.0.id', $s['invoice']->id)
        ->assertJsonPath('data.0.balance_minor', 10000);
});

it('blocks guardians from other tenants students', function (): void {
    $s = portalSetup();
    $other = portalSetup('Other School');
    Sanctum::actingAs($s['user']);

    $this->getJson("/api/v1/portal/students/{$other['student']->id}/invoices")->assertStatus(403);
});

it('starts a checkout with the TENANT credentials when a provider is connected', function (): void {
    $s = portalSetup();
    TenantPaymentProvider::create([
        'tenant_id' => $s['tenant']->id,
        'secret_key' => 'tenant-secret-key',
        'public_key' => 'tenant-public-key',
        'mode' => 'test',
        'is_active' => true,
    ]);
    Sanctum::actingAs($s['user']);

    $client = $this->mock(PayChanguClient::class);
    $client->shouldReceive('createCheckout')
        ->once()
        ->withArgs(fn (array $params): bool => $params['amount'] === 100 && $params['currency'] === 'MWK')
        ->andReturn(['checkout_url' => 'https://checkout.paychangu.com/portal', 'tx_ref' => 'prt-123']);
    $this->mock(PayChanguClientFactory::class)->shouldReceive('make')->once()->andReturn($client);

    $this->postJson("/api/v1/portal/invoices/{$s['invoice']->id}/checkout")
        ->assertOk()
        ->assertJsonPath('data.checkout_url', 'https://checkout.paychangu.com/portal')
        ->assertJsonPath('data.tx_ref', 'prt-123');

    expect(PortalPaymentIntent::count())->toBe(1);
});

it('refuses checkout when the school has no payment provider', function (): void {
    $s = portalSetup();
    Sanctum::actingAs($s['user']);

    $this->postJson("/api/v1/portal/invoices/{$s['invoice']->id}/checkout")->assertStatus(422);
});

it('settles the invoice through the webhook (verified with tenant keys)', function (): void {
    $s = portalSetup();
    $provider = TenantPaymentProvider::create([
        'tenant_id' => $s['tenant']->id,
        'secret_key' => 'tenant-secret-key',
        'mode' => 'test',
        'is_active' => true,
    ]);
    $intent = PortalPaymentIntent::create([
        'tenant_id' => $s['tenant']->id,
        'invoice_id' => $s['invoice']->id,
        'guardian_user_id' => $s['user']->id,
        'tx_ref' => 'prt-999',
        'amount_minor' => 10000,
        'currency' => 'MWK',
        'status' => 'pending',
    ]);

    $client = $this->mock(PayChanguClient::class);
    $client->shouldReceive('verify')
        ->once()
        ->andReturn(['status' => 'success', 'amount_minor' => 10000, 'currency' => 'MWK']);
    $this->mock(PayChanguClientFactory::class)->shouldReceive('make')->once()->andReturn($client);

    $this->postJson('/api/v1/portal/webhooks/paychangu', ['tx_ref' => 'prt-999'])
        ->assertOk()
        ->assertJsonPath('status', 'succeeded');

    $invoice = $s['invoice']->fresh();
    expect($invoice->status)->toBe(InvoiceStatus::Paid)
        ->and($invoice->paid_minor)->toBe(10000)
        ->and(Payment::where('invoice_id', $invoice->id)->count())->toBe(1)
        ->and($intent->fresh()->status)->toBe('succeeded');
});

it('keeps the provider settings private and round-trips the encrypted keys', function (): void {
    $s = portalSetup();
    $staff = User::factory()->create();
    makeMember($staff, $s['tenant'], ['billing.payments.read', 'billing.payments.write']);
    Sanctum::actingAs($staff);

    $this->putJson('/api/v1/billing/provider', [
        'secret_key' => 'sk_test_abc',
        'public_key' => 'pk_test_abc',
        'mode' => 'test',
    ])->assertOk()
        ->assertJsonPath('data.is_active', true)
        ->assertJsonPath('data.has_secret_key', true)
        ->assertJsonPath('data.secret_key', null); // never echoed

    $row = TenantPaymentProvider::query()->first();
    expect($row->decryptedSecretKey())->toBe('sk_test_abc')
        ->and($row->decryptedPublicKey())->toBe('pk_test_abc');

    $this->getJson('/api/v1/billing/provider')
        ->assertOk()
        ->assertJsonPath('data.provider', 'paychangu')
        ->assertJsonMissingPath('data.secret_key');
});

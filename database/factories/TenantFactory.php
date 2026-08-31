<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Tenant>
 */
class TenantFactory extends Factory
{
    protected $model = Tenant::class;

    public function definition(): array
    {
        return [
            'slug' => 'tenant-'.Str::uuid()->toString(),
            'name' => 'Test School',
            'legal_name' => 'Test School Ltd',
            'country_code' => 'MW',
            'timezone' => 'Africa/Blantyre',
            'currency_code' => 'MWK',
            'status' => 'active',
            'tier' => 'institution',
            'created_by' => null,
        ];
    }
}

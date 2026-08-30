<?php

declare(strict_types=1);

use App\Enums\RoleScope;
use App\Models\CourseSection;
use App\Models\Role;
use App\Models\Student;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(TestCase::class)
 // ->use(Illuminate\Foundation\Testing\RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', fn () => $this->toBe(1));

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function makeTenant(array $attributes = []): Tenant
{
    return Tenant::create(array_merge([
        'slug' => 'tenant-'.Str::uuid()->toString(),
        'name' => 'Test School',
        'legal_name' => 'Test School Ltd',
        'country_code' => 'MW',
        'timezone' => 'Africa/Blantyre',
        'currency_code' => 'MWK',
    ], $attributes));
}

/**
 * Creates a role owned by `$tenant`. Pass `['tenant_id' => null, ...]` via
 * `$attributes` for a platform role.
 */
function makeRole(Tenant $tenant, array $permissionKeys = [], array $attributes = []): Role
{
    return Role::create(array_merge([
        'tenant_id' => $tenant->id,
        'key' => 'role-'.Str::uuid()->toString(),
        'name' => 'Test Role',
        'description' => 'Test role',
        'scope' => RoleScope::Tenant,
        'is_system' => false,
        'permission_keys' => $permissionKeys,
    ], $attributes));
}

/**
 * Attaches `$user` to `$tenant` with a role carrying `$permissionKeys`
 * (or the given role) and makes that tenant the user's active one.
 */
function makeMember(User $user, Tenant $tenant, array $permissionKeys = [], ?Role $role = null): Role
{
    $role ??= makeRole($tenant, $permissionKeys);

    TenantMembership::create([
        'user_id' => $user->id,
        'tenant_id' => $tenant->id,
        'role_ids' => [$role->id],
        'joined_at' => now(),
    ]);

    $user->update(['active_tenant_id' => $tenant->id]);

    return $role;
}

function bindTenant(Tenant $tenant): void
{
    app(TenantContext::class)->set($tenant->id);
}

/**
 * The course_enrollments pivot has a UUID primary key, so Eloquent's
 * attach() cannot populate it — insert the row directly.
 */
function enrollDirectly(CourseSection $section, Student $student, Tenant $tenant): void
{
    DB::table('course_enrollments')->insert([
        'id' => Str::uuid()->toString(),
        'tenant_id' => $tenant->id,
        'course_section_id' => $section->id,
        'student_id' => $student->id,
        'enrolled_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

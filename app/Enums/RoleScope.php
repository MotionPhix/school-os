<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Reach of a role.
 *
 * - Platform: cross-tenant, SchoolOS staff only.
 * - Tenant:   scoped to the tenant that owns the role.
 * - Campus:   scoped to specific campuses within the tenant.
 *             TODO(Slice 2 Institution): once `campuses` exists,
 *             wire a `role_campus` pivot for campus-scoped roles.
 * - Guardian: scoped to the students linked to the guardian
 *             through `guardian_student`.
 */
enum RoleScope: string
{
    case Platform = 'platform';
    case Tenant = 'tenant';
    case Campus = 'campus';
    case Guardian = 'guardian';

    /** @return array<int, array{value:string,label:string}> */
    public static function options(): array
    {
        return array_map(fn (self $c) => ['value' => $c->value, 'label' => $c->label()], self::cases());
    }

    public function label(): string
    {
        return match ($this) {
            self::Platform => 'Platform',
            self::Tenant => 'Tenant',
            self::Campus => 'Campus',
            self::Guardian => 'Guardian',
        };
    }
}

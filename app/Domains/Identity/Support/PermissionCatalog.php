<?php

declare(strict_types=1);

namespace App\Domains\Identity\Support;

/**
 * Read-only accessor over the permission catalog.
 *
 * The catalog is composed from:
 *   1. config('identity.permissions')                 — Identity keys.
 *   2. config('<capability>.permissions') for each capability listed in
 *      config('identity.registered_capabilities')     — one entry per slice.
 *
 * A slice registers itself by adding its short name (e.g. "institution")
 * to the `registered_capabilities` array in config/identity.php AND
 * shipping a `config/<slice>.php` file with its own `permissions` block.
 */
final class PermissionCatalog
{
    /** @return list<array{key:string,domain:string,label:string,description:string}> */
    public function all(): array
    {
        $catalog = (array) config('identity.permissions', []);

        foreach ((array) config('identity.registered_capabilities', []) as $slice) {
            foreach ((array) config("{$slice}.permissions", []) as $perm) {
                $catalog[] = $perm;
            }
        }

        return array_values($catalog);
    }

    /** @return list<string> */
    public function keys(): array
    {
        return array_values(array_unique(array_column($this->all(), 'key')));
    }

    public function has(string $key): bool
    {
        return in_array($key, $this->keys(), true);
    }

    /**
     * Filter an incoming list of keys down to those the catalog recognises.
     * Silently drops unknown keys — useful for forward-compat when a role
     * has been assigned a key from a capability that hasn't landed yet.
     *
     * @param  list<string>  $keys
     * @return list<string>
     */
    public function filterKnown(array $keys): array
    {
        return array_values(array_intersect($keys, $this->keys()));
    }
}

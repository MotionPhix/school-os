<?php

declare(strict_types=1);

namespace App\Domains\People\Services;

use App\Domains\People\Events\GuardianCreated;
use App\Domains\People\Events\GuardianUpdated;
use App\Domains\People\Support\AvatarInitials;
use App\Models\Guardian;
use Illuminate\Support\Facades\DB;

final class WriteGuardian
{
    /**
     * @param  array<string,mixed>  $data
     */
    public function handle(array $data, ?Guardian $existing = null): Guardian
    {
        return DB::transaction(function () use ($data, $existing): Guardian {
            $creating = $existing === null;
            $guardian = $existing ?? new Guardian;

            if (isset($data['full_name'])) {
                $data['avatar_initials'] = AvatarInitials::from((string) $data['full_name']);
            }

            $guardian->fill($data);
            $guardian->save();
            $guardian->refresh();

            $creating
                ? GuardianCreated::dispatch($guardian)
                : GuardianUpdated::dispatch($guardian);

            return $guardian;
        });
    }
}

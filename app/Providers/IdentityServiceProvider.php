<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domains\Identity\Events\UserInvited;
use App\Listeners\SendInvitationEmail;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

/**
 * Identity capability wiring (side-effect listeners).
 *
 * Register in bootstrap/providers.php:
 *   App\Providers\IdentityServiceProvider::class,
 */
final class IdentityServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Event::listen(UserInvited::class, SendInvitationEmail::class);
    }
}

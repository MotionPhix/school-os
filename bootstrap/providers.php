<?php

declare(strict_types=1);
use App\Providers\AppServiceProvider;
use App\Providers\AuditServiceProvider;
use App\Providers\AuthorizationServiceProvider;
use App\Providers\BroadcastServiceProvider;
use App\Providers\IdentityServiceProvider;
use App\Providers\NotificationServiceProvider;

return [
    AppServiceProvider::class,
    AuditServiceProvider::class,
    AuthorizationServiceProvider::class,
    BroadcastServiceProvider::class,
    IdentityServiceProvider::class,
    NotificationServiceProvider::class,
];

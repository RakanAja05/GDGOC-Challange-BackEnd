<?php

namespace App\Providers;

use App\Events\MessageCreated;
use App\Listeners\HandleMessageCreated;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        MessageCreated::class => [
            HandleMessageCreated::class,
        ],
    ];
}

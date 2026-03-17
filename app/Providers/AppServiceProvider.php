<?php

namespace App\Providers;

use Illuminate\Database\Events\MigrationsEnded;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use App\Models\Deal;
use App\Models\DealSettlement;
use App\Models\Property;
use App\Observers\DealObserver;
use App\Observers\DealSettlementObserver;
use App\Observers\PropertyObserver;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Deal::observe(DealObserver::class);
        DealSettlement::observe(DealSettlementObserver::class);
        Property::observe(PropertyObserver::class);

        // Auto-sync DocuPerfect named fields after every migration run
        Event::listen(MigrationsEnded::class, function () {
            Artisan::call('docuperfect:sync-fields');
        });

        // @permission('permission_key') ... @endpermission
        Blade::if('permission', function (string $permissionKey) {
            return auth()->check() && auth()->user()->hasPermission($permissionKey);
        });
    }
}

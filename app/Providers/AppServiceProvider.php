<?php

namespace App\Providers;

use App\Models\Music;
use App\Observers\MusicObserver;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Music::observe(MusicObserver::class);
    }
}

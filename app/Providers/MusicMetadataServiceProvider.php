<?php

namespace App\Providers;

use App\Contracts\MusicMetadataServiceInterface;
use App\Services\DeezerService;
use App\Services\MusicBrainzService;
use App\Services\SpotifyService;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Config;

class MusicMetadataServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->bind(MusicMetadataServiceInterface::class, function ($app) {
            $service = Config::get('music.metadata_service', 'musicbrainz');

            return match ($service) {
                'deezer' => $app->make(DeezerService::class),
                'spotify' => $app->make(SpotifyService::class),
                'musicbrainz' => $app->make(MusicBrainzService::class),
                default => $app->make(MusicBrainzService::class),
            };
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}

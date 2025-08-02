<?php

namespace App\Jobs;

use App\Models\Music;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class TriggerMetadataSearchJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public Music $music)
    {
    }

    public function handle(): void
    {
        // Always dispatch MusicBrainz and Deezer (no API keys required)
        dispatch(new SearchMusicMetadataJob($this->music, 'musicbrainz'));
        dispatch(new SearchMusicMetadataJob($this->music, 'deezer'));
        
        // Only dispatch Spotify if API credentials are configured
        if (config('services.spotify.client_id') && config('services.spotify.client_secret')) {
            dispatch(new SearchMusicMetadataJob($this->music, 'spotify'));
        }
        
        // Only dispatch Last.fm if API key is configured
        if (config('services.lastfm.api_key')) {
            dispatch(new SearchMusicMetadataJob($this->music, 'lastfm'));
        }
    }
}

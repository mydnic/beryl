<?php

namespace App\Jobs;

use App\Models\Music;
use App\Services\MusicBrainzService;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SearchMusicMetadataJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public Music $music)
    {
    }

    public function handle(MusicBrainzService $musicBrainzService): void
    {
        // Recherche basée sur les métadonnées existantes
        $searchParams = $this->prepareSearchParams();

        if (empty($searchParams)) {
            Log::info('Insufficient metadata for MusicBrainz search', ['music_id' => $this->music->id]);
            return;
        }

        $searchResults = $musicBrainzService->searchRecording($searchParams);

        if (empty($searchResults) || empty($searchResults['recordings'])) {
            Log::info('No MusicBrainz results found', [
                'music_id' => $this->music->id,
                'search_params' => $searchParams
            ]);
            $this->music->musicbrainz_no_result = true;
            $this->music->save();
            return;
        }

        // Store raw API results
        $apiResults = $this->music->api_results ?? [];
        $apiResults['musicbrainz'] = $searchResults;
        $this->music->api_results = $apiResults;

        // Process and store standardized results
        $this->processAndStoreResults($searchResults['recordings']);

        $this->music->save();

        sleep(60);
    }

    /**
     * Process and store standardized results from MusicBrainz
     * 
     * @param array $recordings
     * @return void
     */
    protected function processAndStoreResults(array $recordings): void
    {
        $results = $this->music->results ?? [];
        
        foreach ($recordings as $recording) {
            $artist = !empty($recording['artist-credit'][0]['name']) 
                ? $recording['artist-credit'][0]['name'] 
                : null;
                
            $album = !empty($recording['releases'][0]['title']) 
                ? $recording['releases'][0]['title'] 
                : null;
                
            $releaseYear = !empty($recording['first-release-date']) 
                ? substr($recording['first-release-date'], 0, 4) 
                : null;
                
            $results[] = [
                'title' => $recording['title'] ?? null,
                'artist' => $artist,
                'album' => $album,
                'release_year' => $releaseYear,
                'api_source' => 'musicbrainz',
                'source_id' => $recording['id'] ?? null,
            ];
        }
        
        $this->music->results = $results;
    }

    /**
     * Prépare les paramètres de recherche à partir des métadonnées existantes
     *
     * @return array
     */
    protected function prepareSearchParams(): array
    {
        $params = [];

        // Priorité aux métadonnées les plus importantes pour une recherche précise
        if (!empty($this->music->title)) {
            $params['recording'] = $this->music->title;
        }

        if (!empty($this->music->artist)) {
            $params['artist'] = $this->music->artist;
        }

        if (!empty($this->music->album)) {
            $params['release'] = $this->music->album;
        }

        return $params;
    }
}

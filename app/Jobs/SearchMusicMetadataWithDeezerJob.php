<?php

namespace App\Jobs;

use App\Models\Music;
use App\Services\DeezerService;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SearchMusicMetadataWithDeezerJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public Music $music)
    {
    }

    public function handle(DeezerService $deezerService): void
    {
        // Search based on existing metadata
        $searchParams = $this->prepareSearchParams();

        if (empty($searchParams)) {
            Log::info('Insufficient metadata for Deezer search', ['music_id' => $this->music->id]);
            return;
        }

        $searchResults = $deezerService->searchTrack($searchParams);

        if (empty($searchResults) || empty($searchResults['data'])) {
            Log::info('No Deezer results found', [
                'music_id' => $this->music->id,
                'search_params' => $searchParams
            ]);
            $this->music->deezer_no_result = true;
            $this->music->save();
            return;
        }

        // Store raw API results
        $apiResults = $this->music->api_results ?? [];
        $apiResults['deezer'] = $searchResults;
        $this->music->api_results = $apiResults;

        // Process and store standardized results
        $this->processAndStoreResults($searchResults['data']);

        $this->music->save();
    }

    /**
     * Process and store standardized results from Deezer
     * 
     * @param array $tracks
     * @return void
     */
    protected function processAndStoreResults(array $tracks): void
    {
        $results = $this->music->results ?? [];
        
        foreach ($tracks as $track) {
            $releaseYear = isset($track['album']['release_date']) 
                ? substr($track['album']['release_date'], 0, 4) 
                : null;
                
            $results[] = [
                'title' => $track['title'] ?? null,
                'artist' => $track['artist']['name'] ?? null,
                'album' => $track['album']['title'] ?? null,
                'release_year' => $releaseYear,
                'api_source' => 'deezer',
                'source_id' => $track['id'] ?? null,
                'preview_url' => $track['preview'] ?? null,
                'cover_url' => $track['album']['cover_medium'] ?? null
            ];
        }
        
        $this->music->results = $results;
    }

    /**
     * Prepare search parameters from existing metadata
     *
     * @return array
     */
    protected function prepareSearchParams(): array
    {
        $params = [];

        // Prioritize the most important metadata for accurate search
        if (!empty($this->music->title)) {
            $params['title'] = $this->music->title;
        }

        if (!empty($this->music->artist)) {
            $params['artist'] = $this->music->artist;
        }

        if (!empty($this->music->album)) {
            $params['album'] = $this->music->album;
        }

        return $params;
    }
}

<?php

namespace App\Jobs;

use App\Events\MusicResultFetchedEvent;
use App\Models\Music;
use App\Services\DeezerService;
use App\Services\MusicBrainzService;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;

class SearchMusicMetadataFromFilenameJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public Music $music)
    {
    }

    public function handle(MusicBrainzService $musicBrainzService, DeezerService $deezerService): void
    {
        // Get cleaned filename for search
        $cleanedFilename = $this->getCleanedFilename();

        if (empty($cleanedFilename) || strlen($cleanedFilename) < 3) {
            Log::info("Filename too short or empty for search", [
                'music_id' => $this->music->id,
                'filepath' => $this->music->filepath,
                'cleaned_filename' => $cleanedFilename
            ]);
            return;
        }

        // Determine which service to use based on configuration
        $service = Config::get('music.metadata_service', 'musicbrainz');

        // Prepare search parameters using full filename as free search
        $searchParams = $this->prepareSearchParamsFromFilename($service, $cleanedFilename);

        // Search using the configured service
        $searchResults = $this->performSearch($service, $searchParams, $musicBrainzService, $deezerService);

        if (empty($searchResults)) {
            Log::info("No {$service} results found for filename search", [
                'music_id' => $this->music->id,
                'search_params' => $searchParams,
                'cleaned_filename' => $cleanedFilename
            ]);

            $this->music->save();
            return;
        }

        // Store raw API results with filename prefix
        $apiResults = $this->music->api_results ?? [];
        $apiResults[$service . '_filename'] = $searchResults;
        $this->music->api_results = $apiResults;

        // Process and store standardized results
        $this->processAndStoreResults($service, $searchResults);

        $this->music->save();

        event(new MusicResultFetchedEvent($this->music));

        // MusicBrainz requires throttling
        if ($service === 'musicbrainz') {
            $throttleTime = Config::get('music.musicbrainz.throttle_time', 1);
            sleep($throttleTime);
        }
    }

    /**
     * Prepare search parameters from filename info only
     *
     * @param string $service
     * @param string $cleanedFilename
     * @return array
     */
    protected function prepareSearchParamsFromFilename(string $service, string $cleanedFilename): array
    {
        $params = [];

        // Use cleaned filename as free search
        if (!empty($cleanedFilename)) {
            if ($service === 'musicbrainz') {
                // For MusicBrainz, use the filename as a general recording search
                $params['recording'] = $cleanedFilename;
            } else {
                // For Deezer, use the filename as title search (it will be combined in buildQueryString)
                $params['title'] = $cleanedFilename;
            }
        }

        return $params;
    }

    /**
     * Perform the actual search using the specified service
     *
     * @param string $service
     * @param array $searchParams
     * @param MusicBrainzService $musicBrainzService
     * @param DeezerService $deezerService
     * @return array
     */
    protected function performSearch(string $service, array $searchParams, MusicBrainzService $musicBrainzService, DeezerService $deezerService): array
    {
        try {
            if ($service === 'musicbrainz') {
                $results = $musicBrainzService->searchRecording($searchParams);
                return empty($results) || empty($results['recordings']) ? [] : $results;
            } else {
                $results = $deezerService->searchTrack($searchParams);
                return empty($results) || empty($results['data']) ? [] : $results;
            }
        } catch (Exception $e) {
            Log::error("Error searching {$service} with filename data", [
                'music_id' => $this->music->id,
                'search_params' => $searchParams,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Process and store standardized results from the search
     *
     * @param string $service
     * @param array $searchResults
     * @return void
     */
    protected function processAndStoreResults(string $service, array $searchResults): void
    {
        $results = $this->music->results ?? [];

        if ($service === 'musicbrainz') {
            $results['musicbrainz_filename'] = $this->processMusicBrainzResults($searchResults);
        } else {
            $results['deezer_filename'] = $this->processDeezerResults($searchResults);
        }

        $this->music->results = $results;
    }

    /**
     * Process MusicBrainz search results into standardized format
     *
     * @param array $searchResults
     * @return array
     */
    protected function processMusicBrainzResults(array $searchResults): array
    {
        $processed = [];

        foreach ($searchResults['recordings'] ?? [] as $recording) {
            $processed[] = [
                'title' => $recording['title'] ?? null,
                'artist' => $recording['artist-credit'][0]['name'] ?? null,
                'album' => $recording['releases'][0]['title'] ?? null,
                'release_year' => isset($recording['releases'][0]['date'])
                    ? (int) substr($recording['releases'][0]['date'], 0, 4)
                    : null,
                'score' => $recording['score'] ?? 0,
                'source' => 'musicbrainz_filename'
            ];
        }

        return $processed;
    }

    /**
     * Process Deezer search results into standardized format
     *
     * @param array $searchResults
     * @return array
     */
    protected function processDeezerResults(array $searchResults): array
    {
        $processed = [];

        foreach ($searchResults['data'] ?? [] as $track) {
            $processed[] = [
                'title' => $track['title'] ?? null,
                'artist' => $track['artist']['name'] ?? null,
                'album' => $track['album']['title'] ?? null,
                'release_year' => isset($track['album']['release_date'])
                    ? (int) substr($track['album']['release_date'], 0, 4)
                    : null,
                'score' => 100, // Deezer doesn't provide scores, use default
                'source' => 'deezer_filename'
            ];
        }

        return $processed;
    }

    /**
     * Get cleaned filename for search
     *
     * @return string
     */
    protected function getCleanedFilename(): string
    {
        $filename = pathinfo($this->music->filepath, PATHINFO_FILENAME);

        // Clean up the filename - remove common unwanted parts
        $unwantedPatterns = [
            '/\[.*?\]/',           // Remove [brackets content]
            '/\(.*?\)/',           // Remove (parentheses content)
            '/\{.*?\}/',           // Remove {braces content}
            '/_+/',                // Replace multiple underscores with single space
            '/\s+/',               // Replace multiple spaces with single space
            '/^\d+\.?\s*/',        // Remove leading track numbers
            '/\.(mp3|flac|wav|m4a|ogg)$/i', // Remove file extensions (just in case)
        ];

        foreach ($unwantedPatterns as $pattern) {
            if ($pattern === '/_+/' || $pattern === '/\s+/') {
                $filename = preg_replace($pattern, ' ', $filename);
            } else {
                $filename = preg_replace($pattern, '', $filename);
            }
        }

        return trim($filename);
    }
}

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
        // Extract artist and title from filename only
        $filenameInfo = $this->extractInfoFromFilename();
        
        if (empty($filenameInfo['title'])) {
            Log::info("Could not extract title from filename", [
                'music_id' => $this->music->id,
                'filepath' => $this->music->filepath
            ]);
            return;
        }

        // Determine which service to use based on configuration
        $service = Config::get('music.metadata_service', 'musicbrainz');

        // Prepare search parameters based only on filename
        $searchParams = $this->prepareSearchParamsFromFilename($service, $filenameInfo);

        if (empty($searchParams)) {
            Log::info("Insufficient filename data for {$service} search", [
                'music_id' => $this->music->id,
                'filename_info' => $filenameInfo
            ]);
            return;
        }

        // Search using the configured service
        $searchResults = $this->performSearch($service, $searchParams, $musicBrainzService, $deezerService);

        if (empty($searchResults)) {
            Log::info("No {$service} results found for filename search", [
                'music_id' => $this->music->id,
                'search_params' => $searchParams,
                'filename_info' => $filenameInfo
            ]);

            // Mark as no result for the specific service (filename-based search)
            if ($service === 'musicbrainz') {
                $this->music->musicbrainz_filename_no_result = true;
            } else {
                $this->music->deezer_filename_no_result = true;
            }

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
     * @param array $filenameInfo
     * @return array
     */
    protected function prepareSearchParamsFromFilename(string $service, array $filenameInfo): array
    {
        $params = [];

        // Use filename-extracted title
        if (!empty($filenameInfo['title'])) {
            if ($service === 'musicbrainz') {
                $params['recording'] = $filenameInfo['title'];
            } else {
                $params['title'] = $filenameInfo['title'];
            }
        }

        // Use filename-extracted artist if available
        if (!empty($filenameInfo['artist'])) {
            $params['artist'] = $filenameInfo['artist'];
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
                return $musicBrainzService->searchRecordings($searchParams);
            } else {
                return $deezerService->searchTracks($searchParams);
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
     * Extract artist and title from filename
     *
     * @return array
     */
    protected function extractInfoFromFilename(): array
    {
        $filename = pathinfo($this->music->filepath, PATHINFO_FILENAME);
        $result = ['artist' => null, 'title' => null];
        
        // Clean up the filename - remove common unwanted parts
        $filename = $this->cleanFilename($filename);
        
        // Try different common patterns to extract artist and title
        $patterns = [
            // Pattern: "Artist - Title"
            '/^(.+?)\s*-\s*(.+)$/',
            // Pattern: "Artist_Title" (underscore separator)
            '/^(.+?)_(.+)$/',
            // Pattern: "01. Artist - Title" (with track number)
            '/^\d+\.?\s*(.+?)\s*-\s*(.+)$/',
            // Pattern: "01 - Artist - Title" (track number with dash)
            '/^\d+\s*-\s*(.+?)\s*-\s*(.+)$/',
            // Pattern: "Artist Title" (space separator, try to split intelligently)
            '/^([A-Z][a-z]+(?:\s+[A-Z][a-z]+)*)\s+(.+)$/',
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $filename, $matches)) {
                $artist = trim($matches[1]);
                $title = trim($matches[2]);
                
                // Basic validation - avoid very short or suspicious matches
                if (strlen($artist) >= 2 && strlen($title) >= 2) {
                    $result['artist'] = $this->cleanExtractedText($artist);
                    $result['title'] = $this->cleanExtractedText($title);
                    break;
                }
            }
        }
        
        // If no pattern matched but we have a reasonable filename, use it as title
        if (empty($result['title']) && strlen($filename) >= 3) {
            $result['title'] = $this->cleanExtractedText($filename);
        }
        
        return $result;
    }
    
    /**
     * Clean filename by removing common unwanted parts
     *
     * @param string $filename
     * @return string
     */
    protected function cleanFilename(string $filename): string
    {
        // Remove common unwanted patterns
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
    
    /**
     * Clean extracted text (artist or title)
     *
     * @param string $text
     * @return string
     */
    protected function cleanExtractedText(string $text): string
    {
        // Remove common quality indicators and unwanted text
        $unwantedPatterns = [
            '/\b(320kbps?|256kbps?|192kbps?|128kbps?)\b/i',
            '/\b(mp3|flac|wav|m4a|ogg)\b/i',
            '/\b(hq|high.?quality|lossless)\b/i',
            '/\b(www\.[^\s]+)\b/i',        // Remove website URLs
            '/\b([a-z]+\.[a-z]{2,4})\b/i', // Remove domain names
            '/[_\-]+$/',                    // Remove trailing underscores/dashes
            '/^[_\-]+/',                    // Remove leading underscores/dashes
        ];
        
        foreach ($unwantedPatterns as $pattern) {
            $text = preg_replace($pattern, '', $text);
        }
        
        // Clean up spacing and return
        return trim(preg_replace('/\s+/', ' ', $text));
    }
}

<?php

namespace App\Jobs;

use App\Contracts\MusicMetadataServiceInterface;
use App\Events\MusicResultFetchedEvent;
use App\Models\Music;
use App\Models\MusicMetadataResult;
use Exception;
use Illuminate\Broadcasting\Channel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
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

    public function handle(MusicMetadataServiceInterface $metadataService): void
    {
        // Prepare search parameters based on existing metadata
        $searchParams = $this->prepareSearchParams($metadataService->getServiceName());

        if (empty($searchParams)) {
            Log::info("Insufficient metadata for {$metadataService->getServiceName()} search", ['music_id' => $this->music->id]);
            return;
        }

        // Search using the injected service
        $searchResults = $this->performSearch($metadataService, $searchParams);

        if (empty($searchResults)) {
            Log::info("No {$metadataService->getServiceName()} results found", [
                'music_id' => $this->music->id,
                'search_params' => $searchParams
            ]);

            // try by filename
            dispatch(new SearchMusicMetadataFromFilenameJob($this->music));

            return;
        }

        // Store unified results in the new table
        $this->storeUnifiedResults($metadataService, $searchResults);

        $this->music->save();

        event(new MusicResultFetchedEvent($this->music));

        // Apply throttling if required by the service
        if ($metadataService->requiresThrottling()) {
            sleep($metadataService->getThrottleTime());
        }
    }

    /**
     * Perform search using the injected service
     *
     * @param MusicMetadataServiceInterface $metadataService
     * @param array $params
     * @return array
     */
    protected function performSearch(MusicMetadataServiceInterface $metadataService, array $params): array
    {
        try {
            return $metadataService->search($params);
        } catch (Exception $e) {
            Log::error("Error searching {$metadataService->getServiceName()} with metadata", [
                'music_id' => $this->music->id,
                'search_params' => $params,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Store unified results in the new table
     *
     * @param MusicMetadataServiceInterface $metadataService
     * @param array $searchResults
     * @return void
     */
    protected function storeUnifiedResults(MusicMetadataServiceInterface $metadataService, array $searchResults): void
    {
        foreach ($searchResults as $result) {
            MusicMetadataResult::create([
                'music_id' => $this->music->id,
                'service' => $metadataService->getServiceName(),
                'search_type' => 'metadata',
                'title' => $result['title'],
                'artist' => $result['artist'],
                'album' => $result['album'],
                'release_year' => $result['release_year'],
                'score' => $result['score'],
                'external_id' => $result['external_id'],
                'raw_data' => $result['raw_data'],
            ]);
        }
    }

    /**
     * Prepare search parameters from existing metadata
     *
     * @param string $serviceName
     * @return array
     */
    protected function prepareSearchParams(string $serviceName): array
    {
        $params = [];

        // For MusicBrainz, use 'recording' parameter, for others use 'title'
        if (!empty($this->music->title)) {
            if ($serviceName === 'musicbrainz') {
                $params['recording'] = $this->music->title;
            } else {
                $params['title'] = $this->music->title;
            }
        }

        if (!empty($this->music->artist)) {
            $params['artist'] = $this->music->artist;
        }

        // For MusicBrainz, use 'release' parameter, for others use 'album'
        if (!empty($this->music->album)) {
            if ($serviceName === 'musicbrainz') {
                $params['release'] = $this->music->album;
            } else {
                $params['album'] = $this->music->album;
            }
        }

        // If we don't have title or artist, try to extract from filename
        if (empty($params['title']) && empty($params['recording']) && empty($params['artist'])) {
            $filenameInfo = $this->extractInfoFromFilename();

            // Use filename-extracted title if no metadata title exists
            if (empty($this->music->title) && !empty($filenameInfo['title'])) {
                if ($serviceName === 'musicbrainz') {
                    $params['recording'] = $filenameInfo['title'];
                } else {
                    $params['title'] = $filenameInfo['title'];
                }
            }

            // Use filename-extracted artist if no metadata artist exists
            if (empty($this->music->artist) && !empty($filenameInfo['artist'])) {
                $params['artist'] = $filenameInfo['artist'];
            }
        }

        return $params;
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

<?php

namespace App\Jobs;

use App\Contracts\MusicMetadataServiceInterface;
use App\Events\MusicResultFetchedEvent;
use App\Models\Music;
use App\Models\MusicMetadataResult;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SearchMusicMetadataFromFilenameJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public Music $music, public string $service)
    {
    }

    public function handle(): void
    {
        // Resolve the specific metadata service based on the service parameter
        $metadataService = match ($this->service) {
            'musicbrainz' => app(\App\Services\MusicBrainzService::class),
            'deezer' => app(\App\Services\DeezerService::class),
            'spotify' => app(\App\Services\SpotifyService::class),
            default => throw new Exception("Unknown metadata service: {$this->service}")
        };

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

        // Prepare search parameters using full filename as free search
        $searchParams = $this->prepareSearchParamsFromFilename($metadataService->getServiceName(), $cleanedFilename);

        // Search using the injected service
        $searchResults = $this->performSearch($metadataService, $searchParams);

        if (empty($searchResults)) {
            Log::info("No {$metadataService->getServiceName()} results found for filename search", [
                'music_id' => $this->music->id,
                'search_params' => $searchParams,
                'cleaned_filename' => $cleanedFilename
            ]);
            return;
        }

        // Store unified results in the new table
        $this->storeUnifiedResults($metadataService, $searchResults);

        event(new MusicResultFetchedEvent($this->music));

        // Apply throttling if required by the service
        if ($metadataService->requiresThrottling()) {
            sleep($metadataService->getThrottleTime());
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
     * @param MusicMetadataServiceInterface $metadataService
     * @param array $searchParams
     * @return array
     */
    protected function performSearch(MusicMetadataServiceInterface $metadataService, array $searchParams): array
    {
        try {
            return $metadataService->search($searchParams);
        } catch (Exception $e) {
            Log::error("Error searching {$metadataService->getServiceName()} with filename data", [
                'music_id' => $this->music->id,
                'search_params' => $searchParams,
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
                'search_type' => 'filename',
                'title' => $result['title'],
                'artist' => $result['artist'],
                'album' => $result['album'],
                'release_year' => $result['release_year'],
                'score' => $this->calculateSimilarityScore($result),
                'external_id' => $result['external_id'],
                'raw_data' => $result['raw_data'],
            ]);
        }
    }

    protected function calculateSimilarityScore(array $result): float
    {
        $music = "{$result->music->title} {$result->music->artist} {$result->music->album} {$result->music->release_year}";
        $search = "{$result->title} {$result->artist} {$result->album} {$result->release_year}";

        $music = strtolower($music);
        $search = strtolower($search);

        similar_text($music, $search, $score);

        return $score;
    }

    /**
     * Get cleaned filename for search
     *
     * @return string
     */
    protected function getCleanedFilename(): string
    {
        $filename = pathinfo($this->music->filepath, PATHINFO_FILENAME);

        return str($filename)->replace(['(', ')', '[', ']', '{', '}', '_', '-'], ' ')->toString();

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

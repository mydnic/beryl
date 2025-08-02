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
            dispatch(new SearchMusicMetadataFromFilenameJob($this->music, $this->service));

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
            // Calculate custom similarity score
            $customScore = $this->calculateSimilarityScore($result);
            
            MusicMetadataResult::create([
                'music_id' => $this->music->id,
                'service' => $metadataService->getServiceName(),
                'search_type' => 'metadata',
                'title' => $result['title'],
                'artist' => $result['artist'],
                'album' => $result['album'],
                'release_year' => $result['release_year'],
                'score' => $customScore,
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

    /**
     * Calculate similarity score between search result and existing music data
     *
     * @param array $result
     * @return float Score between 0 and 100
     */
    protected function calculateSimilarityScore(array $result): float
    {
        $totalWeight = 0;
        $matchedWeight = 0;

        // Title comparison (weight: 40%)
        if (!empty($this->music->title) && !empty($result['title'])) {
            $titleWeight = 40;
            $totalWeight += $titleWeight;
            $titleSimilarity = $this->calculateStringSimilarity($this->music->title, $result['title']);
            $matchedWeight += $titleSimilarity * $titleWeight;
        }

        // Artist comparison (weight: 35%)
        if (!empty($this->music->artist) && !empty($result['artist'])) {
            $artistWeight = 35;
            $totalWeight += $artistWeight;
            $artistSimilarity = $this->calculateStringSimilarity($this->music->artist, $result['artist']);
            $matchedWeight += $artistSimilarity * $artistWeight;
        }

        // Album comparison (weight: 20%)
        if (!empty($this->music->album) && !empty($result['album'])) {
            $albumWeight = 20;
            $totalWeight += $albumWeight;
            $albumSimilarity = $this->calculateStringSimilarity($this->music->album, $result['album']);
            $matchedWeight += $albumSimilarity * $albumWeight;
        }

        // Release year comparison (weight: 5%)
        if (!empty($this->music->release_year) && !empty($result['release_year'])) {
            $yearWeight = 5;
            $totalWeight += $yearWeight;
            $yearSimilarity = $this->calculateYearSimilarity($this->music->release_year, $result['release_year']);
            $matchedWeight += $yearSimilarity * $yearWeight;
        }

        // If no fields to compare, return 0
        if ($totalWeight === 0) {
            return 0;
        }

        // Calculate final score as percentage
        return round(($matchedWeight / $totalWeight) * 100, 2);
    }

    /**
     * Calculate string similarity between two strings
     *
     * @param string $str1
     * @param string $str2
     * @return float Similarity between 0 and 1
     */
    protected function calculateStringSimilarity(string $str1, string $str2): float
    {
        // Normalize strings for comparison
        $normalized1 = $this->normalizeString($str1);
        $normalized2 = $this->normalizeString($str2);

        // Exact match gets perfect score
        if ($normalized1 === $normalized2) {
            return 1.0;
        }

        // Use Levenshtein distance for similarity calculation
        $maxLength = max(strlen($normalized1), strlen($normalized2));
        if ($maxLength === 0) {
            return 0.0;
        }

        $distance = levenshtein($normalized1, $normalized2);
        $similarity = 1 - ($distance / $maxLength);

        // Also check if one string contains the other (partial match)
        $containsSimilarity = 0;
        if (str_contains($normalized1, $normalized2) || str_contains($normalized2, $normalized1)) {
            $containsSimilarity = 0.8; // High score for partial matches
        }

        // Return the higher of the two similarity scores
        return max($similarity, $containsSimilarity);
    }

    /**
     * Calculate year similarity
     *
     * @param int $year1
     * @param int $year2
     * @return float Similarity between 0 and 1
     */
    protected function calculateYearSimilarity(int $year1, int $year2): float
    {
        $difference = abs($year1 - $year2);
        
        // Exact match
        if ($difference === 0) {
            return 1.0;
        }
        
        // 1 year difference = 0.8
        if ($difference === 1) {
            return 0.8;
        }
        
        // 2 years difference = 0.6
        if ($difference === 2) {
            return 0.6;
        }
        
        // 3+ years difference = 0 (too different)
        return 0.0;
    }

    /**
     * Normalize string for comparison
     *
     * @param string $str
     * @return string
     */
    protected function normalizeString(string $str): string
    {
        // Convert to lowercase
        $normalized = strtolower($str);
        
        // Remove common words that don't affect matching
        $commonWords = ['the', 'a', 'an', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for', 'of', 'with', 'by', 'feat', 'ft'];
        $words = explode(' ', $normalized);
        $words = array_filter($words, function($word) use ($commonWords) {
            return !in_array(trim($word), $commonWords);
        });
        $normalized = implode(' ', $words);
        
        // Remove special characters and extra spaces
        $normalized = preg_replace('/[^\w\s]/', '', $normalized);
        $normalized = preg_replace('/\s+/', ' ', $normalized);
        
        return trim($normalized);
    }
}

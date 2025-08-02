<?php

namespace App\Services;

use App\Contracts\MusicMetadataServiceInterface;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class LastfmService implements MusicMetadataServiceInterface
{
    protected string $baseUrl = 'https://ws.audioscrobbler.com/2.0/';
    protected ?string $apiKey;

    public function __construct()
    {
        $this->apiKey = config('services.lastfm.api_key');
    }

    /**
     * Search for music metadata based on search parameters
     *
     * @param array $params Search parameters (artist, title, album)
     * @return array Array of standardized metadata results
     */
    public function search(array $params): array
    {
        // Check if API key is configured
        if (empty($this->apiKey)) {
            Log::warning('Last.fm API key not configured');
            return [];
        }

        try {
            // Build search query based on available parameters
            $searchQuery = $this->buildSearchQuery($params);

            if (empty($searchQuery)) {
                Log::info('Insufficient parameters for Last.fm search', ['params' => $params]);
                return [];
            }

            // Search for tracks using Last.fm API
            $response = Http::timeout(30)->get($this->baseUrl, [
                'method' => 'track.search',
                'track' => $searchQuery,
                'api_key' => $this->apiKey,
                'format' => 'json',
                'limit' => 10
            ]);

            if (!$response->successful()) {
                Log::error('Last.fm API request failed', [
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);
                return [];
            }

            $data = $response->json();

            // Check if results exist
            if (!isset($data['results']['trackmatches']['track'])) {
                Log::info('No Last.fm results found', ['query' => $searchQuery]);
                return [];
            }

            $tracks = $data['results']['trackmatches']['track'];

            // Handle single result (API returns object instead of array)
            if (isset($tracks['name'])) {
                $tracks = [$tracks];
            }

            return $this->normalizeResults($tracks);

        } catch (Exception $e) {
            Log::error('Last.fm search failed', [
                'error' => $e->getMessage(),
                'params' => $params
            ]);
            return [];
        }
    }

    /**
     * Build search query from parameters
     */
    protected function buildSearchQuery(array $params): string
    {
        $queryParts = [];

        // Add artist if available
        if (!empty($params['artist'])) {
            $queryParts[] = $params['artist'];
        }

        // Add title/track if available
        if (!empty($params['title'])) {
            $queryParts[] = $params['title'];
        } elseif (!empty($params['recording'])) {
            $queryParts[] = $params['recording'];
        }

        return implode(' ', $queryParts);
    }

    /**
     * Normalize Last.fm results to standard format
     */
    protected function normalizeResults(array $tracks): array
    {
        $results = [];

        foreach ($tracks as $track) {
            // Extract year from mbid if available, otherwise null
            $releaseYear = null;

            // Last.fm doesn't provide release year in search results
            // We could make additional API calls to get album info, but for now keep it simple

            $results[] = [
                'title' => $track['name'] ?? '',
                'artist' => $track['artist'] ?? '',
                'album' => '', // Last.fm track search doesn't return album info
                'release_year' => $releaseYear,
                'score' => $this->calculateRelevanceScore($track),
                'external_id' => $track['mbid'] ?? null,
                'raw_data' => $track
            ];
        }

        return $results;
    }

    /**
     * Calculate relevance score based on Last.fm data
     */
    protected function calculateRelevanceScore(array $track): float
    {
        // Last.fm doesn't provide a direct popularity score in search results
        // We can use listeners count if available, or default to a base score
        $score = 50.0; // Base score

        // If listeners count is available, use it to boost score
        if (isset($track['listeners'])) {
            $listeners = (int) $track['listeners'];
            // Scale listeners to a 0-50 bonus (max 100 total)
            $bonus = min(50, ($listeners / 10000) * 50);
            $score += $bonus;
        }

        return round($score, 2);
    }

    /**
     * Get the service name
     */
    public function getServiceName(): string
    {
        return 'lastfm';
    }

    /**
     * Check if this service requires throttling
     */
    public function requiresThrottling(): bool
    {
        return true; // Last.fm has rate limits (5 requests/second)
    }

    /**
     * Get throttle time in seconds
     */
    public function getThrottleTime(): int
    {
        return 1; // 1 second delay to respect rate limits
    }
}

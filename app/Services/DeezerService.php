<?php

namespace App\Services;

use App\Contracts\MusicMetadataServiceInterface;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DeezerService implements MusicMetadataServiceInterface
{
    protected string $baseUrl = 'https://api.deezer.com';

    /**
     * Search for music metadata based on search parameters
     *
     * @param array $params Search parameters (artist, title, album)
     * @return array Array of standardized metadata results
     */
    public function search(array $params): array
    {
        $results = $this->searchTrack($params);
        
        if (empty($results) || empty($results['data'])) {
            return [];
        }

        return $this->normalizeResults($results['data']);
    }

    /**
     * Get the service name/identifier
     *
     * @return string
     */
    public function getServiceName(): string
    {
        return 'deezer';
    }

    /**
     * Check if the service requires throttling between requests
     *
     * @return bool
     */
    public function requiresThrottling(): bool
    {
        return false;
    }

    /**
     * Get the throttle time in seconds (if throttling is required)
     *
     * @return int
     */
    public function getThrottleTime(): int
    {
        return 0;
    }

    /**
     * Normalize Deezer results to standardized format
     *
     * @param array $tracks
     * @return array
     */
    protected function normalizeResults(array $tracks): array
    {
        $normalized = [];

        foreach ($tracks as $track) {
            $normalized[] = [
                'title' => $track['title'] ?? null,
                'artist' => $track['artist']['name'] ?? null,
                'album' => $track['album']['title'] ?? null,
                'release_year' => isset($track['album']['release_date']) 
                    ? (int) substr($track['album']['release_date'], 0, 4)
                    : null,
                'score' => $track['rank'] ?? 0, // Deezer uses 'rank' as popularity score
                'external_id' => $track['id'] ?? null,
                'raw_data' => $track,
            ];
        }

        return $normalized;
    }

    /**
     * Search for tracks based on query parameters
     *
     * @param array $params Search parameters (artist, title, album)
     * @return array|null
     */
    public function searchTrack(array $params): ?array
    {
        $queryString = $this->buildQueryString($params);

        if (empty($queryString)) {
            return null;
        }

        return $this->searchApi('search', $queryString);
    }

    /**
     * Get track details by Deezer ID
     *
     * @param string $id Deezer track ID
     * @return array|null
     */
    public function getTrack(string $id): ?array
    {
        return $this->request("track/{$id}");
    }

    /**
     * Build a query string from search parameters
     *
     * @param array $params Search parameters
     * @return string
     */
    protected function buildQueryString(array $params): string
    {
        $queryParts = [];

        if (!empty($params['artist'])) {
            $queryParts[] = $this->sanitizeQueryParam($params['artist']);
        }

        if (!empty($params['title'])) {
            $queryParts[] = $this->sanitizeQueryParam($params['title']);
        }

        if (!empty($params['album'])) {
            $queryParts[] = $this->sanitizeQueryParam($params['album']);
        }

        return implode(' ', $queryParts);
    }

    /**
     * Sanitize a query parameter to avoid API issues
     *
     * @param string $param
     * @return string
     */
    protected function sanitizeQueryParam(string $param): string
    {
        // Remove special characters that might break the API query
        return preg_replace('/[^\p{L}\p{N}\s]/u', '', $param);
    }

    /**
     * Search for entities in Deezer
     *
     * @param string $endpoint API endpoint
     * @param string $query Search query
     * @param int $limit Results limit
     * @return array|null
     */
    protected function searchApi(string $endpoint, string $query, int $limit = 10): ?array
    {
        return $this->request($endpoint, [
            'q' => $query,
            'limit' => $limit
        ]);
    }

    /**
     * Make a request to the Deezer API
     *
     * @param string $endpoint API endpoint
     * @param array $params Query parameters
     * @return array|null
     */
    protected function request(string $endpoint, array $params = []): ?array
    {
        try {
            $response = Http::get("{$this->baseUrl}/{$endpoint}", $params);

            if ($response->successful()) {
                return $response->json();
            }

            Log::error('Deezer API error', [
                'status' => $response->status(),
                'body' => $response->body(),
                'endpoint' => $endpoint,
                'params' => $params
            ]);

            return null;
        } catch (Exception $e) {
            Log::error('Deezer API exception', [
                'message' => $e->getMessage(),
                'endpoint' => $endpoint,
                'params' => $params
            ]);

            return null;
        }
    }
}

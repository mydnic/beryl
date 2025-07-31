<?php

namespace App\Services;

use App\Contracts\MusicMetadataServiceInterface;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class SpotifyService implements MusicMetadataServiceInterface
{
    protected string $baseUrl = 'https://api.spotify.com/v1';
    protected string $authUrl = 'https://accounts.spotify.com/api/token';
    protected ?string $clientId;
    protected ?string $clientSecret;

    public function __construct()
    {
        $this->clientId = config('services.spotify.client_id');
        $this->clientSecret = config('services.spotify.client_secret');
    }

    /**
     * Search for music metadata based on search parameters
     *
     * @param array $params Search parameters (artist, title, album)
     * @return array Array of standardized metadata results
     */
    public function search(array $params): array
    {
        // Check if credentials are configured
        if (empty($this->clientId) || empty($this->clientSecret)) {
            Log::warning('Spotify credentials not configured', [
                'client_id_set' => !empty($this->clientId),
                'client_secret_set' => !empty($this->clientSecret)
            ]);
            return [];
        }

        $results = $this->searchTrack($params);

        if (empty($results) || empty($results['tracks']['items'])) {
            return [];
        }

        return $this->normalizeResults($results['tracks']['items']);
    }

    /**
     * Get the service name/identifier
     *
     * @return string
     */
    public function getServiceName(): string
    {
        return 'spotify';
    }

    /**
     * Check if the service requires throttling between requests
     *
     * @return bool
     */
    public function requiresThrottling(): bool
    {
        return false; // Spotify has generous rate limits
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
     * Normalize Spotify results to standardized format
     *
     * @param array $tracks
     * @return array
     */
    protected function normalizeResults(array $tracks): array
    {
        $normalized = [];

        foreach ($tracks as $track) {
            $normalized[] = [
                'title' => $track['name'] ?? null,
                'artist' => $track['artists'][0]['name'] ?? null,
                'album' => $track['album']['name'] ?? null,
                'release_year' => isset($track['album']['release_date'])
                    ? (int) substr($track['album']['release_date'], 0, 4)
                    : null,
                'score' => $track['popularity'] ?? 0, // Spotify uses popularity (0-100)
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

        return $this->searchApi('search', [
            'q' => $queryString,
            'type' => 'track',
            'limit' => 10
        ]);
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

        // If no specific fields, treat as general search
        if (empty($queryParts) && !empty($params['recording'])) {
            return $this->sanitizeQueryParam($params['recording']);
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
        // Spotify requires quotes for phrases with spaces
        $sanitized = trim($param);

        return $sanitized;
    }

    /**
     * Search for entities in Spotify
     *
     * @param string $endpoint API endpoint
     * @param array $params Query parameters
     * @return array|null
     */
    protected function searchApi(string $endpoint, array $params = []): ?array
    {
        $accessToken = $this->getAccessToken();

        if (!$accessToken) {
            Log::error('Failed to get Spotify access token');
            return null;
        }

        return $this->request($endpoint, $params, $accessToken);
    }

    /**
     * Get access token using Client Credentials flow
     *
     * @return string|null
     */
    protected function getAccessToken(): ?string
    {
        // Cache the token for 55 minutes (tokens expire in 1 hour)
        return Cache::remember('spotify_access_token', 55 * 60, function () {
            try {
                $response = Http::asForm()->post($this->authUrl, [
                    'grant_type' => 'client_credentials',
                    'client_id' => $this->clientId,
                    'client_secret' => $this->clientSecret,
                ]);

                if ($response->successful()) {
                    $data = $response->json();
                    return $data['access_token'] ?? null;
                }

                Log::error('Spotify auth error', [
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);

                return null;
            } catch (Exception $e) {
                Log::error('Spotify auth exception', [
                    'message' => $e->getMessage()
                ]);
                return null;
            }
        });
    }

    /**
     * Make a request to the Spotify API
     *
     * @param string $endpoint API endpoint
     * @param array $params Query parameters
     * @param string|null $accessToken Access token
     * @return array|null
     */
    protected function request(string $endpoint, array $params = [], ?string $accessToken = null): ?array
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $accessToken,
                'Content-Type' => 'application/json',
            ])->get("{$this->baseUrl}/{$endpoint}", $params);

            if ($response->successful()) {
                return $response->json();
            }

            Log::error('Spotify API error', [
                'status' => $response->status(),
                'body' => $response->body(),
                'endpoint' => $endpoint,
                'params' => $params
            ]);

            return null;
        } catch (Exception $e) {
            Log::error('Spotify API exception', [
                'message' => $e->getMessage(),
                'endpoint' => $endpoint,
                'params' => $params
            ]);

            return null;
        }
    }
}

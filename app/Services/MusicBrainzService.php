<?php

namespace App\Services;

use App\Contracts\MusicMetadataServiceInterface;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Config;

class MusicBrainzService implements MusicMetadataServiceInterface
{
    protected string $baseUrl = 'https://musicbrainz.org/ws/2';
    protected string $userAgent;
    protected int $throttleTime = 1; // MusicBrainz requires at least 1 second between requests

    public function __construct()
    {
        // MusicBrainz requires a proper user agent with contact information
        // https://musicbrainz.org/doc/MusicBrainz_API/Rate_Limiting
        $this->userAgent = Config::get('music.musicbrainz.user_agent', 'Beryl/1.0 ( rigoclement@mydnic.be )');
        $this->throttleTime = Config::get('music.musicbrainz.throttle_time', 1);
    }

    /**
     * Search for music metadata based on search parameters
     *
     * @param array $params Search parameters (artist, title, album, recording, etc.)
     * @return array Array of standardized metadata results
     */
    public function search(array $params): array
    {
        $results = $this->searchRecording($params);
        
        if (empty($results) || empty($results['recordings'])) {
            return [];
        }

        return $this->normalizeResults($results['recordings']);
    }

    /**
     * Get the service name/identifier
     *
     * @return string
     */
    public function getServiceName(): string
    {
        return 'musicbrainz';
    }

    /**
     * Check if the service requires throttling between requests
     *
     * @return bool
     */
    public function requiresThrottling(): bool
    {
        return true;
    }

    /**
     * Get the throttle time in seconds (if throttling is required)
     *
     * @return int
     */
    public function getThrottleTime(): int
    {
        return $this->throttleTime;
    }

    /**
     * Normalize MusicBrainz results to standardized format
     *
     * @param array $recordings
     * @return array
     */
    protected function normalizeResults(array $recordings): array
    {
        $normalized = [];

        foreach ($recordings as $recording) {
            $normalized[] = [
                'title' => $recording['title'] ?? null,
                'artist' => $recording['artist-credit'][0]['name'] ?? null,
                'album' => $recording['releases'][0]['title'] ?? null,
                'release_year' => isset($recording['releases'][0]['date'])
                    ? (int) substr($recording['releases'][0]['date'], 0, 4)
                    : null,
                'score' => $recording['score'] ?? 0,
                'external_id' => $recording['id'] ?? null,
                'raw_data' => $recording,
            ];
        }

        return $normalized;
    }

    /**
     * Search for recordings (tracks/songs) based on query parameters
     *
     * @param array $params Search parameters (artist, recording, release, etc.)
     * @return array|null
     */
    public function searchRecording(array $params): ?array
    {
        $queryParams = [];

        // Build the query string
        foreach ($params as $key => $value) {
            if (!empty($value)) {
//                $queryParams[] = "$key:\"$value\"";
                $queryParams[] = $value;
            }
        }

        if (empty($queryParams)) {
            return null;
        }

        $query = implode(' ', $queryParams);

        return $this->searchEntity('recording', $query);
    }

    /**
     * Get recording details by MBID (MusicBrainz ID)
     *
     * @param string $mbid MusicBrainz ID
     * @return array|null
     */
    public function getRecording(string $mbid): ?array
    {
        return $this->request("recording/$mbid", [
            'inc' => 'artists+releases+isrcs+url-rels'
        ]);
    }

    /**
     * Search for entities in MusicBrainz
     *
     * @param string $entity Entity type (recording, release, artist, etc.)
     * @param string $query Search query
     * @param int $limit Results limit
     * @return array|null
     */
    protected function searchEntity(string $entity, string $query, int $limit = 10): ?array
    {
        return $this->request("$entity", [
            'query' => $query,
            'limit' => $limit,
            'fmt' => 'json'
        ]);
    }

    /**
     * Make a request to the MusicBrainz API
     *
     * @param string $endpoint API endpoint
     * @param array $params Query parameters
     * @return array|null
     */
    protected function request(string $endpoint, array $params = []): ?array
    {
        try {
            // Respect rate limiting
            sleep($this->throttleTime);

            $response = Http::withHeaders([
                'User-Agent' => $this->userAgent,
                'Accept' => 'application/json',
            ])->get("{$this->baseUrl}/{$endpoint}", array_merge($params, ['fmt' => 'json']));

            if ($response->successful()) {
                return $response->json();
            }

            Log::error('MusicBrainz API error', [
                'status' => $response->status(),
                'body' => $response->body(),
                'endpoint' => $endpoint,
                'params' => $params
            ]);

            return null;
        } catch (Exception $e) {
            Log::error('MusicBrainz API exception', [
                'message' => $e->getMessage(),
                'endpoint' => $endpoint,
                'params' => $params
            ]);

            return null;
        }
    }
}

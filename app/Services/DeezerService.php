<?php

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DeezerService
{
    protected string $baseUrl = 'https://api.deezer.com';

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

        return $this->search('search', $queryString);
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
    protected function search(string $endpoint, string $query, int $limit = 10): ?array
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

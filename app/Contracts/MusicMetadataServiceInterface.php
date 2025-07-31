<?php

namespace App\Contracts;

interface MusicMetadataServiceInterface
{
    /**
     * Search for music metadata based on search parameters
     *
     * @param array $params Search parameters (artist, title, album, recording, etc.)
     * @return array Array of standardized metadata results
     */
    public function search(array $params): array;

    /**
     * Get the service name/identifier
     *
     * @return string
     */
    public function getServiceName(): string;

    /**
     * Check if the service requires throttling between requests
     *
     * @return bool
     */
    public function requiresThrottling(): bool;

    /**
     * Get the throttle time in seconds (if throttling is required)
     *
     * @return int
     */
    public function getThrottleTime(): int;
}

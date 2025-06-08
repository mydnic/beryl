<?php

namespace App\Jobs;

use App\Events\MusicResultFetchedEvent;
use App\Models\Music;
use App\Services\DeezerService;
use App\Services\MusicBrainzService;
use Exception;
use Illuminate\Broadcasting\Channel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;

class SearchMusicMetadataJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public Music $music)
    {
    }

    public function handle(MusicBrainzService $musicBrainzService, DeezerService $deezerService): void
    {
        // Determine which service to use based on configuration
        $service = Config::get('music.metadata_service', 'musicbrainz');

        // Prepare search parameters based on existing metadata
        $searchParams = $this->prepareSearchParams($service);

        if (empty($searchParams)) {
            Log::info("Insufficient metadata for {$service} search", ['music_id' => $this->music->id]);
            return;
        }

        // Search using the configured service
        $searchResults = $this->performSearch($service, $searchParams, $musicBrainzService, $deezerService);

        if (empty($searchResults)) {
            Log::info("No {$service} results found", [
                'music_id' => $this->music->id,
                'search_params' => $searchParams
            ]);

            // Mark as no result for the specific service
            if ($service === 'musicbrainz') {
                $this->music->musicbrainz_no_result = true;
            } else {
                $this->music->deezer_no_result = true;
            }

            $this->music->save();
            return;
        }

        // Store raw API results
        $apiResults = $this->music->api_results ?? [];
        $apiResults[$service] = $searchResults;
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
     * Perform search using the configured service
     *
     * @param string $service
     * @param array $params
     * @param MusicBrainzService $musicBrainzService
     * @param DeezerService $deezerService
     * @return array|null
     */
    protected function performSearch(
        string $service,
        array $params,
        MusicBrainzService $musicBrainzService,
        DeezerService $deezerService
    ): ?array {
        if ($service === 'musicbrainz') {
            $results = $musicBrainzService->searchRecording($params);
            return empty($results) || empty($results['recordings']) ? null : $results;
        } else {
            $results = $deezerService->searchTrack($params);
            return empty($results) || empty($results['data']) ? null : $results;
        }
    }

    /**
     * Process and store standardized results from the API
     *
     * @param string $service
     * @param array $searchResults
     * @return void
     */
    protected function processAndStoreResults(string $service, array $searchResults): void
    {
        $results = $this->music->results ?? [];

        if ($service === 'musicbrainz') {
            $this->processMusicBrainzResults($searchResults['recordings'], $results);
        } else {
            $this->processDeezerResults($searchResults['data'], $results);
        }

        $this->music->results = $results;
    }

    /**
     * Process MusicBrainz results into standardized format
     *
     * @param array $recordings
     * @param array &$results
     * @return void
     */
    protected function processMusicBrainzResults(array $recordings, array &$results): void
    {
        foreach ($recordings as $recording) {
            $artist = !empty($recording['artist-credit'][0]['name'])
                ? $recording['artist-credit'][0]['name']
                : null;

            $album = !empty($recording['releases'][0]['title'])
                ? $recording['releases'][0]['title']
                : null;

            $releaseYear = !empty($recording['first-release-date'])
                ? substr($recording['first-release-date'], 0, 4)
                : null;

            $results[] = [
                'title' => $recording['title'] ?? null,
                'artist' => $artist,
                'album' => $album,
                'release_year' => $releaseYear,
                'api_source' => 'musicbrainz',
                'source_id' => $recording['id'] ?? null,
            ];
        }
    }

    /**
     * Process Deezer results into standardized format
     *
     * @param array $tracks
     * @param array &$results
     * @return void
     */
    protected function processDeezerResults(array $tracks, array &$results): void
    {
        $deezerService = app(DeezerService::class);

        foreach ($tracks as $track) {
            $releaseYear = null;
            $trackDetails = $track;

            // Si la date de sortie n'est pas disponible dans les résultats de recherche
            // et que nous avons un ID de piste, récupérer les détails complets
            if ((!isset($track['album']['release_date']) || empty($track['album']['release_date']))
                && isset($track['id'])) {
                $trackDetails = $deezerService->getTrack($track['id']);

                // Attendre un peu pour respecter les limites de l'API
                usleep(250000); // 250ms

                if (!$trackDetails) {
                    $trackDetails = $track; // Revenir aux détails originaux si l'appel API échoue
                }
            }

            // Essayer d'obtenir l'année de sortie à partir des détails de la piste
            if (isset($trackDetails['album']['release_date']) && !empty($trackDetails['album']['release_date'])) {
                $releaseYear = substr($trackDetails['album']['release_date'], 0, 4);
            }

            $results[] = [
                'title' => $trackDetails['title'] ?? null,
                'artist' => $trackDetails['artist']['name'] ?? null,
                'album' => $trackDetails['album']['title'] ?? null,
                'release_year' => $releaseYear,
                'api_source' => 'deezer',
                'source_id' => $trackDetails['id'] ?? null,
                'preview_url' => $trackDetails['preview'] ?? null,
                'cover_url' => $trackDetails['album']['cover_medium'] ?? null
            ];
        }
    }

    /**
     * Prepare search parameters from existing metadata
     *
     * @param string $service
     * @return array
     */
    protected function prepareSearchParams(string $service): array
    {
        $params = [];

        // Prioritize the most important metadata for accurate search
        if (!empty($this->music->title)) {
            if ($service === 'musicbrainz') {
                $params['recording'] = $this->music->title;
            } else {
                $params['title'] = $this->music->title;
            }
        }

        if (!empty($this->music->artist)) {
            $params['artist'] = $this->music->artist;
        }

        if (!empty($this->music->album)) {
            if ($service === 'musicbrainz') {
                $params['release'] = $this->music->album;
            } else {
                $params['album'] = $this->music->album;
            }
        }

        return $params;
    }
}

<?php

namespace App\Jobs;

use App\Models\Music;
use App\Services\MusicBrainzService;
use Exception;
use Illuminate\Bus\Queueable;
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

    public function handle(MusicBrainzService $musicBrainzService): void
    {
        // Recherche basée sur les métadonnées existantes
        $searchParams = $this->prepareSearchParams();

        if (empty($searchParams)) {
            Log::info('Insufficient metadata for MusicBrainz search', ['music_id' => $this->music->id]);
            return;
        }

        $searchResults = $musicBrainzService->searchRecording($searchParams);

        if (empty($searchResults) || empty($searchResults['recordings'])) {
            Log::info('No MusicBrainz results found', [
                'music_id' => $this->music->id,
                'search_params' => $searchParams
            ]);
            $this->music->musicbrainz_no_result = true;
            $this->music->save();
            return;
        }

//        // Utiliser le premier résultat (le plus pertinent)
//        $recording = $searchResults['recordings'][0];
//
//        // Si on a un ID MusicBrainz, on peut obtenir plus de détails
//        if (isset($recording['id'])) {
//            $detailedRecording = $musicBrainzService->getRecording($recording['id']);
//            if ($detailedRecording) {
//                $recording = $detailedRecording;
//            }
//        }

        // Mettre à jour les métadonnées de la musique
        $this->music->musicbrainz_data = [
            'results' => $searchResults['recordings']
        ];
        $this->music->save();

        sleep(60);
    }

    /**
     * Prépare les paramètres de recherche à partir des métadonnées existantes
     *
     * @return array
     */
    protected function prepareSearchParams(): array
    {
        $params = [];

        // Priorité aux métadonnées les plus importantes pour une recherche précise
        if (!empty($this->music->title)) {
            $params['recording'] = $this->music->title;
        }

        if (!empty($this->music->artist)) {
            $params['artist'] = $this->music->artist;
        }

        if (!empty($this->music->album)) {
            $params['release'] = $this->music->album;
        }

        return $params;
    }
}

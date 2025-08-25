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
            'lastfm' => app(\App\Services\LastfmService::class),
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

            // If an exact match on all four fields is found, mark as not needing fixing
            if (
                !empty($this->music->title) && !empty($this->music->artist) && !empty($this->music->album) && !empty($this->music->release_year) &&
                isset($result['title'], $result['artist'], $result['album'], $result['release_year']) &&
                $this->normalizeString((string) $this->music->title) === $this->normalizeString((string) $result['title']) &&
                $this->normalizeString((string) $this->music->artist) === $this->normalizeString((string) $result['artist']) &&
                $this->normalizeString((string) $this->music->album) === $this->normalizeString((string) $result['album']) &&
                (int) $this->music->release_year === (int) $result['release_year']
            ) {
                $this->music->need_fixing = false;
                $this->music->save();
            }
        }
    }

    protected function calculateSimilarityScore(array $result): float
    {
        // We compare the candidate result fields against the current music record
        $titleMusic = $this->music->title;
        $artistMusic = $this->music->artist;
        $albumMusic = $this->music->album;
        $yearMusic = $this->music->release_year;

        $titleRes = $result['title'] ?? null;
        $artistRes = $result['artist'] ?? null;
        $albumRes = $result['album'] ?? null;
        $yearRes = $result['release_year'] ?? null;

        // Weights
        $weights = [
            'title' => 0.40,
            'artist' => 0.35,
            'album' => 0.20,
            'year' => 0.05,
        ];

        $scoreSum = 0.0;
        $weightSum = 0.0;

        if (!empty($titleMusic) && !empty($titleRes)) {
            $sim = $this->stringSimilarity((string) $titleMusic, (string) $titleRes);
            $scoreSum += $sim * $weights['title'];
            $weightSum += $weights['title'];
        }

        if (!empty($artistMusic) && !empty($artistRes)) {
            $sim = $this->stringSimilarity((string) $artistMusic, (string) $artistRes);
            $scoreSum += $sim * $weights['artist'];
            $weightSum += $weights['artist'];
        }

        if (!empty($albumMusic) && !empty($albumRes)) {
            $sim = $this->stringSimilarity((string) $albumMusic, (string) $albumRes);
            $scoreSum += $sim * $weights['album'];
            $weightSum += $weights['album'];
        }

        if (!empty($yearMusic) && !empty($yearRes)) {
            $sim = $this->yearSimilarity((int) $yearMusic, (int) $yearRes);
            $scoreSum += $sim * $weights['year'];
            $weightSum += $weights['year'];
        }

        if ($weightSum <= 0.0) {
            return 0.0; // Not enough comparable data
        }

        // Convert to 0-100 percentage
        return round(($scoreSum / $weightSum) * 100, 2);
    }

    private function stringSimilarity(string $a, string $b): float
    {
        $na = $this->normalizeString($a);
        $nb = $this->normalizeString($b);

        if ($na === '' || $nb === '') {
            return 0.0;
        }

        if ($na === $nb) {
            return 1.0;
        }

        // Levenshtein similarity
        $len = max(strlen($na), strlen($nb));
        $lev = levenshtein($na, $nb);
        $levScore = $len > 0 ? max(0.0, 1.0 - ($lev / $len)) : 0.0;

        // Partial containment score
        $contains = (str_contains($na, $nb) || str_contains($nb, $na)) ? 0.8 : 0.0;

        return max($levScore, $contains);
    }

    private function normalizeString(string $s): string
    {
        $s = mb_strtolower($s);
        // Remove punctuation
        $s = preg_replace('/[\p{P}\p{S}]+/u', ' ', $s) ?? '';
        // Remove common stop-words and credit words
        $stop = [' the ', ' a ', ' and ', ' feat ', ' ft ', ' featuring '];
        $s = ' ' . preg_replace('/\s+/', ' ', trim($s)) . ' ';
        foreach ($stop as $st) {
            $s = str_replace($st, ' ', $s);
        }
        return trim(preg_replace('/\s+/', ' ', $s) ?? '');
    }

    private function yearSimilarity(int $a, int $b): float
    {
        if ($a === 0 || $b === 0) {
            return 0.0;
        }
        if ($a === $b) return 1.0;
        $diff = abs($a - $b);
        return match (true) {
            $diff === 1 => 0.8,
            $diff === 2 => 0.6,
            default => 0.0,
        };
    }

    /**
     * Get cleaned filename for search
     *
     * @return string
     */
    protected function getCleanedFilename(): string
    {
        $filename = pathinfo($this->music->filepath, PATHINFO_FILENAME);

        // Remove bracketed/parenthetical content
        $filename = preg_replace('/\[.*?\]/', ' ', $filename) ?? $filename;
        $filename = preg_replace('/\(.*?\)/', ' ', $filename) ?? $filename;
        $filename = preg_replace('/\{.*?\}/', ' ', $filename) ?? $filename;

        // Remove leading track numbers like "01.", "01 -", "01_"
        $filename = preg_replace('/^\s*\d{1,3}[\.-_\s]+/u', ' ', $filename) ?? $filename;

        // Replace separators with spaces
        $filename = str_replace(['_', '-'], ' ', $filename);

        // Collapse multiple whitespace
        $filename = preg_replace('/\s+/', ' ', $filename) ?? $filename;

        $cleaned = trim($filename);

        return mb_strlen($cleaned) >= 3 ? $cleaned : '';
    }
}

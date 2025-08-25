<?php

namespace App\Services;

use App\Models\Music;
use Exception;
use getID3;
use getID3_writetags;
use Symfony\Component\Process\Process;

class AudioTagService
{
    /**
     * Apply metadata to a music file without clearing existing tags for missing fields.
     * - Merges incoming metadata with existing tags (prefers incoming non-empty values)
     * - Chooses proper tag writer based on actual container (with MP4-family fallbacks)
     * - Writes only rich tag formats (e.g., ID3v2.3 for MP3)
     *
     * @param Music $music
     * @param array{title?:string|null,artist?:string|null,album?:string|null,year?:string|int|null} $metadata
     * @throws Exception on failure with detailed message
     */
    public function applyToMusic(Music $music, array $metadata): void
    {
        $this->applyToPath($music->filepath, $metadata);
    }

    /**
     * Apply metadata directly to a file path (test-friendly, no Eloquent dependency).
     *
     * @param string $filepath
     * @param array{title?:string|null,artist?:string|null,album?:string|null,year?:string|int|null} $metadata
     * @throws Exception
     */
    public function applyToPath(string $filepath, array $metadata): void
    {
        if (!is_string($filepath) || !file_exists($filepath)) {
            throw new Exception('File not found: ' . ($filepath ?? 'unknown'));
        }

        // Detect container
        $analyzer = new getID3();
        $info = $analyzer->analyze($filepath);
        $extension = strtolower(pathinfo($filepath, PATHINFO_EXTENSION));
        $container = strtolower($info['fileformat'] ?? $extension);
        if (strpos($container, '.') !== false) {
            $container = strtok($container, '.');
        }

        // Build candidate writer formats and/or external strategies
        $candidateFormats = [];
        $useExternalMp4 = false;
        switch ($container) {
            case 'flac':
            case 'ogg':
                $candidateFormats = ['vorbiscomment'];
                break;
            case 'mp4':
            case 'quicktime':
            case 'm4a':
            case 'alac':
            case 'mov':
                // MP4-family writing via external tools
                $useExternalMp4 = true;
                break;
            case 'mp3':
            default:
                $candidateFormats = ['id3v2.3'];
                break;
        }

        // Read existing tags to merge defaults
        $existing = $this->readExistingTags($info);

        // Merge incoming with existing
        $merged = $this->mergeMetadata($existing, $metadata);

        // Nothing to write? If no fields available, bail gracefully
        if (empty($merged)) {
            return; // no-op
        }

        if ($useExternalMp4) {
            $this->writeMp4WithExternalTool($filepath, $merged);
            return;
        }

        // Prepare writer for formats supported by getID3
        if (!class_exists('getID3_writetags')) {
            // Composer doesn't autoload write.php; include it manually without relying on base_path()
            $writePath = __DIR__ . '/../../vendor/james-heinrich/getid3/getid3/write.php';
            if (file_exists($writePath)) {
                require_once $writePath;
            }
        }
        $writer = new getID3_writetags();
        $writer->filename = $filepath;
        $writer->remove_other_tags = true;
        $writer->overwrite_tags = true;
        $writer->tag_encoding = 'UTF-8';

        $writer->tag_data = $merged;

        // Attempt with fallbacks
        $errors = [];
        foreach ($candidateFormats as $fmt) {
            $writer->tagformats = [$fmt];
            if ($writer->WriteTags()) {
                return; // success
            }
            if (!empty($writer->errors)) {
                $errors[] = '[' . $fmt . '] ' . implode('; ', $writer->errors);
                $writer->errors = [];
            } else {
                $errors[] = '[' . $fmt . '] unknown write failure';
            }
        }

        $diag = sprintf('container=%s, ext=%s, tried=[%s]', $container, $extension, implode(',', $candidateFormats));
        throw new Exception('Failed to apply metadata: ' . implode(' | ', $errors) . ' | ' . $diag);
    }

    /**
     * Write MP4/M4A/QuickTime tags using external tools (exiftool, AtomicParsley).
     * @param string $filepath
     * @param array $merged tag_data built by mergeMetadata (keys: title, artist, album, year/date)
     * @throws Exception
     */
    private function writeMp4WithExternalTool(string $filepath, array $merged): void
    {
        $title  = $merged['title'][0]  ?? null;
        $artist = $merged['artist'][0] ?? null;
        $album  = $merged['album'][0]  ?? null;
        $year   = $merged['year'][0]   ?? ($merged['date'][0] ?? null);

        // Prefer exiftool if available
        if ($this->binaryExists('exiftool')) {
            $cmd = ['exiftool', '-overwrite_original', '-charset', 'UTF8'];
            if ($title)  { $cmd[] = '-Title=' . $title; }
            if ($artist) { $cmd[] = '-Artist=' . $artist; }
            if ($album)  { $cmd[] = '-Album=' . $album; }
            if ($year)   { $cmd[] = '-Year=' . substr((string)$year, 0, 4); }
            $cmd[] = $filepath;
            $process = new Process($cmd);
            $process->run();
            if (!$process->isSuccessful()) {
                throw new Exception('exiftool failed: ' . $process->getErrorOutput());
            }
            return;
        }

        // Fallback to AtomicParsley
        if ($this->binaryExists('AtomicParsley')) {
            $cmd = ['AtomicParsley', $filepath, '--overWrite'];
            if ($title)  { $cmd[] = '--title'; $cmd[] = $title; }
            if ($artist) { $cmd[] = '--artist'; $cmd[] = $artist; }
            if ($album)  { $cmd[] = '--album'; $cmd[] = $album; }
            if ($year)   { $cmd[] = '--year';  $cmd[] = substr((string)$year, 0, 4); }
            $process = new Process($cmd);
            $process->run();
            if (!$process->isSuccessful()) {
                throw new Exception('AtomicParsley failed: ' . $process->getErrorOutput());
            }
            return;
        }

        throw new Exception('No supported MP4 tag writer found (install exiftool or AtomicParsley).');
    }

    private function binaryExists(string $binary): bool
    {
        $process = Process::fromShellCommandline('which ' . escapeshellarg($binary));
        $process->run();
        return $process->isSuccessful() && trim($process->getOutput()) !== '';
    }

    /**
     * Read existing tags with a priority that prefers rich formats first.
     *
     * @param array $fileInfo
     * @return array<string,array{0:string}>
     */
    private function readExistingTags(array $fileInfo): array
    {
        $tags = $fileInfo['tags'] ?? [];
        $formatPriority = ['id3v2.4', 'id3v2.3', 'id3v2', 'quicktime', 'vorbiscomment', 'ape', 'id3v1'];
        $existing = [];
        $firstVal = function ($value) {
            if (is_array($value)) {
                foreach ($value as $v) {
                    if (is_string($v) && trim($v) !== '') return $v;
                }
                return $value[0] ?? null;
            }
            return $value;
        };
        foreach ($formatPriority as $fmt) {
            if (!isset($tags[$fmt]) || !is_array($tags[$fmt])) continue;
            foreach (['title', 'artist', 'album', 'year', 'date'] as $key) {
                if (!array_key_exists($key, $existing) && isset($tags[$fmt][$key])) {
                    $val = $firstVal($tags[$fmt][$key]);
                    if ($val !== null && $val !== '') $existing[$key] = $val;
                }
            }
        }
        return $existing;
    }

    /**
     * Merge incoming metadata with existing tags and build tag_data payload for getID3.
     * Only includes non-empty fields after merging.
     *
     * @param array $existing ['title'|'artist'|'album'|'year'|'date' => string]
     * @param array $incoming ['title'|'artist'|'album'|'year' => string|int|null]
     * @return array tag_data compatible with getID3_writetags
     */
    private function mergeMetadata(array $existing, array $incoming): array
    {
        $get = function (?string $key) use ($incoming) {
            if ($key === null) return null;
            return array_key_exists($key, $incoming) ? $incoming[$key] : null;
        };

        $mergedTitle  = ($t = $get('title'))  !== null && trim((string)$t) !== '' ? (string)$t : ($existing['title']  ?? null);
        $mergedArtist = ($a = $get('artist')) !== null && trim((string)$a) !== '' ? (string)$a : ($existing['artist'] ?? null);
        $mergedAlbum  = ($al= $get('album'))  !== null && trim((string)$al) !== '' ? (string)$al: ($existing['album']  ?? null);

        $incomingYear = $get('year');
        $incomingYear = $incomingYear !== null ? trim((string)$incomingYear) : '';
        $existingYear = isset($existing['year']) ? substr((string)$existing['year'], 0, 4) : (isset($existing['date']) ? substr((string)$existing['date'], 0, 4) : null);
        $mergedYear   = $incomingYear !== '' ? substr($incomingYear, 0, 4) : $existingYear;

        $tagData = [];
        if ($mergedTitle !== null && $mergedTitle !== '')  $tagData['title']  = [$mergedTitle];
        if ($mergedArtist !== null && $mergedArtist !== '') $tagData['artist'] = [$mergedArtist];
        if ($mergedAlbum !== null && $mergedAlbum !== '')   $tagData['album']  = [$mergedAlbum];
        if ($mergedYear !== null && $mergedYear !== '') {
            $tagData['year'] = [$mergedYear];
            $tagData['date'] = [$mergedYear];
        }
        return $tagData;
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use getID3;
use App\Models\MusicMetadataResult;

class Music extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'metadata' => 'array',
        'need_fixing' => 'boolean',
    ];

    protected $appends = ['relative_path'];

    /**
     * Mutateur pour l'attribut release_year
     * Convertit les valeurs vides en null
     *
     * @param mixed $value
     * @return void
     */
    public function setReleaseYearAttribute($value)
    {
        $this->attributes['release_year'] = (is_null($value) || $value === '') ? null : $value;
    }

    public function syncTags()
    {
        $getID3 = new getID3;
        $fileInfo = $getID3->analyze($this->filepath);
        // Extract tags from the file
        $tags = $fileInfo['tags'] ?? [];

        // Prefer richer/longer formats first to avoid truncation (e.g., ID3v1 limits to 30 chars)
        $formatPriority = [
            'id3v2.4', 'id3v2.3', 'id3v2', // MP3 rich tags first
            'quicktime',                    // M4A/MP4
            'vorbiscomment',                // FLAC/OGG
            'ape',                          // APE tags
            'id3v1',                        // last resort (truncated fields)
        ];

        // Build a merged tag map taking first non-empty per key according to priority
        $allTags = [];
        $extractFirstNonEmpty = function ($value) {
            if (is_array($value)) {
                foreach ($value as $v) {
                    if (is_string($v) && trim($v) !== '') {
                        return $v;
                    }
                }
                return $value[0] ?? null;
            }
            return $value;
        };

        foreach ($formatPriority as $format) {
            if (!isset($tags[$format]) || !is_array($tags[$format])) {
                continue;
            }
            foreach ($tags[$format] as $key => $values) {
                if (!array_key_exists($key, $allTags)) {
                    $val = $extractFirstNonEmpty($values);
                    if ($val !== null && $val !== '') {
                        $allTags[$key] = $val;
                    }
                }
            }
        }

        // Map common tag names to our database fields
        $this->title = $allTags['title'] ?? $allTags['song'] ?? null;
        $this->artist = $allTags['artist'] ?? null;
        $this->album = $allTags['album'] ?? null;
        $this->release_year = !empty($allTags['year']) ? $allTags['year'] : (!empty($allTags['date']) ? substr($allTags['date'], 0, 4) : null);
        $this->genre = $allTags['genre'] ?? null;

        // Store all metadata including file info
        $this->metadata = array_merge($this->metadata ?? [], [
            'all_tags' => $allTags,
            'file_format' => $fileInfo['fileformat'] ?? null,
            'audio_format' => $fileInfo['audio']['dataformat'] ?? null,
            'bitrate' => $fileInfo['audio']['bitrate'] ?? null,
            'sample_rate' => $fileInfo['audio']['sample_rate'] ?? null,
            'duration' => $fileInfo['playtime_seconds'] ?? null,
        ]);

        $this->save();
    }

    public function deleteFile()
    {
        if (file_exists($this->filepath)) {
            unlink($this->filepath);
        }
    }

    public function getRelativePathAttribute()
    {
        return str_replace(config('filesystems.disks.music_directory.root') . '/', '', $this->filepath);
    }

    /**
     * Get the metadata results for this music record
     */
    public function metadataResults(): HasMany
    {
        return $this->hasMany(MusicMetadataResult::class);
    }
}

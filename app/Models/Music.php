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
        'api_results' => 'array',
        'results' => 'array',
        'musicbrainz_no_result' => 'boolean',
        'deezer_no_result' => 'boolean',
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

        // GetID3 stores tags in arrays by format (id3v2, id3v1, quicktime, etc.)
        // We'll merge all available tag formats and take the first non-empty value
        $allTags = [];
        foreach ($tags as $tagFormat => $tagData) {
            foreach ($tagData as $key => $values) {
                if (!isset($allTags[$key]) && !empty($values[0])) {
                    $allTags[$key] = $values[0];
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

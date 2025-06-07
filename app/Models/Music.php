<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use wapmorgan\Mp3Info\Mp3Info;

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
        $audio = new Mp3Info($this->filepath, true);
        $this->title = $audio->tags['song'] ?? null;
        $this->artist = $audio->tags['artist'] ?? null;
        $this->album = $audio->tags['album'] ?? null;
        $this->release_year = !empty($audio->tags['year']) ? $audio->tags['year'] : null;
        $this->genre = $audio->tags['genre'] ?? null;
        $this->metadata = array_merge($this->metadata ?? [], [
            'all_tags' => $audio->tags
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
}

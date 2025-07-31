<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MusicMetadataResult extends Model
{
    use HasFactory;

    protected $fillable = [
        'music_id',
        'service',
        'search_type',
        'title',
        'artist',
        'album',
        'release_year',
        'score',
        'external_id',
        'raw_data',
    ];

    protected $casts = [
        'raw_data' => 'array',
        'score' => 'decimal:2',
        'release_year' => 'integer',
    ];

    /**
     * Get the music record that owns this metadata result
     */
    public function music(): BelongsTo
    {
        return $this->belongsTo(Music::class);
    }

    /**
     * Scope to filter by service
     */
    public function scopeByService($query, string $service)
    {
        return $query->where('service', $service);
    }

    /**
     * Scope to filter by search type
     */
    public function scopeBySearchType($query, string $searchType)
    {
        return $query->where('search_type', $searchType);
    }

    /**
     * Scope to order by score (highest first)
     */
    public function scopeOrderByScore($query)
    {
        return $query->orderByDesc('score');
    }
}

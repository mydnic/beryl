<?php

namespace App\Observers;

use App\Jobs\SearchMusicMetadataJob;
use App\Models\Music;

class MusicObserver
{
    public function created(Music $music): void
    {
        $music->syncTags();
//        dispatch(new SearchMusicMetadataJob($music))->delay(now()->addSeconds(5));
    }
}

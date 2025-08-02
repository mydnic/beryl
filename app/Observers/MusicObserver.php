<?php

namespace App\Observers;

use App\Jobs\SearchMusicMetadataJob;
use App\Jobs\TriggerMetadataSearchJob;
use App\Models\Music;

class MusicObserver
{
    public function created(Music $music): void
    {
        $music->syncTags();

        // Dispatch separate jobs for each metadata service
        dispatch(new TriggerMetadataSearchJob($music));
    }
}

<?php

namespace App\Observers;

use App\Models\Music;

class MusicObserver
{
    public function created(Music $music): void
    {
        $music->syncTags();
//        dispatch()
    }
}

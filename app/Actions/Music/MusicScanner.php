<?php

namespace App\Actions\Music;

class MusicScanner
{
    public function handle()
    {
        $path = config('filesystems.disks.music_directory.root');
    }
}

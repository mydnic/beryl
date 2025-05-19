<?php

namespace App\Actions\Music;

class MusicScanner
{
    public function handle()
    {
        $path = config('filesystems.disks.music_directory.root');

        // scan the directory for music files
        $files = collect(scandir($path))->filter(function ($file) {
            return $file !== '.' && $file !== '..';
        });

        dd($files);

        return $files;
    }
}

<?php

namespace App\Actions\Music;

class MusicScanner
{
    public function scan()
    {
        $path = config('filesystems.disks.music_directory.root');

        // scan the directory for music files
        $files = collect(scandir($path))->filter(function ($file) {
            $toExclude = ['.', '..', '.DS_Store'];

            return ! in_array($file, $toExclude);
        });

        dd($files);

        return $files;
    }
}

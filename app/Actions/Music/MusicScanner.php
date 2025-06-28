<?php

namespace App\Actions\Music;

use App\Jobs\ScanMusicDirectory;

class MusicScanner
{
    protected $files = [];

    /**
     * List of supported audio file extensions
     *
     * @var array
     */
    protected $supportedExtensions = [
        'mp3', 'flac', 'm4a', 'wav', 'ogg', 'aac', 'wma', 'aiff', 'alac'
    ];

    /**
     * Initiate the scanning process by dispatching the root directory job
     *
     * @return void
     */
    public function scan()
    {
        $path = config('filesystems.disks.music_directory.root');
        
        // Dispatch the initial job to scan the root music directory
        ScanMusicDirectory::dispatch($path);
        
        return "Music scanning jobs have been queued. Check the queue worker logs for progress.";
    }
}

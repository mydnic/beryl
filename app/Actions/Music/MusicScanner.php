<?php

namespace App\Actions\Music;

class MusicScanner
{
    protected $files = [];

    public function scan()
    {
        $path = config('filesystems.disks.music_directory.root');

        // Recursively scan the directory for all files
        $this->scanDirectory($path);

        return collect($this->files);
    }

    /**
     * Recursively scan a directory and collect all file paths
     *
     * @param string $directory The directory to scan
     * @return void
     */
    protected function scanDirectory($directory)
    {
        $toExclude = ['.', '..', '.DS_Store'];
        
        // Get all items in the current directory
        $items = scandir($directory);
        
        foreach ($items as $item) {
            // Skip excluded items
            if (in_array($item, $toExclude)) {
                continue;
            }
            
            $path = $directory . DIRECTORY_SEPARATOR . $item;
            
            if (is_dir($path)) {
                // If it's a directory, recursively scan it
                $this->scanDirectory($path);
            } else {
                // If it's a file, add it to our files collection
                $this->files[] = $path;
            }
        }
    }
}

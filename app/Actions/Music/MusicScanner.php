<?php

namespace App\Actions\Music;

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
        $toExclude = ['.', '..', '.DS_Store', '@eaDir'];

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
                // If it's a file, check if it's a supported audio file
                if ($this->isSupportedAudioFile($path)) {
                    $this->files[] = $path;
                }
            }
        }
    }

    /**
     * Check if a file is a supported audio file based on its extension
     *
     * @param string $filePath The file path to check
     * @return bool
     */
    protected function isSupportedAudioFile($filePath)
    {
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        return in_array($extension, $this->supportedExtensions);
    }
}

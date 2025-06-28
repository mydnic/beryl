<?php

namespace App\Jobs;

use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ScanMusicDirectory implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The directory path to scan
     *
     * @var string
     */
    protected $directory;

    /**
     * List of supported audio file extensions
     *
     * @var array
     */
    protected $supportedExtensions = [
        'mp3', 'flac', 'm4a', 'wav', 'ogg', 'aac', 'wma', 'aiff', 'alac'
    ];

    /**
     * Create a new job instance.
     *
     * @param string $directory
     * @return void
     */
    public function __construct($directory)
    {
        $this->directory = $directory;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $this->scanDirectory($this->directory);
    }

    /**
     * Scan a directory and dispatch jobs for subdirectories
     *
     * @param string $directory The directory to scan
     * @return void
     */
    protected function scanDirectory($directory)
    {
        $toExclude = ['.', '..', '.DS_Store', '@eaDir'];

        try {
            // Get all items in the current directory
            $items = scandir($directory);

            foreach ($items as $item) {
                // Skip excluded items
                if (in_array($item, $toExclude)) {
                    continue;
                }

                $path = $directory . DIRECTORY_SEPARATOR . $item;

                if (is_dir($path)) {
                    // If it's a directory, dispatch a new job to scan it
                    self::dispatch($path);
                } else {
                    // If it's a file, check if it's a supported audio file
                    if ($this->isSupportedAudioFile($path)) {
                        // Dispatch the existing ProcessMusicFileJob to handle the file
                        ProcessMusicFileJob::dispatch($path);
                    }
                }
            }
        } catch (Exception $e) {
            Log::error('Error scanning directory: ' . $directory . ' - ' . $e->getMessage());
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

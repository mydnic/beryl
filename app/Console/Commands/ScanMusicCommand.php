<?php

namespace App\Console\Commands;

use App\Actions\Music\MusicScanner;
use Illuminate\Console\Command;

class ScanMusicCommand extends Command
{
    protected $signature = 'scan:music';

    protected $description = 'Command description';

    public function handle(): void
    {
        (new MusicScanner())->scan();
    }
}

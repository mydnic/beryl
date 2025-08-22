<?php

use App\Console\Commands\ScanMusicCommand;
use App\Console\Commands\ReconcileMissingMusicFilesCommand;
use Illuminate\Support\Facades\Schedule;

Schedule::command(ScanMusicCommand::class)->daily();
Schedule::command(ReconcileMissingMusicFilesCommand::class)->hourly();

<?php

use App\Console\Commands\ScanMusicCommand;

Schedule::command(ScanMusicCommand::class)->daily();

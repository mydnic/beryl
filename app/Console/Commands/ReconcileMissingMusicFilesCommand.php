<?php

namespace App\Console\Commands;

use App\Models\Music;
use Illuminate\Console\Command;

class ReconcileMissingMusicFilesCommand extends Command
{
    protected $signature = 'music:reconcile-missing {--dry-run : Only report, do not delete}';

    protected $description = 'Delete music records from the database when the underlying file no longer exists on disk';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $deleted = 0;
        $missing = 0;
        $checked = 0;

        $this->info(($dryRun ? '[DRY RUN] ' : '') . 'Reconciling missing music files...');

        Music::query()
            ->orderBy('id')
            ->chunk(500, function ($musics) use (&$deleted, &$missing, &$checked, $dryRun) {
                foreach ($musics as $music) {
                    $checked++;
                    if (!file_exists($music->filepath)) {
                        $missing++;
                        $this->line("Missing: {$music->id} | {$music->filepath}");
                        if (!$dryRun) {
                            // Deleting the model will cascade delete metadata results (FK onDelete('cascade'))
                            $music->delete();
                            $deleted++;
                        }
                    }
                }
            });

        $this->info("Checked: {$checked} | Missing: {$missing} | Deleted: {$deleted}");
        return Command::SUCCESS;
    }
}

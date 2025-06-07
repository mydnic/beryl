<?php

namespace App\Jobs;

use App\Models\Music;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\PrivateChannel;
class ProcessMusicFileJob implements ShouldQueue, ShouldBroadcast
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public string $filePath)
    {
    }

    public function handle(): void
    {
        // check if a Music record exists in the database for this file
        $music = Music::firstWhere('filepath', $this->filePath);

        if (!$music) {
            // if not, create a new Music record
            $music = new Music();
            $music->filepath = $this->filePath;
            $music->save();
        }
    }

    public function broadcastOn(): Channel
    {
        return new Channel('music-added');
    }
}

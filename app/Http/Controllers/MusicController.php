<?php

namespace App\Http\Controllers;

use App\Actions\Music\MusicScanner;
use App\Jobs\ProcessMusicFileJob;
use App\Jobs\SearchMusicMetadataJob;
use App\Models\Music;
use Illuminate\Support\Facades\Http;

class MusicController extends Controller
{
    public function index()
    {
        $musics = Music::all();
        return inertia('Music/Index', compact('musics'));
    }

    public function scan()
    {
        if (request()->boolean('truncate', true)) {
             Music::truncate();
        }

        $files = (new MusicScanner())->scan();

        foreach ($files as $file) {
            dispatch(new ProcessMusicFileJob($file));
        }

        return back();
    }

    public function searchMetadata(Music $music)
    {
        dispatch(new SearchMusicMetadataJob($music));

        return back();
    }

    public function destroy(Music $music)
    {
        $music->deleteFile();
        $music->delete();

        return back();
    }
}

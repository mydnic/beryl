<?php

namespace App\Http\Controllers;

use App\Actions\Music\MusicScanner;
use App\Jobs\ProcessMusicFileJob;
use App\Jobs\SearchMusicMetadataJob;
use App\Models\Music;
use Exception;
use getID3_writetags;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Request;

class MusicController extends Controller
{
    public function index(Request $request)
    {
        $query = Music::query();

        // Handle search
        if ($request->filled('search')) {
            $search = $request->get('search');
            $query->where(function ($q) use ($search) {
                $q->where('title', 'LIKE', "%{$search}%")
                  ->orWhere('artist', 'LIKE', "%{$search}%")
                  ->orWhere('album', 'LIKE', "%{$search}%")
                  ->orWhere('filepath', 'LIKE', "%{$search}%");
            });
        }

        // Handle sorting
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');

        $allowedSorts = ['artist', 'created_at', 'release_year'];
        $allowedOrders = ['asc', 'desc'];

        if (in_array($sortBy, $allowedSorts) && in_array($sortOrder, $allowedOrders)) {
            $query->orderBy($sortBy, $sortOrder);
        } else {
            $query->orderBy('created_at', 'desc');
        }

        $musics = $query->paginate(100)->withQueryString();

        return inertia('Music/Index', [
            'musics' => $musics,
            'filters' => [
                'search' => $request->get('search', ''),
                'sort_by' => $sortBy,
                'sort_order' => $sortOrder,
            ]
        ]);
    }

    public function scan()
    {
        if (request()->boolean('truncate')) {
             Music::truncate();
        }

        // Initialize the scanner which will queue directory scanning jobs
        $message = (new MusicScanner())->scan();

        // Return with a success message
        return back()->with('success', $message);
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

    public function stream(Music $music)
    {
        if (!file_exists($music->filepath)) {
            abort(404);
        }

        return response()->file($music->filepath);
    }

    public function applyMetadata(Music $music)
    {
        $metadata = request()->input('metadata');

        try {
            // Create a new getID3 writer object
            require_once base_path('vendor/james-heinrich/getid3/getid3/getid3.php');
            require_once base_path('vendor/james-heinrich/getid3/getid3/write.php');

            // Initialize getID3 tag writer
            $getID3 = new getID3_writetags();
            $getID3->filename = $music->filepath;
            $getID3->tagformats = ['id3v1', 'id3v2.3'];
            $getID3->overwrite_tags = true;
            $getID3->tag_encoding = 'UTF-8';
            $getID3->tag_data = [
                'title'  => [$metadata['title'] ?? ''],
                'artist' => [$metadata['artist'] ?? ''],
                'album'  => [$metadata['album'] ?? ''],
                'year'   => [$metadata['year'] ?? ''],
            ];

            // Write the tags
            if ($getID3->WriteTags()) {
                // Sync the tags in the database
                $music->syncTags();

                return back()->with('success', 'Metadata applied successfully');
            } else {
                return back()->with('error', 'Failed to apply metadata: ' . implode(', ', $getID3->errors));
            }
        } catch (Exception $e) {
            return back()->with('error', 'Error applying metadata: ' . $e->getMessage());
        }
    }
}

<?php

namespace App\Http\Controllers;

use App\Actions\Music\MusicScanner;
use App\Jobs\ProcessMusicFileJob;
use App\Jobs\SearchMusicMetadataFromFilenameJob;
use App\Jobs\SearchMusicMetadataJob;
use App\Jobs\TriggerMetadataSearchJob;
use App\Models\Music;
use App\Services\AudioTagService;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Request;

class MusicController extends Controller
{
    public function index(Request $request)
    {
        $query = Music::query()
            ->with(['metadataResults' => function ($q) {
                $q->orderByDesc('score');
            }]);

        // Handle search
        if ($request->filled('search')) {
            $search = trim($request->get('search'));
            // Split by whitespace to support multi-term search (e.g., "avicii Edom")
            $terms = preg_split('/\s+/', $search, -1, PREG_SPLIT_NO_EMPTY) ?: [];

            $query->where(function ($outer) use ($terms, $search) {
                if (count($terms) <= 1) {
                    // Single term behavior (backward compatible)
                    $like = "%{$search}%";
                    $outer->where('title', 'LIKE', $like)
                        ->orWhere('artist', 'LIKE', $like)
                        ->orWhere('album', 'LIKE', $like)
                        ->orWhere('filepath', 'LIKE', $like);
                    return;
                }

                // For multiple terms: require ALL terms to be present in any of the fields (AND of ORs)
                foreach ($terms as $term) {
                    $outer->where(function ($q) use ($term) {
                        $like = "%{$term}%";
                        $q->where('title', 'LIKE', $like)
                          ->orWhere('artist', 'LIKE', $like)
                          ->orWhere('album', 'LIKE', $like)
                          ->orWhere('filepath', 'LIKE', $like);
                    });
                }
            });
        }

        // Handle "needs fixing" filter using fast boolean flag
        if ($request->boolean('needs_fixing')) {
            $query->where('need_fixing', true);
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

        // Get job statistics
        $jobController = new JobController();
        $jobStats = $jobController->index();

        return inertia('Music/Index', [
            'musics' => $musics,
            'filters' => [
                'search' => $request->get('search', ''),
                'sort_by' => $sortBy,
                'sort_order' => $sortOrder,
                'needs_fixing' => $request->boolean('needs_fixing'),
            ],
            'job_stats' => $jobStats,
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
        // Dispatch separate jobs for each metadata service
        dispatch(new TriggerMetadataSearchJob($music));

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
            app(AudioTagService::class)->applyToMusic($music, $metadata ?? []);

            // Sync the tags in the database
            $music->syncTags();

            // Mark as no longer needing fixing
            $music->need_fixing = false;
            $music->save();

            return back()->with('success', 'Metadata applied successfully');
        } catch (Exception $e) {
            return back()->with('error', 'Error applying metadata: ' . $e->getMessage());
        }
    }

    public function markClean(Music $music)
    {
        $music->update(['need_fixing' => false]);
        return back()->with('success', "Marked as not needing fixing");
    }
}

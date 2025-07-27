<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class JobController extends Controller
{
    public function index()
    {
        return $this->getJobStats();
    }

    public function stats()
    {
        return response()->json($this->getJobStats());
    }

    private function getJobStats()
    {
        // Get job statistics from the jobs table
        $totalJobs = DB::table('jobs')->count();
        $failedJobs = DB::table('failed_jobs')->count();

        // Get job counts by type
        $jobsByType = DB::table('jobs')
            ->select(DB::raw('
                CASE
                    WHEN payload LIKE "%ScanMusicDirectory%" THEN "Directory Scanning"
                    WHEN payload LIKE "%ProcessMusicFileJob%" THEN "Processing Files"
                    WHEN payload LIKE "%SearchMusicMetadataJob%" THEN "Fetching Metadata"
                    WHEN payload LIKE "%SearchMusicMetadataFromFilenameJob%" THEN "Fetching Metadata"
                    ELSE "Other"
                END as job_type
            '), DB::raw('COUNT(*) as count'))
            ->groupBy('job_type')
            ->get()
            ->keyBy('job_type')
            ->map(fn($item) => $item->count);

        // Get recent failed jobs for debugging
        $recentFailedJobs = DB::table('failed_jobs')
            ->select('payload', 'exception', 'failed_at')
            ->orderBy('failed_at', 'desc')
            ->limit(5)
            ->get()
            ->map(function ($job) {
                $payload = json_decode($job->payload, true);
                $jobClass = $payload['displayName'] ?? 'Unknown';

                return [
                    'job_class' => $jobClass,
                    'failed_at' => $job->failed_at,
                    'error' => substr($job->exception, 0, 200) . '...'
                ];
            });

        return [
            'total_pending' => $totalJobs,
            'total_failed' => $failedJobs,
            'jobs_by_type' => $jobsByType,
            'recent_failed' => $recentFailedJobs,
            'is_processing' => $totalJobs > 0,
        ];
    }
}

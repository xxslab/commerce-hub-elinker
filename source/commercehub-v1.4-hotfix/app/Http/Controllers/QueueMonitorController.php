<?php

namespace App\Http\Controllers;

use App\Models\SalesChannel;
use Illuminate\Support\Facades\DB;

class QueueMonitorController extends Controller
{
    public function index()
    {
        $jobsTable = DB::getSchemaBuilder()->hasTable('jobs');
        $failedTable = DB::getSchemaBuilder()->hasTable('failed_jobs');

        $queued = $jobsTable ? DB::table('jobs')->count() : 0;
        $reserved = $jobsTable ? DB::table('jobs')->whereNotNull('reserved_at')->count() : 0;
        $failed = $failedTable ? DB::table('failed_jobs')->count() : 0;

        $jobs = $jobsTable
            ? DB::table('jobs')->orderByDesc('id')->limit(20)->get()
            : collect();

        $failedJobs = $failedTable
            ? DB::table('failed_jobs')->orderByDesc('id')->limit(10)->get()
            : collect();

        $channels = SalesChannel::orderBy('id')->get();

        return view('queue.index', compact('queued', 'reserved', 'failed', 'jobs', 'failedJobs', 'channels'));
    }
}

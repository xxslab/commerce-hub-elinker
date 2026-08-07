<?php

namespace App\Http\Controllers;

use App\Models\SalesChannel;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

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

        $channels = SalesChannel::where('company_id', $this->company()->id)->orderBy('id')->get();

        return view('queue.index', compact('queued', 'reserved', 'failed', 'jobs', 'failedJobs', 'channels'));
    }

    public function clearFailed(Request $request)
    {
        abort_unless($request->user(), 403);

        if (DB::getSchemaBuilder()->hasTable('failed_jobs')) {
            DB::table('failed_jobs')->delete();
        }

        return back()->with('ok', 'Obsłużone failed jobs zostały wyczyszczone.');
    }
}

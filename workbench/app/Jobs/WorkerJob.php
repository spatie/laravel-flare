<?php

namespace Workbench\App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

class WorkerJob implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        DB::table('users')->first();

        cache()->set('hello', 'world');
        cache()->get('hello');
    }

    public function tags(): array
    {
        return ['worker', 'job'];
    }
}

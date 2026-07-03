<?php

namespace Workbench\App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Workbench\App\Jobs\Concerns\Queueable;

class DeletedJob implements ShouldQueue
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
        $this->delete();
    }

    public function tags(): array
    {
        return ['delete', 'job'];
    }
}

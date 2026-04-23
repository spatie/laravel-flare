<?php

namespace Workbench\App\Jobs;

use Exception;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

class AttemptTestJob implements ShouldQueue
{
    use Queueable;

    public $tries = 5;

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
        ray('Attempt Test Job ' . $this->attempts());

        if($this->attempts() !== 5){
            throw new Exception('Failed');
        }
    }

    public function tags(): array
    {
        return ['attempts', 'job'];
    }
}

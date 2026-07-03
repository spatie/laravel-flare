<?php

namespace Workbench\App\Jobs;

use Exception;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class BatchedJob implements ShouldQueue
{
    use Batchable;
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public bool $shouldFail = false,
        public bool $shouldAddAnotherJob = false,
    ) {
    }

    public static function success(): self
    {
        return new self();
    }

    public static function failed(): self
    {
        return new self(shouldFail: true);
    }

    public static function addingAnotherJob(): self
    {
        return new self(shouldAddAnotherJob: true);
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        if ($this->shouldFail) {
            throw new Exception('Batched job failed');
        }

        if($this->shouldAddAnotherJob) {
            $this->batch()?->add(new self());
        }
    }

    public function tags(): array
    {
        return ['batch', 'job'];
    }
}

<?php

namespace Spatie\LaravelFlare\Tests\TestClasses;

use Exception;
use GuzzleHttp\Exception\ConnectException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Spatie\FlareClient\Enums\FlareEntityType;
use Spatie\FlareClient\Tests\Shared\ExpectLogData;
use Spatie\FlareClient\Tests\Shared\ExpectReport;
use Spatie\FlareClient\Tests\Shared\ExpectTrace;
use SplFileInfo;

class ExpectSentPayloads
{
    protected string $url = 'http://127.0.0.1:8000';

    public static function get(
        string $endpoint,
        ?int $waitAtLeastMs = null,
        bool $waitUntilAllJobsAreProcessed = false,
    ): self {
        return new self($endpoint, 'get', waitAtLeastMs: $waitAtLeastMs, waitUntilAllJobsAreProcessed: $waitUntilAllJobsAreProcessed);
    }

    public static function post(
        string $endpoint,
        array $parameters,
        ?int $waitAtLeastMs = null
    ): self {
        return new self($endpoint, 'post', $parameters, waitAtLeastMs: $waitAtLeastMs);
    }

    private string $workSpacePath;

    /**
     * @param string $endpoint
     * @param array<int, ExpectReport> $reports
     * @param array<int, ExpectTrace> $traces
     * @param array<int, ExpectLogData> $logs
     */
    public function __construct(
        public string $endpoint,
        public string $method,
        public array $params = [],
        public array $reports = [],
        public array $traces = [],
        public array $logs = [],
        ?int $waitAtLeastMs = null,
        bool $waitUntilAllJobsAreProcessed = false,
    ) {
        $this->workSpacePath = __DIR__.'/../../workbench/storage';

        // The shared queue worker may still be flushing a trace file from a previous
        // test (the JobProcessed handler runs after the row is removed from `jobs`).
        // Drain the workspace until it stays empty long enough for any in-flight
        // writes to land and be cleaned up.
        $this->drainWorkspace();

        $this->initializeWorkspace(
            waitAtLeastMs: $waitAtLeastMs,
            waitUntilAllJobsAreProcessed: $waitUntilAllJobsAreProcessed
        );
    }

    public function assertSent(?int $reports = 0, ?int $traces = 0, ?int $logs = 0): void
    {
        if ($reports !== null) {
            $this->assertReportsSent($reports);
        }

        if ($traces !== null) {
            $this->assertTracesSent($traces);
        }

        if ($logs !== null) {
            $this->assertLogsSent($logs);
        }
    }

    public function assertReportsSent(int $expectedCount): void
    {
        expect(count($this->reports))->toBe($expectedCount, 'Number of reports sent does not match expected count.');
    }

    public function assertTracesSent(int $expectedCount): void
    {
        expect(count($this->traces))->toBe($expectedCount, 'Number of traces sent does not match expected count.');
    }

    public function assertLogsSent(int $expectedCount): void
    {
        expect(count($this->logs))->toBe($expectedCount, 'Number of logs sent does not match expected count.');
    }

    public function assertNothingSent(): void
    {
        $this->assertSent(
            reports: 0,
            traces: 0,
            logs: 0,
        );
    }

    public function report(int $index): ExpectReport
    {
        return $this->reports[$index];
    }

    public function trace(int $index): ExpectTrace
    {
        return $this->traces[$index];
    }

    public function log(int $index): ExpectLogData
    {
        return $this->logs[$index];
    }

    public function lastReport(): ExpectReport
    {
        return $this->reports[array_key_last($this->reports)];
    }

    public function lastTrace(): ExpectTrace
    {
        return $this->traces[array_key_last($this->traces)];
    }

    public function lastLog(): ExpectLogData
    {
        return $this->logs[array_key_last($this->logs)];
    }


    public function __destruct()
    {
        $this->cleanupWorkspace();
    }

    protected function cleanupWorkspace(): void
    {
        File::delete(array_map(
            fn (SplFileInfo $file) => $file->getRealPath(),
            File::files($this->workSpacePath),
        ));
    }

    /**
     * Repeatedly clean the workspace until it stays empty for a full stability
     * window. This protects against in-flight trace file writes from the previous
     * test's queue worker leaking into this test's payloads.
     */
    protected function drainWorkspace(int $stabilityWindowMs = 3_000, int $maxWaitMs = 10_000): void
    {
        $checkIntervalUs = 50_000;
        $requiredStableChecks = max(1, intdiv($stabilityWindowMs * 1_000, $checkIntervalUs));
        $maxIterations = max($requiredStableChecks, intdiv($maxWaitMs * 1_000, $checkIntervalUs));

        $consecutiveEmpty = 0;

        for ($i = 0; $i < $maxIterations; $i++) {
            $this->cleanupWorkspace();
            usleep($checkIntervalUs);

            if (count(File::files($this->workSpacePath)) === 0) {
                $consecutiveEmpty++;

                if ($consecutiveEmpty >= $requiredStableChecks) {
                    return;
                }

                continue;
            }

            $consecutiveEmpty = 0;
        }

        $this->cleanupWorkspace();
    }

    protected function initializeWorkspace(
        ?int $waitAtLeastMs,
        bool $waitUntilAllJobsAreProcessed,
    ): void {
        // The workbench endpoints can make slow external HTTP calls (e.g. jsonplaceholder).
        // A short timeout here triggers restartServer for what is really just a slow upstream,
        // and that restart can clobber the workbench/storage symlink and cascade-fail the rest
        // of the suite. Give the server room to wait on its own upstream timeouts first.
        $client = Http::timeout(30)->baseUrl($this->url);

        try {
            $response = match ($this->method) {
                'get' => $client->get($this->endpoint),
                'post' => $client->post($this->endpoint, $this->params),
                default => throw new \InvalidArgumentException("Unsupported method {$this->method}"),
            };
        } catch (ConnectException|ConnectionException $e) {
            if (! $this->restartServer()) {
                throw new Exception('Workbench server is not running. Please start it by running `composer run serve`');
            }

            try {
                $response = match ($this->method) {
                    'get' => $client->get($this->endpoint),
                    'post' => $client->post($this->endpoint, $this->params),
                };
            } catch (ConnectException|ConnectionException $e) {
                throw new Exception('Workbench server is not running after restart attempt.');
            }
        }

        $this->wait(
            waitAtLeastMs: $waitAtLeastMs,
            waitUntilAllJobsAreProcessed: $waitUntilAllJobsAreProcessed,
        );

        foreach (File::files($this->workSpacePath) as $file) {
            $entityType = match (true) {
                str_starts_with($file->getFilename(), FlareEntityType::Errors->value) => FlareEntityType::Errors,
                str_starts_with($file->getFilename(), FlareEntityType::Traces->value) => FlareEntityType::Traces,
                str_starts_with($file->getFilename(), FlareEntityType::Logs->value) => FlareEntityType::Logs,
                default => null,
            };

            if ($entityType === null) {
                continue;
            }

            // Filenames carry a microtime sequence prefix (see FileSender) so sorting by filename
            // matches the chronological order in which the entities were sent. Inode order is
            // not reliable across filesystems.
            $key = $file->getFilename();

            if (array_key_exists($key, match ($entityType) {
                FlareEntityType::Errors => $this->reports,
                FlareEntityType::Traces => $this->traces,
                FlareEntityType::Logs => $this->logs,
            })) {
                continue;
            }

            $content = json_decode(File::get($file->getRealPath()), true);

            match ($entityType) {
                FlareEntityType::Errors => $this->reports[$key] = new ExpectReport($content),
                FlareEntityType::Traces => $this->traces[$key] = new ExpectTrace($content),
                FlareEntityType::Logs => $this->logs[$key] = new ExpectLogData($content),
            };
        }

        ksort($this->reports);
        ksort($this->traces);
        ksort($this->logs);

        $this->reports = array_values($this->reports);
        $this->traces = array_values($this->traces);
        $this->logs = array_values($this->logs);
    }

    protected function restartServer(): bool
    {
        $testbench = __DIR__ . '/../../vendor/bin/testbench';

        exec("php {$testbench} serve --port=8000 > /dev/null 2>&1 &");
        exec("php {$testbench} queue:work > /dev/null 2>&1 &");

        for ($i = 0; $i < 50; $i++) {
            usleep(100_000);

            try {
                Http::timeout(1)->baseUrl($this->url)->get('/');

                return true;
            } catch (ConnectException|ConnectionException) {
            }
        }

        return false;
    }

    protected function wait(
        ?int $waitAtLeastMs,
        bool $waitUntilAllJobsAreProcessed,
    ): void {
        if ($waitAtLeastMs !== null) {
            usleep($waitAtLeastMs);
        }

        if (! $waitUntilAllJobsAreProcessed) {
            // Even tests that don't drive the queue can have trace writes still in flight
            // (e.g. dispatchAfterResponse jobs flush after the HTTP response was already
            // sent back to the client). Wait briefly for file count stability so we don't
            // read the workspace mid-write.
            $this->waitForFileStability(stabilityWindowMs: 750);

            return;
        }

        $backoff = [
            500_000,
            750_000,
            1_000_000,
            1_500_000,
            2_500_000,
            4_000_000,
        ];

        $currentBackoffIndex = 0;

        while (true) {
            if (! array_key_exists($currentBackoffIndex, $backoff)) {
                throw new Exception('Jobs were not executed, either make sure the worker is started by running `vendor/bin/testbench queue:work` or that we waited long enough for the jobs to be processed.');
            }

            usleep($backoff[$currentBackoffIndex]);

            if (DB::table('jobs')->count() !== 0) {
                $currentBackoffIndex++;

                continue;
            }

            // The queue worker deletes a job from the `jobs` table before its
            // JobProcessed handler finishes writing the trace file, and a job
            // can chain another job after it's already been popped. Wait until
            // the file count stays put and the queue stays empty across the
            // stability window before reading the workspace.
            if ($this->waitForFileStability()) {
                return;
            }

            $currentBackoffIndex++;
        }
    }

    /**
     * Wait until the workspace file count stays unchanged and the queue stays
     * empty for the full stability window. Returns false if a new job appears
     * before the window completes, signalling that the outer wait loop should
     * keep backing off.
     */
    protected function waitForFileStability(int $stabilityWindowMs = 3_000): bool
    {
        $checkIntervalUs = 50_000;
        $requiredStableChecks = max(1, intdiv($stabilityWindowMs * 1_000, $checkIntervalUs));

        $previousCount = count(File::files($this->workSpacePath));
        $consecutiveStable = 0;

        while ($consecutiveStable < $requiredStableChecks) {
            usleep($checkIntervalUs);

            if (DB::table('jobs')->count() !== 0) {
                return false;
            }

            $currentCount = count(File::files($this->workSpacePath));

            if ($currentCount === $previousCount) {
                $consecutiveStable++;

                continue;
            }

            $previousCount = $currentCount;
            $consecutiveStable = 0;
        }

        return true;
    }
}

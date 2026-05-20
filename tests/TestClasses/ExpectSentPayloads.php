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

        // The shared queue worker may still be flushing a trace file from the previous
        // test (the JobProcessed handler runs after the row is removed from `jobs`).
        // Clean, give late writes time to land, then clean again.
        $this->cleanupWorkspace();
        usleep(500_000);
        $this->cleanupWorkspace();

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

    protected function initializeWorkspace(
        ?int $waitAtLeastMs,
        bool $waitUntilAllJobsAreProcessed,
    ): void {
        // 30s timeout so slow upstream calls (e.g. jsonplaceholder in /http-post)
        // don't surface as ConnectExceptions on the client.
        $client = Http::timeout(30)->baseUrl($this->url);

        $this->sendRequest($client);

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

    /**
     * Send the request, retrying once if the workbench server has gone away.
     * dispatchAfterResponse jobs that throw can take down the PHP built-in server,
     * so spawn a fresh testbench serve and wait for the port to come back. We probe
     * with a raw TCP socket instead of an HTTP ping so the health check doesn't
     * leak a traced welcome-page request into the workspace.
     */
    protected function sendRequest($client): mixed
    {
        try {
            return $this->performRequest($client);
        } catch (ConnectException|ConnectionException $e) {
            if (! $this->ensureServerReachable()) {
                throw new Exception('Workbench server is not responding. Please start it by running `composer run serve`.', previous: $e);
            }

            return $this->performRequest($client);
        }
    }

    protected function performRequest($client): mixed
    {
        return match ($this->method) {
            'get' => $client->get($this->endpoint),
            'post' => $client->post($this->endpoint, $this->params),
            default => throw new \InvalidArgumentException("Unsupported method {$this->method}"),
        };
    }

    protected function ensureServerReachable(): bool
    {
        $testbench = __DIR__.'/../../vendor/bin/testbench';

        exec("php {$testbench} serve --port=8000 > /dev/null 2>&1 &");
        exec("php {$testbench} queue:work > /dev/null 2>&1 &");

        for ($i = 0; $i < 50; $i++) {
            $socket = @fsockopen('127.0.0.1', 8000, $errno, $errstr, 1);

            if ($socket !== false) {
                fclose($socket);

                return true;
            }

            usleep(100_000);
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

        // dispatchAfterResponse jobs and JobProcessed handlers can flush traces
        // after the response is returned to the client; give the server a moment
        // before we read the workspace.
        usleep(500_000);

        if (! $waitUntilAllJobsAreProcessed) {
            return;
        }

        $backoff = [500_000, 750_000, 1_000_000, 1_500_000, 2_500_000, 4_000_000];

        foreach ($backoff as $sleep) {
            usleep($sleep);

            if (DB::table('jobs')->count() !== 0) {
                continue;
            }

            // The worker deletes a job from `jobs` before its JobProcessed handler
            // finishes writing the trace file, and a job can chain another job after
            // it's already been popped. Hold for a generous window so the next test
            // doesn't pick up an in-flight write.
            usleep(1_000_000);

            if (DB::table('jobs')->count() === 0) {
                return;
            }
        }

        throw new Exception('Jobs were not executed, either make sure the worker is started by running `vendor/bin/testbench queue:work` or that we waited long enough for the jobs to be processed.');
    }
}

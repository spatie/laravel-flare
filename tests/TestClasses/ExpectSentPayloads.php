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
        $client = Http::timeout(2)->baseUrl($this->url);

        try {
            $response = match ($this->method) {
                'get' => $client->get($this->endpoint),
                'post' => $client->post($this->endpoint, $this->params),
                default => throw new \InvalidArgumentException("Unsupported method {$this->method}"),
            };
        } catch (ConnectException|ConnectionException $e) {
            throw new Exception('Workbench server is not running. Please start it by running `composer run serve`');
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

            if (array_key_exists($file->getInode(), match ($entityType) {
                FlareEntityType::Errors => $this->reports,
                FlareEntityType::Traces => $this->traces,
                FlareEntityType::Logs => $this->logs,
            })) {
                continue;
            }

            $content = json_decode(File::get($file->getRealPath()), true);

            match ($entityType) {
                FlareEntityType::Errors => $this->reports[$file->getInode()] = new ExpectReport($content),
                FlareEntityType::Traces => $this->traces[$file->getInode()] = new ExpectTrace($content),
                FlareEntityType::Logs => $this->logs[$file->getInode()] = new ExpectLogData($content),
            };
        }

        ksort($this->reports);
        ksort($this->traces);
        ksort($this->logs);

        $this->reports = array_values($this->reports);
        $this->traces = array_values($this->traces);
        $this->logs = array_values($this->logs);
    }

    protected function wait(
        ?int $waitAtLeastMs,
        bool $waitUntilAllJobsAreProcessed,
    ): void {
        if ($waitAtLeastMs === null && ! $waitUntilAllJobsAreProcessed) {
            usleep(500); // Just to be sure

            return;
        }

        if ($waitAtLeastMs !== null) {
            usleep($waitAtLeastMs);
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
            if(! array_key_exists($currentBackoffIndex, $backoff)) {
                throw new Exception('Jobs were not executed, either make sure the worker is started by running `vendor/bin/testbench queue:work` or that we waited long enough for the jobs to be processed.');
            }

            usleep($backoff[$currentBackoffIndex]);

            $pendingJobs = DB::table('jobs');

            if($pendingJobs->count() === 0) {
                return;
            }

            $currentBackoffIndex++;
        }
    }
}

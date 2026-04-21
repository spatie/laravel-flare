<?php

namespace Spatie\LaravelFlare\Tests\TestClasses;

use Exception;
use GuzzleHttp\Exception\ConnectException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use PDO;

class Workspace
{
    private static ?int $serverPid = null;

    private static ?int $queuePid = null;

    private static bool $built = false;

    public static function start(): void
    {
        if (! self::$built) {
            self::build();
            self::$built = true;
        }

        self::stop();
        self::truncateDatabase();
        self::startServer();
        self::startQueueWorker();
        self::waitForServer();
    }

    public static function stop(): void
    {
        self::killProcesses();
    }

    private static function build(): void
    {
        $testbench = self::testbenchPath();

        exec("php {$testbench} workbench:build 2>&1");
    }

    private static function truncateDatabase(): void
    {
        $dbPath = self::databasePath();

        if (! file_exists($dbPath)) {
            return;
        }

        $pdo = new PDO("sqlite:{$dbPath}");
        $tables = ['posts', 'jobs', 'failed_jobs', 'job_batches', 'cache', 'cache_locks'];

        foreach ($tables as $table) {
            $pdo->exec("DELETE FROM {$table}");
        }
    }

    private static function databasePath(): string
    {
        return __DIR__ . '/../../vendor/orchestra/testbench-core/laravel/database/database.sqlite';
    }

    private static function startServer(): void
    {
        $testbench = self::testbenchPath();

        $output = [];
        exec("php {$testbench} serve --port=8000 > /dev/null 2>&1 & echo $!", $output);

        self::$serverPid = (int) ($output[0] ?? 0);
    }

    private static function startQueueWorker(): void
    {
        $testbench = self::testbenchPath();

        $output = [];
        exec("php {$testbench} queue:work --sleep=0 > /dev/null 2>&1 & echo $!", $output);

        self::$queuePid = (int) ($output[0] ?? 0);
    }

    private static function waitForServer(): void
    {
        for ($i = 0; $i < 50; $i++) {
            usleep(100_000);

            try {
                Http::timeout(1)->get('http://127.0.0.1:8000/');

                return;
            } catch (ConnectException|ConnectionException) {
            }
        }

        throw new Exception('Workbench server failed to start within 5 seconds.');
    }

    private static function killProcesses(): void
    {
        if (self::$serverPid > 0) {
            exec("kill " . self::$serverPid . " 2>/dev/null");
            exec("pkill -P " . self::$serverPid . " 2>/dev/null");
            self::$serverPid = null;
        }

        if (self::$queuePid > 0) {
            exec("kill " . self::$queuePid . " 2>/dev/null");
            exec("pkill -P " . self::$queuePid . " 2>/dev/null");
            self::$queuePid = null;
        }

        exec("lsof -ti :8000 | xargs kill -9 2>/dev/null");

        usleep(200_000);
    }

    private static function testbenchPath(): string
    {
        return __DIR__ . '/../../vendor/bin/testbench';
    }
}

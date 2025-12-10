<?php

namespace Spatie\LaravelFlare\Tests\TestClasses;

use Exception;
use Illuminate\Process\InvokedProcess;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;

class WorkbenchServer
{
    public static InvokedProcess $process;

    public static string $url = 'http://127.0.0.1';

    public static int $port = 8000;

    public static function setup(): void
    {
        if (isset(self::$process)) {
            return;
        }

        self::$process = Process::timeout(200)->command('composer run serve')->start();

        // Todo listen to the output to check when ready and update url and port

        sleep(3); // allow some time for the server to start

        try {
            Http::timeout(2)->get(self::fullUrl());
        } catch (Exception $e) {
            throw new Exception('Workbench server is not running at '.self::fullUrl().'. Please start it by running `composer run serve`');
        }
    }

    public static function fullUrl(): string
    {
        return self::$url.':'.self::$port;
    }

    public static function stop(): void
    {
        if (isset(self::$process)) {
            return;
        }

        self::$process->stop();

        unset(self::$process);
    }
}

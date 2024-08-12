<?php

namespace Spatie\LaravelFlare\Tests\stubs\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Spatie\LaravelFlare\Tests\stubs\Exceptions\ExpectedException;

class TestCommand extends Command
{
    protected $signature = 'flare:test-command {--option=} {--boolean-option} {argument=with-default} {--should-fail} {--run-nested}';

    protected $description = 'Test command';

    public function handle(): int
    {
        if ($this->option('should-fail')) {
            throw new ExpectedException('Test exception');
        }

        if($this->option('run-nested')) {
            Artisan::call('flare:test-command', [
                '--option' => 'nested',
                '--boolean-option' => true,
                'argument' => 'nested-argument',
            ]);
        }

        return 0;
    }
}

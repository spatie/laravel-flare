<?php

namespace Spatie\LaravelFlare\Commands;

use Illuminate\Console\Command;
use Spatie\LaravelFlare\Support\LaravelTester;

class TestCommand extends Command
{
    protected $signature = 'flare:test {--errors} {--logs} {--traces}';

    protected $description = 'Send a test notification to Flare';

    public function handle(): int
    {
        $tester = app(LaravelTester::class, [
            'input' => $this->input,
            'output' => $this->output,
        ]);

        return $tester->run() ? Command::SUCCESS : Command::FAILURE;
    }
}

<?php

namespace Workbench\App\Commands;

use Illuminate\Console\Command;

class SuccessfulCommand extends Command
{
    protected $signature = 'app:successful-command';

    protected $description = 'Run a successful command';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('I am a successful command!');
    }
}

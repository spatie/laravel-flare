<?php

namespace Workbench\App\Commands;

use Exception;
use Illuminate\Console\Command;

class FailingCommand extends Command
{
    protected $signature = 'app:failing-command';

    protected $description = 'Run a command which fails';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        throw new Exception('I failed, sorry!');
    }
}

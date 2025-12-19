<?php

namespace Workbench\App\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class NestedCommand extends Command
{
    protected $signature = 'app:nested-command';

    protected $description = 'Run a command which runs multiple nested commands';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        Artisan::call('app:successful-command');
        Artisan::call('app:failing-command');
        Artisan::call('app:successful-command');
    }
}

<?php

namespace Workbench\App\Commands;

use Exception;
use Illuminate\Console\Command;
use Spatie\LaravelFlare\Facades\Flare;

class ReportingCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:reporting-command';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        foreach (range(1, 10) as $index) {
            Flare::report(new Exception('Test'));
        }
    }
}

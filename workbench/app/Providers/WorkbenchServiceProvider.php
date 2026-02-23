<?php

namespace Workbench\App\Providers;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;
use Spatie\LaravelFlare\Recorders\FilesystemRecorder\FilesystemRecorder;

class WorkbenchServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        config()->set(
            'filesystems.disks.test',
            [
                'driver' => 'local',
                'root' => config('filesystems.disks.local.root'),
                'flare' => true,
            ]
        );

        // Ensure the disk is wrapped by Flare
        FilesystemRecorder::registered(
            $this->app,
            [],
        );
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        Blade::componentNamespace('Workbench\\App\\View\\Components', 'workbench');
    }
}

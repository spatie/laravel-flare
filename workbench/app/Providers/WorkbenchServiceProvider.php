<?php

namespace Workbench\App\Providers;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use Orchestra\Testbench\Http\Middleware\VerifyCsrfToken;
use Spatie\LaravelFlare\Facades\Flare;
use Spatie\LaravelFlare\Recorders\FilesystemRecorder\FilesystemRecorder;
use Workbench\App\Livewire\Counter;
use Workbench\App\Livewire\Inline;
use Workbench\App\Livewire\MountException;
use Workbench\App\Livewire\Nested;
use Workbench\App\Livewire\NestedViewException;
use Workbench\App\Livewire\ViewException;

class WorkbenchServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Laravel 10 defaults the queue connection to "sync".
        config()->set('queue.default', 'database');

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
        if ($this->runningOnLaravel10()) {
            // Laravel 11+ wires this through bootstrap/app.php.
            Flare::handles();

            // Laravel 11+ disables CSRF through bootstrap/app.php.
            $this->app['router']->removeMiddlewareFromGroup('web', VerifyCsrfToken::class);
        }

        Blade::componentNamespace('Workbench\\App\\View\\Components', 'workbench');

        Livewire::component('counter', Counter::class);
        Livewire::component('nested', Nested::class);
        Livewire::component('inline', Inline::class);
        Livewire::component('mount-exception', MountException::class);
        Livewire::component('view-exception', ViewException::class);
        Livewire::component('nested-view-exception', NestedViewException::class);
    }

    private function runningOnLaravel10(): bool
    {
        return version_compare($this->app->version(), '11.0', '<');
    }
}

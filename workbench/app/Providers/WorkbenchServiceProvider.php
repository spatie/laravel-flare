<?php

namespace Workbench\App\Providers;

use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
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
        // Laravel 10 defaults the queue connection to "sync"; Laravel 11+ defaults to
        // "database". Pin it so jobs are dispatched to the worker on every version.
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

        // Laravel 11+ disables CSRF for the workbench through bootstrap/app.php. On the
        // Laravel 10 skeleton that file is not used, so exclude every route here instead.
        // Testbench's web group resolves its own VerifyCsrfToken subclass, so bind that.
        if ($this->runningOnLegacySkeleton()) {
            $this->app->singleton(\Orchestra\Testbench\Http\Middleware\VerifyCsrfToken::class, fn ($app) => new class($app, $app['encrypter']) extends VerifyCsrfToken
            {
                protected $except = ['*'];
            });
        }
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // On Laravel 11+ exception reporting is wired through bootstrap/app.php's
        // withExceptions() closure. Laravel 10 uses testbench's default skeleton,
        // which does not run that file, so register Flare's reportable here.
        if ($this->runningOnLegacySkeleton()) {
            Flare::handles();
        }

        Blade::componentNamespace('Workbench\\App\\View\\Components', 'workbench');

        Livewire::component('counter', Counter::class);
        Livewire::component('nested', Nested::class);
        Livewire::component('inline', Inline::class);
        Livewire::component('mount-exception', MountException::class);
        Livewire::component('view-exception', ViewException::class);
        Livewire::component('nested-view-exception', NestedViewException::class);
    }

    // Laravel 11 replaced the App\Http\Kernel / bootstrap.php skeleton with bootstrap/app.php
    // and the Configuration\Exceptions object. Its absence means we are on Laravel 10.
    private function runningOnLegacySkeleton(): bool
    {
        return ! class_exists(Exceptions::class);
    }
}

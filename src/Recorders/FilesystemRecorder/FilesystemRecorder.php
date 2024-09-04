<?php

namespace Spatie\LaravelFlare\Recorders\FilesystemRecorder;

use Illuminate\Container\Container;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Arr;
use Spatie\FlareClient\Recorders\FilesystemRecorder\FilesystemRecorder as BaseFilesystemRecorder;
use Spatie\FlareClient\Support\BackTracer;
use Spatie\FlareClient\Tracer;
use Spatie\LaravelFlare\Filesystem\FlareFilesystemManager;

class FilesystemRecorder extends BaseFilesystemRecorder
{
    protected bool $trackAllDisks = false;

    protected const FLARE_PASS_THROUGH = '_flare_passthrough_configured';

    public function __construct(
        protected Tracer $tracer,
        protected BackTracer $backTracer,
        protected Container $container,
        array $config,
    ) {
        parent::__construct($tracer, $backTracer, $config);

        $this->trackAllDisks = $config['track_all_disks'] ?? false;
    }

    public static function registered(Application $container, array $config)
    {
        $shouldWrapDisks = $config['track_all_disks'] ?? false
        || Arr::first($config, fn ($disk) => is_array($disk) && array_key_exists('flare', $disk)) !== null;

        if ($shouldWrapDisks) {
            $container->singleton('filesystem', function () use ($config) {
                $manager = new FlareFilesystemManager(app());

                $manager->configureFlare($config);

                return $manager;
            });
        }
    }

    public function start(): void
    {
        // Registration of the FlareFilesystemManager is done in the registered method
    }
}

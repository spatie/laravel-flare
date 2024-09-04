<?php

namespace Spatie\LaravelFlare\Filesystem;

use Illuminate\Contracts\Filesystem\Cloud;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Filesystem\FilesystemManager;

class FlareFilesystemManager extends FilesystemManager
{
    protected array $flareConfig = [
        'trace' => false,
        'report' => false,
        'max_reported' => null,
        'track_all_disks' => false,
    ];

    /**
     * Resolve the given disk.
     *
     * @param string $name
     * @param array|null $config
     *
     * @return \Illuminate\Contracts\Filesystem\Filesystem
     *
     * @throws \InvalidArgumentException
     */
    protected function resolve($name, $config = null)
    {
        $filesystem = parent::resolve($name, $config);

        if (! $this->shouldWrapFilesystem($config)) {
            return $filesystem;
        }

        $trace = $config['flare']['trace'] ?? $this->flareConfig['trace'] ?? false;
        $report = $config['flare']['report'] ?? $this->flareConfig['report'] ?? false;
        $maxReports = $config['flare']['max_reported'] ?? $this->flareConfig['max_reported'] ?? null;

        if ($filesystem instanceof FilesystemAdapter) {
            return new FlareFilesystemAdapter($filesystem, $trace, $report, $maxReports);
        }

        if ($filesystem instanceof Cloud) {
            return new FlareCloudFilesystem($filesystem, $trace, $report, $maxReports);
        }

        return new FlareFilesystem($filesystem, $trace, $report, $maxReports);
    }

    public function configureFlare(
        array $config,
    ): void {
        $this->flareConfig = $config;
    }

    protected function shouldWrapFilesystem(?array $config): bool
    {
        return $this->flareConfig['track_all_disks'] || ($config !== null && array_key_exists('flare', $config));
    }
}

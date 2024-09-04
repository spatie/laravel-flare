<?php

namespace Spatie\LaravelFlare\Filesystem;

use Illuminate\Contracts\Filesystem\Cloud;
use Illuminate\Contracts\Filesystem\Filesystem;
use Spatie\LaravelFlare\Filesystem\Concerns\WrapsCloudFilesystem;

class FlareCloudFilesystem implements Cloud
{
    use WrapsCloudFilesystem;

    public function __construct(
        protected Cloud $filesystem,
        protected bool $trace,
        protected bool $report,
        protected int $maxReports
    ) {
    }
}

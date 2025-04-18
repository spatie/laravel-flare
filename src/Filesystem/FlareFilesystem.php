<?php

namespace Spatie\LaravelFlare\Filesystem;

use Illuminate\Contracts\Filesystem\Filesystem;
use Spatie\LaravelFlare\Filesystem\Concerns\WrapsFileSystem;

class FlareFilesystem implements Filesystem
{
    use WrapsFileSystem;

    public function __construct(
        protected Filesystem $filesystem,
        protected bool $trace,
        protected bool $report,
        protected int $maxReports
    ) {
    }
}

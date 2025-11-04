<?php

namespace Spatie\LaravelFlare\Support;

use Composer\InstalledVersions;
use Spatie\FlareClient\Support\Telemetry as BaseTelemetry;

class Telemetry extends BaseTelemetry
{
    public const NAME = 'spatie/laravel-flare';
}

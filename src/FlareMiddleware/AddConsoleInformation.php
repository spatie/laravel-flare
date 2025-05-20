<?php

namespace Spatie\LaravelFlare\FlareMiddleware;

use Spatie\FlareClient\FlareMiddleware\AddConsoleInformation as BaseAddConsoleInformation;

class AddConsoleInformation extends BaseAddConsoleInformation
{
    protected function isRunningInConsole(): bool
    {
        return app()->runningInConsole();
    }
}

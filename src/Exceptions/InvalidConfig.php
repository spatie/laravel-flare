<?php

namespace Spatie\LaravelFlare\Exceptions;

use Exception;
use Monolog\Level;

class InvalidConfig extends Exception
{
    public static function invalidLogLevel(string $logLevel): self
    {
        $validLogLevels = implode(', ', array_map(
            fn (string $level) => strtolower($level),
            array_keys(Level::VALUES)
        ));

        return new self("Invalid log level `{$logLevel}` specified. Valid log levels are {$validLogLevels}.");
    }
}

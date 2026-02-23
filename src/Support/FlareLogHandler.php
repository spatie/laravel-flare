<?php

namespace Spatie\LaravelFlare\Support;

use Monolog\Level;
use Monolog\LogRecord;
use Spatie\FlareClient\Logger;
use Spatie\FlareClient\Support\FlareLogHandler as BaseFlareLogHandler;

class FlareLogHandler extends BaseFlareLogHandler
{
    public function __construct(
        protected Logger $logger,
        Level $level = self::DEFAULT_MONOLOG_LEVEL,
        bool $bubble = true
    ) {
        parent::__construct($this->logger, $level, $bubble);
    }

    protected function write(LogRecord $record): void
    {
        if (array_key_exists('exception', $record->context)) {
            return;
        }

        parent::write($record);
    }
}

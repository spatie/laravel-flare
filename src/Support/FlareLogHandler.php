<?php

namespace Spatie\LaravelFlare\Support;

use Exception;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Level;
use Monolog\LogRecord;
use Spatie\FlareClient\Flare;
use Spatie\FlareClient\ReportFactory;
use Spatie\LaravelFlare\Exceptions\InvalidConfig;

class FlareLogHandler extends AbstractProcessingHandler
{
    public static function logLevelFromName(?string $logLevelString): Level
    {
        try {
            $logLevel = Level::fromName($logLevelString);
        } catch (Exception) {
            throw InvalidConfig::invalidLogLevel($logLevelString);
        }

        return $logLevel;
    }

    public function __construct(
        protected Flare $flare,
        protected Level $minimumReportLogLevel,
        protected bool $traceOrigins = false,
        Level $handlingLevel = Level::Debug,
        bool $bubble = true
    ) {
        parent::__construct($handlingLevel, $bubble);
    }

    protected function write(LogRecord $record): void
    {
        if ($record->level->isLowerThan($this->minimumReportLogLevel)) {
            return;
        }

        if (array_key_exists('exception', $record['context'])) {
            return;
        }

        $this->flare->reportMessage(
            $record->message,
            $record->level->name,
            function (ReportFactory $flareReport) use ($record) {
                $flareReport->context($record['context']);

                if ($this->traceOrigins === false) {
                    return;
                }

                $this->flare->backTracer->setFrameAsAttributes(
                    $this->flare->backTracer->firstApplicationFrame(20),
                    $flareReport
                );
            }
        );
    }
}

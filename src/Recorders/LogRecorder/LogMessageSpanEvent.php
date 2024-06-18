<?php

namespace Spatie\LaravelFlare\Recorders\LogRecorder;

use Illuminate\Log\Events\MessageLogged;
use Spatie\FlareClient\Recorders\LogRecorder\LogMessageSpanEvent as BaseLogMessageSpanEvent;
use Spatie\LaravelFlare\Performance\Enums\SpanEventType;

class LogMessageSpanEvent extends BaseLogMessageSpanEvent
{
    public static function fromMessageLoggedEvent(MessageLogged $event): self
    {
        return new self(
            $event->message,
            $event->level,
            $event->context,
            spanEventType: SpanEventType::Log
        );
    }
}

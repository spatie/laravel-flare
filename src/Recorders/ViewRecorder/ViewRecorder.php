<?php

namespace Spatie\LaravelFlare\Recorders\ViewRecorder;

use Spatie\FlareClient\Concerns\Recorders\RecordsPendingSpans;
use Spatie\FlareClient\Contracts\Recorders\SpansRecorder;
use Spatie\FlareClient\Enums\RecorderType;

class ViewRecorder implements SpansRecorder
{
    use RecordsPendingSpans;

    public static function type(): string|RecorderType
    {
        return RecorderType::View;
    }


}

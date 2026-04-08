<?php

namespace Spatie\LaravelFlare\Support;

use Spatie\Backtrace\Frame;
use Spatie\FlareClient\Support\StacktraceMapper;
use Spatie\LaravelFlare\Views\ViewFrameMapper;
use Throwable;

class LaravelStacktraceMapper extends StacktraceMapper
{
    public function __construct(
        protected ViewFrameMapper $viewFrameMapper,
    ) {
    }

    public function map(array $frames, ?Throwable $throwable): array
    {
        $frames = array_map(function (Frame $frame) {
            if ($originalPath = $this->viewFrameMapper->findCompiledView($frame->file)) {
                $frame->file = $originalPath;
                $frame->lineNumber = $this->viewFrameMapper->getBladeLineNumber($frame->file, $frame->lineNumber);
            }

            return $frame;
        }, $frames);

        return parent::map($frames, $throwable);
    }
}

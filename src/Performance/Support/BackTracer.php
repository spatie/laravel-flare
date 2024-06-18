<?php

namespace Spatie\LaravelFlare\Performance\Support;

use Spatie\Backtrace\Frame;
use Spatie\FlareClient\Performance\Support\BackTracer as BaseBackTracer;
use Spatie\LaravelFlare\Views\ViewFrameMapper;

class BackTracer extends BaseBackTracer
{
    public function __construct(
        protected ViewFrameMapper $viewFrameMapper
    ) {
    }

    public function frames(int $limit = null): array
    {
        return array_map(function (Frame $frame) {
            if ($originalPath = $this->viewFrameMapper->findCompiledView($frame->file)) {
                $frame->file = $originalPath;
                $frame->lineNumber = $this->viewFrameMapper->getBladeLineNumber($frame->file, $frame->lineNumber);
            }

            return $frame;
        }, parent::frames($limit));
    }
}

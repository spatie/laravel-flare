<?php

namespace Spatie\LaravelFlare\Recorders\LivewireRecorder;

use Spatie\FlareClient\Spans\Span;
use Spatie\LaravelFlare\Enums\LivewireComponentPhase;

class LivewireComponentState
{
    /**
     * @param array<int, ?Span> $callingSpans
     */
    public function __construct(
        public Span $span,
        public LivewireComponentPhase $phase,
        public ?Span $mountingSpan = null,
        public ?Span $hydratingSpan = null,
        public ?Span $renderingSpan = null,
        public ?Span $dehydratingSpan = null,
        public array $callingSpans = [],
        public ?string $stubbedId = null,
        public ?int $currentPhaseStartTime = null,
    ) {
    }

    public function getSpanForCurrentPhase(): ?Span
    {
        return match ($this->phase) {
            LivewireComponentPhase::Mounting => $this->mountingSpan,
            LivewireComponentPhase::Hydrating => $this->hydratingSpan,
            LivewireComponentPhase::Rendering => $this->renderingSpan,
            LivewireComponentPhase::Dehydrating => $this->dehydratingSpan,
            LivewireComponentPhase::Calling => end($this->callingSpans),
            LivewireComponentPhase::Destroyed => throw new \RuntimeException('No span available for destroyed phase'),
        };
    }
}

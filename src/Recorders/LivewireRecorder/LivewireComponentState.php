<?php

namespace Spatie\LaravelFlare\Recorders\LivewireRecorder;

use Spatie\FlareClient\Spans\Span;
use Spatie\LaravelFlare\Enums\LivewireComponentPhase;

/**
 * A component runs through one of two flows.
 *
 * First run, part of a regular page request:
 *     Mounting => Rendering => Dehydrating => Destroyed
 *
 * Later runs, through Livewire's update endpoint:
 *     Hydrating => Calling => Rendering => Dehydrating => Destroyed
 *
 * Mounting starts on the pre-mount event, before the component has an id, so the
 * state waits in $premountState until mount. Livewire can answer pre-mount with
 * mount.stub instead, when the parent already rendered this child, and no phase
 * follows.
 *
 * Calling is optional and repeats per method call. Rendering drops out whenever
 * Livewire sets skipRender, which is why Dehydrating accepts every earlier phase:
 * lazy placeholders (Mounting), children removed by their parent (Hydrating),
 * #[Renderless], skipRender(), redirect() and JSON methods (Calling).
 *
 * An exception ends any flow at Destroyed.
 */
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
        public bool $isSingleFileComponent = false,
    ) {
    }

    public function getSpanForCurrentPhase(): ?Span
    {
        return match ($this->phase) {
            LivewireComponentPhase::Mounting => $this->mountingSpan,
            LivewireComponentPhase::Hydrating => $this->hydratingSpan,
            LivewireComponentPhase::Rendering => $this->renderingSpan,
            LivewireComponentPhase::Dehydrating => $this->dehydratingSpan,
            LivewireComponentPhase::Calling => end($this->callingSpans) ?: null,
            LivewireComponentPhase::Destroyed => throw new \RuntimeException('No span available for destroyed phase'),
        };
    }
}

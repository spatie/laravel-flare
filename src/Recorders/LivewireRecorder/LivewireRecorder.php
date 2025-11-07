<?php

namespace Spatie\LaravelFlare\Recorders\LivewireRecorder;

use Exception;
use Illuminate\View\View;
use Livewire\Component;
use Livewire\EventBus;
use Livewire\Mechanisms\ComponentRegistry;
use Spatie\Backtrace\Arguments\ReduceArgumentPayloadAction;
use Spatie\FlareClient\Enums\RecorderType;
use Spatie\FlareClient\Recorders\SpansRecorder;
use Spatie\FlareClient\Support\BackTracer;
use Spatie\FlareClient\Tracer;
use Spatie\LaravelFlare\Enums\LivewireComponentPhase;
use Spatie\LaravelFlare\Enums\SpanType;
use Spatie\LaravelFlare\Recorders\ViewRecorder\ViewRecorder;

class LivewireRecorder extends SpansRecorder
{
    public const DEFAULT_SPLIT_BY_PHASE = true;
    public const DEFAULT_IGNORED = [];

    /** @var array<string> */
    protected array $ignoredComponents = [];

    protected bool $splitByPhase = false;

    /** @var array<string, LivewireComponentState> */
    protected array $componentStates = [];

    protected ?LivewireComponentState $premountState = null;

    public function __construct(
        Tracer $tracer,
        BackTracer $backTracer,
        array $config,
        protected EventBus $eventBus,
        protected ComponentRegistry $componentRegistry,
        protected ReduceArgumentPayloadAction $reduceArgumentPayloadAction,
        protected ?ViewRecorder $viewRecorder = null,
    ) {
        parent::__construct($tracer, $backTracer, $config);
    }

    protected function configure(array $config): void
    {
        $this->withErrors = false;
        $this->ignoredComponents = $config['ignored'] ?? self::DEFAULT_IGNORED;
        $this->splitByPhase = $config['split_by_phase'] ?? self::DEFAULT_SPLIT_BY_PHASE;
    }

    public static function type(): string|RecorderType
    {
        return 'livewire';
    }

    public function boot(): void
    {
        if ($this->withTraces === false) {
            return;
        }

        try {
            $this->eventBus->before('pre-mount', fn ($component, $props, $stubbedId) => $this->handlePreMount($component, $stubbedId));
            $this->eventBus->before('mount', fn ($component) => $this->handleMount($component));
            $this->eventBus->before('mount.stub', fn ($element, $cachedId, $props, $parent, $stubbedId) => $this->handleMountStub($stubbedId));
            $this->eventBus->before('hydrate', fn ($component) => $this->handleHydration($component));
            $this->eventBus->before('call', fn ($component, $method, $params) => $this->handleCall($component, $method, $params));
            $this->eventBus->before('render', fn ($component, $view) => $this->handleRender($component, $view));
            $this->eventBus->before('dehydrate', fn ($component) => $this->handleDehydrate($component));
            $this->eventBus->before('destroy', fn ($component) => $this->handleDestruction($component));
            $this->eventBus->before('exception', fn ($component) => $this->handleDestruction($component));
        } catch (Exception $exception) {
        }
    }

    protected function handlePreMount(
        string $component,
        ?string $stubbedId,
    ): void {
        $class = $this->componentRegistry->getClass($component);

        $class = ltrim($class, '\\');

        if (in_array($class, $this->ignoredComponents)) {
            return;
        }

        $span = $this->startSpan(
            name: "Livewire - {$component}  (pre-mount)",
            attributes: [
                'flare.span_type' => SpanType::LivewireComponent,
                'livewire.component.class' => $class,
                'livewire.component.name' => $component,
            ],
        );

        if ($span === null) {
            return;
        }

        $this->premountState = new LivewireComponentState(
            span: $span,
            phase: LivewireComponentPhase::Mounting,
            stubbedId: $stubbedId,
            currentPhaseStartTime: $this->tracer->time->getCurrentTime(),
        );

        if ($this->splitByPhase === false) {
            return;
        }

        $this->premountState->mountingSpan = $this->startSpan(
            name: "Livewire - {$component} - (pre-mounting)",
            attributes: [
                'flare.span_type' => SpanType::LivewireComponentPreMounting,
                'livewire.component.name' => $component,
            ],
        );
    }

    protected function handleMount(Component $component): void
    {
        if (array_key_exists($component->getId(), $this->componentStates)) {
            return; // Already mounted
        }

        if ($this->premountState === null) {
            return; // No premount state
        }

        $this->componentStates[$component->id()] = $componentState = $this->premountState;

        $this->premountState = null;

        $componentState->span->name = "Livewire - {$component->getName()}";
        $componentState->span->addAttribute(
            'livewire.component.name',
            $component->getName(),
        );

        if ($componentState->mountingSpan === null) {
            return;
        }

        $componentState->mountingSpan->name = "Livewire - {$component->getName()} - mounting";
        $componentState->mountingSpan->addAttributes([
            'flare.span_type' => SpanType::LivewireComponentMounting,
            'livewire.component.name' => $component->getName(),
        ]);
    }

    protected function handleMountStub(string $stubbedId): void
    {
        if ($this->premountState === null) {
            return;
        }

        if ($this->premountState->stubbedId !== $stubbedId) {
            return;
        }

        $componentState = $this->premountState;

        $this->premountState = null;

        if ($componentState->mountingSpan !== null) {
            $componentState->mountingSpan->name = "Livewire - stubbed mount";

            $this->endSpan();
        }

        $this->endSpan();

        // This view does not enter the rendered phase, so we need to end it here.
        $this->viewRecorder?->recordRendered();
    }

    protected function handleHydration(Component $component): void
    {
        if (array_key_exists($component->getId(), $this->componentStates)) {
            return; // Already hydrated
        }

        if (in_array($component::class, $this->ignoredComponents)) {
            return;
        }

        $span = $this->startSpan(
            name: "Livewire - {$component->getName()}",
            attributes: [
                'flare.span_type' => SpanType::LivewireComponent,
                'livewire.component.name' => $component->getName(),
                'livewire.component.class' => $component::class,
            ],
        );

        if ($span === null) {
            return;
        }

        $this->componentStates[$component->id()] = new LivewireComponentState(
            span: $span,
            phase: LivewireComponentPhase::Hydrating,
            currentPhaseStartTime: $this->tracer->time->getCurrentTime(),
        );

        if ($this->splitByPhase === false) {
            return;
        }

        $this->componentStates[$component->id()]->hydratingSpan = $this->startSpan(
            name: "Livewire - {$component->getName()} - hydrating",
            attributes: [
                'flare.span_type' => SpanType::LivewireComponentHydrating,
                'livewire.component.name' => $component->getName(),
            ],
        );
    }

    protected function handleCall(
        Component $component,
        string $method,
        array $params,
    ): void {
        $componentState = $this->componentStates[$component->id()] ?? null;

        if ($componentState === null) {
            return;
        }

        $this->moveState(
            $componentState,
            from: [LivewireComponentPhase::Mounting, LivewireComponentPhase::Hydrating, LivewireComponentPhase::Calling],
            to: LivewireComponentPhase::Calling
        );

        if ($this->splitByPhase === false) {
            return;
        }

        $params = array_map(fn ($param) => $this->reduceArgumentPayloadAction->reduce($param), $params);

        $this->componentStates[$component->id()]->callingSpans[] = $this->startSpan(
            name: "Livewire - {$component->getName()} - call",
            attributes: [
                'flare.span_type' => SpanType::LivewireComponentCall,
                'livewire.component.name' => $component->getName(),
                'livewire.component.phase' => LivewireComponentPhase::Calling,
                'livewire.component.call.method' => $method,
                'livewire.component.call.params' => $params,
            ],
        );
    }

    protected function handleRender(
        Component $component,
        View $view,
    ): void {
        $componentState = $this->componentStates[$component->id()] ?? null;

        if ($componentState === null) {
            return;
        }

        $this->moveState(
            $componentState,
            from: [LivewireComponentPhase::Mounting, LivewireComponentPhase::Hydrating, LivewireComponentPhase::Calling],
            to: LivewireComponentPhase::Rendering,
        );

        $componentState->span->addAttributes([
            'view.name' => $view->getName(),
        ]);

        if ($this->splitByPhase === false) {
            return;
        }

        $this->componentStates[$component->id()]->renderingSpan = $this->startSpan(
            name: "Livewire - {$component->getName()} - rendering",
            attributes: [
                'flare.span_type' => SpanType::LivewireComponentRendering,
                'livewire.component.name' => $component->getName(),
                'livewire.component.phase' => LivewireComponentPhase::Mounting,
            ],
        );
    }

    protected function handleDehydrate(
        Component $component,
    ): void {
        $componentState = $this->componentStates[$component->id()] ?? null;

        if ($componentState === null) {
            return;
        }

        $this->moveState(
            $componentState,
            from: [LivewireComponentPhase::Rendering],
            to: LivewireComponentPhase::Dehydrating,
        );

        if ($this->splitByPhase === false) {
            return;
        }

        $this->componentStates[$component->id()]->dehydratingSpan = $this->startSpan(
            name: "Livewire - {$component->getName()} - dehydrating",
            attributes: [
                'flare.span_type' => SpanType::LivewireComponentDehydrating,
                'livewire.component.name' => $component->getName(),
            ],
        );
    }

    protected function handleDestruction(
        Component $component,
    ): void {
        $componentState = $this->componentStates[$component->id()] ?? null;

        if ($componentState === null) {
            return;
        }

        $this->moveState(
            $componentState,
            from: [
                LivewireComponentPhase::Mounting,
                LivewireComponentPhase::Hydrating,
                LivewireComponentPhase::Calling,
                LivewireComponentPhase::Rendering,
                LivewireComponentPhase::Dehydrating,
            ],
            to: LivewireComponentPhase::Destroyed,
        );

        $this->endSpan();

        unset($this->componentStates[$component->id()]);
    }

    protected function moveState(
        LivewireComponentState $state,
        array $from,
        LivewireComponentPhase $to,
    ): void {
        if (! in_array($state->phase, $from)) {
            return;
        }

        $endTime = null;

        if ($this->splitByPhase
            && ($phaseSpan = $state->getSpanForCurrentPhase())
            && $this->tracer->currentSpanId() === $phaseSpan->spanId
            && $phaseSpan->end === null
        ) {
            $endedSpan = $this->endSpan();

            $endTime = $endedSpan?->end;
        }

        $endTime ??= $this->tracer->time->getCurrentTime();

        if ($state->currentPhaseStartTime !== null) {
            $duration = $endTime - $state->currentPhaseStartTime;

            $state->span->addAttribute(
                "livewire.component.phase.{$state->phase->value}",
                $duration,
            );
        }

        $state->currentPhaseStartTime = $endTime;
        $state->phase = $to;
    }
}

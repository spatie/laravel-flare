<?php

namespace Spatie\LaravelFlare\Recorders\ContextRecorder;

use Illuminate\Log\Context\Repository;
use Illuminate\Support\Facades\Context;
use Spatie\FlareClient\Recorders\ContextRecorder\ContextRecorder as BaseContextRecorder;

class ContextRecorder extends BaseContextRecorder
{
    protected bool $includeLaravelContext;

    const DEFAULT_INCLUDE_LARAVEL_CONTEXT = true;

    protected ?Repository $repository = null;

    public function __construct(
        protected array $config = [
            'include_laravel_context' => self::DEFAULT_INCLUDE_LARAVEL_CONTEXT,
        ],
    ) {
        if (class_exists(Repository::class)) {
            $this->repository = app(Repository::class);
        }

        $this->configure($this->config);
    }

    protected function configure(array $config): void
    {
        $this->includeLaravelContext = $config['include_laravel_context'] ?? self::DEFAULT_INCLUDE_LARAVEL_CONTEXT;
    }

    public function toArray(): array
    {
        $contextGroups = parent::toArray();

        if ($this->includeLaravelContext && ($laravelContext = $this->fetchLaravelContext())) {
            $contextGroups['context.laravel'] = $laravelContext;
        }

        return $contextGroups;
    }

    /** @return array<array-key, mixed>|null */
    protected function fetchLaravelContext(): array|null
    {
        $allContext = $this->repository->all() ?? [];

        if (count($allContext)) {
            return $allContext;
        }

        return null;
    }
}

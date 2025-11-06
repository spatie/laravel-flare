<?php

namespace Spatie\LaravelFlare\Recorders\ContextRecorder;

use Illuminate\Log\Context\Repository;
use Illuminate\Support\Facades\Context;
use Spatie\FlareClient\Recorders\ContextRecorder\ContextRecorder as BaseContextRecorder;

class ContextRecorder extends BaseContextRecorder
{
    public function __construct(
        protected bool $includeLaravelContext,
    ) {

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
        if (! class_exists(Repository::class)) {
            return null;
        }

        $allContext = Context::all();

        if (count($allContext)) {
            return $allContext;
        }

        return null;
    }
}

<?php

namespace Spatie\LaravelFlare\Recorders\QueryRecorder;

use Spatie\FlareClient\Performance\Concerns\HasOriginAttributes;
use Spatie\FlareClient\Recorders\QueryRecorder\QuerySpan as BaseQuerySpan;
use Spatie\LaravelFlare\Performance\Enums\SpanType;

class QuerySpan extends BaseQuerySpan
{
    use HasOriginAttributes;

    public function __construct(
        string $traceId,
        string $parentSpanId,
        string $sql,
        int $duration,
        ?array $bindings = null,
        ?string $databaseName = null,
        ?string $driverName = null,
        protected ?string $connectionName = null,
    ) {
        parent::__construct(
            traceId: $traceId,
            parentSpanId: $parentSpanId,
            sql: $sql,
            duration: $duration,
            bindings: $bindings,
            databaseName: $databaseName,
            driverName: $driverName,
            spanType: SpanType::Query,
        );
    }

    protected function collectAttributes(): array
    {
        return [
            ...parent::collectAttributes(),
            'laravel.db.connection' => $this->connectionName,
        ];
    }

    public function toOriginalFlareFormat(): array
    {
        return [
            parent::toOriginalFlareFormat(),
            'connection_name' => $this->connectionName,
        ];
    }
}

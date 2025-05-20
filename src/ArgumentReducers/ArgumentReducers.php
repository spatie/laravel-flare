<?php

namespace Spatie\LaravelFlare\ArgumentReducers;

use Spatie\Backtrace\Arguments\ArgumentReducers as BaseArgumentReducers;

class ArgumentReducers extends BaseArgumentReducers
{
    protected static function defaultReducers(array $extra = []): array
    {
        return parent::defaultReducers(array_merge(
            [
                new CollectionArgumentReducer(),
                new ModelArgumentReducer(),
                new ViewArgumentReducer(),
            ],
            $extra,
        ));
    }
}

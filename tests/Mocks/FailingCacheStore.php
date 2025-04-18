<?php

namespace Spatie\LaravelFlare\Tests\Mocks;

use Illuminate\Cache\ArrayStore;

class FailingCacheStore extends ArrayStore
{
    public function put($key, $value, $seconds)
    {
        return false;
    }

    public function forget($key)
    {
        return false;
    }
}

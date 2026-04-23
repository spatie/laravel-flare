<?php

use Illuminate\Database\QueryException;
use Spatie\LaravelFlare\Facades\Flare;

it('will add query information with a query exception', function () {
    setupFlare();

    $sql = 'select * from users where emai = "ruben@spatie.be"';

    $report = Flare::report(new QueryException(
        'default',
        '' . $sql . '',
        [],
        new Exception()
    ))->toArray();

    $attributes = $report['attributes'];

    $this->assertArrayHasKey('flare.exception.db_statement', $attributes);
    expect($attributes['flare.exception.db_statement'])->toBe($sql);
});

it('wont add query information without a query exception', function () {
    setupFlare();

    $report = Flare::report(new Exception())->toArray();

    $attributes = $report['attributes'];

    $this->assertArrayNotHasKey('flare.exception.db_statement', $attributes);
});

it('will add user context when provided on a custom exception', function () {
    setupFlare();

    $report = Flare::report(new class extends Exception {
        public function context()
        {
            return [
                'hello' => 'world',
            ];
        }
    })->toArray();

    $context = $report['attributes']['context.exception'];

    expect($context['hello'])->toBe('world');
});

it('will only add arrays as user provided context', function () {
    setupFlare();

    $report = Flare::report(new class extends Exception {
        public function context()
        {
            return (object) [
                'hello' => 'world',
            ];
        }
    })->toArray();

    expect($report['attributes'])->not()->toHaveKey('context');
});

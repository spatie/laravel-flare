<?php

use Illuminate\Database\QueryException;
use Spatie\LaravelFlare\Facades\Flare;

it('will add query information with a query exception', function () {
    $sql = 'select * from users where emai = "ruben@spatie.be"';

    $report = Flare::report(new QueryException(
        'default',
        '' . $sql . '',
        [],
        new Exception()
    ));

    $attributes = $report->toArray()['attributes'];

    $this->assertArrayHasKey('flare.exception.db_statement', $attributes);
    expect($attributes['flare.exception.db_statement'])->toBe($sql);
});

it('wont add query information without a query exception', function () {
    $report = Flare::report(new Exception());

    $attributes = $report->toArray()['attributes'];

    $this->assertArrayNotHasKey('flare.exception.db_statement', $attributes);
});

it('will add user context when provided on a custom exception', function () {
    $report = Flare::report(new class extends Exception {
        public function context()
        {
            return [
                'hello' => 'world',
            ];
        }
    });

    $context = $report->toArray()['attributes']['context.exception'];

    expect($context['hello'])->toBe('world');
});

it('will only add arrays as user provided context', function () {
    $report = Flare::report(new class extends Exception {
        public function context()
        {
            return (object) [
                'hello' => 'world',
            ];
        }
    });

    expect($report->toArray()['attributes'])->not()->toHaveKey('context');
});

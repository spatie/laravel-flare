<?php

use Illuminate\Support\Facades\View;
use Spatie\LaravelFlare\Solutions\MakeViewVariableOptionalSolution;
use Spatie\LaravelFlare\Support\Composer\ComposerClassMap;

it('does not open scheme paths', function () {
    View::addLocation(__DIR__.'/../stubs/views');

    app()->bind(
        ComposerClassMap::class,
        function () {
            return new ComposerClassMap(__DIR__.'/../../vendor/autoload.php');
        }
    );

    $solution = new MakeViewVariableOptionalSolution('notSet', 'php://filter/resource=./tests/stubs/views/blade-exception.blade.php');

    expect($solution)->toBeInstanceOf(MakeViewVariableOptionalSolution::class);
    expect($solution->getSolutionTitle())->toEqual('$notSet is undefined');
});

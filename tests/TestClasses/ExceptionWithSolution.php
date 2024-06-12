<?php

namespace Spatie\LaravelFlare\Tests\TestClasses;

use Spatie\ErrorSolutions\Contracts\BaseSolution;
use Spatie\ErrorSolutions\Contracts\ProvidesSolution;
use Spatie\ErrorSolutions\Contracts\Solution;

class ExceptionWithSolution extends \Exception implements ProvidesSolution
{
    public function getSolution(): Solution
    {
        return BaseSolution::create('This is a solution')
            ->setSolutionDescription('With a description');
    }
}

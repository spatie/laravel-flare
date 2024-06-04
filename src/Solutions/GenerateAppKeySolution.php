<?php

namespace Spatie\LaravelFlare\Solutions;

use Illuminate\Support\Facades\Artisan;
use Spatie\Ignition\Contracts\RunnableSolution;
use Spatie\Ignition\Contracts\Solution;

class GenerateAppKeySolution implements Solution
{
    public function getSolutionTitle(): string
    {
        return 'Your app key is missing';
    }

    public function getDocumentationLinks(): array
    {
        return [
            'Laravel installation' => 'https://laravel.com/docs/master/installation#configuration',
        ];
    }

    public function getSolutionDescription(): string
    {
        return '';
    }
}

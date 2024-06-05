<?php

namespace Spatie\LaravelFlare\Solutions;

use Spatie\Ignition\Contracts\Solution;

class UseDefaultValetDbCredentialsSolution implements Solution
{
    public function getSolutionTitle(): string
    {
        return 'Could not connect to database';
    }

    public function getDocumentationLinks(): array
    {
        return [
            'Valet documentation' => 'https://laravel.com/docs/master/valet',
        ];
    }

    public function getSolutionDescription(): string
    {
        return 'You seem to be using Valet, but the .env file does not contain the right default database credentials.';
    }
}

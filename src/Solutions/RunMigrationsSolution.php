<?php

namespace Spatie\LaravelFlare\Solutions;

use Spatie\Ignition\Contracts\Solution;

class RunMigrationsSolution implements Solution
{
    protected string $customTitle;

    public function __construct(string $customTitle = '')
    {
        $this->customTitle = $customTitle;
    }

    public function getSolutionTitle(): string
    {
        return $this->customTitle;
    }

    public function getSolutionDescription(): string
    {
        return 'You might have forgotten to run your database migrations.';
    }

    public function getDocumentationLinks(): array
    {
        return [
            'Database: Running Migrations docs' => 'https://laravel.com/docs/master/migrations#running-migrations',
        ];
    }
}

<?php

namespace Spatie\LaravelFlare\Exceptions;

use ErrorException;
use Spatie\FlareClient\Contracts\ProvidesFlareContext;
use Spatie\FlareClient\Recorders\DumpRecorder\HtmlDumper;

class ViewException extends ErrorException
{
    /** @var array<string, mixed> */
    protected array $viewData = [];

    protected string $viewFile = '';

    /**
     * @param array<string, mixed> $data
     *
     * @return void
     */
    public function setViewData(array $data): void
    {
        $this->viewData = $data;
    }

    /** @return array<string, mixed> */
    public function getViewData(): array
    {
        return $this->viewData;
    }

    public function setViewFile(string $path): void
    {
        $this->viewFile = $path;
    }

    public function getViewFile(): string
    {
        return $this->viewFile;
    }
}

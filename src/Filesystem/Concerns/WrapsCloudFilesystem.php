<?php

namespace Spatie\LaravelFlare\Filesystem\Concerns;

trait WrapsCloudFilesystem
{
    use WrapsFileSystem;

    /**
     * Get the URL for the file at the given path.
     *
     * @param string $path
     *
     * @return string
     */
    public function url($path)
    {
        $path = $this->humanizeFilesystemEntries($path);

        return $this->wrapCall(
            __FUNCTION__,
            func_get_args(),
            $path,
            [
                'laravel.filesystem.path' => $path,
            ],
            fn ($return) => ['laravel.filesystem.url' => $return]
        );
    }
}

<?php

namespace Spatie\LaravelFlare\Filesystem\Concerns;

use League\Flysystem\UnableToProvideChecksum;

trait WrapsFilesystemAdapter
{
    use WrapsCloudFilesystem;

    /**
     * Assert that the given file or directory exists.
     *
     * @param string|array $path
     * @param string|null $content
     *
     * @return $this
     */
    public function assertExists($path, $content = null)
    {
        $path = $this->humanizeFilesystemEntries($path);

        return $this->wrapCall(
            __FUNCTION__,
            func_get_args(),
            $path,
            [
                'laravel.filesystem.path' => $path,
                'laravel.filesystem.contents.size' => $this->humanFilesize($this->getSizeOfContents($content)),
            ]
        );
    }

    /**
     * Assert that the given file or directory does not exist.
     *
     * @param string|array $path
     *
     * @return $this
     */
    public function assertMissing($path)
    {
        $path = $this->humanizeFilesystemEntries($path);

        return $this->wrapCall(
            __FUNCTION__,
            func_get_args(),
            $path,
            [
                'laravel.filesystem.path' => $path,
            ]
        );
    }

    /**
     * Assert that the given directory is empty.
     *
     * @param string $path
     *
     * @return $this
     */
    public function assertDirectoryEmpty($path)
    {
        $path = $this->humanizeFilesystemEntries($path);

        return $this->wrapCall(
            __FUNCTION__,
            func_get_args(),
            $path,
            [
                'laravel.filesystem.path' => $path,
            ]
        );
    }

    /**
     * Get the checksum for a file.
     *
     * @return string|false
     *
     * @throws UnableToProvideChecksum
     */
    public function checksum(string $path, array $options = [])
    {
        $path = $this->humanizeFilesystemEntries($path);

        return $this->wrapCall(
            __FUNCTION__,
            func_get_args(),
            $path,
            [
                'laravel.filesystem.path' => $path,
            ],
            fn ($return) => ['laravel.filesystem.checksum' => $return]
        );
    }

    /**
     * Get the mime-type of a given file.
     *
     * @param string $path
     *
     * @return string|false
     */
    public function mimeType($path)
    {
        $path = $this->humanizeFilesystemEntries($path);

        return $this->wrapCall(
            __FUNCTION__,
            func_get_args(),
            $path,
            [
                'laravel.filesystem.path' => $path,
            ],
            fn ($return) => ['laravel.filesystem.mime_type' => $return]
        );
    }

    /**
     * Get a temporary URL for the file at the given path.
     *
     * @param string $path
     * @param \DateTimeInterface $expiration
     * @param array $options
     *
     * @return string
     *
     * @throws \RuntimeException
     */
    public function temporaryUrl($path, $expiration, array $options = [])
    {
        $path = $this->humanizeFilesystemEntries($path);

        return $this->wrapCall(
            __FUNCTION__,
            func_get_args(),
            $path,
            [
                'laravel.filesystem.path' => $path,
                'laravel.filesystem.expiration' => $expiration,
            ],
            fn ($return) => ['laravel.filesystem.url' => $return]
        );
    }

    /**
     * Get a temporary upload URL for the file at the given path.
     *
     * @param string $path
     * @param \DateTimeInterface $expiration
     * @param array $options
     *
     * @return array
     *
     * @throws \RuntimeException
     */
    public function temporaryUploadUrl($path, $expiration, array $options = [])
    {
        $path = $this->humanizeFilesystemEntries($path);

        return $this->wrapCall(
            __FUNCTION__,
            func_get_args(),
            $path,
            [
                'laravel.filesystem.path' => $path,
                'laravel.filesystem.expiration' => $expiration,
            ]
        );
    }
}

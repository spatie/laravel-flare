<?php

namespace Spatie\LaravelFlare\Filesystem\Concerns;

use League\Flysystem\UnableToProvideChecksum;
use Spatie\FlareClient\Enums\FilesystemOperation;
use Spatie\FlareClient\Support\Humanizer;
use Spatie\LaravelFlare\Enums\LaravelFilesystemOperation;
use Spatie\LaravelFlare\Recorders\FilesystemRecorder\FilesystemRecorder;

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
        return $this->wrapCall(
            __FUNCTION__,
            func_get_args(),
            fn(FilesystemRecorder $recorder) => $recorder->recordOperationStart(
                operation: LaravelFilesystemOperation::AssertExists->value,
                attributes: [
                    'filesystem.path' => Humanizer::filesystemPaths($path),
                    'filesystem.contents.size' => Humanizer::contentSize($content),
                ]
            )
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
        return $this->wrapCall(
            __FUNCTION__,
            func_get_args(),
            fn(FilesystemRecorder $recorder) => $recorder->recordOperationStart(
                operation:LaravelFilesystemOperation::AssertMissing->value,
                attributes:[
                    'filesystem.path' => Humanizer::filesystemPaths($path),
                ]
            ),
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
        return $this->wrapCall(
            __FUNCTION__,
            func_get_args(),
            fn(FilesystemRecorder $recorder) => $recorder->recordOperationStart(
                operation:LaravelFilesystemOperation::AssertDirectoryEmpty->value,
                attributes:[
                    'filesystem.path' => Humanizer::filesystemPaths($path),
                ]
            ),
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
        return $this->wrapCall(
            __FUNCTION__,
            func_get_args(),
            fn(FilesystemRecorder $recorder) => $recorder->recordChecksum($path),
            fn ($return) => ['filesystem.checksum' => $return]
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
        return $this->wrapCall(
            __FUNCTION__,
            func_get_args(),
            fn(FilesystemRecorder $recorder) => $recorder->recordMimeType($path),
            fn ($return) => ['filesystem.mime_type' => $return]
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
        return $this->wrapCall(
            __FUNCTION__,
            func_get_args(),
            fn(FilesystemRecorder $recorder) => $recorder->recordTemporaryUrl(
                $path,
                $expiration,
            ),
            fn ($return) => ['filesystem.url' => $return]
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
        return $this->wrapCall(
            __FUNCTION__,
            func_get_args(),
            fn(FilesystemRecorder $recorder) => $recorder->recordTemporaryUrl(
                $path,
                $expiration,
            ),
        );
    }
}

<?php

namespace Spatie\LaravelFlare\Filesystem\Concerns;

use Closure;
use Illuminate\Http\File;
use Illuminate\Http\UploadedFile;
use Psr\Http\Message\StreamInterface;
use Spatie\FlareClient\Concerns\PrefersHumanFormats;
use Spatie\FlareClient\Enums\FilesystemOperation;
use Spatie\FlareClient\Spans\Span;
use Spatie\LaravelFlare\Enums\LaravelFilesystemOperation;
use Spatie\LaravelFlare\Facades\Flare;
use Spatie\LaravelFlare\Recorders\FilesystemRecorder\FilesystemRecorder;
use Spatie\LaravelFlare\Support\Humanizer;
use Throwable;

trait WrapsFileSystem
{
    /**
     * Get the full path to the file that exists at the given relative path.
     *
     * @param string $path
     *
     * @return string
     */
    public function path($path)
    {
        return $this->wrapCall(
            __FUNCTION__,
            func_get_args(),
            fn(FilesystemRecorder $recorder) => $recorder->recordPath($path),
            fn(mixed $return) => [
                'filesystem.full_path' => $return,
            ],
        );
    }

    /**
     * Determine if a file exists.
     *
     * @param ?string $path
     *
     * @return bool
     */
    public function exists($path)
    {
        return $this->wrapCall(
            __FUNCTION__,
            func_get_args(),
            fn(FilesystemRecorder $recorder) => $recorder->recordExists($path ?? '/'),
            fn(mixed $return) => [
                'filesystem.exists' => $return,
            ],
        );
    }

    /**
     * Get the contents of a file.
     *
     * @param ?string $path
     *
     * @return string|null
     */
    public function get($path)
    {
        return $this->wrapCall(
            __FUNCTION__,
            func_get_args(),
            fn(FilesystemRecorder $recorder) => $recorder->recordGet($path ?? '/'),
            fn(mixed $file) => [
                'filesystem.contents.size' => Humanizer::contentSize($file),
            ],
        );
    }

    /**
     * Get a resource to read the file.
     *
     * @param string $path
     *
     * @return resource|null The path resource or null on failure.
     */
    public function readStream($path)
    {
        return $this->wrapCall(
            __FUNCTION__,
            func_get_args(),
            fn(FilesystemRecorder $recorder) => $recorder->recordGet($path, [
                'filesystem.is_stream' => true,
            ]),
        );
    }

    /**
     * Write the contents of a file.
     *
     * @param string $path
     * @param StreamInterface|File|UploadedFile|string|resource $contents
     * @param mixed $options
     *
     * @return bool
     */
    public function put($path, $contents, $options = [])
    {
        return $this->wrapCall(
            __FUNCTION__,
            func_get_args(),
            fn(FilesystemRecorder $recorder) => $recorder->recordPut($path, $contents),
            fn(mixed $return) => [
                'filesystem.operation.success' => $return,
            ],
        );
    }

    /**
     * Store the uploaded file on the disk.
     *
     * @param File|UploadedFile|string $path
     * @param File|UploadedFile|string|array|null $file
     * @param mixed $options
     *
     * @return string|false
     */
    public function putFile($path, $file = null, $options = [])
    {
        return $this->wrapCall(
            __FUNCTION__,
            func_get_args(),
            fn(FilesystemRecorder $recorder) => $recorder->recordPut(
                $path,
                $file,
            ),
            fn ($return) => ['filesystem.operation.success' => $return]
        );
    }

    /**
     * Store the uploaded file on the disk with a given name.
     *
     * @param File|UploadedFile|string $path
     * @param File|UploadedFile|string|array|null $file
     * @param string|array|null $name
     * @param mixed $options
     *
     * @return string|false
     */
    public function putFileAs($path, $file, $name = null, $options = [])
    {
        return $this->wrapCall(
            __FUNCTION__,
            func_get_args(),
            fn(FilesystemRecorder $recorder) => $recorder->recordPut(
                $path,
                $file,
                [
                    'filesystem.as_path' => Humanizer::filesystemPaths($name),
                ]
            ),
            fn ($return) => ['filesystem.operation.success' => $return]
        );
    }

    /**
     * Write a new file using a stream.
     *
     * @param string $path
     * @param resource $resource
     * @param array $options
     *
     * @return bool
     */
    public function writeStream($path, $resource, array $options = [])
    {
        return $this->wrapCall(
            __FUNCTION__,
            func_get_args(),
            fn(FilesystemRecorder $recorder) => $recorder->recordPut(
                $path,
                $resource,
                [
                    'filesystem.is_stream' => true,
                ]
            ),
            fn ($return) => ['filesystem.operation.success' => $return]
        );
    }

    /**
     * Get the visibility for the given path.
     *
     * @param string $path
     *
     * @return string
     */
    public function getVisibility($path)
    {
        return $this->wrapCall(
            __FUNCTION__,
            func_get_args(),
            fn(FilesystemRecorder $recorder) => $recorder->recordGetVisibility($path,),
            fn ($return) => ['filesystem.visibility' => $return]
        );
    }

    /**
     * Set the visibility for the given path.
     *
     * @param string $path
     * @param string $visibility
     *
     * @return bool
     */
    public function setVisibility($path, $visibility)
    {
        return $this->wrapCall(
            __FUNCTION__,
            func_get_args(),
            fn(FilesystemRecorder $recorder) => $recorder->recordSetVisibility($path, $visibility),
            fn ($return) => ['filesystem.operation.success' => $return]
        );
    }

    /**
     * Prepend to a file.
     *
     * @param string $path
     * @param string $data
     * @param string $separator
     *
     * @return bool
     */
    public function prepend($path, $data, $separator = PHP_EOL)
    {
        return $this->wrapCall(
            __FUNCTION__,
            func_get_args(),
            fn(FilesystemRecorder $recorder) => $recorder->recordPrepend($path, $data),
            fn ($return) => ['filesystem.operation.success' => $return]
        );
    }

    /**
     * Append to a file.
     *
     * @param string $path
     * @param string $data
     * @param string $separator
     *
     * @return bool
     */
    public function append($path, $data, $separator = PHP_EOL)
    {
        return $this->wrapCall(
            __FUNCTION__,
            func_get_args(),
            fn(FilesystemRecorder $recorder) => $recorder->recordAppend($path, $data),
            fn ($return) => ['filesystem.operation.success' => $return]
        );
    }

    /**
     * Delete the file at a given path.
     *
     * @param string|array $paths
     *
     * @return bool
     */
    public function delete($paths)
    {
        return $this->wrapCall(
            __FUNCTION__,
            func_get_args(),
            fn(FilesystemRecorder $recorder) => $recorder->recordDelete($paths),
            fn ($return) => ['filesystem.operation.success' => $return]
        );
    }

    /**
     * Copy a file to a new location.
     *
     * @param string $from
     * @param string $to
     *
     * @return bool
     */
    public function copy($from, $to)
    {
        return $this->wrapCall(
            __FUNCTION__,
            func_get_args(),
            fn(FilesystemRecorder $recorder) => $recorder->recordCopy($from, $to),
            fn ($return) => ['filesystem.operation.success' => $return]
        );
    }

    /**
     * Move a file to a new location.
     *
     * @param string $from
     * @param string $to
     *
     * @return bool
     */
    public function move($from, $to)
    {
        return $this->wrapCall(
            __FUNCTION__,
            func_get_args(),
            fn(FilesystemRecorder $recorder) => $recorder->recordMove($from, $to),
            fn ($return) => ['filesystem.operation.success' => $return]
        );
    }

    /**
     * Get the file size of a given file.
     *
     * @param string $path
     *
     * @return int
     */
    public function size($path)
    {
        return $this->wrapCall(
            __FUNCTION__,
            func_get_args(),
            fn(FilesystemRecorder $recorder) => $recorder->recordSize($path),
            fn ($return) => ['filesystem.contents.size' => Humanizer::contentSize($return)]
        );
    }

    /**
     * Get the file's last modification time.
     *
     * @param string $path
     *
     * @return int
     */
    public function lastModified($path)
    {
        return $this->wrapCall(
            __FUNCTION__,
            func_get_args(),
            fn(FilesystemRecorder $recorder) => $recorder->recordLastModified($path),
            fn ($return) => ['filesystem.last_modified' => Humanizer::unixTime($return)]
        );
    }

    /**
     * Get an array of all files in a directory.
     *
     * @param string|null $directory
     * @param bool $recursive
     *
     * @return array
     */
    public function files($directory = null, $recursive = false)
    {
        return $this->wrapCall(
            __FUNCTION__,
            func_get_args(),
            fn(FilesystemRecorder $recorder) => $recorder->recordFiles($directory  ?? '/', $recursive),
            fn ($return) => ['filesystem.found_paths' => Humanizer::filesystemPaths($return, 'files')]
        );
    }

    /**
     * Get all of the files from the given directory (recursive).
     *
     * @param string|null $directory
     *
     * @return array
     */
    public function allFiles($directory = null)
    {
        return $this->wrapCall(
            __FUNCTION__,
            func_get_args(),
            fn(FilesystemRecorder $recorder) => $recorder->recordFiles($directory  ?? '/', true),
            fn ($return) => ['filesystem.found_paths' => Humanizer::filesystemPaths($return, 'files')]
        );
    }

    /**
     * Get all of the directories within a given directory.
     *
     * @param string|null $directory
     * @param bool $recursive
     *
     * @return array
     */
    public function directories($directory = null, $recursive = false)
    {
        return $this->wrapCall(
            __FUNCTION__,
            func_get_args(),
            fn(FilesystemRecorder $recorder) => $recorder->recordDirectories($directory ?? '/', $recursive),
            fn ($return) => ['filesystem.found_paths' => Humanizer::filesystemPaths($return, 'directories')]
        );
    }

    /**
     * Get all (recursive) of the directories within a given directory.
     *
     * @param string|null $directory
     *
     * @return array
     */
    public function allDirectories($directory = null)
    {
        return $this->wrapCall(
            __FUNCTION__,
            func_get_args(),
            fn(FilesystemRecorder $recorder) => $recorder->recordDirectories($directory  ?? '/', true),
            fn ($return) => ['filesystem.found_paths' => Humanizer::filesystemPaths($return, 'directories')]
        );
    }

    /**
     * Create a directory.
     *
     * @param string $path
     *
     * @return bool
     */
    public function makeDirectory($path)
    {
        return $this->wrapCall(
            __FUNCTION__,
            func_get_args(),
            fn(FilesystemRecorder $recorder) => $recorder->recordMakeDirectory($path),
            fn ($return) => ['filesystem.operation.success' => $return]
        );
    }

    /**
     * Recursively delete a directory.
     *
     * @param string $directory
     *
     * @return bool
     */
    public function deleteDirectory($directory)
    {
        return $this->wrapCall(
            __FUNCTION__,
            func_get_args(),
            fn(FilesystemRecorder $recorder) => $recorder->recordDeleteDirectory($directory),
            fn ($return) => ['filesystem.operation.success' => $return]
        );
    }
    /**
     * @param Closure(FileSystemRecorder):(Span|null) $start
     * @param Closure(mixed):array<string, mixed>|null $end
     */
    protected function wrapCall(
        string $method,
        array $arguments,
        Closure $start,
        ?Closure $end = null,
    ): mixed {
        $recorder = Flare::filesystem();

        $start($recorder);

        $returned = $this->filesystem->{$method}(...$arguments);

        $recorder->recordOperationEnd(
            $end !== null ? $end($returned) : []
        );

        return $returned;
    }
}

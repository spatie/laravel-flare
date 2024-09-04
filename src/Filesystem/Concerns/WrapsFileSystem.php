<?php

namespace Spatie\LaravelFlare\Filesystem\Concerns;

use Closure;
use Illuminate\Http\File;
use Illuminate\Http\UploadedFile;
use Psr\Http\Message\StreamInterface;
use Spatie\FlareClient\Concerns\PrefersHumanFormats;
use Spatie\LaravelFlare\Enums\SpanType;
use Spatie\LaravelFlare\Facades\Flare;

trait WrapsFileSystem
{
    use PrefersHumanFormats;

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
            $path,
            [
                'laravel.filesystem.path' => $path,
            ],
            fn ($return) => ['laravel.filesystem.full_path' => $return]
        );
    }

    /**
     * Determine if a file exists.
     *
     * @param string $path
     *
     * @return bool
     */
    public function exists($path)
    {
        return $this->wrapCall(
            __FUNCTION__,
            func_get_args(),
            $path,
            [
                'laravel.filesystem.path' => $path,
            ],
            fn ($return) => ['laravel.filesystem.exists' => $return]
        );
    }

    /**
     * Get the contents of a file.
     *
     * @param string $path
     *
     * @return string|null
     */
    public function get($path)
    {
        return $this->wrapCall(
            __FUNCTION__,
            func_get_args(),
            $path,
            [
                'laravel.filesystem.path' => $path,
            ],
            fn ($return) => ['laravel.filesystem.contents.size' => $this->humanFilesize($this->getSizeOfContents($return))]
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
            $path,
            [
                'laravel.filesystem.path' => $path,
            ]
        );
    }

    /**
     * Write the contents of a file.
     *
     * @param string $path
     * @param \Psr\Http\Message\StreamInterface|\Illuminate\Http\File|\Illuminate\Http\UploadedFile|string|resource $contents
     * @param mixed $options
     *
     * @return bool
     */
    public function put($path, $contents, $options = [])
    {
        return $this->wrapCall(
            __FUNCTION__,
            func_get_args(),
            $path,
            [
                'laravel.filesystem.path' => $path,
                'laravel.filesystem.contents.size' => $this->humanFilesize($this->getSizeOfContents($contents)),
            ],
            fn ($return) => ['laravel.filesystem.success' => $return]
        );
    }

    /**
     * Store the uploaded file on the disk.
     *
     * @param \Illuminate\Http\File|\Illuminate\Http\UploadedFile|string $path
     * @param \Illuminate\Http\File|\Illuminate\Http\UploadedFile|string|array|null $file
     * @param mixed $options
     *
     * @return string|false
     */
    public function putFile($path, $file = null, $options = [])
    {
        return $this->wrapCall(
            __FUNCTION__,
            func_get_args(),
            $path,
            [
                'laravel.filesystem.path' => $path,
                'laravel.filesystem.contents.size' => $this->humanFilesize($this->getSizeOfContents($file)),
            ],
            fn ($return) => ['laravel.filesystem.success' => $return]
        );
    }

    /**
     * Store the uploaded file on the disk with a given name.
     *
     * @param \Illuminate\Http\File|\Illuminate\Http\UploadedFile|string $path
     * @param \Illuminate\Http\File|\Illuminate\Http\UploadedFile|string|array|null $file
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
            $path,
            [
                'laravel.filesystem.path' => $path,
                'laravel.filesystem.name' => $name,
                'laravel.filesystem.contents.size' => $this->humanFilesize($this->getSizeOfContents($file)),
            ],
            fn ($return) => ['laravel.filesystem.success' => $return]
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
            $path,
            [
                'laravel.filesystem.path' => $path,
                'laravel.filesystem.contents.size' => $this->getSizeOfContents($this->getSizeOfContents($resource)),
            ],
            fn ($return) => ['laravel.filesystem.success' => $return]
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
            $path,
            [
                'laravel.filesystem.path' => $path,
            ],
            fn ($return) => ['laravel.filesystem.visibility' => $return]
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
            $path,
            [
                'laravel.filesystem.path' => $path,
                'laravel.filesystem.visibility' => $visibility,
            ],
            fn ($return) => ['laravel.filesystem.success' => $return]
        );
    }

    /**
     * Prepend to a file.
     *
     * @param string $path
     * @param string $data
     *
     * @return bool
     */
    public function prepend($path, $data, $separator = PHP_EOL)
    {
        return $this->wrapCall(
            __FUNCTION__,
            func_get_args(),
            $path,
            [
                'laravel.filesystem.path' => $path,
                'laravel.filesystem.contents.size' => $this->humanFilesize($this->getSizeOfContents($data)),
            ],
            fn ($return) => ['laravel.filesystem.success' => $return]
        );
    }

    /**
     * Append to a file.
     *
     * @param string $path
     * @param string $data
     *
     * @return bool
     */
    public function append($path, $data, $separator = PHP_EOL)
    {
        return $this->wrapCall(
            __FUNCTION__,
            func_get_args(),
            $path,
            [
                'laravel.filesystem.path' => $path,
                'laravel.filesystem.contents.size' => $this->humanFilesize($this->getSizeOfContents($data)),
            ],
            fn ($return) => ['laravel.filesystem.success' => $return]
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
            $this->humanizeFilesystemEntries($paths),
            [
                'laravel.filesystem.paths' => $this->humanizeFilesystemEntries($paths),
            ],
            fn ($return) => ['laravel.filesystem.success' => $return]
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
            "from \"{$from}\" to \"{$to}\"",
            [
                'laravel.filesystem.from_path' => $from,
                'laravel.filesystem.to_path' => $to,
            ],
            fn ($return) => ['laravel.filesystem.success' => $return]
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
            "from \"{$from}\" to \"{$to}\"",
            [
                'laravel.filesystem.from_path' => $from,
                'laravel.filesystem.to_path' => $to,
            ],
            fn ($return) => ['laravel.filesystem.success' => $return]
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
            $path,
            [
                'laravel.filesystem.path' => $path,
            ],
            fn ($return) => ['laravel.filesystem.size' => $return]
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
            $path,
            [
                'laravel.filesystem.path' => $path,
            ],
            fn ($return) => ['laravel.filesystem.last_modified' => $this->humanizeUnixTime($return)]
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
            $directory,
            [
                'laravel.filesystem.directory' => $directory,
                'laravel.filesystem.recursive' => $recursive,
            ],
            fn ($return) => ['laravel.filesystem.files' => $this->humanizeFilesystemEntries($return, 'files')]
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
            $directory,
            [
                'laravel.filesystem.directory' => $directory,
            ],
            fn ($return) => ['laravel.filesystem.files' => $this->humanizeFilesystemEntries($return, 'files')]
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
            $directory,
            [
                'laravel.filesystem.directory' => $directory,
                'laravel.filesystem.recursive' => $recursive,
            ],
            fn ($return) => ['laravel.filesystem.directories' => $this->humanizeFilesystemEntries($return, 'directories')]
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
            $directory,
            [
                'laravel.filesystem.directory' => $directory,
            ],
            fn ($return) => ['laravel.filesystem.directories' => $this->humanizeFilesystemEntries($return, 'directories')]
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
            $path,
            [
                'laravel.filesystem.path' => $path,
            ],
            fn ($return) => ['laravel.filesystem.success' => $return]
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
            $directory,
            [
                'laravel.filesystem.directory' => $directory,
            ],
            fn ($return) => ['laravel.filesystem.success' => $return]
        );
    }

    /**
     * @param array<string, mixed> $attributes
     * @param Closure(mixed):array<string, mixed>|null $afterAttributes
     */
    protected function wrapCall(
        string $method,
        array $arguments,
        string $description,
        array $attributes = [],
        ?Closure $afterAttributes = null
    ) {
        $attributes = array_merge($attributes, [
            'flare.span.type' => SpanType::Filesystem,
            'laravel.filesystem.operation' => $method,
        ]);

        Flare::filesystem()->recordOperationStart("{$method} : {$description}", $attributes);

        $returned = $this->filesystem->{$method}(...$arguments);

        $attributes = $afterAttributes !== null ? $afterAttributes($returned) : null;

        Flare::filesystem()->recordOperationEnd($attributes);

        return $returned;
    }

    /**
     * @param \Psr\Http\Message\StreamInterface|\Illuminate\Http\File|\Illuminate\Http\UploadedFile|string|resource $contents
     */
    protected function getSizeOfContents(
        mixed $contents
    ): int|string {
        if ($contents instanceof StreamInterface || $contents instanceof UploadedFile || $contents instanceof File) {
            return $contents->getSize();
        }

        if (is_string($contents)) {
            return strlen($contents);
        }

        if (is_resource($contents)) {
            return fstat($contents)['size'];
        }

        if (is_null($contents)) {
            return 0;
        }

        return '?';
    }

    protected function humanizeFilesystemEntries(array|string $paths, string $type = 'paths')
    {
        if (is_string($paths)) {
            return $paths;
        }

        $count = count($paths);

        if ($count === 1) {
            return $paths[0];
        }

        if ($count <= 3) {
            return implode(', ', $paths);
        }

        $firstThreePaths = array_slice($paths, 0, 3);
        $remainingCount = $count - 3;

        return implode(', ', $firstThreePaths)." and +{$remainingCount} {$type}";
    }
}

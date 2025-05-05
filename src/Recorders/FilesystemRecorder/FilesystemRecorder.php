<?php

namespace Spatie\LaravelFlare\Recorders\FilesystemRecorder;

use DateTimeInterface;
use Illuminate\Container\Container;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\File;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Psr\Http\Message\StreamInterface;
use Spatie\FlareClient\Recorders\FilesystemRecorder\FilesystemRecorder as BaseFilesystemRecorder;
use Spatie\FlareClient\Spans\Span;
use Spatie\FlareClient\Support\BackTracer;
use Spatie\FlareClient\Tracer;
use Spatie\LaravelFlare\Enums\LaravelFilesystemOperation;
use Spatie\LaravelFlare\Filesystem\FlareFilesystemManager;
use Spatie\LaravelFlare\Support\Humanizer;

class FilesystemRecorder extends BaseFilesystemRecorder
{
    protected bool $trackAllDisks = false;

    protected const FLARE_PASS_THROUGH = '_flare_pass_through_configured';

    public const DEFAULT_TRACK_ALL_DISKS = false;

    public function __construct(
        protected Tracer $tracer,
        protected BackTracer $backTracer,
        protected Container $container,
        array $config,
    ) {
        parent::__construct($tracer, $backTracer, $config);

        $this->trackAllDisks = $config['track_all_disks'] ?? static::DEFAULT_TRACK_ALL_DISKS;
    }

    public static function registered(Application $container, array $config): void
    {
        $shouldWrapDisks = ($config['track_all_disks'] ?? static::DEFAULT_TRACK_ALL_DISKS)
            || Arr::first($config, fn ($disk) => is_array($disk) && array_key_exists('flare', $disk)) !== null;

        if ($shouldWrapDisks) {
            $container->singleton('filesystem', function () use ($config) {
                $manager = new FlareFilesystemManager(app());

                $manager->configureFlare($config);

                return $manager;
            });
        }
    }

    public function start(): void
    {
        // Registration of the FlareFilesystemManager is done in the registered method
    }

    public function recordGetVisibility(string|array $path, array $attributes = []): ?Span
    {
        return $this->recordOperationStart(
            operation: LaravelFilesystemOperation::GetVisibility->value,
            attributes: [
                'filesystem.path' => static::humanizerClass()::filesystemPaths($path),
                ...$attributes,
            ]
        );
    }

    public function recordSetVisibility(string|array $path, string $visibility, array $attributes = []): ?Span
    {
        return $this->recordOperationStart(
            operation: LaravelFilesystemOperation::SetVisibility->value,
            attributes: [
                'filesystem.path' => static::humanizerClass()::filesystemPaths($path),
                'filesystem.visibility' => $visibility,
                ...$attributes,
            ]
        );
    }

    public function recordLastModified(string $path, array $attributes = []): ?Span
    {
        return $this->recordOperationStart(
            operation: LaravelFilesystemOperation::LastModified->value,
            attributes: [
                'filesystem.path' => static::humanizerClass()::filesystemPaths($path),
                ...$attributes,
            ]
        );
    }

    public function recordChecksum(string $path, array $attributes = []): ?Span
    {
        return $this->recordOperationStart(
            operation: LaravelFilesystemOperation::Checksum->value,
            attributes: [
                'filesystem.path' => static::humanizerClass()::filesystemPaths($path),
                ...$attributes,
            ]
        );
    }

    public function recordMimeType(string $path, array $attributes = []): ?Span
    {
        return $this->recordOperationStart(
            operation: LaravelFilesystemOperation::MimeType->value,
            attributes: [
                'filesystem.path' => static::humanizerClass()::filesystemPaths($path),
                ...$attributes,
            ]
        );
    }

    public function recordTemporaryUrl(string $path, DateTimeInterface $expiration, array $attributes = []): ?Span
    {
        return $this->recordOperationStart(
            operation: LaravelFilesystemOperation::TemporaryUrl->value,
            attributes: [
                'filesystem.path' => static::humanizerClass()::filesystemPaths($path),
                'filesystem.expiration' => static::humanizerClass()::unixTime($expiration->getTimestamp()),
                ...$attributes,
            ]
        );
    }

    public function recordTemporaryUploadUrl(string $path, DateTimeInterface $expiration, array $attributes = []): ?Span
    {
        return $this->recordOperationStart(
            operation: LaravelFilesystemOperation::TemporaryUploadUrl->value,
            attributes: [
                'filesystem.path' => static::humanizerClass()::filesystemPaths($path),
                'filesystem.expiration' => static::humanizerClass()::unixTime($expiration->getTimestamp()),
                ...$attributes,
            ]
        );
    }

    /** @return class-string<Humanizer> */
    protected function humanizerClass(): string
    {
        return Humanizer::class;
    }
}

<?php

namespace Spatie\LaravelFlare\Recorders\NotificationRecorder;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Events\Dispatcher;
use Illuminate\Notifications\Events\NotificationFailed;
use Illuminate\Notifications\Events\NotificationSending;
use Illuminate\Notifications\Events\NotificationSent;
use Spatie\FlareClient\Concerns\Recorders\RecordsPendingSpans;
use Spatie\FlareClient\Contracts\Recorders\Recorder;
use Spatie\FlareClient\Enums\RecorderType;
use Spatie\FlareClient\Spans\Span;
use Spatie\FlareClient\Support\BackTracer;
use Spatie\FlareClient\Tracer;

class NotificationRecorder implements Recorder
{
    use RecordsPendingSpans;

    public function __construct(
        protected Tracer $tracer,
        protected Dispatcher $dispatcher,
        protected BackTracer $backTracer,
        array $config
    ) {
        $this->configure($config);
    }

    public static function type(): string|RecorderType
    {
        return RecorderType::Notification;
    }

    public function start(): void
    {
        $this->dispatcher->listen(NotificationSending::class, [$this, 'recordNotificationSending']);
        $this->dispatcher->listen(NotificationSent::class, [$this, 'recordNotificationSent']);
        $this->dispatcher->listen(NotificationFailed::class, [$this, 'recordNotificationSent']);
    }

    public function recordNotificationSending(NotificationSending $event): void
    {
        $this->startSpan(function () use ($event) {
            return Span::build(
                $this->tracer->currentTraceId(),
                $this->tracer->currentSpanId(),
                name: "Notification - {$event->notification->id}",
                attributes: [
                    'notification.id' => $event->notification->id,
                    'notification.channel' => $event->channel,
                    'notification.type' => get_class($event->notification),
                    'notification.to' => $this->resolveNotifiable($event->notifiable),
                ]
            );
        });
    }

    protected function resolveNotifiable($notifiable): ?array
    {
        if ($notifiable instanceof Authenticatable) {
            // TODO: somehow we should allow users to customize this (would be usefull throughout Flare)
            return ['email' => $notifiable->email ?? '', 'name' => $notifiable->name ?? ''];
        }

        return null;
    }
}

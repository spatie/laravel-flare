<?php

namespace Spatie\LaravelFlare\Recorders\NotificationRecorder;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Notifications\Events\NotificationFailed;
use Illuminate\Notifications\Events\NotificationSending;
use Illuminate\Notifications\Events\NotificationSent;
use Spatie\FlareClient\AttributesProviders\UserAttributesProvider;
use Spatie\FlareClient\Concerns\Recorders\RecordsSpans;
use Spatie\FlareClient\Contracts\Recorders\Recorder;
use Spatie\FlareClient\Enums\RecorderType;
use Spatie\FlareClient\Recorders\SpansRecorder;
use Spatie\FlareClient\Spans\Span;
use Spatie\FlareClient\Support\BackTracer;
use Spatie\FlareClient\Tracer;

class NotificationRecorder extends SpansRecorder{

    public function __construct(
        Tracer $tracer,
        protected Dispatcher $dispatcher,
        BackTracer $backTracer,
        protected UserAttributesProvider $userAttributesProvider,
        array $config
    ) {
        parent::__construct($tracer, $backTracer, $config);
    }

    public static function type(): string|RecorderType
    {
        return RecorderType::Notification;
    }

    public function boot(): void
    {
        $this->dispatcher->listen(NotificationSending::class, [$this, 'recordNotificationSending']);
        $this->dispatcher->listen(NotificationSent::class, [$this, 'recordNotificationSent']);
        $this->dispatcher->listen(NotificationFailed::class, [$this, 'recordNotificationFailed']);
    }

    public function recordNotificationSending(NotificationSending $event): ?Span
    {
        return $this->startSpan(
            name: "Notification - {$event->notification->id}",
            attributes: fn () => [
                'flare.span_type' => 'notification.sending',
                'notification.id' => $event->notification->id,
                'notification.channel' => $event->channel,
                'notification.type' => get_class($event->notification),
                'notification.to' => $this->resolveNotifiable($event->notifiable),
            ],
        );
    }

    public function recordNotificationSent(
        NotificationSent $event,
    ): ?Span {
        return $this->endSpan();
    }

    public function recordNotificationFailed(
        NotificationFailed $event,
    ): ?Span {
        return $this->endSpan();
    }

    protected function resolveNotifiable(mixed $notifiable): string|null
    {
        if ($notifiable instanceof Authenticatable) {
            return $this->userAttributesProvider->email($notifiable) ?? $this->userAttributesProvider->fullName($notifiable);
        }

        return null;
    }
}

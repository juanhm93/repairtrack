<?php

namespace App\Listeners;

use App\Enums\TicketNotificationStatus;
use App\Models\TicketNotification;
use App\Notifications\TicketStatusNotification;
use Illuminate\Notifications\Events\NotificationFailed;
use Throwable;

class MarkTicketNotificationFailed
{
    public function handle(NotificationFailed $event): void
    {
        if (! $event->notification instanceof TicketStatusNotification) {
            return;
        }

        TicketNotification::query()
            ->whereKey($event->notification->notificationLogId)
            ->where('status', TicketNotificationStatus::Queued)
            ->update([
                'status' => TicketNotificationStatus::Failed,
                'error_message' => $this->errorMessage($event->data),
            ]);
    }

    private function errorMessage(mixed $data): string
    {
        if ($data instanceof Throwable) {
            return $data->getMessage();
        }

        if (! is_array($data)) {
            return 'Notification failed.';
        }

        $exception = $data['exception'] ?? $data['message'] ?? null;

        if ($exception instanceof Throwable) {
            return $exception->getMessage();
        }

        if (is_string($exception) && $exception !== '') {
            return $exception;
        }

        return 'Notification failed.';
    }
}

<?php

namespace App\Listeners;

use App\Enums\TicketNotificationStatus;
use App\Models\TicketNotification;
use App\Notifications\TicketStatusNotification;
use Illuminate\Notifications\Events\NotificationSent;

class MarkTicketNotificationSent
{
    public function handle(NotificationSent $event): void
    {
        if (! $event->notification instanceof TicketStatusNotification) {
            return;
        }

        TicketNotification::query()
            ->whereKey($event->notification->notificationLogId)
            ->where('status', TicketNotificationStatus::Queued)
            ->update(['status' => TicketNotificationStatus::Sent]);
    }
}

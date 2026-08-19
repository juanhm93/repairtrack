<?php

namespace App\Services;

use App\Enums\TicketNotificationStatus;
use App\Enums\TicketNotificationType;
use App\Models\RepairTicket;
use App\Notifications\TicketStatusNotification;
use Illuminate\Support\Facades\Notification;
use Throwable;

class TicketNotificationService
{
    public function notify(RepairTicket $ticket, TicketNotificationType $type): void
    {
        $ticket->loadMissing('customer');

        $email = $ticket->customer->email;

        if ($email === '') {
            return;
        }

        $log = $ticket->notifications()->create([
            'type' => $type,
            'to_email' => $email,
            'status' => TicketNotificationStatus::Queued,
            'ticket_status' => $ticket->status,
        ]);

        try {
            Notification::route('mail', $email)->notify(
                new TicketStatusNotification($ticket, $type, $log->id),
            );
        } catch (Throwable $exception) {
            $log->update([
                'status' => TicketNotificationStatus::Failed,
                'error_message' => $exception->getMessage(),
            ]);
        }
    }
}

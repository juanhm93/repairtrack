<?php

namespace App\Notifications;

use App\Enums\TicketNotificationType;
use App\Models\RepairTicket;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TicketStatusNotification extends Notification
{
    public function __construct(
        public RepairTicket $ticket,
        public TicketNotificationType $type,
        public int $notificationLogId,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $this->ticket->loadMissing(['customer', 'history']);

        $appName = (string) config('app.name');
        $statusLabel = $this->ticket->status->label();
        $fromAddress = (string) config('mail.from.address');
        $fromName = (string) config('mail.from.name');

        $subject = $this->type === TicketNotificationType::Created
            ? "Recibimos tu equipo — {$appName}"
            : "Actualización de tu reparación — {$statusLabel}";

        return (new MailMessage)
            ->from($fromAddress, $fromName)
            ->subject($subject)
            ->markdown('emails.ticket-status', [
                'appName' => $appName,
                'customerName' => $this->ticket->customer->name,
                'equipment' => $this->equipmentLine(),
                'statusLabel' => $statusLabel,
                'note' => $this->ticket->history->last()?->note,
                'statusUrl' => route('public.tickets.show', $this->ticket->public_token),
                'branding' => null,
            ]);
    }

    private function equipmentLine(): string
    {
        $types = [
            'celular' => 'Celular',
            'tablet' => 'Tablet',
            'laptop' => 'Laptop',
            'pc_desktop' => 'PC de escritorio',
            'consola' => 'Consola',
            'otro' => 'Otro',
        ];

        $parts = array_filter([
            $types[$this->ticket->device_type] ?? $this->ticket->device_type,
            $this->ticket->brand,
            $this->ticket->model,
        ], fn (mixed $value): bool => filled($value));

        return implode(' · ', $parts);
    }
}

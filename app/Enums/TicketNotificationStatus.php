<?php

namespace App\Enums;

enum TicketNotificationStatus: string
{
    case Queued = 'queued';
    case Sent = 'sent';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Queued => 'En cola',
            self::Sent => 'Enviado',
            self::Failed => 'Fallido',
        };
    }
}

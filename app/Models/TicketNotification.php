<?php

namespace App\Models;

use App\Enums\TicketNotificationStatus;
use App\Enums\TicketNotificationType;
use App\Enums\TicketStatus;
use Database\Factories\TicketNotificationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $repair_ticket_id
 * @property TicketNotificationType $type
 * @property string $to_email
 * @property TicketNotificationStatus $status
 * @property TicketStatus $ticket_status
 * @property string|null $error_message
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read RepairTicket $repairTicket
 */
#[Fillable([
    'repair_ticket_id',
    'type',
    'to_email',
    'status',
    'ticket_status',
    'error_message',
])]
class TicketNotification extends Model
{
    /** @use HasFactory<TicketNotificationFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<RepairTicket, $this>
     */
    public function repairTicket(): BelongsTo
    {
        return $this->belongsTo(RepairTicket::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => TicketNotificationType::class,
            'status' => TicketNotificationStatus::class,
            'ticket_status' => TicketStatus::class,
        ];
    }
}

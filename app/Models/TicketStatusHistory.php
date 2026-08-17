<?php

namespace App\Models;

use App\Enums\TicketStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $repair_ticket_id
 * @property TicketStatus|null $from_status
 * @property TicketStatus $to_status
 * @property string|null $note
 * @property int|null $changed_by
 * @property Carbon|null $created_at
 */
#[Fillable(['repair_ticket_id', 'from_status', 'to_status', 'note', 'changed_by'])]
class TicketStatusHistory extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'ticket_status_history';

    /**
     * @return BelongsTo<RepairTicket, $this>
     */
    public function repairTicket(): BelongsTo
    {
        return $this->belongsTo(RepairTicket::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'from_status' => TicketStatus::class,
            'to_status' => TicketStatus::class,
            'created_at' => 'datetime',
        ];
    }
}

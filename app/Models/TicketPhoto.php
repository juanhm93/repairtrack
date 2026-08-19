<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Appends;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * @property int $id
 * @property int $repair_ticket_id
 * @property string $path
 * @property int $sort_order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read string $url
 * @property-read RepairTicket $repairTicket
 */
#[Fillable(['repair_ticket_id', 'path', 'sort_order'])]
#[Hidden(['path'])]
#[Appends(['url'])]
class TicketPhoto extends Model
{
    /**
     * @return BelongsTo<RepairTicket, $this>
     */
    public function repairTicket(): BelongsTo
    {
        return $this->belongsTo(RepairTicket::class);
    }

    /**
     * @return Attribute<string, never>
     */
    protected function url(): Attribute
    {
        return Attribute::get(
            fn (): string => Storage::disk('public')->url($this->path),
        );
    }
}

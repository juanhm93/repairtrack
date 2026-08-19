<?php

namespace App\Models;

use App\Enums\TicketStatus;
use Database\Factories\RepairTicketFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

/**
 * @property int $id
 * @property int $user_id
 * @property int $customer_id
 * @property string $public_token
 * @property string $device_type
 * @property string|null $brand
 * @property string|null $model
 * @property string|null $serial_number
 * @property string $reported_issue
 * @property string|null $estimated_cost
 * @property Carbon $received_at
 * @property Carbon|null $estimated_delivery_at
 * @property TicketStatus $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Customer $customer
 * @property-read User $user
 * @property-read Collection<int, TicketStatusHistory> $history
 * @property-read Collection<int, TicketPhoto> $photos
 * @property-read Collection<int, TicketNotification> $notifications
 * @property-read TicketNotification|null $latestNotification
 */
#[Fillable([
    'user_id',
    'customer_id',
    'public_token',
    'device_type',
    'brand',
    'model',
    'serial_number',
    'reported_issue',
    'estimated_cost',
    'received_at',
    'estimated_delivery_at',
    'status',
])]
class RepairTicket extends Model
{
    /** @use HasFactory<RepairTicketFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * @return HasMany<TicketStatusHistory, $this>
     */
    public function history(): HasMany
    {
        return $this->hasMany(TicketStatusHistory::class)
            ->orderBy('created_at')
            ->orderBy('id');
    }

    /**
     * @return HasMany<TicketPhoto, $this>
     */
    public function photos(): HasMany
    {
        return $this->hasMany(TicketPhoto::class)
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    /**
     * @return HasMany<TicketNotification, $this>
     */
    public function notifications(): HasMany
    {
        return $this->hasMany(TicketNotification::class);
    }

    /**
     * @return HasOne<TicketNotification, $this>
     */
    public function latestNotification(): HasOne
    {
        return $this->hasOne(TicketNotification::class)->latestOfMany();
    }

    /**
     * Scope route model binding to the authenticated owner.
     */
    public function resolveRouteBinding(mixed $value, $field = null): RepairTicket
    {
        $query = $this->where($field ?? $this->getRouteKeyName(), $value);

        if (Auth::check()) {
            $query->where('user_id', Auth::id());
        }

        return $query->firstOrFail();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => TicketStatus::class,
            'received_at' => 'date',
            'estimated_delivery_at' => 'date',
            'estimated_cost' => 'decimal:2',
        ];
    }
}

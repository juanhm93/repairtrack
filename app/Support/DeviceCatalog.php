<?php

namespace App\Support;

use App\Models\RepairTicket;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

final class DeviceCatalog
{
    /**
     * @return array<string, array<string, list<string>>>
     */
    public static function all(): array
    {
        $catalog = config('devices');

        if (! is_array($catalog)) {
            return [];
        }

        return $catalog;
    }

    /**
     * @return list<array{device_type: string, brand: string|null, model: string|null}>
     */
    public static function historyFor(User $user): array
    {
        $tickets = RepairTicket::query()
            ->where('user_id', $user->id)
            ->where(function (Builder $query): void {
                $query->whereNotNull('brand')->orWhereNotNull('model');
            })
            ->select(['device_type', 'brand', 'model'])
            ->distinct()
            ->orderBy('brand')
            ->orderBy('model')
            ->get();

        $history = [];

        foreach ($tickets as $ticket) {
            $history[] = [
                'device_type' => $ticket->device_type,
                'brand' => $ticket->brand,
                'model' => $ticket->model,
            ];
        }

        return $history;
    }
}

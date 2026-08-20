<?php

namespace App\Services;

use App\Enums\TicketStatus;
use App\Models\RepairTicket;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use RuntimeException;

class DashboardService
{
    /**
     * Read-only snapshot of recent tickets and current-month KPIs for a technician.
     *
     * @return array{
     *     month: array{year: int, month: int, label: string},
     *     recentTickets: Collection<int, RepairTicket>,
     *     stats: array{
     *         tickets_count: int,
     *         customers_count: int,
     *         completed_count: int,
     *         pending_count: int,
     *         by_status: list<array{value: string, label: string, count: int}>
     *     }
     * }
     */
    public function snapshotForUser(User $user): array
    {
        $now = now();

        $recentTickets = RepairTicket::query()
            ->where('user_id', $user->id)
            ->with('customer')
            ->orderByDesc('received_at')
            ->orderByDesc('id')
            ->limit(3)
            ->get();

        $monthTickets = $this->monthTicketsQuery($user, $now);

        $countsByStatus = (clone $monthTickets)
            ->toBase()
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $customersCount = (clone $monthTickets)
            ->distinct()
            ->count('customer_id');

        $byStatus = [];
        $ticketsCount = 0;
        $completedCount = 0;

        foreach (TicketStatus::cases() as $status) {
            $count = (int) $countsByStatus->get($status->value, 0);
            $ticketsCount += $count;

            if ($status === TicketStatus::Delivered) {
                $completedCount = $count;
            }

            $byStatus[] = [
                'value' => $status->value,
                'label' => $status->label(),
                'count' => $count,
            ];
        }

        return [
            'month' => [
                'year' => $now->year,
                'month' => $now->month,
                'label' => $this->monthLabel($now),
            ],
            'recentTickets' => $recentTickets,
            'stats' => [
                'tickets_count' => $ticketsCount,
                'customers_count' => $customersCount,
                'completed_count' => $completedCount,
                'pending_count' => $ticketsCount - $completedCount,
                'by_status' => $byStatus,
            ],
        ];
    }

    /**
     * @return Builder<RepairTicket>
     */
    private function monthTicketsQuery(User $user, CarbonInterface $now): Builder
    {
        return RepairTicket::query()
            ->where('user_id', $user->id)
            ->whereYear('received_at', $now->year)
            ->whereMonth('received_at', $now->month);
    }

    private function monthLabel(CarbonInterface $now): string
    {
        $name = match ($now->month) {
            1 => 'Enero',
            2 => 'Febrero',
            3 => 'Marzo',
            4 => 'Abril',
            5 => 'Mayo',
            6 => 'Junio',
            7 => 'Julio',
            8 => 'Agosto',
            9 => 'Septiembre',
            10 => 'Octubre',
            11 => 'Noviembre',
            12 => 'Diciembre',
            default => throw new RuntimeException('Invalid month.'),
        };

        return $name.' '.$now->year;
    }
}

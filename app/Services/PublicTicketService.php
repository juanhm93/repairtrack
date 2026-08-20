<?php

namespace App\Services;

use App\Models\RepairTicket;

class PublicTicketService
{
    /**
     * Resolve a ticket by public token and return props safe for the guest page.
     *
     * @return array{
     *     app: array{name: string},
     *     ticket: array{
     *         device_type: string,
     *         brand: string|null,
     *         model: string|null,
     *         status: string,
     *         status_label: string,
     *         received_at: string,
     *         estimated_delivery_at: string|null,
     *         history: list<array{to_status: string, to_status_label: string, note: string|null, created_at: string|null}>
     *     },
     *     customer: array{name: string}
     * }
     */
    public function pageProps(string $token): array
    {
        $ticket = RepairTicket::query()
            ->where('public_token', $token)
            ->with(['customer', 'history'])
            ->firstOrFail();

        $history = [];

        foreach ($ticket->history as $item) {
            $history[] = [
                'to_status' => $item->to_status->value,
                'to_status_label' => $item->to_status->label(),
                'note' => $item->note,
                'created_at' => $item->created_at?->toIso8601String(),
            ];
        }

        return [
            'app' => [
                'name' => (string) config('app.name'),
            ],
            'ticket' => [
                'device_type' => $ticket->device_type,
                'brand' => $ticket->brand,
                'model' => $ticket->model,
                'status' => $ticket->status->value,
                'status_label' => $ticket->status->label(),
                'received_at' => $ticket->received_at->toDateString(),
                'estimated_delivery_at' => $ticket->estimated_delivery_at?->toDateString(),
                'history' => $history,
            ],
            'customer' => [
                'name' => $ticket->customer->name,
            ],
        ];
    }
}

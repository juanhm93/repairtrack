<?php

namespace App\Services;

use App\Enums\TicketStatus;
use App\Models\Customer;
use App\Models\RepairTicket;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TicketService
{
    /**
     * @param  array{
     *     customer_name?: string|null,
     *     customer_email: string,
     *     customer_phone?: string|null,
     *     device_type: string,
     *     brand?: string|null,
     *     model?: string|null,
     *     serial_number?: string|null,
     *     reported_issue: string,
     *     estimated_cost?: mixed,
     *     received_at: string,
     *     estimated_delivery_at?: string|null,
     *     note?: string|null
     * }  $data
     * @param  list<UploadedFile>  $photos
     */
    public function create(User $actor, array $data, array $photos = []): RepairTicket
    {
        return DB::transaction(function () use ($actor, $data, $photos): RepairTicket {
            $customer = $this->findOrCreateCustomer($actor, $data);

            $ticket = RepairTicket::query()->create([
                'user_id' => $actor->id,
                'customer_id' => $customer->id,
                'public_token' => Str::random(32),
                'device_type' => $data['device_type'],
                'brand' => $data['brand'] ?? null,
                'model' => $data['model'] ?? null,
                'serial_number' => $data['serial_number'] ?? null,
                'reported_issue' => $data['reported_issue'],
                'estimated_cost' => $data['estimated_cost'] ?? null,
                'received_at' => $data['received_at'],
                'estimated_delivery_at' => $data['estimated_delivery_at'] ?? null,
                'status' => TicketStatus::Received,
            ]);

            $ticket->history()->create([
                'from_status' => null,
                'to_status' => TicketStatus::Received,
                'note' => $data['note'] ?? null,
                'changed_by' => $actor->id,
            ]);

            $this->storePhotos($ticket, $actor, $photos);

            return $ticket->load(['customer', 'history.changedBy', 'photos']);
        });
    }

    public function changeStatus(
        RepairTicket $ticket,
        User $actor,
        TicketStatus $to,
        ?string $note = null,
    ): RepairTicket {
        if ($ticket->status === $to) {
            throw ValidationException::withMessages([
                'status' => __('El ticket ya está en ese estado.'),
            ]);
        }

        return DB::transaction(function () use ($ticket, $actor, $to, $note): RepairTicket {
            $from = $ticket->status;

            $ticket->update([
                'status' => $to,
            ]);

            $ticket->history()->create([
                'from_status' => $from,
                'to_status' => $to,
                'note' => $note,
                'changed_by' => $actor->id,
            ]);

            return $ticket->refresh()->load(['customer', 'history.changedBy']);
        });
    }

    /**
     * @param  array{customer_name?: string|null, customer_email: string, customer_phone?: string|null}  $data
     */
    private function findOrCreateCustomer(User $actor, array $data): Customer
    {
        $customer = Customer::query()->firstOrNew([
            'user_id' => $actor->id,
            'email' => Str::lower($data['customer_email']),
        ]);

        if (filled($data['customer_name'] ?? null)) {
            $customer->name = $data['customer_name'];
        }

        if (array_key_exists('customer_phone', $data)) {
            $customer->phone = $data['customer_phone'];
        }

        $customer->save();

        return $customer;
    }

    /**
     * @param  list<UploadedFile>  $photos
     */
    private function storePhotos(RepairTicket $ticket, User $actor, array $photos): void
    {
        foreach ($photos as $index => $photo) {
            $extension = $photo->guessExtension() ?: $photo->getClientOriginalExtension() ?: 'jpg';
            $filename = Str::uuid()->toString().'.'.$extension;
            $path = $photo->storeAs(
                "tickets/{$actor->id}/{$ticket->id}",
                $filename,
                'public',
            );

            if (! is_string($path) || $path === '') {
                throw ValidationException::withMessages([
                    'photos' => __('No se pudieron guardar las fotos.'),
                ]);
            }

            $ticket->photos()->create([
                'path' => $path,
                'sort_order' => $index,
            ]);
        }
    }
}

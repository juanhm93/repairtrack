<?php

namespace Database\Seeders;

use App\Enums\TicketNotificationType;
use App\Enums\TicketStatus;
use App\Models\Customer;
use App\Models\RepairTicket;
use App\Models\TicketNotification;
use App\Models\User;
use App\Services\TicketService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Notification;

class TicketSeeder extends Seeder
{
    /**
     * Seed demo tickets for the test user.
     */
    public function run(): void
    {
        Notification::fake();

        $user = User::query()->where('email', 'test@example.com')->firstOrFail();
        $tickets = app(TicketService::class);

        $maria = Customer::factory()->create([
            'user_id' => $user->id,
            'name' => 'María López',
            'phone' => '809-555-0101',
            'email' => 'maria@example.com',
        ]);

        $carlos = Customer::factory()->create([
            'user_id' => $user->id,
            'name' => 'Carlos Peña',
            'phone' => '809-555-0102',
            'email' => 'carlos@example.com',
        ]);

        $lucia = Customer::factory()->create([
            'user_id' => $user->id,
            'name' => 'Lucía Gómez',
            'phone' => '809-555-0103',
            'email' => 'lucia@example.com',
        ]);

        RepairTicket::factory()->create([
            'user_id' => $user->id,
            'customer_id' => $maria->id,
            'device_type' => 'celular',
            'brand' => 'Samsung',
            'model' => 'Galaxy A54',
            'serial_number' => 'R58M30ABCDE',
            'reported_issue' => 'Pantalla rota al caer',
            'estimated_cost' => 85,
            'status' => TicketStatus::Received,
        ]);

        $inRepair = RepairTicket::factory()->create([
            'user_id' => $user->id,
            'customer_id' => $carlos->id,
            'device_type' => 'consola',
            'brand' => 'Sony',
            'model' => 'PlayStation 5',
            'reported_issue' => 'No lee discos',
            'estimated_cost' => 120,
            'status' => TicketStatus::Received,
        ]);
        $tickets->changeStatus($inRepair, $user, TicketStatus::InReview, 'Diagnóstico inicial');
        $tickets->changeStatus($inRepair, $user, TicketStatus::InRepair, 'Cambiando el lector');

        $waiting = RepairTicket::factory()->create([
            'user_id' => $user->id,
            'customer_id' => $lucia->id,
            'device_type' => 'celular',
            'brand' => 'Apple',
            'model' => 'iPhone 13',
            'serial_number' => 'F2LXQ3ABCDE',
            'reported_issue' => 'Batería se infló',
            'estimated_cost' => 95,
            'status' => TicketStatus::Received,
        ]);
        $tickets->changeStatus($waiting, $user, TicketStatus::WaitingApproval, 'Esperando aprobación del costo de batería');

        $ready = RepairTicket::factory()->create([
            'user_id' => $user->id,
            'customer_id' => $maria->id,
            'device_type' => 'otro',
            'brand' => 'Dell',
            'model' => 'XPS 13',
            'reported_issue' => 'No carga',
            'estimated_cost' => 150,
            'status' => TicketStatus::Received,
        ]);
        $tickets->changeStatus($ready, $user, TicketStatus::InRepair, 'Puerto de carga dañado');
        $tickets->changeStatus($ready, $user, TicketStatus::Ready, 'Listo para entregar');

        $delivered = RepairTicket::factory()->create([
            'user_id' => $user->id,
            'customer_id' => $carlos->id,
            'device_type' => 'celular',
            'brand' => 'Xiaomi',
            'model' => 'Redmi Note 12',
            'reported_issue' => 'No da señal',
            'estimated_cost' => 60,
            'status' => TicketStatus::Received,
        ]);
        $tickets->changeStatus($delivered, $user, TicketStatus::Ready);
        $tickets->changeStatus($delivered, $user, TicketStatus::Delivered, 'Entregado en taller');

        RepairTicket::factory()->overdue()->create([
            'user_id' => $user->id,
            'customer_id' => $lucia->id,
            'device_type' => 'consola',
            'brand' => 'Nintendo',
            'model' => 'Switch OLED',
            'reported_issue' => 'Joy-Con con drift',
            'estimated_cost' => 45,
        ]);

        TicketNotification::query()->delete();

        $demoTickets = RepairTicket::query()
            ->with('customer')
            ->orderBy('id')
            ->limit(2)
            ->get();

        foreach ($demoTickets as $index => $ticket) {
            TicketNotification::factory()->create([
                'repair_ticket_id' => $ticket->id,
                'to_email' => $ticket->customer->email,
                'ticket_status' => $ticket->status,
                'type' => $index === 0
                    ? TicketNotificationType::Created
                    : TicketNotificationType::StatusChanged,
            ]);
        }
    }
}

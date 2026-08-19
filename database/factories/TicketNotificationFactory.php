<?php

namespace Database\Factories;

use App\Enums\TicketNotificationStatus;
use App\Enums\TicketNotificationType;
use App\Enums\TicketStatus;
use App\Models\RepairTicket;
use App\Models\TicketNotification;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TicketNotification>
 */
class TicketNotificationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'repair_ticket_id' => RepairTicket::factory(),
            'type' => TicketNotificationType::Created,
            'to_email' => fake()->safeEmail(),
            'status' => TicketNotificationStatus::Sent,
            'ticket_status' => TicketStatus::Received,
            'error_message' => null,
        ];
    }
}

<?php

namespace Database\Factories;

use App\Enums\TicketStatus;
use App\Models\Customer;
use App\Models\RepairTicket;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<RepairTicket>
 */
class RepairTicketFactory extends Factory
{
    /**
     * Configure the factory.
     */
    public function configure(): static
    {
        return $this->afterCreating(function (RepairTicket $ticket): void {
            if ($ticket->history()->exists()) {
                return;
            }

            $ticket->history()->create([
                'from_status' => null,
                'to_status' => TicketStatus::Received,
                'note' => null,
                'changed_by' => $ticket->user_id,
            ]);
        });
    }

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'customer_id' => function (array $attributes): int {
                return Customer::factory()->create([
                    'user_id' => $attributes['user_id'],
                ])->id;
            },
            'public_token' => Str::random(32),
            'device_type' => fake()->randomElement(['celular', 'tablet', 'laptop', 'pc_desktop', 'consola', 'otro']),
            'brand' => fake()->optional()->company(),
            'model' => fake()->optional()->bothify('Model-##??'),
            'serial_number' => fake()->optional()->bothify('SN########'),
            'reported_issue' => fake()->sentence(),
            'estimated_cost' => fake()->optional()->randomFloat(2, 20, 500),
            'received_at' => now()->toDateString(),
            'estimated_delivery_at' => now()->addDays(5)->toDateString(),
            'status' => TicketStatus::Received,
        ];
    }

    public function inReview(): static
    {
        return $this->state(fn (): array => [
            'status' => TicketStatus::InReview,
        ]);
    }

    public function ready(): static
    {
        return $this->state(fn (): array => [
            'status' => TicketStatus::Ready,
        ]);
    }

    public function delivered(): static
    {
        return $this->state(fn (): array => [
            'status' => TicketStatus::Delivered,
        ]);
    }

    public function overdue(): static
    {
        return $this->state(fn (): array => [
            'received_at' => now()->subDays(10)->toDateString(),
            'estimated_delivery_at' => now()->subDays(3)->toDateString(),
            'status' => TicketStatus::InRepair,
        ]);
    }
}

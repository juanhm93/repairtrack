<?php

namespace Tests\Feature\Notifications;

use App\Enums\TicketNotificationStatus;
use App\Enums\TicketNotificationType;
use App\Enums\TicketStatus;
use App\Models\Customer;
use App\Models\RepairTicket;
use App\Models\TicketNotification;
use App\Models\User;
use App\Notifications\TicketStatusNotification;
use App\Services\TicketService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class TicketNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_a_ticket_sends_a_notification_and_writes_a_log(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->actingAs($user)->post(route('tickets.store'), $this->validPayload([
            'customer_email' => 'ana@example.com',
        ]));

        $ticket = RepairTicket::query()->first();
        $this->assertNotNull($ticket);

        Notification::assertSentOnDemand(TicketStatusNotification::class, function (
            TicketStatusNotification $notification,
            array $channels,
            object $notifiable,
        ) use ($ticket): bool {
            return $notifiable instanceof AnonymousNotifiable
                && $notifiable->routes['mail'] === 'ana@example.com'
                && $notification->type === TicketNotificationType::Created
                && $notification->ticket->is($ticket)
                && $channels === ['mail'];
        });

        $this->assertDatabaseHas('ticket_notifications', [
            'repair_ticket_id' => $ticket->id,
            'type' => TicketNotificationType::Created->value,
            'to_email' => 'ana@example.com',
            'status' => TicketNotificationStatus::Queued->value,
            'ticket_status' => TicketStatus::Received->value,
        ]);
        $this->assertSame(1, $ticket->notifications()->count());
    }

    public function test_changing_status_sends_another_notification_and_log(): void
    {
        Notification::fake();

        $user = User::factory()->create();
        $ticket = RepairTicket::factory()->create([
            'user_id' => $user->id,
        ]);

        $this->actingAs($user)
            ->from(route('tickets.show', $ticket))
            ->patch(route('tickets.status.update', $ticket), [
                'status' => TicketStatus::InRepair->value,
                'note' => 'Cambiando pantalla',
            ])
            ->assertRedirect();

        Notification::assertSentOnDemand(TicketStatusNotification::class, function (
            TicketStatusNotification $notification,
            array $channels,
            object $notifiable,
        ) use ($ticket): bool {
            return $notifiable instanceof AnonymousNotifiable
                && $notifiable->routes['mail'] === $ticket->customer->email
                && $notification->type === TicketNotificationType::StatusChanged
                && $notification->ticket->is($ticket);
        });

        $this->assertDatabaseHas('ticket_notifications', [
            'repair_ticket_id' => $ticket->id,
            'type' => TicketNotificationType::StatusChanged->value,
            'to_email' => $ticket->customer->email,
            'status' => TicketNotificationStatus::Queued->value,
            'ticket_status' => TicketStatus::InRepair->value,
        ]);
        $this->assertSame(1, $ticket->notifications()->count());
    }

    public function test_mail_uses_the_app_from_address_not_a_per_user_sender(): void
    {
        Notification::fake();

        config([
            'mail.from.address' => 'noreply@repairtrack.test',
            'mail.from.name' => 'RepairTrack',
            'app.name' => 'RepairTrack',
        ]);

        $user = User::factory()->create([
            'name' => 'Técnico Propio',
            'email' => 'tecnico@example.com',
        ]);

        $this->actingAs($user)->post(route('tickets.store'), $this->validPayload());

        Notification::assertSentOnDemand(TicketStatusNotification::class, function (
            TicketStatusNotification $notification,
        ): bool {
            $mail = $notification->toMail(new AnonymousNotifiable);

            return $mail->from[0] === 'noreply@repairtrack.test'
                && $mail->from[1] === 'RepairTrack'
                && $mail->subject === 'Recibimos tu equipo — RepairTrack';
        });
    }

    public function test_mail_contains_the_public_ticket_url(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->actingAs($user)->post(route('tickets.store'), $this->validPayload());

        $ticket = RepairTicket::query()->first();
        $this->assertNotNull($ticket);

        $statusUrl = route('public.tickets.show', $ticket->public_token);

        Notification::assertSentOnDemand(TicketStatusNotification::class, function (
            TicketStatusNotification $notification,
        ) use ($ticket, $statusUrl): bool {
            $mail = $notification->toMail(new AnonymousNotifiable);
            $viewData = $mail->viewData;

            return ($viewData['statusUrl'] ?? null) === $statusUrl
                && str_contains($statusUrl, '/t/'.$ticket->public_token);
        });
    }

    public function test_ticket_of_user_a_does_not_notify_customer_of_user_b(): void
    {
        Notification::fake();

        $userA = User::factory()->create();
        $userB = User::factory()->create();

        Customer::factory()->create([
            'user_id' => $userB->id,
            'email' => 'cliente-b@example.com',
        ]);

        $this->actingAs($userA)->post(route('tickets.store'), $this->validPayload([
            'customer_email' => 'cliente-a@example.com',
        ]));

        Notification::assertSentOnDemandTimes(TicketStatusNotification::class, 1);
        Notification::assertSentOnDemand(TicketStatusNotification::class, function (
            TicketStatusNotification $notification,
            array $channels,
            object $notifiable,
        ): bool {
            return $notifiable instanceof AnonymousNotifiable
                && $notifiable->routes['mail'] === 'cliente-a@example.com';
        });

        $this->assertFalse(
            TicketNotification::query()->where('to_email', 'cliente-b@example.com')->exists(),
        );
    }

    public function test_same_status_change_does_not_notify(): void
    {
        Notification::fake();

        $user = User::factory()->create();
        $ticket = RepairTicket::factory()->create([
            'user_id' => $user->id,
        ]);

        $this->actingAs($user)
            ->from(route('tickets.show', $ticket))
            ->patch(route('tickets.status.update', $ticket), [
                'status' => TicketStatus::Received->value,
            ])
            ->assertInvalid(['status']);

        Notification::assertNothingSent();
        $this->assertSame(0, $ticket->notifications()->count());
    }

    public function test_show_includes_the_latest_notification_log(): void
    {
        $user = User::factory()->create();
        $ticket = RepairTicket::factory()->create([
            'user_id' => $user->id,
        ]);

        TicketNotification::factory()->create([
            'repair_ticket_id' => $ticket->id,
            'to_email' => 'viejo@example.com',
            'status' => TicketNotificationStatus::Sent,
            'created_at' => now()->subHour(),
        ]);

        $latest = TicketNotification::factory()->create([
            'repair_ticket_id' => $ticket->id,
            'to_email' => 'ana@example.com',
            'status' => TicketNotificationStatus::Queued,
            'created_at' => now(),
        ]);

        $this->actingAs($user)
            ->get(route('tickets.show', $ticket))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('tickets/Show')
                ->where('ticket.latest_notification.id', $latest->id)
                ->where('ticket.latest_notification.to_email', 'ana@example.com')
                ->where('ticket.latest_notification.status', TicketNotificationStatus::Queued->value));
    }

    public function test_show_omits_notification_block_when_there_are_no_logs(): void
    {
        $user = User::factory()->create();
        $ticket = RepairTicket::factory()->create([
            'user_id' => $user->id,
        ]);

        $this->actingAs($user)
            ->get(route('tickets.show', $ticket))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('tickets/Show')
                ->where('ticket.latest_notification', null));
    }

    public function test_public_ticket_route_is_registered_and_not_found_until_spec_03(): void
    {
        $ticket = RepairTicket::factory()->create();

        $this->get(route('public.tickets.show', $ticket->public_token))
            ->assertNotFound();
    }

    public function test_notification_listener_marks_the_log_as_sent(): void
    {
        $user = User::factory()->create();
        $ticket = RepairTicket::factory()->create([
            'user_id' => $user->id,
        ]);

        app(TicketService::class)->changeStatus($ticket, $user, TicketStatus::InRepair, 'En taller');

        $log = TicketNotification::query()
            ->where('repair_ticket_id', $ticket->id)
            ->first();

        $this->assertNotNull($log);
        $this->assertSame(TicketNotificationStatus::Sent, $log->status);
        $this->assertSame($ticket->customer->email, $log->to_email);
        $this->assertSame(TicketNotificationType::StatusChanged, $log->type);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function validPayload(array $overrides = []): array
    {
        return [
            'customer_name' => 'Ana Pérez',
            'customer_phone' => '809-555-0100',
            'customer_email' => 'ana@example.com',
            'device_type' => 'celular',
            'brand' => 'Apple',
            'model' => 'iPhone 13',
            'reported_issue' => 'No enciende',
            'estimated_cost' => 150,
            'received_at' => now()->toDateString(),
            ...$overrides,
        ];
    }
}

<?php

namespace Tests\Feature\Tickets;

use App\Enums\TicketStatus;
use App\Models\Customer;
use App\Models\RepairTicket;
use App\Models\TicketPhoto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class TicketTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_access_ticket_create_or_show(): void
    {
        $ticket = RepairTicket::factory()->create();

        $this->get(route('tickets.create'))
            ->assertRedirect(route('login'));

        $this->get(route('tickets.show', $ticket))
            ->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_view_the_create_ticket_page(): void
    {
        $user = User::factory()->create();

        Customer::factory()->create([
            'user_id' => $user->id,
            'name' => 'Cliente Propio',
            'email' => 'own@example.com',
        ]);
        Customer::factory()->create([
            'email' => 'other@example.com',
        ]);

        $this->actingAs($user)
            ->get(route('tickets.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('tickets/Create')
                ->has('customers', 1)
                ->where('customers.0.email', 'own@example.com')
                ->where('customers.0.name', 'Cliente Propio')
                ->has('deviceCatalog.celular')
                ->has('deviceHistory', 0));
    }

    public function test_user_can_create_a_ticket(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('tickets.store'), [
            'customer_name' => 'Ana Pérez',
            'customer_phone' => '809-555-0100',
            'customer_email' => 'ana@example.com',
            'device_type' => 'celular',
            'brand' => 'Apple',
            'model' => 'iPhone 13',
            'reported_issue' => 'No enciende',
            'estimated_cost' => 150,
            'received_at' => now()->toDateString(),
            'estimated_delivery_at' => now()->addDays(3)->toDateString(),
        ]);

        $ticket = RepairTicket::query()->first();

        $this->assertNotNull($ticket);
        $response->assertRedirect(route('tickets.show', $ticket));

        $this->assertSame($user->id, $ticket->user_id);
        $this->assertSame(TicketStatus::Received, $ticket->status);
        $this->assertNotNull($ticket->public_token);
        $this->assertSame(32, strlen($ticket->public_token));
        $this->assertSame('celular', $ticket->device_type);

        $this->assertDatabaseHas('customers', [
            'user_id' => $user->id,
            'name' => 'Ana Pérez',
            'email' => 'ana@example.com',
            'phone' => '809-555-0100',
        ]);

        $this->assertDatabaseHas('ticket_status_history', [
            'repair_ticket_id' => $ticket->id,
            'from_status' => null,
            'to_status' => TicketStatus::Received->value,
            'changed_by' => $user->id,
        ]);
    }

    public function test_same_email_reuses_the_customer_for_the_same_user(): void
    {
        $user = User::factory()->create();
        $payload = $this->validPayload([
            'customer_name' => 'Ana Pérez',
            'customer_email' => 'ana@example.com',
            'customer_phone' => '809-555-0100',
        ]);

        $this->actingAs($user)->post(route('tickets.store'), $payload)->assertRedirect();

        $this->actingAs($user)->post(route('tickets.store'), $this->validPayload([
            'customer_name' => 'Ana María Pérez',
            'customer_email' => 'Ana@example.com',
            'customer_phone' => '809-555-0199',
            'device_type' => 'consola',
            'reported_issue' => 'No lee juegos',
        ]))->assertRedirect();

        $this->assertSame(1, Customer::query()->where('user_id', $user->id)->count());
        $this->assertSame(2, RepairTicket::query()->where('user_id', $user->id)->count());

        $customer = Customer::query()->where('user_id', $user->id)->first();

        $this->assertNotNull($customer);
        $this->assertSame('Ana María Pérez', $customer->name);
        $this->assertSame('ana@example.com', $customer->email);
        $this->assertSame('809-555-0199', $customer->phone);
    }

    public function test_existing_customer_can_be_reused_without_name_or_phone(): void
    {
        $user = User::factory()->create();
        $customer = Customer::factory()->create([
            'user_id' => $user->id,
            'name' => 'Ana Pérez',
            'email' => 'ana@example.com',
            'phone' => '809-555-0100',
        ]);

        $payload = $this->validPayload([
            'customer_email' => 'Ana@example.com',
        ]);
        unset($payload['customer_name'], $payload['customer_phone']);

        $this->actingAs($user)->post(route('tickets.store'), $payload)->assertRedirect();

        $this->assertSame(1, Customer::query()->where('user_id', $user->id)->count());

        $customer->refresh();

        $this->assertSame('Ana Pérez', $customer->name);
        $this->assertSame('809-555-0100', $customer->phone);
        $this->assertSame(1, RepairTicket::query()->where('customer_id', $customer->id)->count());
    }

    public function test_new_customer_requires_a_name(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('tickets.store'), $this->validPayload([
                'customer_name' => null,
            ]))
            ->assertInvalid(['customer_name']);

        $this->assertSame(0, Customer::query()->count());
        $this->assertSame(0, RepairTicket::query()->count());
    }

    public function test_same_email_creates_another_customer_for_a_different_user(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $payload = $this->validPayload([
            'customer_email' => 'ana@example.com',
        ]);

        $this->actingAs($userA)->post(route('tickets.store'), $payload)->assertRedirect();
        $this->actingAs($userB)->post(route('tickets.store'), $payload)->assertRedirect();

        $this->assertSame(2, Customer::query()->where('email', 'ana@example.com')->count());
        $this->assertTrue(Customer::query()->where('user_id', $userA->id)->where('email', 'ana@example.com')->exists());
        $this->assertTrue(Customer::query()->where('user_id', $userB->id)->where('email', 'ana@example.com')->exists());
    }

    public function test_show_loads_ticket_customer_and_history(): void
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
                ->where('ticket.id', $ticket->id)
                ->where('ticket.status', TicketStatus::Received->value)
                ->has('ticket.customer', fn (Assert $customer) => $customer
                    ->where('id', $ticket->customer_id)
                    ->etc())
                ->has('ticket.history', 1)
                ->has('ticket.history.0', fn (Assert $history) => $history
                    ->where('to_status', TicketStatus::Received->value)
                    ->where('from_status', null)
                    ->etc())
                ->has('ticket.photos', 0)
                ->has('statuses'));
    }

    public function test_user_can_change_ticket_status_with_a_note(): void
    {
        $user = User::factory()->create();
        $ticket = RepairTicket::factory()->create([
            'user_id' => $user->id,
        ]);

        $this->actingAs($user)
            ->from(route('tickets.show', $ticket))
            ->patch(route('tickets.status.update', $ticket), [
                'status' => TicketStatus::InRepair->value,
                'note' => 'Esperando pantalla',
            ])
            ->assertRedirect();

        $ticket->refresh();

        $this->assertSame(TicketStatus::InRepair, $ticket->status);
        $this->assertDatabaseHas('ticket_status_history', [
            'repair_ticket_id' => $ticket->id,
            'from_status' => TicketStatus::Received->value,
            'to_status' => TicketStatus::InRepair->value,
            'note' => 'Esperando pantalla',
            'changed_by' => $user->id,
        ]);
        $this->assertSame(2, $ticket->history()->count());
    }

    public function test_changing_to_the_same_status_is_invalid(): void
    {
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

        $this->assertSame(TicketStatus::Received, $ticket->fresh()->status);
        $this->assertSame(1, $ticket->history()->count());
    }

    public function test_user_cannot_view_or_update_another_users_ticket(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $ticket = RepairTicket::factory()->create([
            'user_id' => $owner->id,
        ]);

        $this->actingAs($other)
            ->get(route('tickets.show', $ticket))
            ->assertNotFound();

        $this->actingAs($other)
            ->patch(route('tickets.status.update', $ticket), [
                'status' => TicketStatus::Ready->value,
            ])
            ->assertNotFound();

        $this->assertSame(TicketStatus::Received, $ticket->fresh()->status);
    }

    public function test_create_page_device_history_only_includes_the_authenticated_users_tickets(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        RepairTicket::factory()->create([
            'user_id' => $user->id,
            'device_type' => 'celular',
            'brand' => 'Apple',
            'model' => 'iPhone 13',
        ]);

        RepairTicket::factory()->create([
            'user_id' => $other->id,
            'device_type' => 'celular',
            'brand' => 'SecretBrand',
            'model' => 'SecretModel',
        ]);

        $this->actingAs($user)
            ->get(route('tickets.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('tickets/Create')
                ->has('deviceCatalog.celular.Apple')
                ->has('deviceHistory', 1)
                ->where('deviceHistory.0.brand', 'Apple')
                ->where('deviceHistory.0.model', 'iPhone 13')
                ->where('deviceHistory.0.device_type', 'celular'));
    }

    public function test_user_can_create_a_ticket_with_a_serial_number(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('tickets.store'), $this->validPayload([
                'serial_number' => 'F2LXQ3ABCDE',
            ]))
            ->assertRedirect();

        $ticket = RepairTicket::query()->first();

        $this->assertNotNull($ticket);
        $this->assertSame('F2LXQ3ABCDE', $ticket->serial_number);
    }

    public function test_user_can_create_a_ticket_with_photos(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
        $front = UploadedFile::fake()->image('front.jpg');
        $back = UploadedFile::fake()->image('back.png');

        $this->actingAs($user)
            ->post(route('tickets.store'), $this->validPayload([
                'photos' => [$front, $back],
            ]))
            ->assertRedirect();

        $ticket = RepairTicket::query()->first();

        $this->assertNotNull($ticket);
        $this->assertSame(2, $ticket->photos()->count());

        $ticket->photos->each(function (TicketPhoto $photo) use ($ticket): void {
            $this->assertNotSame('', $photo->path);
            $this->assertStringContainsString("tickets/{$ticket->user_id}/{$ticket->id}/", $photo->path);
            Storage::disk('public')->assertExists($photo->path);
            $this->assertStringStartsWith('/storage/', $photo->url);
            $this->assertStringContainsString($photo->path, $photo->url);
        });

        $this->actingAs($user)
            ->get(route('tickets.show', $ticket))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('ticket.photos', 2)
                ->has('ticket.photos.0', fn (Assert $photo) => $photo
                    ->has('id')
                    ->has('url')
                    ->has('sort_order')
                    ->missing('path')
                    ->etc()));
    }

    public function test_ticket_photos_cannot_exceed_five(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
        $photos = [];

        for ($i = 1; $i <= 6; $i++) {
            $photos[] = UploadedFile::fake()->image("photo-{$i}.jpg");
        }

        $this->actingAs($user)
            ->post(route('tickets.store'), $this->validPayload([
                'photos' => $photos,
            ]))
            ->assertInvalid(['photos']);

        $this->assertSame(0, RepairTicket::query()->count());
    }

    public function test_ticket_photos_must_be_images(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('tickets.store'), $this->validPayload([
                'photos' => [UploadedFile::fake()->create('notes.pdf', 100, 'application/pdf')],
            ]))
            ->assertInvalid(['photos.0']);

        $this->assertSame(0, RepairTicket::query()->count());
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
            'estimated_delivery_at' => now()->addDays(3)->toDateString(),
            ...$overrides,
        ];
    }
}

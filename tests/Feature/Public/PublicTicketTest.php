<?php

namespace Tests\Feature\Public;

use App\Enums\TicketStatus;
use App\Models\Customer;
use App\Models\RepairTicket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PublicTicketTest extends TestCase
{
    use RefreshDatabase;

    public function test_existing_token_renders_the_public_status_page(): void
    {
        $ticket = $this->ticketWithHistory();

        $this->get(route('public.tickets.show', $ticket->public_token))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('public/TicketStatus'));
    }

    public function test_unknown_token_returns_not_found(): void
    {
        $this->get(route('public.tickets.show', Str::random(32)))
            ->assertNotFound();
    }

    public function test_guest_can_view_the_public_status_page(): void
    {
        $ticket = $this->ticketWithHistory();

        $this->get(route('public.tickets.show', $ticket->public_token))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('public/TicketStatus')
                ->where('customer.name', 'Ana Pérez'));
    }

    public function test_public_route_does_not_redirect_to_login(): void
    {
        $ticket = $this->ticketWithHistory();

        $this->get(route('public.tickets.show', $ticket->public_token))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('public/TicketStatus'));
    }

    public function test_authenticated_user_still_sees_the_public_page(): void
    {
        $user = User::factory()->create();
        $ticket = $this->ticketWithHistory();

        $this->actingAs($user)
            ->get(route('public.tickets.show', $ticket->public_token))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('public/TicketStatus'));
    }

    public function test_safe_props_are_present_and_sensitive_fields_are_omitted(): void
    {
        $ticket = $this->ticketWithHistory();

        $this->get(route('public.tickets.show', $ticket->public_token))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('public/TicketStatus')
                ->where('app.name', config('app.name'))
                ->where('customer.name', 'Ana Pérez')
                ->where('ticket.device_type', 'celular')
                ->where('ticket.brand', 'Apple')
                ->where('ticket.model', 'iPhone 13')
                ->where('ticket.status', TicketStatus::InRepair->value)
                ->where('ticket.status_label', TicketStatus::InRepair->label())
                ->where('ticket.received_at', $ticket->received_at->toDateString())
                ->where('ticket.estimated_delivery_at', $ticket->estimated_delivery_at?->toDateString())
                ->has('ticket.history', 2)
                ->has('ticket.history.0', fn (Assert $history) => $history
                    ->where('to_status', TicketStatus::Received->value)
                    ->where('to_status_label', TicketStatus::Received->label())
                    ->where('note', null)
                    ->has('created_at')
                    ->missing('from_status')
                    ->missing('changed_by')
                    ->missing('id'))
                ->has('ticket.history.1', fn (Assert $history) => $history
                    ->where('to_status', TicketStatus::InRepair->value)
                    ->where('to_status_label', TicketStatus::InRepair->label())
                    ->where('note', 'Cambiando pantalla')
                    ->has('created_at')
                    ->missing('from_status')
                    ->missing('changed_by'))
                ->missing('ticket.estimated_cost')
                ->missing('ticket.id')
                ->missing('ticket.user_id')
                ->missing('ticket.public_token')
                ->missing('ticket.reported_issue')
                ->missing('customer.email')
                ->missing('customer.phone')
                ->missing('customer.id')
                ->missing('user'));
    }

    public function test_token_for_ticket_a_does_not_reveal_ticket_b(): void
    {
        $ticketA = $this->ticketWithHistory([
            'customer_name' => 'Ana Pérez',
            'customer_email' => 'ana@example.com',
            'device_type' => 'celular',
            'brand' => 'Apple',
            'model' => 'iPhone 13',
        ]);

        $ticketB = $this->ticketWithHistory([
            'customer_name' => 'Carlos Gómez',
            'customer_email' => 'carlos@example.com',
            'customer_phone' => '809-555-0199',
            'device_type' => 'laptop',
            'brand' => 'Lenovo',
            'model' => 'ThinkPad',
            'estimated_cost' => '999.00',
        ]);

        $this->get(route('public.tickets.show', $ticketA->public_token))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('public/TicketStatus')
                ->where('customer.name', 'Ana Pérez')
                ->where('ticket.device_type', 'celular')
                ->where('ticket.brand', 'Apple')
                ->where('ticket.model', 'iPhone 13')
                ->where('ticket.status', TicketStatus::InRepair->value)
                ->missing('ticket.estimated_cost'))
            ->assertDontSee('Carlos Gómez')
            ->assertDontSee('carlos@example.com')
            ->assertDontSee('809-555-0199')
            ->assertDontSee('Lenovo')
            ->assertDontSee('ThinkPad')
            ->assertDontSee('999.00')
            ->assertDontSee($ticketB->public_token);
    }

    /**
     * @param  array{
     *     customer_name?: string,
     *     customer_email?: string,
     *     customer_phone?: string|null,
     *     device_type?: string,
     *     brand?: string|null,
     *     model?: string|null,
     *     estimated_cost?: string|null
     * }  $overrides
     */
    private function ticketWithHistory(array $overrides = []): RepairTicket
    {
        $user = User::factory()->create();

        $customer = Customer::factory()->create([
            'user_id' => $user->id,
            'name' => $overrides['customer_name'] ?? 'Ana Pérez',
            'email' => $overrides['customer_email'] ?? 'ana@example.com',
            'phone' => $overrides['customer_phone'] ?? '809-555-0100',
        ]);

        $ticket = RepairTicket::factory()->create([
            'user_id' => $user->id,
            'customer_id' => $customer->id,
            'device_type' => $overrides['device_type'] ?? 'celular',
            'brand' => $overrides['brand'] ?? 'Apple',
            'model' => $overrides['model'] ?? 'iPhone 13',
            'estimated_cost' => $overrides['estimated_cost'] ?? '150.00',
            'status' => TicketStatus::InRepair,
        ]);

        $ticket->history()->create([
            'from_status' => TicketStatus::Received,
            'to_status' => TicketStatus::InRepair,
            'note' => 'Cambiando pantalla',
            'changed_by' => $user->id,
        ]);

        return $ticket->refresh()->load(['customer', 'history']);
    }
}

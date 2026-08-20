<?php

namespace Tests\Feature;

use App\Enums\TicketStatus;
use App\Models\Customer;
use App\Models\RepairTicket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page(): void
    {
        $response = $this->get(route('dashboard'));
        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_users_can_visit_the_dashboard(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->has('month.label')
                ->has('recentTickets')
                ->has('stats'));
    }

    public function test_dashboard_is_scoped_to_the_authenticated_user(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $own = RepairTicket::factory()->create([
            'user_id' => $user->id,
            'status' => TicketStatus::Received,
            'received_at' => now()->toDateString(),
        ]);

        RepairTicket::factory()->create([
            'user_id' => $other->id,
            'status' => TicketStatus::Delivered,
            'received_at' => now()->toDateString(),
        ]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->has('recentTickets', 1)
                ->where('recentTickets.0.id', $own->id)
                ->missing('recentTickets.0.history')
                ->missing('recentTickets.0.photos')
                ->has('recentTickets.0.customer')
                ->where('stats.tickets_count', 1)
                ->where('stats.completed_count', 0)
                ->where('stats.pending_count', 1));
    }

    public function test_recent_tickets_are_the_three_newest_and_may_include_previous_months(): void
    {
        $user = User::factory()->create();
        $startOfMonth = now()->startOfMonth();

        $oldest = RepairTicket::factory()->create([
            'user_id' => $user->id,
            'received_at' => $startOfMonth->copy()->subDays(20)->toDateString(),
        ]);
        $previousMonth = RepairTicket::factory()->create([
            'user_id' => $user->id,
            'received_at' => $startOfMonth->copy()->subDay()->toDateString(),
        ]);
        $second = RepairTicket::factory()->create([
            'user_id' => $user->id,
            'received_at' => $startOfMonth->copy()->addDays(4)->toDateString(),
        ]);
        $newest = RepairTicket::factory()->create([
            'user_id' => $user->id,
            'received_at' => $startOfMonth->copy()->addDays(8)->toDateString(),
        ]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('recentTickets', 3)
                ->where('recentTickets.0.id', $newest->id)
                ->where('recentTickets.1.id', $second->id)
                ->where('recentTickets.2.id', $previousMonth->id)
                ->where('recentTickets', fn (Collection $tickets): bool => $tickets
                    ->pluck('id')
                    ->doesntContain($oldest->id)));
    }

    public function test_kpis_use_the_current_calendar_month_and_any_status(): void
    {
        $user = User::factory()->create();
        $customer = Customer::factory()->create(['user_id' => $user->id]);
        $otherCustomer = Customer::factory()->create(['user_id' => $user->id]);
        $startOfMonth = now()->startOfMonth();

        RepairTicket::factory()->create([
            'user_id' => $user->id,
            'customer_id' => $customer->id,
            'status' => TicketStatus::Received,
            'received_at' => $startOfMonth->toDateString(),
        ]);
        RepairTicket::factory()->create([
            'user_id' => $user->id,
            'customer_id' => $customer->id,
            'status' => TicketStatus::Delivered,
            'received_at' => $startOfMonth->copy()->addDays(2)->toDateString(),
        ]);
        RepairTicket::factory()->create([
            'user_id' => $user->id,
            'customer_id' => $otherCustomer->id,
            'status' => TicketStatus::NotRepairable,
            'received_at' => $startOfMonth->copy()->addDays(3)->toDateString(),
        ]);
        RepairTicket::factory()->create([
            'user_id' => $user->id,
            'customer_id' => $otherCustomer->id,
            'status' => TicketStatus::InRepair,
            'received_at' => $startOfMonth->copy()->subDay()->toDateString(),
        ]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('stats.tickets_count', 3)
                ->where('stats.customers_count', 2)
                ->where('stats.completed_count', 1)
                ->where('stats.pending_count', 2)
                ->where('stats.by_status', function (Collection $byStatus) {
                    $this->assertCount(count(TicketStatus::cases()), $byStatus);
                    $this->assertSame(
                        array_map(fn (TicketStatus $status): string => $status->value, TicketStatus::cases()),
                        $byStatus->pluck('value')->all(),
                    );
                    $this->assertSame(3, (int) $byStatus->sum('count'));
                    $this->assertSame(1, $this->statusCount($byStatus, TicketStatus::Received));
                    $this->assertSame(1, $this->statusCount($byStatus, TicketStatus::Delivered));
                    $this->assertSame(1, $this->statusCount($byStatus, TicketStatus::NotRepairable));
                    $this->assertSame(0, $this->statusCount($byStatus, TicketStatus::InRepair));
                    $this->assertSame(0, $this->statusCount($byStatus, TicketStatus::Ready));

                    return true;
                }));
    }

    public function test_empty_dashboard_shows_zero_stats_and_no_recent_tickets(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->has('recentTickets', 0)
                ->where('stats.tickets_count', 0)
                ->where('stats.customers_count', 0)
                ->where('stats.completed_count', 0)
                ->where('stats.pending_count', 0)
                ->where('stats.by_status', function (Collection $byStatus) {
                    $this->assertCount(count(TicketStatus::cases()), $byStatus);
                    $this->assertSame(0, (int) $byStatus->sum('count'));

                    return true;
                }));
    }

    /**
     * @param  Collection<int, array{value: string, label: string, count: int}>  $byStatus
     */
    private function statusCount(Collection $byStatus, TicketStatus $status): int
    {
        $item = $byStatus->firstWhere('value', $status->value);

        if (! is_array($item) || ! array_key_exists('count', $item)) {
            $this->fail("Missing status [{$status->value}] in by_status.");
        }

        return (int) $item['count'];
    }
}

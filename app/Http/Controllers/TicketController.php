<?php

namespace App\Http\Controllers;

use App\Enums\TicketStatus;
use App\Http\Requests\IndexTicketRequest;
use App\Http\Requests\StoreTicketRequest;
use App\Http\Requests\UpdateTicketRequest;
use App\Http\Requests\UpdateTicketStatusRequest;
use App\Models\RepairTicket;
use App\Models\User;
use App\Services\TicketService;
use App\Support\DeviceCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class TicketController extends Controller
{
    /**
     * Display a listing of the user's tickets.
     */
    public function index(IndexTicketRequest $request, TicketService $tickets): Response
    {
        $filters = $request->filters();

        return Inertia::render('tickets/Index', [
            'tickets' => $tickets->paginateForIndex($this->actor($request), $filters),
            'filters' => [
                'status' => $filters['status']?->value,
                'q' => $filters['q'],
            ],
            'statusOptions' => TicketStatus::options(),
        ]);
    }

    /**
     * Show the form for creating a new ticket.
     */
    public function create(Request $request): Response
    {
        $customers = $this->actor($request)
            ->customers()
            ->orderByDesc('updated_at')
            ->get(['id', 'name', 'email', 'phone']);

        return Inertia::render('tickets/Create', [
            'customers' => $customers,
            'deviceCatalog' => DeviceCatalog::all(),
            'deviceHistory' => DeviceCatalog::historyFor($this->actor($request)),
        ]);
    }

    /**
     * Store a newly created ticket.
     */
    public function store(StoreTicketRequest $request, TicketService $tickets): RedirectResponse
    {
        $ticket = $tickets->create($this->actor($request), $request->payload(), $request->photos());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Ticket creado.'),
        ]);

        return to_route('tickets.show', $ticket);
    }

    /**
     * Show the form for editing the ticket.
     */
    public function edit(Request $request, RepairTicket $ticket): Response
    {
        Gate::authorize('update', $ticket);

        $ticket->load('customer');

        $customers = $this->actor($request)
            ->customers()
            ->orderByDesc('updated_at')
            ->get(['id', 'name', 'email', 'phone']);

        return Inertia::render('tickets/Edit', [
            'ticket' => $ticket,
            'customers' => $customers,
            'deviceCatalog' => DeviceCatalog::all(),
            'deviceHistory' => DeviceCatalog::historyFor($this->actor($request)),
        ]);
    }

    /**
     * Update the ticket and customer fields (not status).
     */
    public function update(
        UpdateTicketRequest $request,
        RepairTicket $ticket,
        TicketService $tickets,
    ): RedirectResponse {
        Gate::authorize('update', $ticket);

        $tickets->update($ticket, $this->actor($request), $request->payload());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Ticket actualizado.'),
        ]);

        return to_route('tickets.show', $ticket);
    }

    /**
     * Remove the ticket.
     */
    public function destroy(
        Request $request,
        RepairTicket $ticket,
        TicketService $tickets,
    ): RedirectResponse {
        Gate::authorize('delete', $ticket);

        $tickets->delete($ticket, $this->actor($request));

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Ticket eliminado.'),
        ]);

        return to_route('tickets.index');
    }

    /**
     * Display the specified ticket.
     */
    public function show(RepairTicket $ticket): Response
    {
        Gate::authorize('view', $ticket);

        $ticket->load([
            'customer',
            'history.changedBy:id,name',
            'photos',
            'latestNotification',
        ]);

        return Inertia::render('tickets/Show', [
            'ticket' => $ticket,
            'statuses' => TicketStatus::options(),
        ]);
    }

    /**
     * Update the ticket status.
     */
    public function updateStatus(
        UpdateTicketStatusRequest $request,
        RepairTicket $ticket,
        TicketService $tickets,
    ): RedirectResponse {
        Gate::authorize('update', $ticket);

        $tickets->changeStatus(
            $ticket,
            $this->actor($request),
            $request->status(),
            $request->note(),
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Estado actualizado.'),
        ]);

        return back();
    }

    private function actor(Request $request): User
    {
        $user = $request->user();

        assert($user instanceof User);

        return $user;
    }
}

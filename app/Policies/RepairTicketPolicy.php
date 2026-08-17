<?php

namespace App\Policies;

use App\Models\RepairTicket;
use App\Models\User;

class RepairTicketPolicy
{
    /**
     * Determine whether the user can view the ticket.
     */
    public function view(User $user, RepairTicket $repairTicket): bool
    {
        return $repairTicket->user_id === $user->id;
    }

    /**
     * Determine whether the user can update the ticket.
     */
    public function update(User $user, RepairTicket $repairTicket): bool
    {
        return $repairTicket->user_id === $user->id;
    }
}

<?php

namespace App\Policies;

use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Models\User;

class TicketPolicy
{
    /**
     * Admin boleh melakukan semuanya.
     */
    public function before(User $user): ?bool
    {
        return $user->hasRole('admin') ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return true; // daftar tiket di-scope per role di controller/query
    }

    public function view(User $user, Ticket $ticket): bool
    {
        if ($user->hasRole('agent')) {
            return $ticket->assigned_to === $user->id
                || $ticket->status === TicketStatus::Open;
        }

        return $ticket->created_by === $user->id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Ticket $ticket): bool
    {
        if ($user->hasRole('agent')) {
            return $ticket->assigned_to === $user->id;
        }

        // User hanya boleh mengubah tiketnya selama masih open
        return $ticket->created_by === $user->id
            && $ticket->status === TicketStatus::Open;
    }

    public function delete(User $user, Ticket $ticket): bool
    {
        return false; // hanya admin (ditangani oleh before())
    }

    public function changeStatus(User $user, Ticket $ticket): bool
    {
        return $user->hasRole('agent') && $ticket->assigned_to === $user->id;
    }

    public function assign(User $user, Ticket $ticket): bool
    {
        // Agent boleh mengambil tiket open (assign ke dirinya sendiri)
        return $user->hasRole('agent') && $ticket->status === TicketStatus::Open;
    }
}

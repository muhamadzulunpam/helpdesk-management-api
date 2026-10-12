<?php

namespace App\Enums;

enum TicketStatus: string
{
    case Open = 'open';
    case InProgress = 'in_progress';
    case Resolved = 'resolved';
    case Closed = 'closed';

    /**
     * Transisi status yang diizinkan.
     * Contoh: tiket closed tidak bisa langsung kembali ke in_progress.
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Open => [self::InProgress, self::Closed],
            self::InProgress => [self::Resolved, self::Open],
            self::Resolved => [self::Closed, self::InProgress],
            self::Closed => [],
        };
    }

    public function canTransitionTo(self $next): bool
    {
        return in_array($next, $this->allowedTransitions(), true);
    }
}

<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    /**
     * Admin boleh melakukan semuanya.
     */
    public function before(User $user): ?bool
    {
        return $user->hasRole('admin') ? true : null;
    }

    // Selain admin, tidak ada yang boleh mengelola user
    // (ditangani oleh before() untuk admin)
    public function viewAny(User $user): bool
    {
        return false;
    }

    public function view(User $user, User $model): bool
    {
        return false;
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, User $model): bool
    {
        return false;
    }

    public function delete(User $user, User $model): bool
    {
        return false;
    }
}

<?php

namespace App\Policies;

use App\Models\Category;
use App\Models\User;

class CategoryPolicy
{
    /**
     * Admin boleh melakukan semuanya.
     */
    public function before(User $user): ?bool
    {
        return $user->hasRole('admin') ? true : null;
    }

    // Semua user yang login boleh melihat daftar (dipakai untuk dropdown di form tiket)
    public function viewAny(User $user): bool
    {
        return true;
    }

    // Tambah, ubah, hapus: hanya admin (ditangani oleh before())
    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Category $category): bool
    {
        return false;
    }

    public function delete(User $user, Category $category): bool
    {
        return false;
    }
}

<?php

namespace App\Policies;

use App\Models\Attachment;
use App\Models\User;

class AttachmentPolicy
{
    /**
     * Admin boleh melakukan semuanya.
     */
    public function before(User $user): ?bool
    {
        return $user->hasRole('admin') ? true : null;
    }

    public function view(User $user, Attachment $attachment): bool
    {
        // Harus berhak melihat tiketnya dulu
        if (! $user->can('view', $attachment->ticket)) {
            return false;
        }

        // Lampiran pada catatan internal hanya untuk agent dan admin
        if ($attachment->comment?->is_internal && ! $user->hasRole('agent')) {
            return false;
        }

        return true;
    }

    public function delete(User $user, Attachment $attachment): bool
    {
        // Hanya pengunggahnya (atau admin lewat before())
        return $attachment->uploaded_by === $user->id;
    }
}
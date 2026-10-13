<?php

namespace App\Http\Requests\Comment;

use Illuminate\Foundation\Http\FormRequest;

class StoreCommentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        // Harus berhak melihat tiketnya dulu
        if (! $user->can('view', $this->route('ticket'))) {
            return false;
        }

        // Catatan internal hanya boleh dibuat oleh agent atau admin
        if ($this->boolean('is_internal') && ! $user->hasAnyRole(['admin', 'agent'])) {
            return false;
        }

        return true;
    }

    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'max:5000'],
            'is_internal' => ['sometimes', 'boolean'],
        ];
    }
}
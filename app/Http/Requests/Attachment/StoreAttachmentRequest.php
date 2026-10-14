<?php

namespace App\Http\Requests\Attachment;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAttachmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Siapa pun yang boleh melihat tiket boleh melampirkan file
        return $this->user()->can('view', $this->route('ticket'));
    }

    public function rules(): array
    {
        return [
            // maksimal 5 MB (5120 KB), hanya tipe yang aman
            'file' => ['required', 'file', 'max:5120', 'mimes:jpg,jpeg,png,pdf,txt,doc,docx'],

            // opsional: tautkan ke komentar milikmu sendiri pada tiket yang sama
            'comment_id' => [
                'nullable',
                'integer',
                Rule::exists('comments', 'id')
                    ->where('ticket_id', $this->route('ticket')->id)
                    ->where('user_id', $this->user()->id),
            ],
        ];
    }
}

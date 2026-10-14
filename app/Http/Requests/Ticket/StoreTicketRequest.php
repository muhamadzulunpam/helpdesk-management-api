<?php

namespace App\Http\Requests\Ticket;

use App\Enums\TicketPriority;
use App\Models\Ticket;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Ticket::class);
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'priority' => ['sometimes', Rule::enum(TicketPriority::class)],
        ];
    }
}

<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CommentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'ticket_id' => $this->ticket_id,
            'body' => $this->body,
            'is_internal' => $this->is_internal,
            'author' => $this->user
                ? ['id' => $this->user->id, 'name' => $this->user->name]
                : null,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}

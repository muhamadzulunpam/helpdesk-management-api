<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AttachmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'ticket_id' => $this->ticket_id,
            'comment_id' => $this->comment_id,
            'original_name' => $this->original_name,
            'mime' => $this->mime,
            'size' => $this->size,
            'uploaded_by' => $this->uploader
                ? ['id' => $this->uploader->id, 'name' => $this->uploader->name]
                : null,
            // lokasi file di server (path) sengaja tidak ditampilkan
            'download_url' => url("/api/v1/attachments/{$this->id}/download"),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
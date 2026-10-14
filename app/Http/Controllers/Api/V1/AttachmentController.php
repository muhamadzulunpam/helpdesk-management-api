<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\TicketStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Attachment\StoreAttachmentRequest;
use App\Http\Resources\AttachmentResource;
use App\Models\Attachment;
use App\Models\Ticket;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttachmentController extends Controller
{
    public function index(Request $request, Ticket $ticket): AnonymousResourceCollection
    {
        Gate::authorize('view', $ticket);

        $query = $ticket->attachments()->with('uploader'); // eager loading agar tidak kena N+1

        // Lampiran pada catatan internal hanya terlihat oleh agent dan admin
        if (! $request->user()->hasAnyRole(['admin', 'agent'])) {
            $query->whereDoesntHave('comment', fn ($q) => $q->where('is_internal', true));
        }

        return AttachmentResource::collection($query->orderBy('id')->get());
    }

    public function store(StoreAttachmentRequest $request, Ticket $ticket): JsonResponse
    {
        if ($ticket->status === TicketStatus::Closed) {
            return response()->json([
                'message' => 'Tiket sudah ditutup, lampiran baru tidak bisa ditambahkan.',
            ], 409);
        }

        $file = $request->file('file');

        // Disimpan di disk "local" (privat, tidak bisa dibuka langsung lewat URL).
        // Nama file di server diacak oleh Laravel, nama asli hanya disimpan di database.
        $path = $file->store("tickets/{$ticket->id}", 'local');

        $attachment = $ticket->attachments()->create([
            'comment_id' => $request->validated('comment_id'),
            'uploaded_by' => $request->user()->id,
            'path' => $path,
            'original_name' => Str::limit($file->getClientOriginalName(), 200, ''),
            'mime' => $file->getMimeType() ?? 'application/octet-stream',
            'size' => $file->getSize(),
        ]);

        $ticket->touch();

        return (new AttachmentResource($attachment->load('uploader')))
            ->response()
            ->setStatusCode(201);
    }

    public function download(Attachment $attachment): StreamedResponse
    {
        Gate::authorize('view', $attachment);

        abort_unless(Storage::disk('local')->exists($attachment->path), 404, 'File tidak ditemukan.');

        return Storage::disk('local')->download($attachment->path, $attachment->original_name);
    }

    public function destroy(Attachment $attachment): Response
    {
        Gate::authorize('delete', $attachment);

        // Hapus catatan di database dulu, baru file-nya
        $path = $attachment->path;
        $attachment->delete();
        Storage::disk('local')->delete($path);

        return response()->noContent();
    }
}

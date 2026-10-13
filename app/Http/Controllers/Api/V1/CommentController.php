<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\TicketStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Comment\StoreCommentRequest;
use App\Http\Resources\CommentResource;
use App\Models\Ticket;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class CommentController extends Controller
{
    public function index(Request $request, Ticket $ticket): AnonymousResourceCollection
    {
        Gate::authorize('view', $ticket);

        $query = $ticket->comments()->with('user'); // eager loading agar tidak kena N+1

        // Catatan internal hanya terlihat oleh agent dan admin
        if (! $request->user()->hasAnyRole(['admin', 'agent'])) {
            $query->where('is_internal', false);
        }

        return CommentResource::collection(
            $query->orderBy('created_at')->orderBy('id')->get()
        );
    }

    public function store(StoreCommentRequest $request, Ticket $ticket): JsonResponse
    {
        if ($ticket->status === TicketStatus::Closed) {
            return response()->json([
                'message' => 'Tiket sudah ditutup, komentar baru tidak bisa ditambahkan.',
            ], 409);
        }

        $comment = $ticket->comments()->create([
            'user_id' => $request->user()->id,
            'body' => $request->validated('body'),
            'is_internal' => $request->boolean('is_internal'),
        ]);

        // Perbarui waktu "terakhir diubah" tiket, supaya aktivitas terlihat
        $ticket->touch();

        return (new CommentResource($comment->load('user')))
            ->response()
            ->setStatusCode(201);
    }
}
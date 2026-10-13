<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\TicketStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Ticket\StoreTicketRequest;
use App\Http\Requests\Ticket\UpdateStatusRequest;
use App\Http\Requests\Ticket\UpdateTicketRequest;
use App\Http\Resources\TicketResource;
use App\Models\Ticket;
use App\Models\TicketHistory;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class TicketController extends Controller
{
    private const RELATIONS = ['category', 'creator', 'assignee'];

    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Ticket::class);

        $user = $request->user();

        $query = Ticket::query()
            ->with(self::RELATIONS) // eager loading agar tidak kena N+1
            ->filter($request->only(['status', 'priority', 'category_id', 'search']));

        if ($user->hasRole('admin')) {
            // admin melihat semua tiket
        } elseif ($user->hasRole('agent')) {
            // agent: tiket yang di-assign ke dia + tiket yang masih open
            $query->where(function ($q) use ($user) {
                $q->where('assigned_to', $user->id)
                    ->orWhere('status', TicketStatus::Open->value);
            });
        } else {
            // user biasa: hanya tiket miliknya
            $query->where('created_by', $user->id);
        }

        $perPage = max(1, min($request->integer('per_page', 10), 50));

        return TicketResource::collection(
            $query->latest()->paginate($perPage)->withQueryString()
        );
    }

    public function store(StoreTicketRequest $request): JsonResponse
    {
        $ticket = DB::transaction(function () use ($request) {
            $ticket = Ticket::create([
                ...$request->validated(),
                'created_by' => $request->user()->id,
                'status' => TicketStatus::Open,
            ]);

            TicketHistory::create([
                'ticket_id' => $ticket->id,
                'user_id' => $request->user()->id,
                'from_status' => null,
                'to_status' => TicketStatus::Open,
            ]);

            return $ticket;
        });

        return (new TicketResource($ticket->load(self::RELATIONS)))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Ticket $ticket): TicketResource
    {
        Gate::authorize('view', $ticket);

        return new TicketResource($ticket->load(self::RELATIONS));
    }

    public function update(UpdateTicketRequest $request, Ticket $ticket): TicketResource
    {
        $ticket->update($request->validated());

        return new TicketResource($ticket->load(self::RELATIONS));
    }

    public function destroy(Ticket $ticket): Response
    {
        Gate::authorize('delete', $ticket);

        $ticket->delete();

        return response()->noContent();
    }

    public function updateStatus(UpdateStatusRequest $request, Ticket $ticket): TicketResource
    {
        $current = $ticket->status;
        $next = TicketStatus::from($request->validated('status'));

        if (! $current->canTransitionTo($next)) {
            throw ValidationException::withMessages([
                'status' => ["Status tidak bisa diubah dari {$current->value} ke {$next->value}."],
            ]);
        }

        DB::transaction(function () use ($request, $ticket, $current, $next) {
            $ticket->update([
                'status' => $next,
                'resolved_at' => match ($next) {
                    TicketStatus::Resolved => now(),
                    TicketStatus::Closed => $ticket->resolved_at, // tetap, kosong kalau tidak pernah resolved
                    default => null,
                },
            ]);

            TicketHistory::create([
                'ticket_id' => $ticket->id,
                'user_id' => $request->user()->id,
                'from_status' => $current,
                'to_status' => $next,
            ]);
        });

        return new TicketResource($ticket->load(self::RELATIONS));
    }

    public function assign(Request $request, Ticket $ticket): TicketResource
    {
        Gate::authorize('assign', $ticket);

        $user = $request->user();

        if ($user->hasRole('admin')) {
            // admin memilih agent yang akan menangani
            $data = $request->validate([
                'assigned_to' => ['required', 'integer', 'exists:users,id'],
            ]);

            $assignee = User::findOrFail($data['assigned_to']);

            abort_unless($assignee->hasRole('agent'), 422, 'User yang dipilih bukan agent.');
        } else {
            // agent mengambil tiket untuk dirinya sendiri
            $assignee = $user;
        }

        $ticket->update(['assigned_to' => $assignee->id]);

        return new TicketResource($ticket->load(self::RELATIONS));
    }
}
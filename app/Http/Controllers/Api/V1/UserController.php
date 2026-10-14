<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\User\StoreUserRequest;
use App\Http\Requests\User\UpdateRoleRequest;
use App\Http\Requests\User\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\Attachment;
use App\Models\Comment;
use App\Models\Ticket;
use App\Models\TicketHistory;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', User::class);

        $filters = $request->validate([
            'role' => ['sometimes', 'nullable', Rule::enum(UserRole::class)],
            'search' => ['sometimes', 'nullable', 'string', 'max:100'],
            'per_page' => ['sometimes', 'nullable', 'integer'],
        ]);

        $query = User::query()
            ->with('roles') // eager loading agar tidak kena N+1
            ->when($filters['role'] ?? null, fn ($q, $role) => $q->role($role))
            ->when($filters['search'] ?? null, function ($q, $search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            });

        $perPage = max(1, min((int) ($filters['per_page'] ?? 10), 50));

        return UserResource::collection(
            $query->orderBy('name')->paginate($perPage)->withQueryString()
        );
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        $user = User::create([
            'name' => $request->validated('name'),
            'email' => $request->validated('email'),
            'password' => Hash::make($request->validated('password')),
        ]);

        $user->assignRole($request->validated('role'));

        return (new UserResource($user->load('roles')))
            ->response()
            ->setStatusCode(201);
    }

    public function show(User $user): UserResource
    {
        Gate::authorize('view', $user);

        return new UserResource($user->load('roles'));
    }

    public function update(UpdateUserRequest $request, User $user): UserResource
    {
        $data = $request->validated();

        if (isset($data['password'])) {
            $data['password'] = Hash::make($data['password']);

            // Password diganti: semua sesi login lama dicabut
            $user->tokens()->delete();
        }

        $user->update($data);

        return new UserResource($user->load('roles'));
    }

    public function updateRole(UpdateRoleRequest $request, User $user): UserResource|JsonResponse
    {
        $role = $request->validated('role');

        // Cegah sistem tanpa admin sama sekali
        if ($role !== UserRole::Admin->value && $this->isLastAdmin($user)) {
            return response()->json([
                'message' => 'Minimal harus ada satu admin. Role admin terakhir tidak bisa diturunkan.',
            ], 409);
        }

        $user->syncRoles([$role]);

        return new UserResource($user->load('roles'));
    }

    public function destroy(Request $request, User $user): Response|JsonResponse
    {
        Gate::authorize('delete', $user);

        if ($request->user()->is($user)) {
            return response()->json([
                'message' => 'Kamu tidak bisa menghapus akunmu sendiri.',
            ], 409);
        }

        // Menghapus user yang punya riwayat akan ikut menghapus tiket, komentar,
        // lampiran, dan riwayat statusnya. Jadi ditolak.
        if ($this->hasActivity($user)) {
            return response()->json([
                'message' => 'User tidak bisa dihapus karena sudah punya riwayat aktivitas (tiket, komentar, lampiran, atau perubahan status).',
            ], 409);
        }

        $user->tokens()->delete();
        $user->delete();

        return response()->noContent();
    }

    private function isLastAdmin(User $user): bool
    {
        return $user->hasRole(UserRole::Admin->value)
            && User::role(UserRole::Admin->value)->count() <= 1;
    }

    private function hasActivity(User $user): bool
    {
        return Ticket::where('created_by', $user->id)->exists()
            || Comment::where('user_id', $user->id)->exists()
            || TicketHistory::where('user_id', $user->id)->exists()
            || Attachment::where('uploaded_by', $user->id)->exists();
    }
}

<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Http\Controllers\Controller;
use App\Models\Ticket;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function stats(Request $request): JsonResponse
    {
        // Semua angka dihitung hanya dari tiket yang boleh dilihat user ini
        $base = Ticket::query()->visibleTo($request->user());

        // Jumlah per status (satu query, dikelompokkan di database)
        $statusCounts = (clone $base)->toBase()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        // Jumlah per prioritas
        $priorityCounts = (clone $base)->toBase()
            ->selectRaw('priority, count(*) as total')
            ->groupBy('priority')
            ->pluck('total', 'priority');

        // Pastikan semua status dan prioritas muncul, walau jumlahnya 0
        $byStatus = collect(TicketStatus::cases())
            ->mapWithKeys(fn ($s) => [$s->value => (int) $statusCounts->get($s->value, 0)]);

        $byPriority = collect(TicketPriority::cases())
            ->mapWithKeys(fn ($p) => [$p->value => (int) $priorityCounts->get($p->value, 0)]);

        // Jumlah per kategori, terbanyak dulu
        $byCategory = (clone $base)
            ->leftJoin('categories', 'categories.id', '=', 'tickets.category_id')
            ->toBase()
            ->selectRaw('categories.name as name, count(*) as total')
            ->groupBy('categories.name')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row) => [
                'category' => $row->name ?? 'Tanpa kategori',
                'total' => (int) $row->total,
            ])
            ->values();

        // Tiket open yang belum ada penanganannya
        $unassigned = (clone $base)
            ->where('status', TicketStatus::Open->value)
            ->whereNull('assigned_to')
            ->count();

        $createdLast7Days = (clone $base)
            ->where('created_at', '>=', now()->subDays(7))
            ->count();

        // Rata-rata lama penyelesaian (jam). Dihitung di PHP supaya jalan di MySQL maupun SQLite.
        $resolved = (clone $base)
            ->whereNotNull('resolved_at')
            ->get(['created_at', 'resolved_at']);

        $avgResolutionHours = $resolved->isEmpty()
            ? null
            : round(
                $resolved->avg(fn ($t) => $t->resolved_at->getTimestamp() - $t->created_at->getTimestamp()) / 3600,
                1
            );

        return response()->json([
            'data' => [
                'total' => $byStatus->sum(),
                'by_status' => $byStatus,
                'by_priority' => $byPriority,
                'by_category' => $byCategory,
                'unassigned' => $unassigned,
                'created_last_7_days' => $createdLast7Days,
                'avg_resolution_hours' => $avgResolutionHours,
            ],
        ]);
    }
}

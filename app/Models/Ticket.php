<?php

namespace App\Models;

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Ticket extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'title',
        'description',
        'status',
        'priority',
        'category_id',
        'created_by',
        'assigned_to',
        'resolved_at',
    ];

    protected $attributes = [
        'status' => 'open',
        'priority' => 'medium',
    ];

    protected $casts = [
        'status' => TicketStatus::class,
        'priority' => TicketPriority::class,
        'resolved_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        // Generate kode tiket otomatis, contoh: TKT-20261012-A1B2C3
        static::creating(function (Ticket $ticket) {
            if (empty($ticket->code)) {
                $ticket->code = 'TKT-'.now()->format('Ymd').'-'.strtoupper(Str::random(6));
            }
        });
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(Attachment::class);
    }

    public function histories(): HasMany
    {
        return $this->hasMany(TicketHistory::class);
    }

    /* ---------- Query scopes untuk filter di endpoint GET /tickets ---------- */

    public function scopeFilter(Builder $query, array $filters): Builder
    {
        return $query
            ->when($filters['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->when($filters['priority'] ?? null, fn ($q, $v) => $q->where('priority', $v))
            ->when($filters['category_id'] ?? null, fn ($q, $v) => $q->where('category_id', $v))
            ->when($filters['search'] ?? null, function ($q, $v) {
                $q->where(function ($sub) use ($v) {
                    $sub->where('title', 'like', "%{$v}%")
                        ->orWhere('code', 'like', "%{$v}%");
                });
            });
    }

    /**
     * Batasi query hanya ke tiket yang boleh dilihat oleh user ini.
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        $table = $query->getModel()->getTable();

        if ($user->hasRole('admin')) {
            return $query; // admin melihat semua tiket
        }

        if ($user->hasRole('agent')) {
            // agent: tiket yang di-assign ke dia + tiket yang masih open
            return $query->where(function ($q) use ($table, $user) {
                $q->where("{$table}.assigned_to", $user->id)
                    ->orWhere("{$table}.status", TicketStatus::Open->value);
            });
        }

        // user biasa: hanya tiket miliknya
        return $query->where("{$table}.created_by", $user->id);
    }
}

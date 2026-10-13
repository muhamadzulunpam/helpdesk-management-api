<?php

use App\Models\Category;
use App\Models\Ticket;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

function buatTiketStat(User $pemilik, array $attrs = []): Ticket
{
    return Ticket::create(array_merge([
        'title' => 'Tiket uji statistik',
        'description' => 'Deskripsi singkat.',
        'created_by' => $pemilik->id,
    ], $attrs));
}

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);

    $this->admin = User::where('email', 'admin@demo.test')->first();
    $this->agent = User::where('email', 'agent@demo.test')->first();
    $this->owner = User::where('email', 'user@demo.test')->first();
});

it('menolak tamu yang belum login', function () {
    $this->getJson('/api/v1/dashboard/stats')->assertUnauthorized();
});

it('menampilkan nol untuk semua status kalau belum ada tiket', function () {
    Sanctum::actingAs($this->admin);

    $this->getJson('/api/v1/dashboard/stats')
        ->assertOk()
        ->assertJsonPath('data.total', 0)
        ->assertJsonPath('data.by_status.open', 0)
        ->assertJsonPath('data.by_status.closed', 0)
        ->assertJsonPath('data.unassigned', 0)
        ->assertJsonPath('data.avg_resolution_hours', null);
});

it('admin melihat jumlah tiket per status dari semua tiket', function () {
    buatTiketStat($this->owner);
    buatTiketStat($this->owner);
    buatTiketStat($this->owner, ['status' => 'in_progress']);
    buatTiketStat(User::factory()->create(), ['status' => 'closed']);

    Sanctum::actingAs($this->admin);

    $this->getJson('/api/v1/dashboard/stats')
        ->assertOk()
        ->assertJsonPath('data.total', 4)
        ->assertJsonPath('data.by_status.open', 2)
        ->assertJsonPath('data.by_status.in_progress', 1)
        ->assertJsonPath('data.by_status.resolved', 0)
        ->assertJsonPath('data.by_status.closed', 1);
});

it('user biasa hanya menghitung tiketnya sendiri', function () {
    buatTiketStat($this->owner);
    buatTiketStat($this->owner);

    $orangLain = User::factory()->create();
    buatTiketStat($orangLain);
    buatTiketStat($orangLain);
    buatTiketStat($orangLain);

    Sanctum::actingAs($this->owner);

    $this->getJson('/api/v1/dashboard/stats')
        ->assertOk()
        ->assertJsonPath('data.total', 2);
});

it('agent menghitung tiket open dan tiket miliknya saja', function () {
    $agentLain = User::factory()->create();
    $agentLain->assignRole('agent');

    buatTiketStat($this->owner); // open: terlihat
    buatTiketStat($this->owner, ['status' => 'in_progress', 'assigned_to' => $this->agent->id]); // milik agent: terlihat
    buatTiketStat($this->owner, ['status' => 'in_progress', 'assigned_to' => $agentLain->id]); // milik agent lain: tidak
    buatTiketStat($this->owner, ['status' => 'closed']); // closed tanpa assign: tidak

    Sanctum::actingAs($this->agent);

    $this->getJson('/api/v1/dashboard/stats')
        ->assertOk()
        ->assertJsonPath('data.total', 2);
});

it('menghitung tiket open yang belum di-assign', function () {
    buatTiketStat($this->owner); // open, belum di-assign: dihitung
    buatTiketStat($this->owner, ['assigned_to' => $this->agent->id]); // open, sudah di-assign: tidak
    buatTiketStat($this->owner, ['status' => 'in_progress']); // bukan open: tidak

    Sanctum::actingAs($this->admin);

    $this->getJson('/api/v1/dashboard/stats')
        ->assertOk()
        ->assertJsonPath('data.unassigned', 1);
});

it('menghitung jumlah tiket per prioritas dan per kategori', function () {
    $kategori = Category::first();

    buatTiketStat($this->owner, ['priority' => 'high', 'category_id' => $kategori->id]);
    buatTiketStat($this->owner, ['priority' => 'high', 'category_id' => $kategori->id]);
    buatTiketStat($this->owner); // prioritas medium (bawaan), tanpa kategori

    Sanctum::actingAs($this->admin);

    $this->getJson('/api/v1/dashboard/stats')
        ->assertOk()
        ->assertJsonPath('data.by_priority.high', 2)
        ->assertJsonPath('data.by_priority.medium', 1)
        ->assertJsonPath('data.by_priority.urgent', 0)
        ->assertJsonPath('data.by_category.0.category', $kategori->name)
        ->assertJsonPath('data.by_category.0.total', 2)
        ->assertJsonPath('data.by_category.1.category', 'Tanpa kategori')
        ->assertJsonPath('data.by_category.1.total', 1);
});

it('menghitung tiket yang dibuat dalam 7 hari terakhir', function () {
    buatTiketStat($this->owner); // baru dibuat

    $lama = buatTiketStat($this->owner);
    $lama->forceFill(['created_at' => now()->subDays(10)])->save();

    Sanctum::actingAs($this->admin);

    $this->getJson('/api/v1/dashboard/stats')
        ->assertOk()
        ->assertJsonPath('data.total', 2)
        ->assertJsonPath('data.created_last_7_days', 1);
});

it('menghitung rata-rata lama penyelesaian dalam jam', function () {
    $empatJam = buatTiketStat($this->owner, ['status' => 'resolved']);
    $empatJam->forceFill([
        'created_at' => now()->subHours(4),
        'resolved_at' => now(),
    ])->save();

    $duaJam = buatTiketStat($this->owner, ['status' => 'resolved']);
    $duaJam->forceFill([
        'created_at' => now()->subHours(2),
        'resolved_at' => now(),
    ])->save();

    Sanctum::actingAs($this->admin);

    $hasil = $this->getJson('/api/v1/dashboard/stats')->assertOk();

    expect($hasil->json('data.avg_resolution_hours'))->toEqual(3.0);
});
<?php

use App\Models\Category;
use App\Models\Ticket;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

function buatTiketUji(User $pemilik, array $attrs = []): Ticket
{
    return Ticket::create(array_merge([
        'title' => 'Tidak bisa login',
        'description' => 'Muncul error 500 saat login.',
        'created_by' => $pemilik->id,
    ], $attrs));
}

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);

    $this->admin = User::where('email', 'admin@demo.test')->first();
    $this->agent = User::where('email', 'agent@demo.test')->first();
    $this->owner = User::where('email', 'user@demo.test')->first();
});

it('user bisa membuat tiket baru', function () {
    Sanctum::actingAs($this->owner);

    $this->postJson('/api/v1/tickets', [
        'title' => 'Printer rusak',
        'description' => 'Printer lantai 2 tidak bisa mencetak.',
        'category_id' => Category::first()->id,
        'priority' => 'high',
    ])
        ->assertCreated()
        ->assertJsonPath('data.status', 'open')
        ->assertJsonPath('data.priority', 'high');

    $this->assertDatabaseHas('tickets', [
        'title' => 'Printer rusak',
        'created_by' => $this->owner->id,
    ]);
    $this->assertDatabaseHas('ticket_histories', ['to_status' => 'open']);
});

it('menolak pembuatan tiket tanpa judul', function () {
    Sanctum::actingAs($this->owner);

    $this->postJson('/api/v1/tickets', ['description' => 'Tanpa judul'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('title');
});

it('user hanya melihat tiket miliknya', function () {
    buatTiketUji($this->owner);
    buatTiketUji(User::factory()->create(), ['title' => 'Tiket orang lain']);

    Sanctum::actingAs($this->owner);

    $this->getJson('/api/v1/tickets')
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

it('user tidak bisa membuka tiket milik orang lain', function () {
    $tiket = buatTiketUji(User::factory()->create());

    Sanctum::actingAs($this->owner);

    $this->getJson("/api/v1/tickets/{$tiket->id}")->assertForbidden();
});

it('admin melihat semua tiket', function () {
    buatTiketUji($this->owner);
    buatTiketUji(User::factory()->create());

    Sanctum::actingAs($this->admin);

    $this->getJson('/api/v1/tickets')
        ->assertOk()
        ->assertJsonCount(2, 'data');
});

it('bisa memfilter tiket berdasarkan status dan pencarian', function () {
    buatTiketUji($this->owner, ['title' => 'Lupa password', 'status' => 'closed']);
    buatTiketUji($this->owner, ['title' => 'Printer rusak']);

    Sanctum::actingAs($this->admin);

    $this->getJson('/api/v1/tickets?status=closed')
        ->assertOk()
        ->assertJsonCount(1, 'data');

    $this->getJson('/api/v1/tickets?search=printer')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.title', 'Printer rusak');
});

it('user bisa mengubah tiketnya selama masih open', function () {
    $tiket = buatTiketUji($this->owner);

    Sanctum::actingAs($this->owner);

    $this->putJson("/api/v1/tickets/{$tiket->id}", ['title' => 'Judul baru'])
        ->assertOk()
        ->assertJsonPath('data.title', 'Judul baru');
});

it('agent bisa mengambil tiket open lalu mengubah statusnya', function () {
    $tiket = buatTiketUji($this->owner);

    Sanctum::actingAs($this->agent);

    $this->patchJson("/api/v1/tickets/{$tiket->id}/assign")
        ->assertOk()
        ->assertJsonPath('data.assignee.id', $this->agent->id);

    $this->patchJson("/api/v1/tickets/{$tiket->id}/status", ['status' => 'in_progress'])
        ->assertOk()
        ->assertJsonPath('data.status', 'in_progress');

    $this->assertDatabaseHas('ticket_histories', [
        'ticket_id' => $tiket->id,
        'from_status' => 'open',
        'to_status' => 'in_progress',
    ]);
});

it('agent tidak bisa mengubah status tiket yang belum di-assign', function () {
    $tiket = buatTiketUji($this->owner);

    Sanctum::actingAs($this->agent);

    $this->patchJson("/api/v1/tickets/{$tiket->id}/status", ['status' => 'in_progress'])
        ->assertForbidden();
});

it('menolak perubahan status yang tidak valid', function () {
    $tiket = buatTiketUji($this->owner, ['assigned_to' => $this->agent->id]);

    Sanctum::actingAs($this->agent);

    // open -> resolved tidak diizinkan (harus lewat in_progress dulu)
    $this->patchJson("/api/v1/tickets/{$tiket->id}/status", ['status' => 'resolved'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('status');
});

it('mengisi resolved_at saat tiket diselesaikan', function () {
    $tiket = buatTiketUji($this->owner, [
        'assigned_to' => $this->agent->id,
        'status' => 'in_progress',
    ]);

    Sanctum::actingAs($this->agent);

    $this->patchJson("/api/v1/tickets/{$tiket->id}/status", ['status' => 'resolved'])
        ->assertOk();

    expect($tiket->fresh()->resolved_at)->not->toBeNull();
});

it('admin bisa meng-assign tiket ke agent', function () {
    $tiket = buatTiketUji($this->owner);

    Sanctum::actingAs($this->admin);

    $this->patchJson("/api/v1/tickets/{$tiket->id}/assign", ['assigned_to' => $this->agent->id])
        ->assertOk()
        ->assertJsonPath('data.assignee.id', $this->agent->id);
});

it('hanya admin yang bisa menghapus tiket', function () {
    $tiket = buatTiketUji($this->owner);

    Sanctum::actingAs($this->owner);
    $this->deleteJson("/api/v1/tickets/{$tiket->id}")->assertForbidden();

    Sanctum::actingAs($this->admin);
    $this->deleteJson("/api/v1/tickets/{$tiket->id}")->assertNoContent();

    $this->assertDatabaseMissing('tickets', ['id' => $tiket->id]);
});

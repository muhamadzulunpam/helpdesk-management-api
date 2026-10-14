<?php

use App\Models\Comment;
use App\Models\Ticket;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

function buatTiketKomentar(User $pemilik, array $attrs = []): Ticket
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

    $this->tiket = buatTiketKomentar($this->owner);
});

it('menolak tamu yang belum login', function () {
    $this->getJson("/api/v1/tickets/{$this->tiket->id}/comments")->assertUnauthorized();
});

it('pemilik tiket bisa menambah komentar', function () {
    Sanctum::actingAs($this->owner);

    $this->postJson("/api/v1/tickets/{$this->tiket->id}/comments", [
        'body' => 'Error-nya muncul lagi tadi pagi.',
    ])
        ->assertCreated()
        ->assertJsonPath('data.body', 'Error-nya muncul lagi tadi pagi.')
        ->assertJsonPath('data.is_internal', false)
        ->assertJsonPath('data.author.id', $this->owner->id);

    $this->assertDatabaseHas('comments', [
        'ticket_id' => $this->tiket->id,
        'user_id' => $this->owner->id,
    ]);
});

it('menolak komentar tanpa isi', function () {
    Sanctum::actingAs($this->owner);

    $this->postJson("/api/v1/tickets/{$this->tiket->id}/comments", [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('body');
});

it('user lain tidak bisa mengomentari tiket yang bukan miliknya', function () {
    Sanctum::actingAs(User::factory()->create());

    $this->postJson("/api/v1/tickets/{$this->tiket->id}/comments", ['body' => 'Numpang komentar'])
        ->assertForbidden();
});

it('user biasa tidak bisa membuat catatan internal', function () {
    Sanctum::actingAs($this->owner);

    $this->postJson("/api/v1/tickets/{$this->tiket->id}/comments", [
        'body' => 'Catatan rahasia',
        'is_internal' => true,
    ])->assertForbidden();

    $this->assertDatabaseCount('comments', 0);
});

it('agent bisa membuat catatan internal', function () {
    Sanctum::actingAs($this->agent);

    $this->postJson("/api/v1/tickets/{$this->tiket->id}/comments", [
        'body' => 'Sepertinya masalah di server email.',
        'is_internal' => true,
    ])
        ->assertCreated()
        ->assertJsonPath('data.is_internal', true);
});

it('catatan internal hanya terlihat oleh agent dan admin', function () {
    Comment::create([
        'ticket_id' => $this->tiket->id,
        'user_id' => $this->owner->id,
        'body' => 'Komentar publik',
    ]);
    Comment::create([
        'ticket_id' => $this->tiket->id,
        'user_id' => $this->agent->id,
        'body' => 'Catatan internal',
        'is_internal' => true,
    ]);

    Sanctum::actingAs($this->owner);
    $this->getJson("/api/v1/tickets/{$this->tiket->id}/comments")
        ->assertOk()
        ->assertJsonCount(1, 'data');

    Sanctum::actingAs($this->agent);
    $this->getJson("/api/v1/tickets/{$this->tiket->id}/comments")
        ->assertOk()
        ->assertJsonCount(2, 'data');

    Sanctum::actingAs($this->admin);
    $this->getJson("/api/v1/tickets/{$this->tiket->id}/comments")
        ->assertOk()
        ->assertJsonCount(2, 'data');
});

it('menampilkan komentar dari yang paling lama', function () {
    Comment::create(['ticket_id' => $this->tiket->id, 'user_id' => $this->owner->id, 'body' => 'Pertama']);
    Comment::create(['ticket_id' => $this->tiket->id, 'user_id' => $this->owner->id, 'body' => 'Kedua']);

    Sanctum::actingAs($this->owner);

    $this->getJson("/api/v1/tickets/{$this->tiket->id}/comments")
        ->assertOk()
        ->assertJsonPath('data.0.body', 'Pertama')
        ->assertJsonPath('data.1.body', 'Kedua');
});

it('menolak komentar baru pada tiket yang sudah closed', function () {
    $tiketTutup = buatTiketKomentar($this->owner, ['status' => 'closed']);

    Sanctum::actingAs($this->owner);

    $this->postJson("/api/v1/tickets/{$tiketTutup->id}/comments", ['body' => 'Masih ada masalah'])
        ->assertStatus(409);

    $this->assertDatabaseCount('comments', 0);
});

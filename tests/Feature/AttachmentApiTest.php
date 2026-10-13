<?php

use App\Models\Attachment;
use App\Models\Comment;
use App\Models\Ticket;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

function buatTiketLampiran(User $pemilik, array $attrs = []): Ticket
{
    return Ticket::create(array_merge([
        'title' => 'Tidak bisa login',
        'description' => 'Muncul error 500 saat login.',
        'created_by' => $pemilik->id,
    ], $attrs));
}

function buatLampiran(Ticket $tiket, User $pengunggah, array $attrs = []): Attachment
{
    $path = "tickets/{$tiket->id}/" . uniqid() . '.txt';
    Storage::disk('local')->put($path, 'isi berkas');

    return Attachment::create(array_merge([
        'ticket_id' => $tiket->id,
        'uploaded_by' => $pengunggah->id,
        'path' => $path,
        'original_name' => 'catatan.txt',
        'mime' => 'text/plain',
        'size' => 10,
    ], $attrs));
}

beforeEach(function () {
    Storage::fake('local'); // file uji tidak menyentuh folder storage asli

    $this->seed(DatabaseSeeder::class);

    $this->admin = User::where('email', 'admin@demo.test')->first();
    $this->agent = User::where('email', 'agent@demo.test')->first();
    $this->owner = User::where('email', 'user@demo.test')->first();

    $this->tiket = buatTiketLampiran($this->owner);
});

it('menolak tamu yang belum login', function () {
    $this->getJson("/api/v1/tickets/{$this->tiket->id}/attachments")->assertUnauthorized();
});

it('pemilik tiket bisa mengunggah lampiran', function () {
    Sanctum::actingAs($this->owner);

    $this->postJson("/api/v1/tickets/{$this->tiket->id}/attachments", [
        'file' => UploadedFile::fake()->create('laporan.pdf', 200, 'application/pdf'),
    ])
        ->assertCreated()
        ->assertJsonPath('data.original_name', 'laporan.pdf')
        ->assertJsonPath('data.mime', 'application/pdf');

    $lampiran = Attachment::first();

    Storage::disk('local')->assertExists($lampiran->path);
    expect($lampiran->uploaded_by)->toEqual($this->owner->id);
});

it('menolak tipe file yang tidak diizinkan', function () {
    Sanctum::actingAs($this->owner);

    $this->postJson("/api/v1/tickets/{$this->tiket->id}/attachments", [
        'file' => UploadedFile::fake()->create('virus.exe', 10),
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('file');

    $this->assertDatabaseCount('attachments', 0);
});

it('menolak file yang lebih dari 5 MB', function () {
    Sanctum::actingAs($this->owner);

    $this->postJson("/api/v1/tickets/{$this->tiket->id}/attachments", [
        'file' => UploadedFile::fake()->create('besar.pdf', 6000, 'application/pdf'),
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('file');
});

it('user lain tidak bisa mengunggah ke tiket yang bukan miliknya', function () {
    Sanctum::actingAs(User::factory()->create());

    $this->postJson("/api/v1/tickets/{$this->tiket->id}/attachments", [
        'file' => UploadedFile::fake()->create('laporan.pdf', 100, 'application/pdf'),
    ])->assertForbidden();
});

it('menolak lampiran baru pada tiket yang sudah closed', function () {
    $tiketTutup = buatTiketLampiran($this->owner, ['status' => 'closed']);

    Sanctum::actingAs($this->owner);

    $this->postJson("/api/v1/tickets/{$tiketTutup->id}/attachments", [
        'file' => UploadedFile::fake()->create('laporan.pdf', 100, 'application/pdf'),
    ])->assertStatus(409);

    $this->assertDatabaseCount('attachments', 0);
});

it('bisa menautkan lampiran ke komentar milik sendiri', function () {
    $komentar = Comment::create([
        'ticket_id' => $this->tiket->id,
        'user_id' => $this->owner->id,
        'body' => 'Ini screenshot-nya.',
    ]);

    Sanctum::actingAs($this->owner);

    $this->postJson("/api/v1/tickets/{$this->tiket->id}/attachments", [
        'file' => UploadedFile::fake()->image('screenshot.png'),
        'comment_id' => $komentar->id,
    ])
        ->assertCreated()
        ->assertJsonPath('data.comment_id', $komentar->id);
});

it('menolak menautkan lampiran ke komentar milik orang lain', function () {
    $komentarAgent = Comment::create([
        'ticket_id' => $this->tiket->id,
        'user_id' => $this->agent->id,
        'body' => 'Komentar agent.',
    ]);

    Sanctum::actingAs($this->owner);

    $this->postJson("/api/v1/tickets/{$this->tiket->id}/attachments", [
        'file' => UploadedFile::fake()->image('screenshot.png'),
        'comment_id' => $komentarAgent->id,
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('comment_id');
});

it('pemilik tiket bisa mengunduh lampiran', function () {
    $lampiran = buatLampiran($this->tiket, $this->owner);

    Sanctum::actingAs($this->owner);

    $this->getJson("/api/v1/attachments/{$lampiran->id}/download")
        ->assertOk()
        ->assertDownload('catatan.txt');
});

it('user lain tidak bisa mengunduh lampiran', function () {
    $lampiran = buatLampiran($this->tiket, $this->owner);

    Sanctum::actingAs(User::factory()->create());

    $this->getJson("/api/v1/attachments/{$lampiran->id}/download")->assertForbidden();
});

it('lampiran pada catatan internal hanya bisa diunduh agent dan admin', function () {
    $catatan = Comment::create([
        'ticket_id' => $this->tiket->id,
        'user_id' => $this->agent->id,
        'body' => 'Catatan internal.',
        'is_internal' => true,
    ]);
    $lampiran = buatLampiran($this->tiket, $this->agent, ['comment_id' => $catatan->id]);

    Sanctum::actingAs($this->owner);
    $this->getJson("/api/v1/attachments/{$lampiran->id}/download")->assertForbidden();

    Sanctum::actingAs($this->agent);
    $this->getJson("/api/v1/attachments/{$lampiran->id}/download")->assertOk();
});

it('daftar lampiran menyembunyikan lampiran catatan internal dari user biasa', function () {
    $catatan = Comment::create([
        'ticket_id' => $this->tiket->id,
        'user_id' => $this->agent->id,
        'body' => 'Catatan internal.',
        'is_internal' => true,
    ]);
    buatLampiran($this->tiket, $this->owner);
    buatLampiran($this->tiket, $this->agent, ['comment_id' => $catatan->id]);

    Sanctum::actingAs($this->owner);
    $this->getJson("/api/v1/tickets/{$this->tiket->id}/attachments")
        ->assertOk()
        ->assertJsonCount(1, 'data');

    Sanctum::actingAs($this->agent);
    $this->getJson("/api/v1/tickets/{$this->tiket->id}/attachments")
        ->assertOk()
        ->assertJsonCount(2, 'data');
});

it('pengunggah bisa menghapus lampirannya beserta file-nya', function () {
    $lampiran = buatLampiran($this->tiket, $this->owner);

    Sanctum::actingAs($this->owner);

    $this->deleteJson("/api/v1/attachments/{$lampiran->id}")->assertNoContent();

    $this->assertDatabaseMissing('attachments', ['id' => $lampiran->id]);
    Storage::disk('local')->assertMissing($lampiran->path);
});

it('bukan pengunggah tidak bisa menghapus lampiran', function () {
    $lampiran = buatLampiran($this->tiket, $this->owner);

    Sanctum::actingAs($this->agent);

    $this->deleteJson("/api/v1/attachments/{$lampiran->id}")->assertForbidden();

    $this->assertDatabaseHas('attachments', ['id' => $lampiran->id]);
});

it('admin bisa menghapus lampiran siapa pun', function () {
    $lampiran = buatLampiran($this->tiket, $this->owner);

    Sanctum::actingAs($this->admin);

    $this->deleteJson("/api/v1/attachments/{$lampiran->id}")->assertNoContent();
});
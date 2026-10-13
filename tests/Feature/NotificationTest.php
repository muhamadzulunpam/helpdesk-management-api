<?php

use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Models\User;
use App\Notifications\TicketAssigned;
use App\Notifications\TicketCommentAdded;
use App\Notifications\TicketStatusChanged;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

function buatTiketNotif(User $pemilik, array $attrs = []): Ticket
{
    return Ticket::create(array_merge([
        'title' => 'Tidak bisa login',
        'description' => 'Muncul error 500 saat login.',
        'created_by' => $pemilik->id,
    ], $attrs));
}

beforeEach(function () {
    Notification::fake(); // tidak ada email sungguhan yang terkirim saat test

    $this->seed(DatabaseSeeder::class);

    $this->admin = User::where('email', 'admin@demo.test')->first();
    $this->agent = User::where('email', 'agent@demo.test')->first();
    $this->owner = User::where('email', 'user@demo.test')->first();
});

/* ---------- Status tiket berubah ---------- */

it('mengirim email ke pembuat tiket saat status berubah', function () {
    $tiket = buatTiketNotif($this->owner, ['assigned_to' => $this->agent->id]);

    Sanctum::actingAs($this->agent);

    $this->patchJson("/api/v1/tickets/{$tiket->id}/status", ['status' => 'in_progress'])
        ->assertOk();

    Notification::assertSentTo(
        $this->owner,
        TicketStatusChanged::class,
        fn ($notif) => $notif->from === TicketStatus::Open && $notif->to === TicketStatus::InProgress
    );
});

it('tidak mengirim email kalau perubahan status ditolak', function () {
    $tiket = buatTiketNotif($this->owner, ['assigned_to' => $this->agent->id]);

    Sanctum::actingAs($this->agent);

    // open -> resolved tidak diizinkan
    $this->patchJson("/api/v1/tickets/{$tiket->id}/status", ['status' => 'resolved'])
        ->assertUnprocessable();

    Notification::assertNothingSent();
});

it('tidak mengirim email ke diri sendiri saat mengubah status tiket miliknya', function () {
    $tiket = buatTiketNotif($this->admin);

    Sanctum::actingAs($this->admin);

    $this->patchJson("/api/v1/tickets/{$tiket->id}/status", ['status' => 'in_progress'])
        ->assertOk();

    Notification::assertNothingSent();
});

/* ---------- Tiket ditugaskan ---------- */

it('mengirim email ke agent saat admin menugaskan tiket', function () {
    $tiket = buatTiketNotif($this->owner);

    Sanctum::actingAs($this->admin);

    $this->patchJson("/api/v1/tickets/{$tiket->id}/assign", ['assigned_to' => $this->agent->id])
        ->assertOk();

    Notification::assertSentTo($this->agent, TicketAssigned::class);
});

it('tidak mengirim email kalau agent mengambil tiket untuk dirinya sendiri', function () {
    $tiket = buatTiketNotif($this->owner);

    Sanctum::actingAs($this->agent);

    $this->patchJson("/api/v1/tickets/{$tiket->id}/assign")->assertOk();

    Notification::assertNothingSent();
});

it('tidak mengirim email lagi kalau tiket ditugaskan ke agent yang sama', function () {
    $tiket = buatTiketNotif($this->owner, ['assigned_to' => $this->agent->id]);

    Sanctum::actingAs($this->admin);

    $this->patchJson("/api/v1/tickets/{$tiket->id}/assign", ['assigned_to' => $this->agent->id])
        ->assertOk();

    Notification::assertNothingSent();
});

/* ---------- Komentar baru ---------- */

it('mengirim email ke pembuat tiket saat staf membalas', function () {
    $tiket = buatTiketNotif($this->owner);

    Sanctum::actingAs($this->agent);

    $this->postJson("/api/v1/tickets/{$tiket->id}/comments", ['body' => 'Sedang kami cek.'])
        ->assertCreated();

    Notification::assertSentTo($this->owner, TicketCommentAdded::class);
    Notification::assertNotSentTo($this->agent, TicketCommentAdded::class);
});

it('mengirim email ke agent yang menangani saat pembuat tiket berkomentar', function () {
    $tiket = buatTiketNotif($this->owner);

    Sanctum::actingAs($this->owner);

    // belum ada agent yang menangani: tidak ada yang perlu dikabari
    $this->postJson("/api/v1/tickets/{$tiket->id}/comments", ['body' => 'Halo, ada yang bisa bantu?'])
        ->assertCreated();

    Notification::assertNothingSent();

    $tiket->update(['assigned_to' => $this->agent->id]);

    $this->postJson("/api/v1/tickets/{$tiket->id}/comments", ['body' => 'Ada kabar terbaru?'])
        ->assertCreated();

    Notification::assertSentTo($this->agent, TicketCommentAdded::class);
});

it('tidak mengirim email untuk catatan internal', function () {
    $tiket = buatTiketNotif($this->owner, ['assigned_to' => $this->agent->id]);

    Sanctum::actingAs($this->agent);

    $this->postJson("/api/v1/tickets/{$tiket->id}/comments", [
        'body' => 'Catatan rahasia untuk tim.',
        'is_internal' => true,
    ])->assertCreated();

    Notification::assertNothingSent();
});

/* ---------- Isi email dan queue ---------- */

it('mengirim notifikasi lewat queue dengan isi email yang sesuai', function () {
    $tiket = buatTiketNotif($this->owner);

    $notif = new TicketStatusChanged($tiket, TicketStatus::Open, TicketStatus::InProgress);

    // implements ShouldQueue = diproses di belakang layar oleh queue worker
    expect($notif)->toBeInstanceOf(ShouldQueue::class);

    $mail = $notif->toMail($this->owner);

    expect($mail->subject)->toContain($tiket->code)
        ->and($mail->introLines[0])->toContain('Terbuka', 'Sedang diproses')
        ->and($mail->actionUrl)->toContain("/tickets/{$tiket->id}");
});
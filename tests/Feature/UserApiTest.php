<?php

use App\Models\Comment;
use App\Models\Ticket;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

function buatUserUji(string $role): User
{
    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);

    $this->admin = User::where('email', 'admin@demo.test')->first();
    $this->agent = User::where('email', 'agent@demo.test')->first();
    $this->owner = User::where('email', 'user@demo.test')->first();
});

it('menolak tamu yang belum login', function () {
    $this->getJson('/api/v1/users')->assertUnauthorized();
});

it('user biasa dan agent tidak boleh mengelola user', function () {
    Sanctum::actingAs($this->owner);
    $this->getJson('/api/v1/users')->assertForbidden();

    Sanctum::actingAs($this->agent);
    $this->getJson('/api/v1/users')->assertForbidden();
    $this->postJson('/api/v1/users', [
        'name' => 'Baru',
        'email' => 'baru@example.com',
        'password' => 'rahasia123',
        'role' => 'agent',
    ])->assertForbidden();
});

it('admin bisa melihat daftar user beserta role-nya', function () {
    Sanctum::actingAs($this->admin);

    $this->getJson('/api/v1/users')
        ->assertOk()
        ->assertJsonCount(3, 'data')
        ->assertJsonStructure(['data' => [['id', 'name', 'email', 'roles']]]);
});

it('bisa memfilter daftar user berdasarkan role dan pencarian', function () {
    Sanctum::actingAs($this->admin);

    $this->getJson('/api/v1/users?role=agent')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.email', 'agent@demo.test');

    $this->getJson('/api/v1/users?search=user@demo')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.email', 'user@demo.test');
});

it('menolak filter role yang tidak dikenal', function () {
    Sanctum::actingAs($this->admin);

    $this->getJson('/api/v1/users?role=bos')
        ->assertUnprocessable()
        ->assertJsonValidationErrors('role');
});

it('admin bisa menambah user baru dengan role tertentu', function () {
    Sanctum::actingAs($this->admin);

    $this->postJson('/api/v1/users', [
        'name' => 'Agent Baru',
        'email' => 'agentbaru@example.com',
        'password' => 'rahasia123',
        'role' => 'agent',
    ])
        ->assertCreated()
        ->assertJsonPath('data.email', 'agentbaru@example.com')
        ->assertJsonPath('data.roles.0', 'agent');

    $user = User::where('email', 'agentbaru@example.com')->first();

    expect($user->hasRole('agent'))->toBeTrue()
        ->and(Hash::check('rahasia123', $user->password))->toBeTrue();
});

it('menolak penambahan user dengan email kembar atau role tidak valid', function () {
    Sanctum::actingAs($this->admin);

    $this->postJson('/api/v1/users', [
        'name' => 'Kembar',
        'email' => 'user@demo.test',
        'password' => 'rahasia123',
        'role' => 'agent',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('email');

    $this->postJson('/api/v1/users', [
        'name' => 'Salah Role',
        'email' => 'salahrole@example.com',
        'password' => 'rahasia123',
        'role' => 'bos',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('role');
});

it('admin bisa melihat detail satu user', function () {
    Sanctum::actingAs($this->admin);

    $this->getJson("/api/v1/users/{$this->agent->id}")
        ->assertOk()
        ->assertJsonPath('data.email', 'agent@demo.test')
        ->assertJsonPath('data.roles.0', 'agent');
});

it('admin bisa mengubah nama dan email, termasuk menyimpan dengan email yang sama', function () {
    Sanctum::actingAs($this->admin);

    $this->putJson("/api/v1/users/{$this->owner->id}", ['name' => 'Nama Baru'])
        ->assertOk()
        ->assertJsonPath('data.name', 'Nama Baru');

    // email sendiri tidak boleh dianggap kembar
    $this->putJson("/api/v1/users/{$this->owner->id}", ['email' => 'user@demo.test'])
        ->assertOk();

    // email milik orang lain ditolak
    $this->putJson("/api/v1/users/{$this->owner->id}", ['email' => 'agent@demo.test'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('email');
});

it('mengganti password mencabut semua sesi login lama user itu', function () {
    $this->owner->createToken('lama');
    $this->assertDatabaseCount('personal_access_tokens', 1);

    Sanctum::actingAs($this->admin);

    $this->putJson("/api/v1/users/{$this->owner->id}", ['password' => 'passwordbaru123'])
        ->assertOk();

    $this->assertDatabaseCount('personal_access_tokens', 0);
    expect(Hash::check('passwordbaru123', $this->owner->fresh()->password))->toBeTrue();
});

it('admin bisa mengubah role user', function () {
    Sanctum::actingAs($this->admin);

    $this->patchJson("/api/v1/users/{$this->owner->id}/role", ['role' => 'agent'])
        ->assertOk()
        ->assertJsonPath('data.roles.0', 'agent');

    $segar = $this->owner->fresh();

    expect($segar->hasRole('agent'))->toBeTrue()
        ->and($segar->hasRole('user'))->toBeFalse();
});

it('menolak role yang tidak valid', function () {
    Sanctum::actingAs($this->admin);

    $this->patchJson("/api/v1/users/{$this->owner->id}/role", ['role' => 'bos'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('role');
});

it('tidak bisa menurunkan admin yang terakhir', function () {
    Sanctum::actingAs($this->admin);

    $this->patchJson("/api/v1/users/{$this->admin->id}/role", ['role' => 'agent'])
        ->assertStatus(409);

    expect($this->admin->fresh()->hasRole('admin'))->toBeTrue();
});

it('bisa menurunkan admin kalau masih ada admin lain', function () {
    $adminLain = buatUserUji('admin');

    Sanctum::actingAs($this->admin);

    $this->patchJson("/api/v1/users/{$adminLain->id}/role", ['role' => 'user'])
        ->assertOk();

    expect($adminLain->fresh()->hasRole('user'))->toBeTrue();
});

it('admin bisa menghapus user yang belum punya riwayat', function () {
    $user = buatUserUji('user');

    Sanctum::actingAs($this->admin);

    $this->deleteJson("/api/v1/users/{$user->id}")->assertNoContent();

    $this->assertDatabaseMissing('users', ['id' => $user->id]);
});

it('tidak bisa menghapus akun sendiri', function () {
    Sanctum::actingAs($this->admin);

    $this->deleteJson("/api/v1/users/{$this->admin->id}")->assertStatus(409);

    $this->assertDatabaseHas('users', ['id' => $this->admin->id]);
});

it('tidak bisa menghapus user yang sudah punya riwayat aktivitas', function () {
    $tiket = Ticket::create([
        'title' => 'Tidak bisa login',
        'description' => 'Muncul error 500 saat login.',
        'created_by' => $this->owner->id,
    ]);
    Comment::create([
        'ticket_id' => $tiket->id,
        'user_id' => $this->agent->id,
        'body' => 'Sedang dicek.',
    ]);

    Sanctum::actingAs($this->admin);

    // pernah membuat tiket
    $this->deleteJson("/api/v1/users/{$this->owner->id}")->assertStatus(409);

    // pernah menulis komentar
    $this->deleteJson("/api/v1/users/{$this->agent->id}")->assertStatus(409);

    $this->assertDatabaseHas('users', ['id' => $this->owner->id]);
    $this->assertDatabaseHas('users', ['id' => $this->agent->id]);
});

it('menghapus agent membuat tiket yang ditanganinya kembali tanpa penanggung jawab', function () {
    $agentBaru = buatUserUji('agent');

    $tiket = Ticket::create([
        'title' => 'Printer rusak',
        'description' => 'Tidak bisa mencetak.',
        'created_by' => $this->owner->id,
        'assigned_to' => $agentBaru->id,
    ]);

    Sanctum::actingAs($this->admin);

    $this->deleteJson("/api/v1/users/{$agentBaru->id}")->assertNoContent();

    expect($tiket->fresh()->assigned_to)->toBeNull();
});
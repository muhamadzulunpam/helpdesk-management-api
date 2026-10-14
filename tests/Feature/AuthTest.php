<?php

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
});

it('bisa register dan langsung mendapat token', function () {
    $this->postJson('/api/v1/auth/register', [
        'name' => 'Budi',
        'email' => 'budi@example.com',
        'password' => 'rahasia123',
        'password_confirmation' => 'rahasia123',
    ])
        ->assertCreated()
        ->assertJsonStructure(['user' => ['id', 'name', 'email', 'roles'], 'token']);

    expect(User::where('email', 'budi@example.com')->first()->hasRole('user'))->toBeTrue();
});

it('menolak register dengan email yang sudah dipakai', function () {
    $this->postJson('/api/v1/auth/register', [
        'name' => 'Duplikat',
        'email' => 'user@demo.test',
        'password' => 'rahasia123',
        'password_confirmation' => 'rahasia123',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('email');
});

it('bisa login dengan akun demo', function () {
    $this->postJson('/api/v1/auth/login', [
        'email' => 'user@demo.test',
        'password' => 'password',
    ])
        ->assertOk()
        ->assertJsonStructure(['user', 'token']);
});

it('menolak login dengan password salah', function () {
    $this->postJson('/api/v1/auth/login', [
        'email' => 'user@demo.test',
        'password' => 'salah-besar',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('email');
});

it('bisa melihat profil dan logout', function () {
    $token = $this->postJson('/api/v1/auth/login', [
        'email' => 'user@demo.test',
        'password' => 'password',
    ])->json('token');

    $this->withToken($token)
        ->getJson('/api/v1/auth/me')
        ->assertOk()
        ->assertJsonPath('data.email', 'user@demo.test');

    $this->withToken($token)
        ->postJson('/api/v1/auth/logout')
        ->assertOk();

    $this->assertDatabaseCount('personal_access_tokens', 0);
});

it('menolak akses profil tanpa token', function () {
    $this->getJson('/api/v1/auth/me')->assertUnauthorized();
});

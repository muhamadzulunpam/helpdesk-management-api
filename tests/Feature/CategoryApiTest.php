<?php

use App\Models\Category;
use App\Models\Ticket;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);

    $this->admin = User::where('email', 'admin@demo.test')->first();
    $this->owner = User::where('email', 'user@demo.test')->first();
});

it('menolak tamu yang belum login', function () {
    $this->getJson('/api/v1/categories')->assertUnauthorized();
});

it('user biasa bisa melihat daftar kategori', function () {
    Sanctum::actingAs($this->owner);

    $this->getJson('/api/v1/categories')
        ->assertOk()
        ->assertJsonCount(5, 'data');
});

it('admin bisa menambah kategori', function () {
    Sanctum::actingAs($this->admin);

    $this->postJson('/api/v1/categories', ['name' => 'Hardware'])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Hardware');

    $this->assertDatabaseHas('categories', ['name' => 'Hardware']);
});

it('user biasa tidak bisa menambah kategori', function () {
    Sanctum::actingAs($this->owner);

    $this->postJson('/api/v1/categories', ['name' => 'Hardware'])->assertForbidden();
});

it('menolak nama kategori yang kembar', function () {
    Sanctum::actingAs($this->admin);

    $this->postJson('/api/v1/categories', ['name' => 'Lainnya'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('name');
});

it('admin bisa mengubah kategori, termasuk menyimpan dengan nama yang sama', function () {
    $kategori = Category::create(['name' => 'Jaringan']);

    Sanctum::actingAs($this->admin);

    $this->putJson("/api/v1/categories/{$kategori->id}", ['name' => 'Jaringan & Wifi'])
        ->assertOk()
        ->assertJsonPath('data.name', 'Jaringan & Wifi');

    // menyimpan ulang dengan nama yang sama tidak boleh dianggap kembar
    $this->putJson("/api/v1/categories/{$kategori->id}", ['name' => 'Jaringan & Wifi'])
        ->assertOk();
});

it('admin bisa menghapus kategori yang kosong', function () {
    $kategori = Category::create(['name' => 'Sementara']);

    Sanctum::actingAs($this->admin);

    $this->deleteJson("/api/v1/categories/{$kategori->id}")->assertNoContent();

    $this->assertDatabaseMissing('categories', ['id' => $kategori->id]);
});

it('menolak menghapus kategori yang masih dipakai tiket', function () {
    $kategori = Category::first();

    Ticket::create([
        'title' => 'Tidak bisa login',
        'description' => 'Muncul error 500 saat login.',
        'created_by' => $this->owner->id,
        'category_id' => $kategori->id,
    ]);

    Sanctum::actingAs($this->admin);

    $this->deleteJson("/api/v1/categories/{$kategori->id}")->assertStatus(409);

    $this->assertDatabaseHas('categories', ['id' => $kategori->id]);
});
<?php

use App\Models\Category;
use App\Models\Ticket;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);

    $this->admin = User::where('email', 'admin@demo.test')->first();
    $this->agent = User::where('email', 'agent@demo.test')->first();
    $this->owner = User::where('email', 'user@demo.test')->first();

    $this->other = User::factory()->create();
    $this->other->assignRole('user');

    $this->ticket = Ticket::create([
        'title' => 'Tidak bisa login',
        'description' => 'Muncul error 500 saat login.',
        'created_by' => $this->owner->id,
        'category_id' => Category::first()->id,
    ]);
});

it('mengizinkan pemilik melihat tiketnya sendiri', function () {
    expect($this->owner->can('view', $this->ticket))->toBeTrue();
});

it('melarang user lain melihat tiket bukan miliknya', function () {
    expect($this->other->can('view', $this->ticket))->toBeFalse();
});

it('mengizinkan agent melihat tiket yang masih open', function () {
    expect($this->agent->can('view', $this->ticket))->toBeTrue();
});

it('melarang agent mengubah status tiket yang bukan miliknya', function () {
    expect($this->agent->can('changeStatus', $this->ticket))->toBeFalse();
});

it('mengizinkan agent mengubah status setelah tiket di-assign', function () {
    $this->ticket->update(['assigned_to' => $this->agent->id]);

    expect($this->agent->can('changeStatus', $this->ticket))->toBeTrue();
});

it('hanya admin yang boleh menghapus tiket', function () {
    expect($this->admin->can('delete', $this->ticket))->toBeTrue()
        ->and($this->owner->can('delete', $this->ticket))->toBeFalse()
        ->and($this->agent->can('delete', $this->ticket))->toBeFalse();
});

<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Role (guard sanctum memakai guard "web" secara default di spatie)
        foreach (['admin', 'agent', 'user'] as $role) {
            Role::findOrCreate($role, 'web');
        }

        // Kategori contoh
        foreach (['Akun & Login', 'Bug / Error', 'Permintaan Fitur', 'Penagihan', 'Lainnya'] as $name) {
            Category::firstOrCreate(['name' => $name]);
        }

        // Akun demo untuk recruiter (cantumkan di README)
        $demo = [
            ['Admin Demo', 'admin@demo.test', 'admin'],
            ['Agent Demo', 'agent@demo.test', 'agent'],
            ['User Demo', 'user@demo.test', 'user'],
        ];

        foreach ($demo as [$name, $email, $role]) {
            $user = User::firstOrCreate(
                ['email' => $email],
                ['name' => $name, 'password' => Hash::make('password')]
            );
            $user->syncRoles([$role]);
        }
    }
}

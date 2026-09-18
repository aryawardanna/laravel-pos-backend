<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $users = [
            [
                'name'     => 'Admin Utama',
                'email'    => 'admin@example.com',
                'password' => '12345678',
                'role'     => 'admin',
            ],
            [
                'name'     => 'Kasir Satu',
                'email'    => 'kasir1@example.com',
                'password' => '12345678',
                'role'     => 'staff',
            ],
            [
                'name'     => 'Kasir Dua',
                'email'    => 'kasir2@example.com',
                'password' => '12345678',
                'role'     => 'staff',
            ],
            [
                'name'     => 'Pengguna Satu',
                'email'    => 'user1@example.com',
                'password' => '12345678',
                'role'     => 'user',
            ],
            [
                'name'     => 'Pengguna Dua',
                'email'    => 'user2@example.com',
                'password' => '12345678',
                'role'     => 'user',
            ],
        ];

        foreach ($users as $user) {
            User::updateOrCreate(
                ['email' => $user['email']],
                [
                    'name'     => $user['name'],
                    'password' => Hash::make($user['password']),
                    'role'     => $user['role'],
                ]
            );
        }
    }
}

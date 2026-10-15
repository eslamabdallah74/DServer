<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $adminUsername = env('ADMIN_USERNAME', 'admin');
        $adminEmail = env('ADMIN_EMAIL', 'admin@offline.local');
        $adminPassword = env('ADMIN_PASSWORD', 'deceit74');

        User::updateOrCreate(
            ['username' => $adminUsername],
            [
                'name' => 'System Admin',
                'email' => $adminEmail,
                'password' => bcrypt($adminPassword),
                'role' => 'admin',
                'coins' => 10000,
                'is_banned' => false,
            ]
        );
    }
}

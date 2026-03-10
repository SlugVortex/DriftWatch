<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $users = [
            [
                'name' => 'Admin User',
                'email' => 'admin@driftwatch.dev',
                'password' => 'password',
                'role' => 'admin',
                'avatar_color' => '#605DFF',
            ],
            [
                'name' => 'Sarah Chen',
                'email' => 'sarah@driftwatch.dev',
                'password' => 'password',
                'role' => 'reviewer',
                'avatar_color' => '#E91E63',
            ],
            [
                'name' => 'James Wilson',
                'email' => 'james@driftwatch.dev',
                'password' => 'password',
                'role' => 'reviewer',
                'avatar_color' => '#FF9800',
            ],
            [
                'name' => 'Demo Viewer',
                'email' => 'viewer@driftwatch.dev',
                'password' => 'password',
                'role' => 'viewer',
                'avatar_color' => '#4CAF50',
            ],
        ];

        foreach ($users as $userData) {
            User::updateOrCreate(
                ['email' => $userData['email']],
                $userData,
            );
        }
    }
}

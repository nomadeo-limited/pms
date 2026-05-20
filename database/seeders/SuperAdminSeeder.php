<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::firstOrCreate(
            ['email' => env('SUPER_ADMIN_EMAIL', 'admin@nomadeo.es')],
            [
                'name' => env('SUPER_ADMIN_NAME', 'Nomadeo Admin'),
                'password' => bcrypt(env('SUPER_ADMIN_PASSWORD', 'secret')),
                'email_verified_at' => now(),
                'is_active' => true,
            ]
        );

        $user->assignRole('super_admin');
    }
}

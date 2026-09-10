<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use LogicException;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        $email = config('reporting.seed_admin.email');
        $password = config('reporting.seed_admin.password');

        if (blank($email) || blank($password)) {
            throw new LogicException(
                'Isi SIBLU_SEED_ADMIN_EMAIL dan SIBLU_SEED_ADMIN_PASSWORD sebelum menjalankan seeder.',
            );
        }

        User::updateOrCreate(
            ['email' => $email],
            [
                'name' => 'Super Admin',
                'username' => 'superadmin',
                'password' => $password,
                'role' => 'ADMIN',
                'unit_id' => null,
                'active' => true,
            ],
        );
    }
}

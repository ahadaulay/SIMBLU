<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class AssignReportingAdmin extends Command
{
    protected $signature = 'reporting:admin {email : Email akun yang akan menjadi admin SIBLU}';

    protected $description = 'Assign the reporting administrator role to an existing account';

    public function handle(): int
    {
        $user = User::where('email', $this->argument('email'))->first();
        if (! $user) {
            $this->error('Akun tidak ditemukan.');

            return self::FAILURE;
        }
        if (! $this->confirm('Tetapkan '.$user->email.' sebagai admin SIBLU?')) {
            return self::FAILURE;
        }
        $user->update(['role' => 'ADMIN', 'active' => true, 'unit_id' => null]);
        $this->info('Admin SIBLU berhasil ditetapkan.');

        return self::SUCCESS;
    }
}

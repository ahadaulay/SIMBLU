<?php

namespace Database\Seeders;

use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\Seeder;
use LogicException;

class UnitAccountSeeder extends Seeder
{
    public function run(): void
    {
        $password = config('reporting.seed_unit.password');

        if (blank($password)) {
            throw new LogicException(
                'Isi SIBLU_SEED_UNIT_PASSWORD sebelum menjalankan seeder.',
            );
        }

        Unit::query()
            ->where('active', true)
            ->orderBy('code')
            ->each(function (Unit $unit) use ($password): void {
                $username = strtolower($unit->code);
                $emailName = strtolower(str_replace('-', '.', $unit->code));

                User::updateOrCreate(
                    ['email' => $emailName.'@uinsyahada.ac.id'],
                    [
                        'name' => 'Operator '.$unit->name,
                        'username' => $username,
                        'password' => $password,
                        'role' => 'UNIT',
                        'unit_id' => $unit->id,
                        'active' => true,
                    ],
                );
            });
    }
}

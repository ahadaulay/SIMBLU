<?php

namespace Database\Seeders;

use App\Models\Unit;
use Illuminate\Database\Seeder;

class UnitSeeder extends Seeder
{
    public function run(): void
    {
        $units = [
            ['code' => 'BAG-UMUM', 'name' => 'Bagian Umum'],
            ['code' => 'AKADEMIK', 'name' => 'Layanan Akademik'],
            ['code' => 'SDM', 'name' => 'Sumber Daya Manusia'],
            ['code' => 'HUMAS', 'name' => 'Hubungan Masyarakat'],
            ['code' => 'FAKULTAS', 'name' => 'Fakultas'],
            ['code' => 'FTIK', 'name' => 'Fakultas Tarbiyah dan Ilmu Keguruan'],
            ['code' => 'FASIH', 'name' => 'Fakultas Syariah dan Ilmu Hukum'],
            ['code' => 'PDIK', 'name' => 'Fakultas Dakwah dan Ilmu Komunikasi'],
            ['code' => 'FEBI', 'name' => 'Fakultas Ekonomi dan Bisnis Islam'],
            ['code' => 'PASCA', 'name' => 'Pascasarjana'],
            ['code' => 'FAKULTAS-PASCA', 'name' => 'Fakultas dan Pascasarjana'],
            ['code' => 'LPM', 'name' => 'Lembaga Penjaminan Mutu'],
            ['code' => 'LPPM', 'name' => 'Lembaga Penelitian dan Pengabdian kepada Masyarakat'],
            ['code' => 'SPI', 'name' => 'Satuan Pengawasan Internal'],
            ['code' => 'UPT-KARIR', 'name' => 'UPT Peningkatan Karir'],
            ['code' => 'UPT-PERPUS', 'name' => 'UPT Perpustakaan'],
            ['code' => 'UPT-BISNIS', 'name' => 'UPT Pengembangan Bisnis'],
            ['code' => 'UPT-BAHASA', 'name' => 'UPT Bahasa'],
            ['code' => 'UPT-TIPD', 'name' => 'UPT Teknologi Informasi dan Pangkalan Data'],
            ['code' => 'UPT-MAHAD', 'name' => "UPT Ma'had Al-Jamiah"],
        ];

        foreach ($units as $unit) {
            Unit::updateOrCreate(
                ['code' => $unit['code']],
                [
                    'name' => $unit['name'],
                    'active' => true,
                ],
            );
        }

        $facultyId = Unit::where('code', 'FAKULTAS')->value('id');

        Unit::whereIn('code', ['FTIK', 'FASIH', 'PDIK', 'FEBI'])
            ->update(['parent_id' => $facultyId]);
    }
}

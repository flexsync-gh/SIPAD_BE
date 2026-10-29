<?php

namespace Database\Seeders;

use App\Models\AppSetting;
use Illuminate\Database\Seeder;

class AppSettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $settings = [
            'is_role_2_required' => 'false',
            'is_role_2_mandatory' => 'false',
            'registration_status' => 'open',
            'registration_timeline' => 'Periode Pendaftaran: 1 - 14 November 2026',
            'registration_terms' => 'Mahasiswa aktif peserta KRS PAD dan telah menyelesaikan prasyarat mata kuliah dasar pemrograman.',
            'academic_year' => '2026/2027',
        ];

        foreach ($settings as $key => $value) {
            AppSetting::updateOrCreate(
                ['key' => $key],
                ['value' => $value]
            );
        }
    }
}

<?php

namespace Database\Seeders;

use App\Models\Bank;
use Illuminate\Database\Seeder;

class BankSeeder extends Seeder
{
    public function run(): void
    {
        $banks = [
            ['code' => 'BCA',     'name' => 'Bank Central Asia'],
            ['code' => 'BNI',     'name' => 'Bank Negara Indonesia'],
            ['code' => 'BRI',     'name' => 'Bank Rakyat Indonesia'],
            ['code' => 'MANDIRI', 'name' => 'Bank Mandiri'],
            ['code' => 'BSI',     'name' => 'Bank Syariah Indonesia'],
            ['code' => 'CIMB',    'name' => 'CIMB Niaga'],
            ['code' => 'DANAMON', 'name' => 'Bank Danamon'],
            ['code' => 'BTN',     'name' => 'Bank Tabungan Negara'],
            ['code' => 'PERMATA', 'name' => 'Bank Permata'],
            ['code' => 'OCBC',    'name' => 'OCBC NISP'],
            ['code' => 'PANIN',   'name' => 'Bank Panin'],
            ['code' => 'MAYBANK', 'name' => 'Maybank Indonesia'],
            ['code' => 'JAGO',    'name' => 'Bank Jago'],
            ['code' => 'SEABANK', 'name' => 'SeaBank Indonesia'],
            ['code' => 'BLU',     'name' => 'blu by BCA Digital'],
        ];

        foreach ($banks as $bank) {
            Bank::firstOrCreate(['code' => $bank['code']], ['name' => $bank['name'], 'is_active' => true]);
        }
    }
}

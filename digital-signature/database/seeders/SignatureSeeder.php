<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;  // <-- wajib ditambahkan

class SignatureSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('signatures')->insert([
            'user_name' => 'Admin Sistem',
            'position' => 'Kepala Instansi',
            'document_name' => 'Dokumen Contoh',
            'qr_code_path' => 'qrcodes/example.png',
            'signed_pdf_path' => 'signed/example.pdf',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}

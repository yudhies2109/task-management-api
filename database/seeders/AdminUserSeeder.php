<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        // Akun contoh hanya dibuat pada lingkungan lokal.
        if (! app()->environment('local')) {
            return;
        }

        // Tidak mengubah akun yang sudah menggunakan email ini.
        if (User::where('email', 'admin@example.com')->exists()) {
            $this->command?->warn('Email admin@example.com sudah digunakan.');

            return;
        }

        $admin = new User();
        $admin->name = 'Admin';
        $admin->email = 'admin@example.com';

        // Cast "hashed" pada model User otomatis mengenkripsi password.
        $admin->password = 'Admin123!';

        $admin->role = 'admin';
        $admin->save();
    }
}

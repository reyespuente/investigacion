<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $email = config('catalogue.admin_email');
        if (! User::where('email', $email)->exists()) {
            $password = config('catalogue.admin_password');
            if (! $password || strlen($password) < 16) {
                throw new \RuntimeException('Define ADMIN_PASSWORD con al menos 16 caracteres antes de ejecutar el seeder.');
            }
            User::create(['name' => config('catalogue.admin_name'), 'email' => $email, 'password' => Hash::make($password), 'role' => 'admin', 'must_change_password' => true, 'email_verified_at' => now()]);
        }
        $this->command?->info('Administrador preparado. El sistema base no incluye datos institucionales; usa los comandos catalog:researchers, catalog:import y catalog:claims.');
    }
}

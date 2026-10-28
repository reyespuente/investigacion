<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class InstallRepository extends Command
{
    protected $signature = 'repository:install';

    protected $description = 'Preparar la base y el primer administrador sin abrir un instalador público';

    public function handle(): int
    {
        if (! is_file(base_path('.env'))) {
            $this->error('Copia .env.example a .env y configura DB_* antes de instalar.');

            return self::FAILURE;
        }
        if (! config('app.key') && $this->call('key:generate', ['--force' => true]) !== 0) {
            return self::FAILURE;
        }
        try {
            DB::connection()->getPdo();
        } catch (\Throwable) {
            $this->error('No se puede conectar a la base. Revisa DB_* en el archivo privado .env. No se modificaron otras bases.');

            return self::FAILURE;
        }
        if ($this->call('migrate', ['--force' => true]) !== 0) {
            return self::FAILURE;
        }
        if (! User::where('role', 'admin')->exists()) {
            $email = config('catalogue.admin_email');
            $name = config('catalogue.admin_name');
            $password = config('catalogue.admin_password');
            if ($this->input->isInteractive()) {
                $name = $this->ask('Nombre del administrador', $name);
                $email = $this->ask('Correo del administrador', $email);
                if (! $password) {
                    $password = $this->secret('Contraseña inicial (mínimo 16 caracteres)');
                    if ($password !== $this->secret('Repite la contraseña inicial')) {
                        $this->error('Las contraseñas no coinciden.');

                        return self::FAILURE;
                    }
                }
            }
            if (! filter_var($email, FILTER_VALIDATE_EMAIL) || ! is_string($password) || strlen($password) < 16) {
                $this->error('Define un correo válido y ADMIN_PASSWORD de al menos 16 caracteres, o utiliza el instalador interactivo.');

                return self::FAILURE;
            }
            config(['catalogue.admin_name' => $name, 'catalogue.admin_email' => strtolower($email), 'catalogue.admin_password' => $password]);
            if ($this->call('db:seed', ['--force' => true]) !== 0) {
                return self::FAILURE;
            }
        }
        $this->info('Base preparada. Los administradores y contraseñas existentes no se modificaron.');
        $this->line('Inicia sesión, cambia la contraseña temporal y abre /admin/instalacion. Allí puedes configurar identidad, dominios, correo, almacenamiento, Google y Turnstile.');
        if (! is_file(public_path('build/manifest.json'))) {
            $this->warn('Falta compilar la interfaz: npm ci && npm run build.');
        }

        return self::SUCCESS;
    }
}

<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class ApplicationMaintenanceService
{
    public function migrate(): string
    {
        set_time_limit(120);

        $exitCode = Artisan::call('migrate', ['--force' => true]);
        $output = trim(Artisan::output());

        if ($exitCode !== 0) {
            throw new RuntimeException($output !== '' ? $output : 'No se pudieron correr las migraciones.');
        }

        return $output;
    }

    public function clearCache(): string
    {
        set_time_limit(120);

        $exitCode = Artisan::call('optimize:clear');
        $output = trim(Artisan::output());

        if ($exitCode !== 0) {
            throw new RuntimeException($output !== '' ? $output : 'No se pudo borrar la caché.');
        }

        return $output;
    }

    public function promoteToAdmin(User $user): void
    {
        if (! Schema::hasColumn('users', 'is_admin')) {
            return;
        }

        User::query()->whereKey($user->id)->update(['is_admin' => true]);
    }
}

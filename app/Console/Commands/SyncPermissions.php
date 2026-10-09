<?php

namespace App\Console\Commands;

use App\Support\Permissions;
use Illuminate\Console\Command;

class SyncPermissions extends Command
{
    protected $signature = 'permissions:sync {--reconcile-users : Recalcular el flag is_admin de todos los usuarios a partir de sus roles}';

    protected $description = 'Sincroniza el catálogo de permisos (app/Support/Permissions.php) con la base de datos y re-aplica los permisos por defecto de los roles del sistema';

    public function handle(): int
    {
        Permissions::syncToDatabase();

        $count = count(Permissions::all());

        $this->info("Catálogo sincronizado: {$count} permisos y 3 roles del sistema verificados.");
        $this->line('Nota: los permisos por defecto de "Super Administrador", "Administrador" y "Vendedor" fueron re-aplicados desde el catálogo. El sync sólo agrega: no revoca permisos de roles custom ni los habilitados a mano desde la matriz.');

        if ($this->option('reconcile-users')) {
            Permissions::reconcileUsers();
            $this->info('Flag is_admin recalculado para todos los usuarios a partir de sus roles.');
        }

        return self::SUCCESS;
    }
}

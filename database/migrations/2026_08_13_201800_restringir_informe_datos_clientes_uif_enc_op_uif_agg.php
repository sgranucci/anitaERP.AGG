<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * AGG: el informe datos clientes UIF queda solo para Enc-Uif y Op-Uif
 * (revoca lo asignado a roles de tesorería).
 */
return new class extends Migration
{
    private const MENU_URL = 'uif/crearexportaoperacion';

    private const PERMISO_SLUG = 'exportar-operacion-uif';

    private const ROLES_PERMITIDOS = [
        'Enc-Uif',
        'Op-Uif',
    ];

    private const ROLES_TESORERIA_REVOCAR = [
        'Enc-tesorería',
        'Op-tesoreria',
        'Ger-Tesoreria',
        'Sup-tesoreria',
        'opflash-tesoreria',
    ];

    public function up(): void
    {
        if (! EntornoEmpresaSupport::esAgg()) {
            return;
        }

        $menuId = (int) (DB::table('menu')->where('url', self::MENU_URL)->value('id') ?? 0);
        $permisoId = (int) (DB::table('permiso')->where('slug', self::PERMISO_SLUG)->value('id') ?? 0);
        $menuPadreId = (int) (DB::table('menu')->where('id', 180)->value('id') ?? 0);

        if ($menuId <= 0 || $permisoId <= 0) {
            return;
        }

        $this->asegurarRolOpUif();

        foreach (self::ROLES_TESORERIA_REVOCAR as $nombreRol) {
            $rolId = (int) (DB::table('rol')->where('nombre', $nombreRol)->value('id') ?? 0);
            if ($rolId <= 0) {
                continue;
            }

            DB::table('permiso_rol')
                ->where('rol_id', $rolId)
                ->where('permiso_id', $permisoId)
                ->delete();

            DB::table('menu_rol')
                ->where('rol_id', $rolId)
                ->where('menu_id', $menuId)
                ->delete();
        }

        foreach (self::ROLES_PERMITIDOS as $nombreRol) {
            $rolId = (int) (DB::table('rol')->where('nombre', $nombreRol)->value('id') ?? 0);
            if ($rolId <= 0) {
                continue;
            }

            $this->asegurarPermisoRol($rolId, $permisoId);
            $this->asegurarMenuRol($rolId, $menuId);
            if ($menuPadreId > 0) {
                $this->asegurarMenuRol($rolId, $menuPadreId);
            }
        }
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esAgg()) {
            return;
        }

        // No recrea grants de tesorería ni borra Op-Uif: solo deshace lo mínimo riesgoso.
        $permisoId = (int) (DB::table('permiso')->where('slug', self::PERMISO_SLUG)->value('id') ?? 0);
        $menuId = (int) (DB::table('menu')->where('url', self::MENU_URL)->value('id') ?? 0);
        $opUifId = (int) (DB::table('rol')->where('nombre', 'Op-Uif')->value('id') ?? 0);

        if ($opUifId > 0 && $permisoId > 0) {
            DB::table('permiso_rol')
                ->where('rol_id', $opUifId)
                ->where('permiso_id', $permisoId)
                ->delete();
        }

        if ($opUifId > 0 && $menuId > 0) {
            DB::table('menu_rol')
                ->where('rol_id', $opUifId)
                ->where('menu_id', $menuId)
                ->delete();
        }
    }

    private function asegurarRolOpUif(): void
    {
        $existe = DB::table('rol')->where('nombre', 'Op-Uif')->exists();
        if ($existe) {
            return;
        }

        DB::table('rol')->insert([
            'nombre' => 'Op-Uif',
        ]);
    }

    private function asegurarPermisoRol(int $rolId, int $permisoId): void
    {
        $existe = DB::table('permiso_rol')
            ->where('rol_id', $rolId)
            ->where('permiso_id', $permisoId)
            ->exists();

        if (! $existe) {
            DB::table('permiso_rol')->insert([
                'rol_id' => $rolId,
                'permiso_id' => $permisoId,
            ]);
        }
    }

    private function asegurarMenuRol(int $rolId, int $menuId): void
    {
        $existe = DB::table('menu_rol')
            ->where('rol_id', $rolId)
            ->where('menu_id', $menuId)
            ->exists();

        if (! $existe) {
            DB::table('menu_rol')->insert([
                'rol_id' => $rolId,
                'menu_id' => $menuId,
            ]);
        }
    }
};

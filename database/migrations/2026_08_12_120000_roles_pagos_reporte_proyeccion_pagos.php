<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * El informe de proyección de pagos había heredado los roles del reporte de requisiciones
 * (21 roles de todas las áreas). Es información de cuentas a pagar: queda solo para los
 * roles de pagos más administrador.
 */
return new class extends Migration
{
    private const PERMISO = 'listar-reporte-proyeccion-pagos';

    private const MENU_URL = 'compras/proyeccion-pagos';

    public function up(): void
    {
        $rolIds = $this->rolesPagos();
        if ($rolIds === []) {
            return;
        }

        $permisoId = (int) (DB::table('permiso')->where('slug', self::PERMISO)->value('id') ?? 0);
        if ($permisoId > 0) {
            DB::table('permiso_rol')
                ->where('permiso_id', $permisoId)
                ->whereNotIn('rol_id', $rolIds)
                ->delete();

            foreach ($rolIds as $rolId) {
                $existe = DB::table('permiso_rol')
                    ->where('permiso_id', $permisoId)
                    ->where('rol_id', $rolId)
                    ->exists();

                if (! $existe) {
                    DB::table('permiso_rol')->insert(['permiso_id' => $permisoId, 'rol_id' => $rolId]);
                }
            }
        }

        $menuId = (int) (DB::table('menu')->where('url', self::MENU_URL)->value('id') ?? 0);
        if ($menuId === 0) {
            return;
        }

        DB::table('menu_rol')
            ->where('menu_id', $menuId)
            ->whereNotIn('rol_id', $rolIds)
            ->delete();

        foreach ($rolIds as $rolId) {
            $existe = DB::table('menu_rol')
                ->where('menu_id', $menuId)
                ->where('rol_id', $rolId)
                ->exists();

            if (! $existe) {
                DB::table('menu_rol')->insert(['menu_id' => $menuId, 'rol_id' => $rolId]);
            }
        }
    }

    public function down(): void
    {
        // La asignación anterior (roles de todas las áreas) era el error que corrige esta
        // migración: no se restaura. Para ampliar roles se usa el ABM de permisos.
    }

    /**
     * Administrador + roles de pagos (Enc-pagos, Op-Pagos y equivalentes futuros).
     *
     * @return list<int>
     */
    private function rolesPagos(): array
    {
        return DB::table('rol')
            ->where('nombre', 'like', '%pagos%')
            ->orWhere('nombre', 'administrador')
            ->orderBy('id')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }
};

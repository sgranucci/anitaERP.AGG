<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const MENU_URL = 'caja/interbanking/saldos-historicos';

    private const PERMISO_SLUG = 'listar-saldos-interbanking-historico';

    private const PERMISO_SLUG_REFERENCIA = 'listar-saldo-cuenta-interbanking';

    /**
     * Menú lateral bajo Módulo de Caja (id 104 en instalaciones base) y permiso dedicado,
     * replicando asignaciones a roles desde el permiso de saldos Interbanking en vivo.
     */
    public function up(): void
    {
        $moduloCajaId = (int) (DB::table('menu')
            ->where('nombre', 'Módulo de Caja')
            ->where('menu_id', 0)
            ->value('id') ?? 0);
        if ($moduloCajaId === 0) {
            $moduloCajaId = 104;
        }

        $menuId = (int) (DB::table('menu')->where('url', self::MENU_URL)->value('id') ?? 0);

        if ($menuId === 0) {
            $menuId = (int) DB::table('menu')->insertGetId([
                'menu_id' => $moduloCajaId,
                'nombre' => 'Interbanking saldos históricos',
                'url' => self::MENU_URL,
                'orden' => 20,
                'icono' => 'fa-chart-line',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $permisoId = (int) (DB::table('permiso')->where('slug', self::PERMISO_SLUG)->value('id') ?? 0);

        if ($permisoId === 0) {
            $permisoId = (int) DB::table('permiso')->insertGetId([
                'nombre' => 'Listar saldos Interbanking históricos',
                'slug' => self::PERMISO_SLUG,
                'menu_id' => $menuId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            DB::table('permiso')->where('id', $permisoId)->update([
                'menu_id' => $menuId,
                'nombre' => 'Listar saldos Interbanking históricos',
                'updated_at' => now(),
            ]);
        }

        $refPermisoId = (int) (DB::table('permiso')->where('slug', self::PERMISO_SLUG_REFERENCIA)->value('id') ?? 0);
        if ($refPermisoId === 0) {
            $refPermisoId = (int) (DB::table('permiso')->where('slug', 'lista-movimientos-caja')->value('id') ?? 0);
        }

        if ($refPermisoId > 0) {
            $rolIdsPermiso = DB::table('permiso_rol')->where('permiso_id', $refPermisoId)->pluck('rol_id')->unique()->all();
            foreach ($rolIdsPermiso as $rolId) {
                $rid = (int) $rolId;
                if (! DB::table('permiso_rol')->where('permiso_id', $permisoId)->where('rol_id', $rid)->exists()) {
                    DB::table('permiso_rol')->insert([
                        'permiso_id' => $permisoId,
                        'rol_id' => $rid,
                    ]);
                }
            }

            $refMenuId = (int) (DB::table('permiso')->where('id', $refPermisoId)->value('menu_id') ?? 0);
            if ($refMenuId > 0) {
                $rolIdsMenu = DB::table('menu_rol')->where('menu_id', $refMenuId)->pluck('rol_id')->unique()->all();
            } else {
                $rolIdsMenu = $rolIdsPermiso;
            }

            foreach ($rolIdsMenu as $rolId) {
                $rid = (int) $rolId;
                if (! DB::table('menu_rol')->where('menu_id', $menuId)->where('rol_id', $rid)->exists()) {
                    DB::table('menu_rol')->insert([
                        'menu_id' => $menuId,
                        'rol_id' => $rid,
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        $permisoId = DB::table('permiso')->where('slug', self::PERMISO_SLUG)->value('id');
        $menuId = DB::table('menu')->where('url', self::MENU_URL)->value('id');

        if ($permisoId) {
            DB::table('permiso_rol')->where('permiso_id', $permisoId)->delete();
            DB::table('permiso')->where('id', $permisoId)->delete();
        }

        if ($menuId) {
            DB::table('menu_rol')->where('menu_id', $menuId)->delete();
            DB::table('menu')->where('id', $menuId)->delete();
        }
    }
};

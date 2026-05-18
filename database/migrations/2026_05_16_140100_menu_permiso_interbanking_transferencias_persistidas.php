<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const MENU_URL = 'caja/interbanking/transferencias-persistidas';

    private const PERMISO_LISTAR = 'listar-interbanking-transferencias-persistidas';

    private const PERMISO_SINCRONIZAR = 'sincronizar-interbanking-transferencias';

    private const PERMISO_REFERENCIA = 'listar-interbanking-movimientos-persistidos';

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
                'nombre' => 'Interbanking transferencias persistidas',
                'url' => self::MENU_URL,
                'orden' => 22,
                'icono' => 'fa-random',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $permisoListarId = $this->asegurarPermiso(
            self::PERMISO_LISTAR,
            'Listar transferencias Interbanking persistidas',
            $menuId
        );
        $permisoSyncId = $this->asegurarPermiso(
            self::PERMISO_SINCRONIZAR,
            'Sincronizar transferencias Interbanking desde API',
            $menuId
        );

        $refPermisoId = (int) (DB::table('permiso')->where('slug', self::PERMISO_REFERENCIA)->value('id') ?? 0);
        if ($refPermisoId === 0) {
            $refPermisoId = (int) (DB::table('permiso')->where('slug', 'ver-transferencias-cuenta-interbanking')->value('id') ?? 0);
        }

        if ($refPermisoId > 0) {
            $rolIdsPermiso = DB::table('permiso_rol')->where('permiso_id', $refPermisoId)->pluck('rol_id')->unique()->all();
            foreach ([$permisoListarId, $permisoSyncId] as $pid) {
                foreach ($rolIdsPermiso as $rolId) {
                    $rid = (int) $rolId;
                    if (! DB::table('permiso_rol')->where('permiso_id', $pid)->where('rol_id', $rid)->exists()) {
                        DB::table('permiso_rol')->insert([
                            'permiso_id' => $pid,
                            'rol_id' => $rid,
                        ]);
                    }
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

    private function asegurarPermiso(string $slug, string $nombre, int $menuId): int
    {
        $permisoId = (int) (DB::table('permiso')->where('slug', $slug)->value('id') ?? 0);

        if ($permisoId === 0) {
            return (int) DB::table('permiso')->insertGetId([
                'nombre' => $nombre,
                'slug' => $slug,
                'menu_id' => $menuId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('permiso')->where('id', $permisoId)->update([
            'menu_id' => $menuId,
            'nombre' => $nombre,
            'updated_at' => now(),
        ]);

        return $permisoId;
    }

    public function down(): void
    {
        foreach ([self::PERMISO_LISTAR, self::PERMISO_SINCRONIZAR] as $slug) {
            $permisoId = DB::table('permiso')->where('slug', $slug)->value('id');
            if ($permisoId) {
                DB::table('permiso_rol')->where('permiso_id', $permisoId)->delete();
                DB::table('permiso')->where('id', $permisoId)->delete();
            }
        }

        $menuId = DB::table('menu')->where('url', self::MENU_URL)->value('id');
        if ($menuId) {
            DB::table('menu_rol')->where('menu_id', $menuId)->delete();
            DB::table('menu')->where('id', $menuId)->delete();
        }
    }
};

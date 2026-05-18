<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const MENU_URL = 'caja/interbanking';

    private const PERMISO_SLUG = 'ver-transferencias-cuenta-interbanking';

    private const PERMISO_REFERENCIA = 'ver-movimientos-cuenta-interbanking';

    public function up(): void
    {
        $menuId = (int) (DB::table('menu')->where('url', self::MENU_URL)->value('id') ?? 0);
        if ($menuId === 0) {
            $moduloCajaId = (int) (DB::table('menu')
                ->where('nombre', 'Módulo de Caja')
                ->where('menu_id', 0)
                ->value('id') ?? 0);
            if ($moduloCajaId === 0) {
                $moduloCajaId = 104;
            }

            $menuId = (int) DB::table('menu')->insertGetId([
                'menu_id' => $moduloCajaId,
                'nombre' => 'Interbanking saldos',
                'url' => self::MENU_URL,
                'orden' => 19,
                'icono' => 'fa-university',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $permisoId = (int) (DB::table('permiso')->where('slug', self::PERMISO_SLUG)->value('id') ?? 0);

        if ($permisoId === 0) {
            $permisoId = (int) DB::table('permiso')->insertGetId([
                'nombre' => 'Ver transferencias cuenta Interbanking',
                'slug' => self::PERMISO_SLUG,
                'menu_id' => $menuId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            DB::table('permiso')->where('id', $permisoId)->update([
                'menu_id' => $menuId,
                'nombre' => 'Ver transferencias cuenta Interbanking',
                'updated_at' => now(),
            ]);
        }

        $refPermisoId = (int) (DB::table('permiso')->where('slug', self::PERMISO_REFERENCIA)->value('id') ?? 0);
        if ($refPermisoId === 0) {
            $refPermisoId = (int) (DB::table('permiso')->where('slug', 'listar-saldo-cuenta-interbanking')->value('id') ?? 0);
        }

        if ($refPermisoId > 0) {
            $rolIds = DB::table('permiso_rol')->where('permiso_id', $refPermisoId)->pluck('rol_id')->unique()->all();
            foreach ($rolIds as $rolId) {
                $rid = (int) $rolId;
                if (! DB::table('permiso_rol')->where('permiso_id', $permisoId)->where('rol_id', $rid)->exists()) {
                    DB::table('permiso_rol')->insert([
                        'permiso_id' => $permisoId,
                        'rol_id' => $rid,
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        $permisoId = DB::table('permiso')->where('slug', self::PERMISO_SLUG)->value('id');
        if ($permisoId) {
            DB::table('permiso_rol')->where('permiso_id', $permisoId)->delete();
            DB::table('permiso')->where('id', $permisoId)->delete();
        }
    }
};

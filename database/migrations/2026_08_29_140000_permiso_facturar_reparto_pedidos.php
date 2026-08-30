<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Facturar en lote los pedidos pesados de un reparto.
 * Solo se asigna a administrador; el resto no lo ve hasta que se habilite.
 */
return new class extends Migration
{
    private const MENU_URL = 'ventas/pedido';

    private const PERMISO = [
        'nombre' => 'Facturar pedidos por reparto',
        'slug' => 'facturar-reparto-pedidos',
    ];

    public function up(): void
    {
        $menuId = (int) (DB::table('menu')->where('url', self::MENU_URL)->value('id') ?? 0);
        $now = now();
        $permisoId = (int) (DB::table('permiso')->where('slug', self::PERMISO['slug'])->value('id') ?? 0);
        if ($permisoId === 0) {
            $permisoId = (int) DB::table('permiso')->insertGetId([
                'nombre' => self::PERMISO['nombre'],
                'slug' => self::PERMISO['slug'],
                'menu_id' => $menuId > 0 ? $menuId : null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        } else {
            DB::table('permiso')->where('id', $permisoId)->update([
                'nombre' => self::PERMISO['nombre'],
                'menu_id' => $menuId > 0 ? $menuId : null,
                'updated_at' => $now,
            ]);
        }

        $rolId = (int) (DB::table('rol')->where('nombre', 'administrador')->value('id') ?? 0);
        if ($rolId > 0 && ! DB::table('permiso_rol')->where('permiso_id', $permisoId)->where('rol_id', $rolId)->exists()) {
            DB::table('permiso_rol')->insert([
                'permiso_id' => $permisoId,
                'rol_id' => $rolId,
            ]);
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        $permisoId = (int) (DB::table('permiso')->where('slug', self::PERMISO['slug'])->value('id') ?? 0);
        if ($permisoId <= 0) {
            return;
        }

        DB::table('permiso_rol')->where('permiso_id', $permisoId)->delete();
        DB::table('permiso')->where('id', $permisoId)->delete();

        SuitecrmPermiso::flushCachePermisos();
    }
};

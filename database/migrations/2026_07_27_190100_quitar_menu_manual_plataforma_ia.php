<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Quita «Manual Plataforma IA» del menú Configuración: el mismo manual ya está
 * en el Centro de ayuda (AyudaManuales → route manual_ia). La ruta permanece.
 */
return new class extends Migration
{
    private const MENU_URL = 'configuracion/manual-ia';

    public function up(): void
    {
        $menuId = (int) (DB::table('menu')->where('url', self::MENU_URL)->value('id') ?? 0);
        if ($menuId > 0) {
            DB::table('menu_rol')->where('menu_id', $menuId)->delete();
            DB::table('menu')->where('id', $menuId)->delete();
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        $padreId = (int) (DB::table('menu')
            ->where('id', 33)
            ->orWhere(function ($q) {
                $q->where('nombre', 'Configuración')->where('url', '#');
            })
            ->orderBy('id')
            ->value('id') ?? 0);

        if ($padreId === 0) {
            return;
        }

        $menuId = (int) (DB::table('menu')->where('url', self::MENU_URL)->value('id') ?? 0);
        if ($menuId === 0) {
            $orden = (int) (DB::table('menu')->where('menu_id', $padreId)->max('orden') ?? 0) + 1;
            $menuId = (int) DB::table('menu')->insertGetId([
                'menu_id' => $padreId,
                'nombre' => 'Manual Plataforma IA',
                'url' => self::MENU_URL,
                'orden' => $orden,
                'icono' => 'fa-book',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $rolIds = DB::table('rol')->where('nombre', 'administrador')->pluck('id');
        $permisoId = (int) (DB::table('permiso')->where('slug', 'listar-ai-decisiones')->value('id') ?? 0);
        if ($permisoId > 0) {
            $rolIds = $rolIds->merge(
                DB::table('permiso_rol')->where('permiso_id', $permisoId)->pluck('rol_id')
            )->unique();
        }

        foreach ($rolIds as $rolId) {
            if (! DB::table('menu_rol')->where('menu_id', $menuId)->where('rol_id', (int) $rolId)->exists()) {
                DB::table('menu_rol')->insert(['menu_id' => $menuId, 'rol_id' => (int) $rolId]);
            }
        }

        SuitecrmPermiso::flushCachePermisos();
    }
};

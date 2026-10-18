<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Menú y permisos del Cierre X / Rendición mozo (solo instalación CROWN).
 * Se cuelga de la misma rama que la Jornada de gastronomía.
 */
return new class extends Migration
{
    private const URL_CIERRE_X = 'ventas/gastronomia-crown/cierre-x';

    private const URL_JORNADA = 'ventas/gastronomia/jornada';

    private const URL_POS = 'ventas/gastronomia-crown/pos';

    /** @var array<string, string> */
    private const PERMISOS = [
        'listar-cierre-x-gastronomia-crown' => 'Listar cierre X por mozo Crown',
        'crear-cierre-x-gastronomia-crown' => 'Crear rendición de cierre X por mozo Crown',
    ];

    public function up(): void
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            return;
        }

        $ahora = now();

        $permisoIds = [];
        foreach (self::PERMISOS as $slug => $nombre) {
            $permisoId = DB::table('permiso')->where('slug', $slug)->value('id');
            if (! $permisoId) {
                $permisoId = DB::table('permiso')->insertGetId([
                    'nombre' => $nombre,
                    'slug' => $slug,
                    'created_at' => $ahora,
                    'updated_at' => $ahora,
                ]);
            }
            $permisoIds[] = (int) $permisoId;
        }

        $jornada = DB::table('menu')->where('url', self::URL_JORNADA)->first(['menu_id', 'orden']);
        $menuPadreId = (int) ($jornada->menu_id
            ?? DB::table('menu')->where('url', self::URL_POS)->value('menu_id')
            ?? 0);
        if ($menuPadreId <= 0) {
            return;
        }

        $menuId = DB::table('menu')->where('url', self::URL_CIERRE_X)->value('id');
        if (! $menuId) {
            // Inmediatamente después de la jornada; si no está, al final de la rama.
            $orden = $jornada !== null
                ? ((int) $jornada->orden) + 1
                : (int) (DB::table('menu')->where('menu_id', $menuPadreId)->max('orden') ?? 0) + 1;

            $menuId = DB::table('menu')->insertGetId([
                'menu_id' => $menuPadreId,
                'nombre' => 'Cierre X / Rendición mozo',
                'url' => self::URL_CIERRE_X,
                'orden' => $orden,
                'icono' => 'fa-cash-register',
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ]);
        }

        $rolIds = DB::table('rol')
            ->whereIn('nombre', ['administrador', 'Enc-sistemas'])
            ->pluck('id');

        foreach ($rolIds as $rolId) {
            foreach ($permisoIds as $permisoId) {
                $existePerm = DB::table('permiso_rol')
                    ->where('rol_id', $rolId)
                    ->where('permiso_id', $permisoId)
                    ->exists();
                if (! $existePerm) {
                    DB::table('permiso_rol')->insert([
                        'rol_id' => $rolId,
                        'permiso_id' => $permisoId,
                    ]);
                }
            }

            foreach ([$menuId, $menuPadreId] as $idMenu) {
                $existeMenu = DB::table('menu_rol')
                    ->where('rol_id', $rolId)
                    ->where('menu_id', $idMenu)
                    ->exists();
                if (! $existeMenu) {
                    DB::table('menu_rol')->insert([
                        'rol_id' => $rolId,
                        'menu_id' => $idMenu,
                    ]);
                }
            }
        }

        if (class_exists(\App\Support\SuitecrmPermiso::class)) {
            \App\Support\SuitecrmPermiso::flushCachePermisos();
        }
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            return;
        }

        $menuId = DB::table('menu')->where('url', self::URL_CIERRE_X)->value('id');
        if ($menuId) {
            DB::table('menu_rol')->where('menu_id', $menuId)->delete();
            DB::table('menu')->where('id', $menuId)->delete();
        }

        $permisoIds = DB::table('permiso')->whereIn('slug', array_keys(self::PERMISOS))->pluck('id');
        if ($permisoIds->isNotEmpty()) {
            DB::table('permiso_rol')->whereIn('permiso_id', $permisoIds)->delete();
            DB::table('permiso')->whereIn('id', $permisoIds)->delete();
        }
    }
};

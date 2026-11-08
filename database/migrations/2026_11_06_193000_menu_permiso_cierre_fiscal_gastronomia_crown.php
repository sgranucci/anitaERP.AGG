<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Menú y permisos del Cierre fiscal Z / p-cierrefact ERP (solo CROWN).
 * Se cuelga junto a Cierre X / Rendición mozo.
 */
return new class extends Migration
{
    private const URL = 'ventas/gastronomia-crown/cierre-fiscal';

    private const URL_CIERRE_X = 'ventas/gastronomia-crown/cierre-x';

    private const URL_JORNADA = 'ventas/gastronomia/jornada';

    /** @var array<string, string> */
    private const PERMISOS = [
        'listar-cierre-fiscal-gastronomia-crown' => 'Listar cierre fiscal Z gastronomía Crown',
        'procesar-cierre-fiscal-gastronomia-crown' => 'Procesar cierre fiscal Z gastronomía Crown',
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

        $cierreX = DB::table('menu')->where('url', self::URL_CIERRE_X)->first(['id', 'menu_id', 'orden']);
        $jornada = DB::table('menu')->where('url', self::URL_JORNADA)->first(['menu_id', 'orden']);
        $menuPadreId = (int) ($cierreX->menu_id
            ?? $jornada->menu_id
            ?? 0);
        if ($menuPadreId <= 0) {
            return;
        }

        $menuId = DB::table('menu')->where('url', self::URL)->value('id');
        if (! $menuId) {
            $orden = $cierreX !== null
                ? ((int) $cierreX->orden) + 1
                : (int) (DB::table('menu')->where('menu_id', $menuPadreId)->max('orden') ?? 0) + 1;

            $menuId = DB::table('menu')->insertGetId([
                'menu_id' => $menuPadreId,
                'nombre' => 'Cierre fiscal Z (facturación)',
                'url' => self::URL,
                'orden' => $orden,
                'icono' => 'fa-file-text-o',
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

        $menuId = DB::table('menu')->where('url', self::URL)->value('id');
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

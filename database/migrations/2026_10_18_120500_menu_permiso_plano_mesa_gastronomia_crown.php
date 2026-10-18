<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Menú y permisos del ABM de planos de mesas (solo instalación CROWN).
 * Mismo patrón que el menú del KDS Crown.
 */
return new class extends Migration
{
    private const URL_PLANO = 'ventas/gastronomia-crown/plano-mesa';

    private const URL_MESA = 'ventas/mesa-gastronomia';

    private const URL_POS = 'ventas/gastronomia-crown/pos';

    /** @var array<string, string> */
    private const PERMISOS = [
        'listar-plano-mesa-gastronomia-crown' => 'Listar planos de mesas Crown',
        'crear-plano-mesa-gastronomia-crown' => 'Crear plano de mesas Crown',
        'editar-plano-mesa-gastronomia-crown' => 'Editar plano de mesas Crown',
        'actualizar-plano-mesa-gastronomia-crown' => 'Actualizar plano de mesas Crown',
        'eliminar-plano-mesa-gastronomia-crown' => 'Eliminar plano de mesas Crown',
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

        // Preferir la rama donde ya viven las mesas de gastronomía (Tablas gastronomía).
        $menuPadreId = (int) (DB::table('menu')->where('url', self::URL_MESA)->value('menu_id')
            ?? DB::table('menu')->where('id', 369)->value('id')
            ?? DB::table('menu')->where('url', self::URL_POS)->value('menu_id')
            ?? DB::table('menu')->where('id', 236)->value('id')
            ?? 0);
        if ($menuPadreId <= 0) {
            return;
        }

        $menuId = DB::table('menu')->where('url', self::URL_PLANO)->value('id');
        if (! $menuId) {
            $orden = (int) (DB::table('menu')->where('menu_id', $menuPadreId)->max('orden') ?? 0) + 1;
            $menuId = DB::table('menu')->insertGetId([
                'menu_id' => $menuPadreId,
                'nombre' => 'Planos de mesas',
                'url' => self::URL_PLANO,
                'orden' => $orden,
                'icono' => 'fa-th-large',
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

        $menuId = DB::table('menu')->where('url', self::URL_PLANO)->value('id');
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

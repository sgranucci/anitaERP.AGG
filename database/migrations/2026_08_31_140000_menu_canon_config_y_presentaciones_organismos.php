<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * - Renombra «Presentaciones ARCA» → «Presentaciones a organismos»
 *   (incluye municipalidad / canon, no solo ARCA).
 * - Mueve Configuración canon municipal a
 *   Configuración → Configuración por módulo → Contable.
 */
return new class extends Migration
{
    private const SUBMENU_VIEJO = 'Presentaciones ARCA';

    private const SUBMENU_NUEVO = 'Presentaciones a organismos';

    private const URL_CONFIG = 'contable/canon-municipal-config';

    private const GRUPO_URL = '#configuracion-por-modulo';

    private const GRUPO_NOMBRE = 'Configuración por módulo';

    public function up(): void
    {
        $submenuId = (int) (DB::table('menu')
            ->where('nombre', self::SUBMENU_VIEJO)
            ->where('url', '#')
            ->value('id') ?? 0);
        if ($submenuId > 0) {
            DB::table('menu')->where('id', $submenuId)->update([
                'nombre' => self::SUBMENU_NUEVO,
                'updated_at' => now(),
            ]);
        }

        $this->moverConfigAConfiguracionPorModulo();

        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        $submenuId = (int) (DB::table('menu')
            ->where('nombre', self::SUBMENU_NUEVO)
            ->where('url', '#')
            ->value('id') ?? 0);
        if ($submenuId > 0) {
            DB::table('menu')->where('id', $submenuId)->update([
                'nombre' => self::SUBMENU_VIEJO,
                'updated_at' => now(),
            ]);

            $configId = (int) (DB::table('menu')->where('url', self::URL_CONFIG)->value('id') ?? 0);
            if ($configId > 0) {
                $orden = (int) (DB::table('menu')->where('menu_id', $submenuId)->max('orden') ?? 0) + 1;
                DB::table('menu')->where('id', $configId)->update([
                    'menu_id' => $submenuId,
                    'orden' => $orden,
                    'nombre' => 'Configuración canon municipal',
                    'updated_at' => now(),
                ]);
            }
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    private function moverConfigAConfiguracionPorModulo(): void
    {
        $menuConfigId = (int) (DB::table('menu')->where('url', self::URL_CONFIG)->value('id') ?? 0);
        if ($menuConfigId <= 0) {
            return;
        }

        $configRootId = $this->resolverMenuConfiguracionId();
        if ($configRootId <= 0) {
            return;
        }

        $grupoId = (int) (DB::table('menu')
            ->where(function ($q) use ($configRootId) {
                $q->where('url', self::GRUPO_URL)
                    ->orWhere(function ($q2) use ($configRootId) {
                        $q2->where('menu_id', $configRootId)->where('nombre', self::GRUPO_NOMBRE);
                    });
            })
            ->value('id') ?? 0);
        if ($grupoId <= 0) {
            return;
        }

        $moduloContableId = (int) (DB::table('menu')
            ->where('menu_id', $grupoId)
            ->where('nombre', 'Contable')
            ->value('id') ?? 0);
        if ($moduloContableId <= 0) {
            return;
        }

        $orden = (int) (DB::table('menu')->where('menu_id', $moduloContableId)->max('orden') ?? 0) + 1;

        DB::table('menu')->where('id', $menuConfigId)->update([
            'menu_id' => $moduloContableId,
            'orden' => $orden,
            'nombre' => 'Configuración canon municipal',
            'icono' => 'fa-landmark',
            'updated_at' => now(),
        ]);

        // Propagar roles del ítem hacia Contable → grupo → Configuración.
        $rolIds = DB::table('menu_rol')
            ->where('menu_id', $menuConfigId)
            ->pluck('rol_id')
            ->map(static fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        foreach ($rolIds as $rolId) {
            foreach ([$moduloContableId, $grupoId, $configRootId] as $mid) {
                DB::table('menu_rol')->updateOrInsert(
                    ['menu_id' => $mid, 'rol_id' => $rolId],
                    []
                );
            }
        }
    }

    private function resolverMenuConfiguracionId(): int
    {
        $id = (int) (DB::table('menu')->where('url', 'configuracion/empresa')->value('menu_id') ?? 0);
        if ($id > 0) {
            return $id;
        }
        foreach (['Configuración', 'Módulo Configuración', 'Configuracion'] as $nombre) {
            $id = (int) (DB::table('menu')
                ->where('nombre', $nombre)
                ->where('url', '#')
                ->orderBy('id')
                ->value('id') ?? 0);
            if ($id > 0) {
                return $id;
            }
        }

        return 0;
    }
};

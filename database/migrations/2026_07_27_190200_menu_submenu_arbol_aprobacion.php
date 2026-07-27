<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Agrupa bajo Configuración → «Árbol de aprobación»:
 * - Carga de árbol (antes «Arbol de Aprobación»)
 * - Reemplazo firmante
 */
return new class extends Migration
{
    private const PADRE_NOMBRE = 'Árbol de aprobación';

    private const CARGA_URL = 'configuracion/arbolaprobacion';

    private const REEMPLAZO_URL = 'configuracion/reemplazo-firmante-arbol';

    public function up(): void
    {
        $configId = $this->resolverMenuConfiguracionId();
        if ($configId === 0) {
            return;
        }

        $ordenPadre = (int) (DB::table('menu')->where('url', self::CARGA_URL)->value('orden') ?? 13);

        $padreId = (int) (DB::table('menu')
            ->where('menu_id', $configId)
            ->where('nombre', self::PADRE_NOMBRE)
            ->where('url', '#')
            ->value('id') ?? 0);

        if ($padreId === 0) {
            $padreId = (int) DB::table('menu')->insertGetId([
                'menu_id' => $configId,
                'nombre' => self::PADRE_NOMBRE,
                'url' => '#',
                'orden' => $ordenPadre,
                'icono' => 'fa-sitemap',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            DB::table('menu')->where('id', $padreId)->update([
                'menu_id' => $configId,
                'nombre' => self::PADRE_NOMBRE,
                'url' => '#',
                'orden' => $ordenPadre,
                'icono' => 'fa-sitemap',
                'updated_at' => now(),
            ]);
        }

        $cargaId = (int) (DB::table('menu')->where('url', self::CARGA_URL)->value('id') ?? 0);
        if ($cargaId > 0) {
            DB::table('menu')->where('id', $cargaId)->update([
                'menu_id' => $padreId,
                'nombre' => 'Carga de árbol',
                'orden' => 1,
                'icono' => 'fa-check',
                'updated_at' => now(),
            ]);
        }

        $reemplazoId = (int) (DB::table('menu')->where('url', self::REEMPLAZO_URL)->value('id') ?? 0);
        if ($reemplazoId > 0) {
            DB::table('menu')->where('id', $reemplazoId)->update([
                'menu_id' => $padreId,
                'nombre' => 'Reemplazo firmante',
                'orden' => 2,
                'icono' => 'fa-exchange-alt',
                'updated_at' => now(),
            ]);
        }

        $rolIds = collect();
        foreach ([$cargaId, $reemplazoId] as $hijoId) {
            if ($hijoId > 0) {
                $rolIds = $rolIds->merge(
                    DB::table('menu_rol')->where('menu_id', $hijoId)->pluck('rol_id')
                );
            }
        }
        $rolIds = $rolIds->map(fn ($id) => (int) $id)->unique()->values();

        foreach ($rolIds as $rolId) {
            if (! DB::table('menu_rol')->where('menu_id', $padreId)->where('rol_id', $rolId)->exists()) {
                DB::table('menu_rol')->insert(['menu_id' => $padreId, 'rol_id' => $rolId]);
            }
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        $configId = $this->resolverMenuConfiguracionId();
        $padreId = (int) (DB::table('menu')
            ->where('menu_id', $configId)
            ->where('nombre', self::PADRE_NOMBRE)
            ->where('url', '#')
            ->value('id') ?? 0);

        $ordenPadre = $padreId > 0
            ? (int) (DB::table('menu')->where('id', $padreId)->value('orden') ?? 13)
            : 13;

        $cargaId = (int) (DB::table('menu')->where('url', self::CARGA_URL)->value('id') ?? 0);
        if ($cargaId > 0) {
            DB::table('menu')->where('id', $cargaId)->update([
                'menu_id' => $configId,
                'nombre' => 'Arbol de Aprobación',
                'orden' => $ordenPadre,
                'icono' => 'fa-check',
                'updated_at' => now(),
            ]);
        }

        $reemplazoId = (int) (DB::table('menu')->where('url', self::REEMPLAZO_URL)->value('id') ?? 0);
        if ($reemplazoId > 0) {
            DB::table('menu')->where('id', $reemplazoId)->update([
                'menu_id' => $configId,
                'nombre' => 'Reemplazo firmante árbol',
                'orden' => $ordenPadre + 1,
                'icono' => 'fa-exchange-alt',
                'updated_at' => now(),
            ]);
        }

        if ($padreId > 0) {
            DB::table('menu_rol')->where('menu_id', $padreId)->delete();
            DB::table('menu')->where('id', $padreId)->delete();
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    private function resolverMenuConfiguracionId(): int
    {
        $id = (int) (DB::table('menu')->where('url', 'configuracion/empresa')->value('menu_id') ?? 0);
        if ($id > 0) {
            return $id;
        }

        foreach (['Configuración', 'Módulo Configuración', 'Configuracion'] as $nombre) {
            $id = (int) (DB::table('menu')->where('nombre', $nombre)->where('menu_id', 0)->value('id') ?? 0);
            if ($id > 0) {
                return $id;
            }
        }

        return 33;
    }
};

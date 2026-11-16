<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Menú + permisos Importaciones (Compras) y config tipos de gasto bajo Configuración por módulo → Compras.
 */
return new class extends Migration
{
    private const URL = 'compras/importacion';

    private const NOMBRE = 'Importaciones';

    private const ICONO = 'fa-ship';

    private const URL_HERMANO = 'compras/ordencompra';

    private const URL_CONFIG = 'compras/importacion-gasto-tipo';

    private const NOMBRE_CONFIG = 'Tipos de gasto importación';

    /** @var array<string, string> */
    private const PERMISOS = [
        'listar-importacion' => 'Listar importaciones',
        'crear-importacion' => 'Crear importaciones',
        'editar-importacion' => 'Editar importaciones',
        'actualizar-importacion' => 'Actualizar importaciones',
        'eliminar-importacion' => 'Eliminar importaciones',
        'nacionalizar-importacion' => 'Nacionalizar importaciones',
        'cerrar-costos-importacion' => 'Cerrar costos de importaciones',
        'listar-importacion-gasto-tipo' => 'Listar tipos de gasto importación',
        'crear-importacion-gasto-tipo' => 'Crear tipos de gasto importación',
        'editar-importacion-gasto-tipo' => 'Editar tipos de gasto importación',
        'actualizar-importacion-gasto-tipo' => 'Actualizar tipos de gasto importación',
        'eliminar-importacion-gasto-tipo' => 'Eliminar tipos de gasto importación',
    ];

    public function up(): void
    {
        $hermano = DB::table('menu')->where('url', self::URL_HERMANO)->first();
        if (! $hermano) {
            $comprasId = $this->resolverMenuComprasId();
            if ($comprasId === 0) {
                return;
            }
            $padreId = $comprasId;
            $orden = (int) (DB::table('menu')->where('menu_id', $padreId)->max('orden') ?? 0) + 1;
            $rolIds = $this->rolesComprasFallback();
        } else {
            $padreId = (int) $hermano->menu_id;
            $orden = (int) $hermano->orden + 1;
            $rolIds = DB::table('menu_rol')
                ->where('menu_id', $hermano->id)
                ->pluck('rol_id')
                ->map(fn ($id) => (int) $id)
                ->filter(fn ($id) => $id > 0)
                ->unique()
                ->values()
                ->all();
        }

        DB::table('menu')
            ->where('menu_id', $padreId)
            ->where('orden', '>=', $orden)
            ->where('url', '!=', self::URL)
            ->update(['orden' => DB::raw('orden + 1'), 'updated_at' => now()]);

        $menuId = $this->upsertMenu(self::URL, self::NOMBRE, self::ICONO, $padreId, $orden);

        foreach ($rolIds as $rolId) {
            if (! DB::table('menu_rol')->where('menu_id', $menuId)->where('rol_id', $rolId)->exists()) {
                DB::table('menu_rol')->insert(['menu_id' => $menuId, 'rol_id' => $rolId]);
            }
        }

        $configPadreId = $this->resolverConfigModuloComprasId();
        $configMenuId = 0;
        if ($configPadreId > 0) {
            $configOrden = (int) (DB::table('menu')->where('menu_id', $configPadreId)->max('orden') ?? 0) + 1;
            $configMenuId = $this->upsertMenu(self::URL_CONFIG, self::NOMBRE_CONFIG, 'fa-cog', $configPadreId, $configOrden);
            foreach ($rolIds as $rolId) {
                if (! DB::table('menu_rol')->where('menu_id', $configMenuId)->where('rol_id', $rolId)->exists()) {
                    DB::table('menu_rol')->insert(['menu_id' => $configMenuId, 'rol_id' => $rolId]);
                }
            }
        }

        foreach (self::PERMISOS as $slug => $nombre) {
            $esConfig = str_contains($slug, 'gasto-tipo');
            $targetMenuId = ($esConfig && $configMenuId > 0) ? $configMenuId : $menuId;
            $permisoId = $this->upsertPermiso($nombre, $slug, $targetMenuId);
            foreach ($rolIds as $rolId) {
                if (! DB::table('permiso_rol')->where('permiso_id', $permisoId)->where('rol_id', $rolId)->exists()) {
                    DB::table('permiso_rol')->insert(['permiso_id' => $permisoId, 'rol_id' => $rolId]);
                }
            }
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        $permisoIds = DB::table('permiso')->whereIn('slug', array_keys(self::PERMISOS))->pluck('id');
        if ($permisoIds->isNotEmpty()) {
            DB::table('permiso_rol')->whereIn('permiso_id', $permisoIds)->delete();
            DB::table('permiso')->whereIn('id', $permisoIds)->delete();
        }

        foreach ([self::URL_CONFIG, self::URL] as $url) {
            $menuId = (int) (DB::table('menu')->where('url', $url)->value('id') ?? 0);
            if ($menuId > 0) {
                DB::table('menu_rol')->where('menu_id', $menuId)->delete();
                DB::table('menu')->where('id', $menuId)->delete();
            }
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    private function resolverMenuComprasId(): int
    {
        return (int) (DB::table('menu')
            ->where('menu_id', 0)
            ->where('nombre', 'like', '%Compras%')
            ->orderBy('id')
            ->value('id') ?? 0);
    }

    private function resolverConfigModuloComprasId(): int
    {
        $id = (int) (DB::table('menu')->where('url', '#config-modulo-compras')->value('id') ?? 0);
        if ($id > 0) {
            return $id;
        }

        return (int) (DB::table('menu')
            ->where('nombre', 'Compras')
            ->where('url', 'like', '#config%')
            ->orderBy('id')
            ->value('id') ?? 0);
    }

    /** @return list<int> */
    private function rolesComprasFallback(): array
    {
        return DB::table('rol')
            ->where(function ($q) {
                $q->where('nombre', 'like', '%compras%')
                    ->orWhere('nombre', 'like', '%Compras%')
                    ->orWhere('nombre', 'like', '%administrador%')
                    ->orWhere('nombre', 'like', '%Admin%');
            })
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->all();
    }

    private function upsertMenu(string $url, string $nombre, string $icono, int $padreId, int $orden): int
    {
        $id = (int) (DB::table('menu')->where('url', $url)->value('id') ?? 0);
        $payload = [
            'nombre' => $nombre,
            'url' => $url,
            'menu_id' => $padreId,
            'orden' => $orden,
            'icono' => $icono,
            'updated_at' => now(),
        ];

        if ($id > 0) {
            DB::table('menu')->where('id', $id)->update($payload);

            return $id;
        }

        return (int) DB::table('menu')->insertGetId(array_merge($payload, ['created_at' => now()]));
    }

    private function upsertPermiso(string $nombre, string $slug, int $menuId): int
    {
        $id = (int) (DB::table('permiso')->where('slug', $slug)->value('id') ?? 0);
        $payload = ['nombre' => $nombre, 'slug' => $slug, 'menu_id' => $menuId, 'updated_at' => now()];

        if ($id > 0) {
            DB::table('permiso')->where('id', $id)->update($payload);

            return $id;
        }

        return (int) DB::table('permiso')->insertGetId(array_merge($payload, ['created_at' => now()]));
    }
};

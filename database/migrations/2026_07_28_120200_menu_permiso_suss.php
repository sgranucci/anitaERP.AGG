<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Menú proceso SUSS bajo Presentaciones ARCA.
 * Configuración: permisos sí, menú no (solo botón en la pantalla del proceso).
 */
return new class extends Migration
{
    private const SUBMENU_NOMBRE = 'Presentaciones ARCA';

    private const MENU_PADRE = 'Módulo Contable';

    private const URL_PROCESO = 'contable/suss';

    /** @var list<string> */
    private const ROLES = ['administrador', 'Enc-contaduría', 'Enc-impuestos', 'Op-impuestos'];

    public function up(): void
    {
        $submenuId = $this->resolverSubmenuId();
        if ($submenuId === 0) {
            return;
        }

        $ordenProceso = (int) (DB::table('menu')->where('menu_id', $submenuId)->max('orden') ?? 0) + 1;
        $menuProceso = $this->upsertMenu(
            self::URL_PROCESO,
            'SUSS',
            $submenuId,
            $ordenProceso,
            'fa-file-invoice-dollar',
        );

        $permisosProceso = [
            ['Listar SUSS', 'listar-suss'],
            ['Exportar SUSS', 'exportar-suss'],
        ];
        $permisosConfig = [
            ['Listar configuración SUSS', 'listar-suss-config'],
            ['Crear configuración SUSS', 'crear-suss-config'],
            ['Editar configuración SUSS', 'editar-suss-config'],
            ['Actualizar configuración SUSS', 'actualizar-suss-config'],
            ['Eliminar configuración SUSS', 'eliminar-suss-config'],
        ];

        $rolIds = $this->resolverRolIds();
        foreach ($rolIds as $rolId) {
            foreach ([$menuProceso, $submenuId] as $mid) {
                DB::table('menu_rol')->updateOrInsert(
                    ['menu_id' => $mid, 'rol_id' => $rolId],
                    []
                );
            }
        }

        foreach ($permisosProceso as [$nombre, $slug]) {
            $permisoId = $this->upsertPermiso($nombre, $slug, $menuProceso);
            foreach ($rolIds as $rolId) {
                DB::table('permiso_rol')->updateOrInsert(
                    ['permiso_id' => $permisoId, 'rol_id' => $rolId],
                    []
                );
            }
        }
        // Config: mismos permisos colgados del menú del proceso (sin entrada de menú propia).
        foreach ($permisosConfig as [$nombre, $slug]) {
            $permisoId = $this->upsertPermiso($nombre, $slug, $menuProceso);
            foreach ($rolIds as $rolId) {
                DB::table('permiso_rol')->updateOrInsert(
                    ['permiso_id' => $permisoId, 'rol_id' => $rolId],
                    []
                );
            }
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        $slugs = [
            'listar-suss', 'exportar-suss',
            'listar-suss-config', 'crear-suss-config',
            'editar-suss-config', 'actualizar-suss-config',
            'eliminar-suss-config',
        ];
        $permisoIds = DB::table('permiso')->whereIn('slug', $slugs)->pluck('id');
        if ($permisoIds->isNotEmpty()) {
            DB::table('permiso_rol')->whereIn('permiso_id', $permisoIds)->delete();
            DB::table('permiso')->whereIn('id', $permisoIds)->delete();
        }
        $menuIds = DB::table('menu')->where('url', self::URL_PROCESO)->pluck('id');
        if ($menuIds->isNotEmpty()) {
            DB::table('menu_rol')->whereIn('menu_id', $menuIds)->delete();
            DB::table('menu')->whereIn('id', $menuIds)->delete();
        }
        SuitecrmPermiso::flushCachePermisos();
    }

    private function resolverSubmenuId(): int
    {
        $padreId = (int) (DB::table('menu')
            ->where('nombre', self::MENU_PADRE)
            ->where('url', '#')
            ->orderBy('id')
            ->value('id') ?? 0);
        if ($padreId <= 0) {
            return 0;
        }

        return (int) (DB::table('menu')
            ->where('menu_id', $padreId)
            ->where('nombre', self::SUBMENU_NOMBRE)
            ->where('url', '#')
            ->value('id') ?? 0);
    }

    /** @return list<int> */
    private function resolverRolIds(): array
    {
        $ids = [];
        foreach (self::ROLES as $nombre) {
            $id = (int) (DB::table('rol')->where('nombre', $nombre)->value('id') ?? 0);
            if ($id > 0) {
                $ids[] = $id;
            }
        }

        return array_values(array_unique($ids));
    }

    private function upsertMenu(string $url, string $nombre, int $padreId, int $orden, string $icono): int
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
        $payload = [
            'nombre' => $nombre,
            'slug' => $slug,
            'menu_id' => $menuId,
            'updated_at' => now(),
        ];
        if ($id > 0) {
            DB::table('permiso')->where('id', $id)->update($payload);

            return $id;
        }

        return (int) DB::table('permiso')->insertGetId(array_merge($payload, ['created_at' => now()]));
    }
};

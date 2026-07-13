<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const MENU_PADRE_CONTABLE = 'Módulo Contable';

    private const MENU_PADRE_CONFIG = 'Módulo Configuración';

    /** @var list<string> */
    private const ROLES = ['administrador', 'Enc-contaduría', 'Enc-impuestos', 'Op-impuestos'];

    public function up(): void
    {
        $this->registrarMenuProceso();
        $this->registrarMenuConfig();
        SuitecrmPermiso::flushCachePermisos();
    }

    private function registrarMenuProceso(): void
    {
        $padreId = $this->resolverMenuId(self::MENU_PADRE_CONTABLE);
        if ($padreId === 0) {
            return;
        }

        $orden = (int) (DB::table('menu')->where('menu_id', $padreId)->max('orden') ?? 0) + 1;
        $menuId = $this->upsertMenu('contable/sicore', 'SICORE (presentación ARCA)', $padreId, $orden, 'fa-file-export');

        $permisoListar = $this->upsertPermiso('Listar proceso SICORE', 'listar-sicore', $menuId);
        $permisoExport = $this->upsertPermiso('Exportar archivo SICORE', 'exportar-sicore', $menuId);

        foreach ($this->resolverRolIds() as $rolId) {
            foreach ([$menuId, $padreId] as $mid) {
                if (! DB::table('menu_rol')->where('menu_id', $mid)->where('rol_id', $rolId)->exists()) {
                    DB::table('menu_rol')->insert(['menu_id' => $mid, 'rol_id' => $rolId]);
                }
            }
            foreach ([$permisoListar, $permisoExport] as $permisoId) {
                if (! DB::table('permiso_rol')->where('permiso_id', $permisoId)->where('rol_id', $rolId)->exists()) {
                    DB::table('permiso_rol')->insert(['permiso_id' => $permisoId, 'rol_id' => $rolId]);
                }
            }
        }
    }

    private function registrarMenuConfig(): void
    {
        $padreId = $this->resolverMenuId(self::MENU_PADRE_CONFIG);
        if ($padreId === 0) {
            return;
        }

        $orden = (int) (DB::table('menu')->where('menu_id', $padreId)->max('orden') ?? 0) + 1;
        $menuId = $this->upsertMenu('configuracion/sicore-config', 'Configuración SICORE', $padreId, $orden, 'fa-cogs');

        $permisos = [
            ['Listar configuración SICORE', 'listar-sicore-config'],
            ['Crear configuración SICORE', 'crear-sicore-config'],
            ['Editar configuración SICORE', 'editar-sicore-config'],
            ['Actualizar configuración SICORE', 'actualizar-sicore-config'],
            ['Eliminar configuración SICORE', 'eliminar-sicore-config'],
        ];

        foreach ($this->resolverRolIds() as $rolId) {
            if (! DB::table('menu_rol')->where('menu_id', $menuId)->where('rol_id', $rolId)->exists()) {
                DB::table('menu_rol')->insert(['menu_id' => $menuId, 'rol_id' => $rolId]);
            }
        }

        foreach ($permisos as [$nombre, $slug]) {
            $permisoId = $this->upsertPermiso($nombre, $slug, $menuId);
            foreach ($this->resolverRolIds() as $rolId) {
                if (! DB::table('permiso_rol')->where('permiso_id', $permisoId)->where('rol_id', $rolId)->exists()) {
                    DB::table('permiso_rol')->insert(['permiso_id' => $permisoId, 'rol_id' => $rolId]);
                }
            }
        }
    }

    private function resolverMenuId(string $nombre): int
    {
        return (int) (DB::table('menu')->where('nombre', $nombre)->value('id') ?? 0);
    }

    /**
     * @return list<int>
     */
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

        return (int) DB::table('menu')->insertGetId(array_merge($payload, [
            'created_at' => now(),
        ]));
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

        return (int) DB::table('permiso')->insertGetId(array_merge($payload, [
            'created_at' => now(),
        ]));
    }

    public function down(): void
    {
        $slugs = [
            'listar-sicore', 'exportar-sicore',
            'listar-sicore-config', 'crear-sicore-config', 'editar-sicore-config',
            'actualizar-sicore-config', 'eliminar-sicore-config',
        ];

        $permisoIds = DB::table('permiso')->whereIn('slug', $slugs)->pluck('id');
        if ($permisoIds->isNotEmpty()) {
            DB::table('permiso_rol')->whereIn('permiso_id', $permisoIds)->delete();
            DB::table('permiso')->whereIn('id', $permisoIds)->delete();
        }

        DB::table('menu')->whereIn('url', ['contable/sicore', 'configuracion/sicore-config'])->delete();
        SuitecrmPermiso::flushCachePermisos();
    }
};

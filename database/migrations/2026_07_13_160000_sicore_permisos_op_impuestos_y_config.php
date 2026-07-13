<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * SICORE: el rol operativo real (Op-impuestos) no estaba en el alta inicial
 * (solo Enc-impuestos, sin usuarios). Además faltaban los permisos del ABM
 * de configuración (listar-sicore-config, etc.), por lo que al entrar a
 * Configuración SICORE can() redirigía con "No tienes permisos…".
 */
return new class extends Migration
{
    /** @var list<string> */
    private const ROLES = [
        'administrador',
        'Enc-contaduría',
        'Enc-impuestos',
        'Op-impuestos',
    ];

    /** @var list<array{0: string, 1: string}> */
    private const PERMISOS_PROCESO = [
        ['Listar proceso SICORE', 'listar-sicore'],
        ['Exportar archivo SICORE', 'exportar-sicore'],
    ];

    /** @var list<array{0: string, 1: string}> */
    private const PERMISOS_CONFIG = [
        ['Listar configuración SICORE', 'listar-sicore-config'],
        ['Crear configuración SICORE', 'crear-sicore-config'],
        ['Editar configuración SICORE', 'editar-sicore-config'],
        ['Actualizar configuración SICORE', 'actualizar-sicore-config'],
        ['Eliminar configuración SICORE', 'eliminar-sicore-config'],
    ];

    public function up(): void
    {
        $menuProcesoId = (int) (DB::table('menu')->where('url', 'contable/sicore')->value('id') ?? 0);
        $menuConfigId = (int) (DB::table('menu')->where('url', 'contable/sicore-config')->value('id') ?? 0);
        $submenuId = (int) (DB::table('menu')
            ->where('nombre', 'Presentaciones ARCA')
            ->where('url', '#')
            ->value('id') ?? 0);

        $rolIds = $this->resolverRolIds();
        if ($rolIds === []) {
            return;
        }

        if ($menuProcesoId > 0) {
            foreach (self::PERMISOS_PROCESO as [$nombre, $slug]) {
                $this->asignarPermisoARoles($this->upsertPermiso($nombre, $slug, $menuProcesoId), $rolIds);
            }
            $this->asignarMenuARoles($menuProcesoId, $rolIds);
        }

        if ($menuConfigId > 0) {
            foreach (self::PERMISOS_CONFIG as [$nombre, $slug]) {
                $this->asignarPermisoARoles($this->upsertPermiso($nombre, $slug, $menuConfigId), $rolIds);
            }
            $this->asignarMenuARoles($menuConfigId, $rolIds);
        }

        if ($submenuId > 0) {
            $this->asignarMenuARoles($submenuId, $rolIds);
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        // No revierte asignaciones a Op-impuestos ni borra permisos de config:
        // pueden estar en uso operativo.
        SuitecrmPermiso::flushCachePermisos();
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

    /**
     * @param  list<int>  $rolIds
     */
    private function asignarMenuARoles(int $menuId, array $rolIds): void
    {
        foreach ($rolIds as $rolId) {
            if (! DB::table('menu_rol')->where('menu_id', $menuId)->where('rol_id', $rolId)->exists()) {
                DB::table('menu_rol')->insert(['menu_id' => $menuId, 'rol_id' => $rolId]);
            }
        }
    }

    /**
     * @param  list<int>  $rolIds
     */
    private function asignarPermisoARoles(int $permisoId, array $rolIds): void
    {
        if ($permisoId <= 0) {
            return;
        }

        foreach ($rolIds as $rolId) {
            if (! DB::table('permiso_rol')->where('permiso_id', $permisoId)->where('rol_id', $rolId)->exists()) {
                DB::table('permiso_rol')->insert(['permiso_id' => $permisoId, 'rol_id' => $rolId]);
            }
        }
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
};

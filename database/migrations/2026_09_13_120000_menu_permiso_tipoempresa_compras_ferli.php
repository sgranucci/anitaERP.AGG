<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Calzados Ferli: permisos del ABM compras/tipoempresa (faltaban en BD) y
 * menú ventas/tipoempresa (URL rota → oculto vía MenuFerliSupport; se desasigna).
 *
 * Canónico: Compras → Tablas de compras → Tipos de empresa (compras/tipoempresa).
 */
return new class extends Migration
{
    private const MENU_COMPRAS_URL = 'compras/tipoempresa';

    private const MENU_VENTAS_LEGACY_URL = 'ventas/tipoempresa';

    /** @var list<array{nombre: string, slug: string}> */
    private const PERMISOS = [
        ['nombre' => 'Crear tipo de empresa', 'slug' => 'crear-tipo-de-empresa'],
        ['nombre' => 'Listar tipos de empresa', 'slug' => 'listar-tipo-de-empresa'],
        ['nombre' => 'Editar tipo de empresa', 'slug' => 'editar-tipo-de-empresa'],
        ['nombre' => 'Actualizar tipo de empresa', 'slug' => 'actualizar-tipo-de-empresa'],
        ['nombre' => 'Borrar tipo de empresa', 'slug' => 'borrar-tipo-de-empresa'],
    ];

    /** @var list<string> */
    private const ROLES = [
        'administrador',
        'Enc-admin',
        'Enc-contaduría',
        'Admin-ventas',
        'Oficina',
    ];

    public function up(): void
    {
        if (! EntornoEmpresaSupport::esFerli()) {
            return;
        }

        $menuComprasId = (int) (DB::table('menu')->where('url', self::MENU_COMPRAS_URL)->value('id') ?? 0);
        if ($menuComprasId === 0) {
            return;
        }

        $rolIds = $this->resolverRolIds(self::ROLES);
        $this->asignarRolesMenu($menuComprasId, $rolIds);

        foreach (self::PERMISOS as $permiso) {
            $permisoId = $this->upsertPermiso($permiso['nombre'], $permiso['slug'], $menuComprasId);
            $this->asignarPermisoRoles($permisoId, $rolIds);
        }

        // Menú ventas legacy (404): quitar asignaciones; MenuFerliSupport lo oculta.
        $menuLegacyId = (int) (DB::table('menu')->where('url', self::MENU_VENTAS_LEGACY_URL)->value('id') ?? 0);
        if ($menuLegacyId > 0) {
            DB::table('menu_rol')->where('menu_id', $menuLegacyId)->delete();
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esFerli()) {
            return;
        }

        $slugs = array_column(self::PERMISOS, 'slug');
        $permisoIds = DB::table('permiso')->whereIn('slug', $slugs)->pluck('id')->all();
        $rolIds = $this->resolverRolIds(self::ROLES);

        foreach ($permisoIds as $permisoId) {
            DB::table('permiso_rol')
                ->where('permiso_id', $permisoId)
                ->whereIn('rol_id', $rolIds)
                ->delete();
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    private function upsertPermiso(string $nombre, string $slug, int $menuId): int
    {
        $id = (int) (DB::table('permiso')->where('slug', $slug)->value('id') ?? 0);
        $payload = [
            'nombre' => mb_substr($nombre, 0, 50),
            'menu_id' => $menuId > 0 ? $menuId : null,
            'updated_at' => now(),
        ];
        if ($id > 0) {
            DB::table('permiso')->where('id', $id)->update($payload);

            return $id;
        }

        return (int) DB::table('permiso')->insertGetId(array_merge($payload, [
            'slug' => $slug,
            'created_at' => now(),
        ]));
    }

    /**
     * @param  list<string>  $nombres
     * @return list<int>
     */
    private function resolverRolIds(array $nombres): array
    {
        $ids = [];
        foreach ($nombres as $nombre) {
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
    private function asignarRolesMenu(int $menuId, array $rolIds): void
    {
        if ($menuId <= 0) {
            return;
        }
        foreach ($rolIds as $rolId) {
            if ($rolId <= 0) {
                continue;
            }
            if (! DB::table('menu_rol')->where('menu_id', $menuId)->where('rol_id', $rolId)->exists()) {
                DB::table('menu_rol')->insert([
                    'menu_id' => $menuId,
                    'rol_id' => $rolId,
                ]);
            }
        }
    }

    /**
     * @param  list<int>  $rolIds
     */
    private function asignarPermisoRoles(int $permisoId, array $rolIds): void
    {
        if ($permisoId <= 0) {
            return;
        }
        foreach ($rolIds as $rolId) {
            if ($rolId <= 0) {
                continue;
            }
            if (! DB::table('permiso_rol')->where('permiso_id', $permisoId)->where('rol_id', $rolId)->exists()) {
                DB::table('permiso_rol')->insert([
                    'permiso_id' => $permisoId,
                    'rol_id' => $rolId,
                ]);
            }
        }
    }
};

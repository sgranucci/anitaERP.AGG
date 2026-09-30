<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Menú motivos de devolución (Configuración → Ventas) e historial (Facturación Local). Solo Ferli.
 * Atajo del ABM en Facturación Local: solo administrador.
 */
return new class extends Migration
{
    private const URL_MOTIVO = 'ventas/facturacion-local/motivos-devolucion';

    private const URL_HISTORIAL = 'ventas/facturacion-local/historial-devoluciones';

    private const GRUPO_URL = '#configuracion-por-modulo';

    private const MODULO_URL = '#config-modulo-ventas';

    private const PADRE_OPERATIVO = '#facturacion-local';

    /** @var list<array{nombre:string,slug:string}> */
    private const PERMISOS_MOTIVO = [
        ['nombre' => 'Listar motivos devolución FL', 'slug' => 'listar-motivo-devolucion-facturacion-local'],
        ['nombre' => 'Crear motivo devolución FL', 'slug' => 'crear-motivo-devolucion-facturacion-local'],
        ['nombre' => 'Editar motivo devolución FL', 'slug' => 'editar-motivo-devolucion-facturacion-local'],
        ['nombre' => 'Actualizar motivo devolución FL', 'slug' => 'actualizar-motivo-devolucion-facturacion-local'],
        ['nombre' => 'Eliminar motivo devolución FL', 'slug' => 'eliminar-motivo-devolucion-facturacion-local'],
    ];

    /** @var list<array{nombre:string,slug:string}> */
    private const PERMISOS_HISTORIAL = [
        ['nombre' => 'Listar historial devolución FL', 'slug' => 'listar-historial-devolucion-facturacion-local'],
    ];

    /** @var list<string> */
    private const ROLES_CONFIG = [
        'administrador',
        'Enc-sistemas',
        'Admin-ventas',
        'Enc-admin',
    ];

    /** @var list<string> */
    private const ROLES_HISTORIAL = [
        'administrador',
        'Admin-ventas',
        'Enc-admin',
        'Ventas',
    ];

    public function up(): void
    {
        if (! EntornoEmpresaSupport::esFerli()) {
            return;
        }

        $this->menuMotivos();
        $this->menuHistorial();
        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esFerli()) {
            return;
        }

        $slugs = array_merge(
            array_column(self::PERMISOS_MOTIVO, 'slug'),
            array_column(self::PERMISOS_HISTORIAL, 'slug'),
        );
        $permisoIds = DB::table('permiso')->whereIn('slug', $slugs)->pluck('id');
        if ($permisoIds->isNotEmpty()) {
            DB::table('permiso_rol')->whereIn('permiso_id', $permisoIds)->delete();
            DB::table('permiso')->whereIn('id', $permisoIds)->delete();
        }

        $menuIds = DB::table('menu')->whereIn('url', [self::URL_MOTIVO, self::URL_HISTORIAL])->pluck('id');
        if ($menuIds->isNotEmpty()) {
            DB::table('menu_rol')->whereIn('menu_id', $menuIds)->delete();
            DB::table('menu')->whereIn('id', $menuIds)->delete();
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    private function menuMotivos(): void
    {
        $configRootId = $this->resolverMenuConfiguracionId();
        $grupoId = (int) (DB::table('menu')->where('url', self::GRUPO_URL)->value('id') ?? 0);
        $moduloVentasId = (int) (DB::table('menu')->where('url', self::MODULO_URL)->value('id') ?? 0);
        if ($configRootId === 0 || $grupoId === 0 || $moduloVentasId === 0) {
            return;
        }

        $orden = (int) (DB::table('menu')->where('menu_id', $moduloVentasId)->max('orden') ?? 0) + 1;
        $menuId = $this->upsertMenu(self::URL_MOTIVO, 'Motivos de devolución', $moduloVentasId, $orden, 'fa-undo');
        $rolIds = $this->resolverRolIds(self::ROLES_CONFIG);
        $this->reemplazarMenuRoles($menuId, $rolIds);
        $this->asignarRolesMenu($moduloVentasId, $rolIds);
        $this->asignarRolesMenu($grupoId, $rolIds);
        $this->asignarRolesMenu($configRootId, $rolIds);

        foreach (self::PERMISOS_MOTIVO as $permiso) {
            $permisoId = $this->upsertPermiso($permiso['nombre'], $permiso['slug'], $menuId);
            foreach ($rolIds as $rolId) {
                if (! DB::table('permiso_rol')->where('permiso_id', $permisoId)->where('rol_id', $rolId)->exists()) {
                    DB::table('permiso_rol')->insert(['permiso_id' => $permisoId, 'rol_id' => $rolId]);
                }
            }
        }

        $padreOpId = (int) (DB::table('menu')->where('url', self::PADRE_OPERATIVO)->orderBy('id')->value('id') ?? 0);
        $adminId = (int) (DB::table('rol')->where('nombre', 'administrador')->value('id') ?? 0);
        if ($padreOpId > 0 && $adminId > 0) {
            $atajoId = (int) (DB::table('menu')
                ->where('menu_id', $padreOpId)
                ->where('url', self::URL_MOTIVO)
                ->where('id', '!=', $menuId)
                ->value('id') ?? 0);
            if ($atajoId === 0) {
                $ordenAtajo = (int) (DB::table('menu')->where('menu_id', $padreOpId)->max('orden') ?? 0) + 1;
                $atajoId = (int) DB::table('menu')->insertGetId([
                    'menu_id' => $padreOpId,
                    'nombre' => 'Motivos de devolución',
                    'url' => self::URL_MOTIVO,
                    'orden' => $ordenAtajo,
                    'icono' => 'fa-undo',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
            $this->reemplazarMenuRoles($atajoId, [$adminId]);
        }
    }

    private function menuHistorial(): void
    {
        $padreId = (int) (DB::table('menu')->where('url', self::PADRE_OPERATIVO)->orderBy('id')->value('id') ?? 0);
        if ($padreId <= 0) {
            return;
        }

        $orden = (int) (DB::table('menu')->where('menu_id', $padreId)->max('orden') ?? 0) + 1;
        $menuId = $this->upsertMenu(self::URL_HISTORIAL, 'Historial de devoluciones', $padreId, $orden, 'fa-history');
        $rolIds = $this->resolverRolIds(self::ROLES_HISTORIAL);
        $this->reemplazarMenuRoles($menuId, $rolIds);
        $this->asignarRolesMenu($padreId, $rolIds);

        foreach (self::PERMISOS_HISTORIAL as $permiso) {
            $permisoId = $this->upsertPermiso($permiso['nombre'], $permiso['slug'], $menuId);
            foreach ($rolIds as $rolId) {
                if (! DB::table('permiso_rol')->where('permiso_id', $permisoId)->where('rol_id', $rolId)->exists()) {
                    DB::table('permiso_rol')->insert(['permiso_id' => $permisoId, 'rol_id' => $rolId]);
                }
            }
        }
    }

    private function resolverMenuConfiguracionId(): int
    {
        $id = (int) (DB::table('menu')->where('menu_id', 0)->where('nombre', 'Configuración')->orderBy('id')->value('id') ?? 0);
        if ($id > 0) {
            return $id;
        }

        return (int) (DB::table('menu')->where('menu_id', 0)->where('nombre', 'Configuracion')->orderBy('id')->value('id') ?? 0);
    }

    private function upsertMenu(string $url, string $nombre, int $padre, int $orden, string $icono): int
    {
        $id = (int) (DB::table('menu')->where('url', $url)->where('menu_id', $padre)->value('id') ?? 0);
        if ($id === 0) {
            return (int) DB::table('menu')->insertGetId([
                'menu_id' => $padre,
                'nombre' => $nombre,
                'url' => $url,
                'orden' => $orden,
                'icono' => $icono,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('menu')->where('id', $id)->update([
            'nombre' => $nombre,
            'orden' => $orden,
            'icono' => $icono,
            'updated_at' => now(),
        ]);

        return $id;
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
                DB::table('menu_rol')->insert(['menu_id' => $menuId, 'rol_id' => $rolId]);
            }
        }
    }

    /**
     * @param  list<int>  $rolIds
     */
    private function reemplazarMenuRoles(int $menuId, array $rolIds): void
    {
        if ($menuId <= 0) {
            return;
        }
        DB::table('menu_rol')->where('menu_id', $menuId)->delete();
        $this->asignarRolesMenu($menuId, $rolIds);
    }
};

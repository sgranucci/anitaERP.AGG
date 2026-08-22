<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Menú Seguridad: carga de tickets de ingreso y tablas de catálogo.
 */
return new class extends Migration
{
    private const MENU_MODULO_NOMBRE = 'Seguridad';

    private const MENU_CONTROL_URL = 'seguridad/control-ingreso';

    private const MENU_CONTROL_NOMBRE = 'Control de ingreso';

    private const MENU_CARGA_URL = 'seguridad/ingreso-proveedor';

    private const MENU_CARGA_NOMBRE = 'Ingreso de proveedores';

    /** @var array<string, array{nombre: string, url: string}> */
    private const CATALOGOS = [
        'punto' => ['nombre' => 'Puntos de ingreso', 'url' => 'seguridad/ingreso-proveedor-punto'],
        'area' => ['nombre' => 'Áreas de destino', 'url' => 'seguridad/ingreso-proveedor-area'],
        'motivo' => ['nombre' => 'Motivos de visita', 'url' => 'seguridad/ingreso-proveedor-motivo'],
        'sector' => ['nombre' => 'Sectores de ingreso', 'url' => 'seguridad/ingreso-proveedor-sector'],
    ];

    /** @var array<string, string> slug => nombre */
    private const PERMISOS_CARGA = [
        'listar-ingreso-proveedor' => 'Listar ingreso de proveedor',
        'crear-ingreso-proveedor' => 'Crear ingreso de proveedor',
        'editar-ingreso-proveedor' => 'Editar ingreso de proveedor',
        'actualizar-ingreso-proveedor' => 'Actualizar ingreso de proveedor',
        'borrar-ingreso-proveedor' => 'Borrar ingreso de proveedor',
        'autorizar-ingreso-proveedor' => 'Autorizar / registrar ingreso-egreso',
    ];

    /** @var array<string, string> */
    private const PERMISOS_CATALOGO = [
        'listar-ingreso-proveedor-catalogo' => 'Listar tablas de ingreso de proveedor',
        'crear-ingreso-proveedor-catalogo' => 'Crear tablas de ingreso de proveedor',
        'editar-ingreso-proveedor-catalogo' => 'Editar tablas de ingreso de proveedor',
        'actualizar-ingreso-proveedor-catalogo' => 'Actualizar tablas de ingreso de proveedor',
        'borrar-ingreso-proveedor-catalogo' => 'Borrar tablas de ingreso de proveedor',
    ];

    /** @var list<string> */
    private const ROLES_COMPRAS = [
        'administrador',
        'Enc-compras',
        'Op-Compras',
    ];

    /** @var list<string> */
    private const ROLES_SEGURIDAD = [
        'administrador',
        'enc-SEGURIDAD',
    ];

    public function up(): void
    {
        $moduloId = $this->asegurarMenuModulo();
        $controlId = $this->asegurarMenuHijo($moduloId, self::MENU_CONTROL_NOMBRE, self::MENU_CONTROL_URL, 'fa-shield', 1);
        $cargaId = $this->asegurarMenuHijo($moduloId, self::MENU_CARGA_NOMBRE, self::MENU_CARGA_URL, 'fa-id-badge', 2);

        $permisosCarga = $this->asegurarPermisos(self::PERMISOS_CARGA, $cargaId);
        $this->asignarRoles($permisosCarga, self::ROLES_COMPRAS);
        $this->asignarRoles([
            $permisosCarga['listar-ingreso-proveedor'] ?? 0,
            $permisosCarga['editar-ingreso-proveedor'] ?? 0,
            $permisosCarga['autorizar-ingreso-proveedor'] ?? 0,
        ], self::ROLES_SEGURIDAD);
        $this->asignarMenus([$controlId], self::ROLES_SEGURIDAD);

        $orden = 3;
        $catalogoMenuIds = [];
        foreach (self::CATALOGOS as $def) {
            $catalogoMenuIds[] = $this->asegurarMenuHijo($moduloId, $def['nombre'], $def['url'], 'fa-list', $orden);
            $orden++;
        }
        $primerCatalogoId = (int) ($catalogoMenuIds[0] ?? 0);

        $permisosCatalogo = $this->asegurarPermisos(self::PERMISOS_CATALOGO, $primerCatalogoId);
        $rolesTablas = array_values(array_unique(array_merge(self::ROLES_COMPRAS, self::ROLES_SEGURIDAD)));
        $this->asignarRoles($permisosCatalogo, $rolesTablas);
        $this->asignarMenus($catalogoMenuIds, $rolesTablas);

        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        $urls = array_merge(
            [self::MENU_CONTROL_URL, self::MENU_CARGA_URL],
            array_column(self::CATALOGOS, 'url')
        );
        $menuIds = DB::table('menu')->whereIn('url', $urls)->pluck('id');
        $slugs = array_merge(array_keys(self::PERMISOS_CARGA), array_keys(self::PERMISOS_CATALOGO));
        $permisoIds = DB::table('permiso')->whereIn('slug', $slugs)->pluck('id');

        if ($permisoIds->isNotEmpty()) {
            DB::table('permiso_rol')->whereIn('permiso_id', $permisoIds)->delete();
            DB::table('permiso')->whereIn('id', $permisoIds)->delete();
        }
        if ($menuIds->isNotEmpty()) {
            DB::table('menu_rol')->whereIn('menu_id', $menuIds)->delete();
            DB::table('menu')->whereIn('id', $menuIds)->delete();
        }

        $moduloId = (int) (DB::table('menu')
            ->where('menu_id', 0)
            ->where('nombre', self::MENU_MODULO_NOMBRE)
            ->value('id') ?? 0);
        if ($moduloId > 0 && ! DB::table('menu')->where('menu_id', $moduloId)->exists()) {
            DB::table('menu_rol')->where('menu_id', $moduloId)->delete();
            DB::table('menu')->where('id', $moduloId)->delete();
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    private function asegurarMenuModulo(): int
    {
        $id = (int) (DB::table('menu')
            ->where('menu_id', 0)
            ->where('nombre', self::MENU_MODULO_NOMBRE)
            ->value('id') ?? 0);
        if ($id > 0) {
            return $id;
        }

        return (int) DB::table('menu')->insertGetId([
            'menu_id' => 0,
            'nombre' => self::MENU_MODULO_NOMBRE,
            'url' => '#',
            'orden' => (int) (DB::table('menu')->where('menu_id', 0)->max('orden') ?? 0) + 1,
            'icono' => 'fa-shield',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function asegurarMenuHijo(int $padreId, string $nombre, string $url, string $icono, int $orden): int
    {
        $id = (int) (DB::table('menu')->where('url', $url)->value('id') ?? 0);
        if ($id > 0) {
            DB::table('menu')->where('id', $id)->update([
                'menu_id' => $padreId,
                'nombre' => $nombre,
                'updated_at' => now(),
            ]);

            return $id;
        }

        return (int) DB::table('menu')->insertGetId([
            'menu_id' => $padreId,
            'nombre' => $nombre,
            'url' => $url,
            'orden' => $orden,
            'icono' => $icono,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * @param  array<string, string>  $permisos
     * @return array<string, int>
     */
    private function asegurarPermisos(array $permisos, int $menuId): array
    {
        $ids = [];
        foreach ($permisos as $slug => $nombre) {
            $id = (int) (DB::table('permiso')->where('slug', $slug)->value('id') ?? 0);
            if ($id === 0) {
                $id = (int) DB::table('permiso')->insertGetId([
                    'nombre' => $nombre,
                    'slug' => $slug,
                    'menu_id' => $menuId > 0 ? $menuId : null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                DB::table('permiso')->where('id', $id)->update([
                    'menu_id' => $menuId > 0 ? $menuId : null,
                    'updated_at' => now(),
                ]);
            }
            $ids[$slug] = $id;
        }

        return $ids;
    }

    /**
     * @param  list<int>  $menuIds
     * @param  list<string>  $roles
     */
    private function asignarMenus(array $menuIds, array $roles): void
    {
        $rolIds = DB::table('rol')->whereIn('nombre', $roles)->pluck('id');
        foreach ($rolIds as $rolId) {
            foreach ($menuIds as $menuId) {
                $menuId = (int) $menuId;
                if ($menuId <= 0) {
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
    }

    /**
     * @param  array<int|string, int>  $permisoIds
     * @param  list<string>  $roles
     */
    private function asignarRoles(array $permisoIds, array $roles): void
    {
        $ids = array_values(array_filter(array_map('intval', $permisoIds)));
        if ($ids === []) {
            return;
        }

        $rolIds = DB::table('rol')->whereIn('nombre', $roles)->pluck('id');
        foreach ($rolIds as $rolId) {
            foreach ($ids as $permisoId) {
                if ($permisoId <= 0) {
                    continue;
                }
                if (! DB::table('permiso_rol')->where('permiso_id', $permisoId)->where('rol_id', $rolId)->exists()) {
                    DB::table('permiso_rol')->insert([
                        'permiso_id' => $permisoId,
                        'rol_id' => $rolId,
                    ]);
                }
            }
            $menuIds = DB::table('permiso')->whereIn('id', $ids)->pluck('menu_id')->filter();
            $moduloId = (int) (DB::table('menu')
                ->where('menu_id', 0)
                ->where('nombre', self::MENU_MODULO_NOMBRE)
                ->value('id') ?? 0);
            foreach ($menuIds->push($moduloId) as $menuId) {
                $menuId = (int) $menuId;
                if ($menuId <= 0) {
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
    }
};

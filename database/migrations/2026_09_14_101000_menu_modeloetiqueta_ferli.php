<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Ferli: diseñador de modelos de etiqueta (ZPL) bajo Configuración por módulo → Stock.
 * Usado en emisión de etiquetas de stock (combinación / talle / cantidad).
 */
return new class extends Migration
{
    private const GRUPO_URL = '#configuracion-por-modulo';

    private const MODULO_URL = '#config-modulo-stock';

    private const MENU_URL = 'configuracion/modeloetiqueta';

    private const MENU_NOMBRE = 'Diseñador de etiquetas';

    /** @var list<array{nombre:string,slug:string}> */
    private const PERMISOS = [
        ['nombre' => 'Listar modelos de etiquetas', 'slug' => 'listar-modeloetiqueta'],
        ['nombre' => 'Ingresar modelos de etiquetas', 'slug' => 'crear-modeloetiqueta'],
        ['nombre' => 'Editar modelos de etiquetas', 'slug' => 'editar-modeloetiqueta'],
        ['nombre' => 'Actualizar modelos de etiquetas', 'slug' => 'actualizar-modeloetiqueta'],
        ['nombre' => 'Borrar modelos de etiquetas', 'slug' => 'borrar-modeloetiqueta'],
    ];

    /** @var list<string> */
    private const ROLES = [
        'administrador',
        'Enc-admin',
        'Admin-ventas',
        'Enc-stock',
        'Stock',
    ];

    public function up(): void
    {
        if (! EntornoEmpresaSupport::esFerli()) {
            return;
        }

        $moduloId = $this->resolverModuloStockId();
        if ($moduloId === 0) {
            return;
        }

        $orden = (int) (DB::table('menu')->where('menu_id', $moduloId)->max('orden') ?? 0) + 1;
        $menuId = $this->upsertMenu(self::MENU_URL, self::MENU_NOMBRE, $moduloId, $orden, 'fa-tags');

        $rolIds = $this->resolverRolIds(self::ROLES);
        $this->asignarRolesMenu($menuId, $rolIds);
        $this->asignarRolesMenu($moduloId, $rolIds);

        $grupoId = (int) (DB::table('menu')->where('url', self::GRUPO_URL)->orderBy('id')->value('id') ?? 0);
        if ($grupoId > 0) {
            $this->asignarRolesMenu($grupoId, $rolIds);
        }

        foreach (self::PERMISOS as $permiso) {
            $permisoId = $this->upsertPermiso($permiso['nombre'], $permiso['slug'], $menuId);
            $this->asignarPermisoRoles($permisoId, $rolIds);
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esFerli()) {
            return;
        }

        $slugs = array_column(self::PERMISOS, 'slug');
        $permisoIds = DB::table('permiso')->whereIn('slug', $slugs)->pluck('id');
        if ($permisoIds->isNotEmpty()) {
            DB::table('permiso_rol')->whereIn('permiso_id', $permisoIds)->delete();
            // No borrar permisos: pueden existir en otros clientes; solo desasignar roles Ferli.
        }

        $menuId = (int) (DB::table('menu')->where('url', self::MENU_URL)->orderBy('id')->value('id') ?? 0);
        if ($menuId > 0) {
            DB::table('menu_rol')->where('menu_id', $menuId)->delete();
            DB::table('menu')->where('id', $menuId)->delete();
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    private function resolverModuloStockId(): int
    {
        $id = (int) (DB::table('menu')->where('url', self::MODULO_URL)->orderBy('id')->value('id') ?? 0);
        if ($id > 0) {
            return $id;
        }

        $grupoId = (int) (DB::table('menu')->where('url', self::GRUPO_URL)->orderBy('id')->value('id') ?? 0);
        if ($grupoId === 0) {
            return 0;
        }

        return (int) DB::table('menu')->insertGetId([
            'menu_id' => $grupoId,
            'nombre' => 'Stock',
            'url' => self::MODULO_URL,
            'orden' => 4,
            'icono' => 'fa-cubes',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function upsertMenu(string $url, string $nombre, int $padre, int $orden, string $icono): int
    {
        $id = (int) (DB::table('menu')->where('url', $url)->where('menu_id', $padre)->value('id') ?? 0);
        if ($id === 0) {
            $id = (int) (DB::table('menu')->where('url', $url)->orderBy('id')->value('id') ?? 0);
        }

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
            'menu_id' => $padre,
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
        if ($menuId <= 0 || $rolIds === []) {
            return;
        }
        foreach ($rolIds as $rolId) {
            $existe = DB::table('menu_rol')
                ->where('menu_id', $menuId)
                ->where('rol_id', $rolId)
                ->exists();
            if (! $existe) {
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
        if ($permisoId <= 0 || $rolIds === []) {
            return;
        }
        foreach ($rolIds as $rolId) {
            $existe = DB::table('permiso_rol')
                ->where('permiso_id', $permisoId)
                ->where('rol_id', $rolId)
                ->exists();
            if (! $existe) {
                DB::table('permiso_rol')->insert([
                    'permiso_id' => $permisoId,
                    'rol_id' => $rolId,
                ]);
            }
        }
    }
};

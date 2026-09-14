<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Calzados Ferli: el Módulo Contable no tenía «Tablas Contable» ni las hojas
 * de maestros (centros de costo, tipos de asiento, cuentas por usuario) ni
 * Asientos. En AGG venían del dump inicial.
 *
 * Solo Ferli: no toca AGG ni otros clientes.
 */
return new class extends Migration
{
    private const MODULO_NOMBRE = 'Módulo Contable';

    private const TABLAS_URL = '#tablas-contable';

    private const TABLAS_NOMBRE = 'Tablas Contable';

    private const ASIENTO_URL = 'contable/asiento';

    /** @var list<string> */
    private const ROLES = [
        'administrador',
        'Enc-admin',
        'Enc-contaduría',
    ];

    /**
     * Maestros bajo Tablas Contable.
     *
     * @var list<array{
     *     url: string,
     *     nombre: string,
     *     icono: string,
     *     orden: int,
     *     permisos: list<array{nombre: string, slug: string}>
     * }>
     */
    private const TABLAS = [
        [
            'url' => 'contable/rubrocontable',
            'nombre' => 'Rubros contables',
            'icono' => 'fa-list',
            'orden' => 1,
            'permisos' => [
                ['nombre' => 'Listar rubros contables', 'slug' => 'listar-rubros-contables'],
                ['nombre' => 'Crear rubros contables', 'slug' => 'crear-rubros-contables'],
                ['nombre' => 'Editar rubros contables', 'slug' => 'editar-rubros-contables'],
                ['nombre' => 'Actualizar rubros contables', 'slug' => 'actualizar-rubros-contables'],
                ['nombre' => 'Borrar rubros contables', 'slug' => 'borrar-rubros-contables'],
            ],
        ],
        [
            'url' => 'contable/centrocosto',
            'nombre' => 'Centros de Costo',
            'icono' => 'fa-sitemap',
            'orden' => 2,
            'permisos' => [
                ['nombre' => 'Listar centros de costo', 'slug' => 'listar-centro-costo'],
                ['nombre' => 'Crear centros de costo', 'slug' => 'crear-centro-costo'],
                ['nombre' => 'Editar centros de costo', 'slug' => 'editar-centro-costo'],
                ['nombre' => 'Actualizar centros de costo', 'slug' => 'actualizar-centro-costo'],
                ['nombre' => 'Borrar centros de costo', 'slug' => 'borrar-centro-costo'],
            ],
        ],
        [
            'url' => 'contable/tipoasiento',
            'nombre' => 'Tipos de asiento',
            'icono' => 'fa-tags',
            'orden' => 3,
            'permisos' => [
                ['nombre' => 'Listar tipos de asiento', 'slug' => 'listar-tipo-asiento'],
                ['nombre' => 'Crear tipos de asiento', 'slug' => 'crear-tipo-asiento'],
                ['nombre' => 'Editar tipos de asiento', 'slug' => 'editar-tipo-asiento'],
                ['nombre' => 'Actualizar tipos de asiento', 'slug' => 'actualizar-tipo-asiento'],
                ['nombre' => 'Borrar tipos de asiento', 'slug' => 'borrar-tipo-asiento'],
            ],
        ],
        [
            'url' => 'contable/usuario_cuentacontable',
            'nombre' => 'Cuentas por usuario',
            'icono' => 'fa-user',
            'orden' => 4,
            'permisos' => [
                ['nombre' => 'Listar usuario cuenta contable', 'slug' => 'listar-usuario-cuentacontable'],
                ['nombre' => 'Crear usuario cuenta contable', 'slug' => 'crear-usuario-cuentacontable'],
                ['nombre' => 'Editar usuario cuenta contable', 'slug' => 'editar-usuario-cuentacontable'],
                ['nombre' => 'Actualizar usuario cuenta contable', 'slug' => 'actualizar-usuario-cuentacontable'],
                ['nombre' => 'Borrar usuario cuenta contable', 'slug' => 'borrar-usuario-cuentacontable'],
            ],
        ],
    ];

    /** @var list<array{nombre: string, slug: string}> */
    private const PERMISOS_ASIENTO = [
        ['nombre' => 'Listar asientos', 'slug' => 'listar-asiento'],
        ['nombre' => 'Crear asientos', 'slug' => 'crear-asiento'],
        ['nombre' => 'Editar asientos', 'slug' => 'editar-asiento'],
        ['nombre' => 'Actualizar asientos', 'slug' => 'actualizar-asiento'],
        ['nombre' => 'Borrar asientos', 'slug' => 'borrar-asiento'],
    ];

    public function up(): void
    {
        if (! EntornoEmpresaSupport::esFerli()) {
            return;
        }

        $moduloId = $this->idModuloContable();
        if ($moduloId <= 0) {
            return;
        }

        $rolIds = $this->resolverRolIds();
        $this->asignarRolesMenu($moduloId, $rolIds);

        $tablasId = $this->upsertMenu(
            self::TABLAS_URL,
            self::TABLAS_NOMBRE,
            $moduloId,
            1,
            'fa-table'
        );
        $this->asignarRolesMenu($tablasId, $rolIds);

        foreach (self::TABLAS as $hoja) {
            $menuId = $this->upsertMenu(
                $hoja['url'],
                $hoja['nombre'],
                $tablasId,
                $hoja['orden'],
                $hoja['icono']
            );
            $this->asignarRolesMenu($menuId, $rolIds);
            foreach ($hoja['permisos'] as $permiso) {
                $permisoId = $this->upsertPermiso($permiso['nombre'], $permiso['slug'], $menuId);
                $this->asignarRolesPermiso($permisoId, $rolIds);
            }
        }

        $asientoExiste = DB::table('menu')->where('url', self::ASIENTO_URL)->exists();
        if (! $asientoExiste) {
            DB::table('menu')
                ->where('menu_id', $moduloId)
                ->where('orden', '>=', 3)
                ->increment('orden');
        }

        $asientoId = $this->upsertMenu(
            self::ASIENTO_URL,
            'Asientos',
            $moduloId,
            3,
            'fa-book'
        );
        $this->asignarRolesMenu($asientoId, $rolIds);
        foreach (self::PERMISOS_ASIENTO as $permiso) {
            $permisoId = $this->upsertPermiso($permiso['nombre'], $permiso['slug'], $asientoId);
            $this->asignarRolesPermiso($permisoId, $rolIds);
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esFerli()) {
            return;
        }

        $moduloId = $this->idModuloContable();
        $rubroId = (int) (DB::table('menu')->where('url', 'contable/rubrocontable')->value('id') ?? 0);
        if ($moduloId > 0 && $rubroId > 0) {
            DB::table('menu')->where('id', $rubroId)->update([
                'menu_id' => $moduloId,
                'orden' => 1,
                'updated_at' => now(),
            ]);
        }

        $urlsNuevas = [
            self::ASIENTO_URL,
            'contable/centrocosto',
            'contable/tipoasiento',
            'contable/usuario_cuentacontable',
            self::TABLAS_URL,
        ];
        foreach ($urlsNuevas as $url) {
            $menuId = (int) (DB::table('menu')->where('url', $url)->value('id') ?? 0);
            if ($menuId <= 0) {
                continue;
            }
            DB::table('permiso')->where('menu_id', $menuId)->update([
                'menu_id' => null,
                'updated_at' => now(),
            ]);
            DB::table('menu_rol')->where('menu_id', $menuId)->delete();
            DB::table('menu')->where('id', $menuId)->delete();
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    private function idModuloContable(): int
    {
        return (int) (DB::table('menu')
            ->where('menu_id', 0)
            ->where('url', '#')
            ->where('nombre', self::MODULO_NOMBRE)
            ->value('id') ?? 0);
    }

    private function upsertMenu(string $url, string $nombre, int $padre, int $orden, string $icono): int
    {
        $id = (int) (DB::table('menu')->where('url', $url)->value('id') ?? 0);
        if ($id === 0 && $url === self::TABLAS_URL) {
            $id = (int) (DB::table('menu')
                ->where('menu_id', $padre)
                ->where('nombre', self::TABLAS_NOMBRE)
                ->value('id') ?? 0);
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
            'url' => $url,
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
            'nombre' => $nombre,
            'menu_id' => $menuId,
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
     * @return list<int>
     */
    private function resolverRolIds(): array
    {
        return DB::table('rol')
            ->whereIn('nombre', self::ROLES)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
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
    private function asignarRolesPermiso(int $permisoId, array $rolIds): void
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

<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Ferli: crea el menú raíz «Módulo de Presupuesto» con ABMs y mueve «Reporte CAPEX»
 * desde Reportes Contables hacia este módulo.
 *
 * No aplica en AGG ni otros clientes (ya tienen menú de presupuesto).
 */
return new class extends Migration
{
    private const MODULO_NOMBRE = 'Módulo de Presupuesto';

    private const MODULO_ICONO = 'fa-calculator';

    /** @var list<string> */
    private const ROLES = [
        'administrador',
        'Enc-admin',
        'Enc-contaduría',
    ];

    /**
     * Hojas del módulo: url, nombre, icono, orden, permisos.
     *
     * @var list<array{
     *     url: string,
     *     nombre: string,
     *     icono: string,
     *     orden: int,
     *     permisos: list<array{nombre: string, slug: string}>
     * }>
     */
    private const HOJAS = [
        [
            'url' => 'presupuesto/presupuesto',
            'nombre' => 'Presupuestos',
            'icono' => 'fa-calendar',
            'orden' => 1,
            'permisos' => [
                ['nombre' => 'Listar presupuestos', 'slug' => 'listar-presupuesto'],
                ['nombre' => 'Crear presupuestos', 'slug' => 'crear-presupuesto'],
                ['nombre' => 'Editar presupuestos', 'slug' => 'editar-presupuesto'],
                ['nombre' => 'Actualizar presupuestos', 'slug' => 'actualizar-presupuesto'],
                ['nombre' => 'Borrar presupuestos', 'slug' => 'borrar-presupuesto'],
            ],
        ],
        [
            'url' => 'presupuesto/capex',
            'nombre' => 'Capex',
            'icono' => 'fa-project-diagram',
            'orden' => 2,
            'permisos' => [
                ['nombre' => 'Listar Capex', 'slug' => 'listar-capex'],
                ['nombre' => 'Crear Capex', 'slug' => 'crear-capex'],
                ['nombre' => 'Editar Capex', 'slug' => 'editar-capex'],
                ['nombre' => 'Actualizar Capex', 'slug' => 'actualizar-capex'],
                ['nombre' => 'Borrar Capex', 'slug' => 'borrar-capex'],
            ],
        ],
        [
            'url' => 'presupuesto/partidagasto',
            'nombre' => 'Partidas de gasto',
            'icono' => 'fa-list',
            'orden' => 3,
            'permisos' => [
                ['nombre' => 'Listar partidas de gasto', 'slug' => 'listar-partidagasto'],
                ['nombre' => 'Crear partidas de gasto', 'slug' => 'crear-partidagasto'],
                ['nombre' => 'Editar partidas de gasto', 'slug' => 'editar-partidagasto'],
                ['nombre' => 'Actualizar partidas de gasto', 'slug' => 'actualizar-partidagasto'],
                ['nombre' => 'Borrar partidas de gasto', 'slug' => 'borrar-partidagasto'],
            ],
        ],
        [
            'url' => 'presupuesto/generaasiento',
            'nombre' => 'Generar asientos',
            'icono' => 'fa-book',
            'orden' => 4,
            'permisos' => [
                ['nombre' => 'Generar asientos de partidas de gasto', 'slug' => 'generar-asientos-partidagasto'],
            ],
        ],
        [
            'url' => 'presupuesto/capex-reporte',
            'nombre' => 'Reporte CAPEX',
            'icono' => 'fa-file-invoice-dollar',
            'orden' => 5,
            'permisos' => [
                ['nombre' => 'Listar reporte CAPEX', 'slug' => 'listar-capex-reporte'],
            ],
        ],
    ];

    public function up(): void
    {
        if (! EntornoEmpresaSupport::esFerli()) {
            return;
        }

        $moduloId = $this->upsertModuloRaiz();
        $rolIds = $this->resolverRolIds();
        $this->asignarRolesMenu($moduloId, $rolIds);

        foreach (self::HOJAS as $hoja) {
            $menuId = $this->upsertMenuHijo(
                $moduloId,
                $hoja['url'],
                $hoja['nombre'],
                $hoja['orden'],
                $hoja['icono']
            );
            $permisoIds = [];
            foreach ($hoja['permisos'] as $permiso) {
                $permisoIds[] = $this->upsertPermiso($permiso['nombre'], $permiso['slug'], $menuId);
            }
            $this->asignarRolesMenu($menuId, $rolIds);
            $this->asignarRolesPermisos($permisoIds, $rolIds);
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esFerli()) {
            return;
        }
        $reportesContablesId = (int) (DB::table('menu')
            ->where('url', '#')
            ->where('nombre', 'Reportes Contables')
            ->value('id') ?? 0);

        $capexReporteId = (int) (DB::table('menu')->where('url', 'presupuesto/capex-reporte')->value('id') ?? 0);
        if ($capexReporteId > 0 && $reportesContablesId > 0) {
            $orden = (int) (DB::table('menu')->where('menu_id', $reportesContablesId)->max('orden') ?? 0) + 1;
            DB::table('menu')->where('id', $capexReporteId)->update([
                'menu_id' => $reportesContablesId,
                'orden' => $orden,
                'updated_at' => now(),
            ]);
        }

        $slugsNuevos = [
            'listar-presupuesto', 'crear-presupuesto', 'editar-presupuesto', 'actualizar-presupuesto', 'borrar-presupuesto',
            'listar-capex', 'crear-capex', 'editar-capex', 'actualizar-capex', 'borrar-capex',
            'listar-partidagasto', 'crear-partidagasto', 'editar-partidagasto', 'actualizar-partidagasto', 'borrar-partidagasto',
            'generar-asientos-partidagasto',
        ];

        foreach (DB::table('permiso')->whereIn('slug', $slugsNuevos)->pluck('id') as $pid) {
            DB::table('permiso_rol')->where('permiso_id', $pid)->delete();
            DB::table('permiso')->where('id', $pid)->delete();
        }

        $urlsBorrar = [
            'presupuesto/presupuesto',
            'presupuesto/capex',
            'presupuesto/partidagasto',
            'presupuesto/generaasiento',
        ];
        foreach ($urlsBorrar as $url) {
            $menuId = (int) (DB::table('menu')->where('url', $url)->value('id') ?? 0);
            if ($menuId === 0) {
                continue;
            }
            DB::table('menu_rol')->where('menu_id', $menuId)->delete();
            DB::table('menu')->where('id', $menuId)->delete();
        }

        $moduloId = (int) (DB::table('menu')
            ->where('menu_id', 0)
            ->where('url', '#')
            ->where('nombre', self::MODULO_NOMBRE)
            ->value('id') ?? 0);
        if ($moduloId > 0) {
            DB::table('menu_rol')->where('menu_id', $moduloId)->delete();
            DB::table('menu')->where('id', $moduloId)->delete();
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    private function upsertModuloRaiz(): int
    {
        $id = (int) (DB::table('menu')
            ->where('menu_id', 0)
            ->where('url', '#')
            ->where('nombre', self::MODULO_NOMBRE)
            ->value('id') ?? 0);

        $ordenContable = (int) (DB::table('menu')
            ->where('menu_id', 0)
            ->where('nombre', 'Módulo Contable')
            ->value('orden') ?? 7);
        $ordenDeseado = $ordenContable + 1;

        if ($id === 0) {
            DB::table('menu')
                ->where('menu_id', 0)
                ->where('orden', '>=', $ordenDeseado)
                ->increment('orden');

            return (int) DB::table('menu')->insertGetId([
                'menu_id' => 0,
                'nombre' => self::MODULO_NOMBRE,
                'url' => '#',
                'orden' => $ordenDeseado,
                'icono' => self::MODULO_ICONO,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('menu')->where('id', $id)->update([
            'nombre' => self::MODULO_NOMBRE,
            'icono' => self::MODULO_ICONO,
            'orden' => $ordenDeseado,
            'updated_at' => now(),
        ]);

        return $id;
    }

    private function upsertMenuHijo(int $padreId, string $url, string $nombre, int $orden, string $icono): int
    {
        $id = (int) (DB::table('menu')->where('url', $url)->value('id') ?? 0);

        if ($id === 0) {
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

        DB::table('menu')->where('id', $id)->update([
            'menu_id' => $padreId,
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

        if ($id === 0) {
            return (int) DB::table('permiso')->insertGetId([
                'nombre' => $nombre,
                'slug' => $slug,
                'menu_id' => $menuId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('permiso')->where('id', $id)->update([
            'nombre' => $nombre,
            'menu_id' => $menuId,
            'updated_at' => now(),
        ]);

        return $id;
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
        foreach ($rolIds as $rolId) {
            if (! DB::table('menu_rol')->where('menu_id', $menuId)->where('rol_id', $rolId)->exists()) {
                DB::table('menu_rol')->insert(['menu_id' => $menuId, 'rol_id' => $rolId]);
            }
        }
    }

    /**
     * @param  list<int>  $permisoIds
     * @param  list<int>  $rolIds
     */
    private function asignarRolesPermisos(array $permisoIds, array $rolIds): void
    {
        foreach ($permisoIds as $permisoId) {
            foreach ($rolIds as $rolId) {
                if (! DB::table('permiso_rol')->where('permiso_id', $permisoId)->where('rol_id', $rolId)->exists()) {
                    DB::table('permiso_rol')->insert(['permiso_id' => $permisoId, 'rol_id' => $rolId]);
                }
            }
        }
    }
};

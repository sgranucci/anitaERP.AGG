<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const MODULO_PRODUCCION_NOMBRE = 'Módulo de Producción';

    /** @var array<string, list<string>> */
    private const PERMISOS_POR_MENU = [
        'stock/serigrafia' => [
            'crear-serigrafias',
            'listar-serigrafias',
            'editar-serigrafias',
            'actualizar-serigrafias',
            'borrar-serigrafias',
            // Slugs legacy con typo en BD (doble «r»)
            'crear-serigrafrias',
            'listar-serigrafrias',
            'editar-serigrafrias',
            'actualizar-serigrafrias',
            'borrar-serigrafrias',
        ],
        'configuracion/padron_exclusionpercepcioniva' => [
            'crear-padron-exclusion-percepcion-iva',
            'listar-padron-exclusion-percepcion-iva',
            'editar-padron-exclusion-percepcion-iva',
            'actualizar-padron-exclusion-percepcion-iva',
            'borrar-padron-exclusion-percepcion-iva',
        ],
        'produccion/empleado' => [
            'crear-empleados',
            'listar-empleados',
            'editar-empleados',
            'actualizar-empleados',
            'borrar-empleados',
        ],
        'produccion/movimientoordentrabajo' => [
            'crear-movimientos-orden-trabajo',
            'listar-movimientos-orden-trabajo',
            'editar-movimientos-orden-trabajo',
            'actualizar-movimientos-orden-trabajo',
            'borrar-movimientos-orden-trabajo',
        ],
        'ticket/ticket' => [
            'usuario-ticket',
            'tecnico-ticket',
            'encargado-ticket',
        ],
        'ventas/ordenestrabajo' => [
            'armar-ordenes-de-trabajo',
        ],
        'ordenventa/ordenventa' => [
            'generar-cliente-orden-de-venta',
            'facturar-orden-de-venta',
            'cobra-orden-de-venta',
        ],
        'ventas/factura' => [
            'modifica-emite-nota-de-credito',
        ],
    ];

    /** @var array<string, array{nombre: string, padre: int}> */
    private const MENUS_A_CREAR = [
        'stock/serigrafia' => ['nombre' => 'Serigrafías', 'padre' => 10],
        'produccion/empleado' => ['nombre' => 'Empleados', 'padre' => 0],
        'produccion/movimientoordentrabajo' => ['nombre' => 'Movimientos OT', 'padre' => 0],
    ];

    public function up(): void
    {
        $moduloProduccionId = $this->resolverModuloProduccionId();

        foreach (self::MENUS_A_CREAR as $url => $meta) {
            $padre = $meta['padre'];
            if (str_starts_with($url, 'produccion/')) {
                $padre = $moduloProduccionId;
            }

            $menuId = (int) (DB::table('menu')->where('url', $url)->value('id') ?? 0);
            if ($menuId === 0) {
                $orden = (int) (DB::table('menu')->where('menu_id', $padre)->max('orden') ?? 0) + 1;
                DB::table('menu')->insert([
                    'menu_id' => $padre,
                    'nombre' => $meta['nombre'],
                    'url' => $url,
                    'orden' => $orden,
                    'icono' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        foreach (self::PERMISOS_POR_MENU as $menuUrl => $slugs) {
            $menuId = (int) (DB::table('menu')->where('url', $menuUrl)->value('id') ?? 0);
            if ($menuId === 0) {
                continue;
            }

            DB::table('permiso')
                ->whereIn('slug', $slugs)
                ->where(function ($query) {
                    $query->whereNull('menu_id')->orWhere('menu_id', 0);
                })
                ->update([
                    'menu_id' => $menuId,
                    'updated_at' => now(),
                ]);
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    private function resolverModuloProduccionId(): int
    {
        $id = (int) (DB::table('menu')
            ->where('nombre', self::MODULO_PRODUCCION_NOMBRE)
            ->where('url', '#')
            ->value('id') ?? 0);

        if ($id > 0) {
            return $id;
        }

        $orden = (int) (DB::table('menu')->where('menu_id', 0)->max('orden') ?? 0) + 1;

        return (int) DB::table('menu')->insertGetId([
            'menu_id' => 0,
            'nombre' => self::MODULO_PRODUCCION_NOMBRE,
            'url' => '#',
            'orden' => $orden,
            'icono' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        $slugs = [];
        foreach (self::PERMISOS_POR_MENU as $rows) {
            $slugs = array_merge($slugs, $rows);
        }

        DB::table('permiso')
            ->whereIn('slug', array_values(array_unique($slugs)))
            ->update(['menu_id' => null, 'updated_at' => now()]);

        foreach (array_keys(self::MENUS_A_CREAR) as $url) {
            $menuId = (int) (DB::table('menu')->where('url', $url)->value('id') ?? 0);
            if ($menuId > 0) {
                DB::table('menu_rol')->where('menu_id', $menuId)->delete();
                DB::table('menu')->where('id', $menuId)->delete();
            }
        }

        SuitecrmPermiso::flushCachePermisos();
    }
};

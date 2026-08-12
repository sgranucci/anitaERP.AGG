<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Agrupa el circuito de cuentas a pagar de Compras en un submenú propio:
 *
 * Compras → Cuentas a pagar → Precarga, Comprobantes, Pago a proveedores,
 *                             Reportes → Errores de precarga, Proyección de pagos
 *
 * El aside filtra por `menu_rol`, así que los contenedores reciben la unión de roles
 * de sus hijos; sin eso la rama no se vería aunque el hijo tenga permiso.
 */
return new class extends Migration
{
    private const CONTENEDOR = 'Cuentas a pagar';

    private const CONTENEDOR_REPORTES = 'Reportes';

    /** @var list<array{url: string, nombre: string}> */
    private const HIJOS = [
        ['url' => 'compras/precarga_comprobante_proveedor', 'nombre' => 'Precarga'],
        ['url' => 'compras/comprobante-proveedor', 'nombre' => 'Comprobantes'],
        ['url' => 'compras/pagoproveedor', 'nombre' => 'Pago a proveedores'],
    ];

    /** @var list<array{url: string, nombre: string}> */
    private const HIJOS_REPORTES = [
        ['url' => 'compras/precarga_comprobante_recepcion_error', 'nombre' => 'Errores de precarga'],
        ['url' => 'compras/proyeccion-pagos', 'nombre' => 'Proyección de pagos'],
    ];

    public function up(): void
    {
        $comprasId = $this->moduloComprasId();
        if ($comprasId === 0) {
            return;
        }

        $contenedorId = $this->contenedor($comprasId, self::CONTENEDOR, 'fa-file-invoice-dollar', 5);
        $reportesId = $this->contenedor($contenedorId, self::CONTENEDOR_REPORTES, 'fa-chart-line', 4);

        $orden = 0;
        foreach (self::HIJOS as $hijo) {
            $orden++;
            $this->mover($hijo['url'], $hijo['nombre'], $contenedorId, $orden);
        }

        $orden = 0;
        foreach (self::HIJOS_REPORTES as $hijo) {
            $orden++;
            $this->mover($hijo['url'], $hijo['nombre'], $reportesId, $orden);
        }

        $this->sincronizarRolesContenedor($reportesId);
        $this->sincronizarRolesContenedor($contenedorId);
    }

    public function down(): void
    {
        $comprasId = $this->moduloComprasId();
        if ($comprasId === 0) {
            return;
        }

        $orden = (int) (DB::table('menu')->where('menu_id', $comprasId)->max('orden') ?? 0);

        foreach (array_merge(self::HIJOS, self::HIJOS_REPORTES) as $hijo) {
            $orden++;
            DB::table('menu')->where('url', $hijo['url'])->update([
                'menu_id' => $comprasId,
                'orden' => $orden,
                'updated_at' => now(),
            ]);
        }

        foreach ([self::CONTENEDOR_REPORTES, self::CONTENEDOR] as $nombre) {
            $ids = DB::table('menu')
                ->where('nombre', $nombre)
                ->where('url', '#')
                ->whereNotExists(fn ($query) => $query->select(DB::raw(1))
                    ->from('menu as hijos')
                    ->whereColumn('hijos.menu_id', 'menu.id'))
                ->pluck('id');

            foreach ($ids as $id) {
                DB::table('menu_rol')->where('menu_id', (int) $id)->delete();
                DB::table('menu')->where('id', (int) $id)->delete();
            }
        }
    }

    private function moduloComprasId(): int
    {
        $id = (int) (DB::table('menu')
            ->where('menu_id', 0)
            ->where('nombre', 'like', '%Compras%')
            ->orderBy('id')
            ->value('id') ?? 0);

        if ($id > 0) {
            return $id;
        }

        return (int) (DB::table('menu')->where('url', 'compras/proveedor')->value('menu_id') ?? 0);
    }

    private function contenedor(int $padreId, string $nombre, string $icono, int $orden): int
    {
        $id = (int) (DB::table('menu')
            ->where('menu_id', $padreId)
            ->where('nombre', $nombre)
            ->where('url', '#')
            ->value('id') ?? 0);

        if ($id > 0) {
            DB::table('menu')->where('id', $id)->update([
                'orden' => $orden,
                'icono' => $icono,
                'updated_at' => now(),
            ]);

            return $id;
        }

        return (int) DB::table('menu')->insertGetId([
            'menu_id' => $padreId,
            'nombre' => $nombre,
            'url' => '#',
            'orden' => $orden,
            'icono' => $icono,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function mover(string $url, string $nombre, int $padreId, int $orden): void
    {
        DB::table('menu')->where('url', $url)->update([
            'menu_id' => $padreId,
            'nombre' => $nombre,
            'orden' => $orden,
            'updated_at' => now(),
        ]);
    }

    /**
     * El contenedor debe tener los roles de todos sus descendientes para que la rama se muestre.
     */
    private function sincronizarRolesContenedor(int $contenedorId): void
    {
        $rolIds = DB::table('menu_rol')
            ->whereIn('menu_id', $this->descendientes($contenedorId))
            ->distinct()
            ->pluck('rol_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        foreach ($rolIds as $rolId) {
            $existe = DB::table('menu_rol')
                ->where('menu_id', $contenedorId)
                ->where('rol_id', $rolId)
                ->exists();

            if (! $existe) {
                DB::table('menu_rol')->insert(['menu_id' => $contenedorId, 'rol_id' => $rolId]);
            }
        }
    }

    /** @return list<int> */
    private function descendientes(int $menuId): array
    {
        $ids = [];
        $pendientes = [$menuId];

        while ($pendientes !== []) {
            $hijos = DB::table('menu')
                ->whereIn('menu_id', $pendientes)
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();

            $nuevos = array_values(array_diff($hijos, $ids));
            if ($nuevos === []) {
                break;
            }

            $ids = array_merge($ids, $nuevos);
            $pendientes = $nuevos;
        }

        return $ids;
    }
};

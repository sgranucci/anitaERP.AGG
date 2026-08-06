<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Alinea menu_id (y slugs de periodicidad) según el controller que usa cada permiso.
 *
 * - Filtros tipo árbol (ArbolaprobacionRepository) → configuracion/arbolaprobacion
 * - listar-ordencompra-proveedor (ProveedorController) → compras/proveedor
 * - CRUD tipo transacción compra → compras/tipotransaccion_compra
 * - CRUD periodicidad compra → configuracion/periodicidadcompra (+ slug moderno del controller)
 * - CRUD tipo servicio proveedor → compras/tiposervicio_proveedor
 */
return new class extends Migration
{
    private const MENU_ARBOL = 'configuracion/arbolaprobacion';

    private const MENU_PROVEEDOR = 'compras/proveedor';

    private const MENU_TIPO_TRANSACCION_COMPRA = 'compras/tipotransaccion_compra';

    private const MENU_PERIODICIDAD = 'configuracion/periodicidadcompra';

    private const MENU_TIPO_SERVICIO_PROVEEDOR = 'compras/tiposervicio_proveedor';

    /** Destinos previos (down). */
    private const MENU_CONDICION_PAGO = 'compras/condicionpago';

    private const MENU_CONDICION_COMPRA = 'compras/condicioncompra';

    private const MENU_ORDEN_VENTA = 'ordenventa/ordenventa';

    private const SLUGS_ARBOL_OC = [
        'actualiza-arbol-orden-de-compra',
        'consulta-arbol-orden-de-compra',
    ];

    private const SLUGS_ARBOL_SP = [
        'actualiza-arbol-solicitudes-de-pago',
        'consulta-arbol-solicitudes-de-pago',
    ];

    private const SLUGS_ARBOL_OV = [
        'actualiza-arbol-ordenes-de-venta',
        'consulta-arbol-ordenes-de-venta',
    ];

    private const SLUGS_ORDENCOMPRA_PROVEEDOR = [
        'listar-ordencompra-proveedor',
    ];

    private const SLUGS_TIPO_TRANSACCION_COMPRA = [
        'listar-tipo-transaccion-compra',
        'crear-tipo-transaccion-compra',
        'editar-tipo-transaccion-compra',
        'actualizar-tipo-transaccion-compra',
        'borrar-tipo-transaccion-compra',
    ];

    private const SLUGS_TIPO_SERVICIO_PROVEEDOR = [
        'listar-tipo-servicio-proveedor',
        'crear-tipo-servicio-proveedor',
        'editar-tipo-servicio-proveedor',
        'actualizar-tipo-servicio-proveedor',
        'borrar-tipo-servicio-proveedor',
    ];

    /** slug viejo (BD) => slug que chequea PeriodicidadcompraController */
    private const PERIODICIDAD_SLUG_RENAME = [
        'lista-periodicidad-de-compra' => 'listar-periodicidad-de-compra',
        'crea-periodicidad-de-compra' => 'crear-periodicidad-de-compra',
        'edita-periodicidad-de-compra' => 'editar-periodicidad-de-compra',
        'actualiza-periodicidad-de-compra' => 'actualizar-periodicidad-de-compra',
        'borra-periodicidad-de-compra' => 'borrar-periodicidad-de-compra',
    ];

    public function up(): void
    {
        $this->asignarMenu(self::MENU_ARBOL, array_merge(
            self::SLUGS_ARBOL_OC,
            self::SLUGS_ARBOL_SP,
            self::SLUGS_ARBOL_OV
        ));
        $this->asignarMenu(self::MENU_PROVEEDOR, self::SLUGS_ORDENCOMPRA_PROVEEDOR);
        $this->asignarMenu(self::MENU_TIPO_TRANSACCION_COMPRA, self::SLUGS_TIPO_TRANSACCION_COMPRA);
        $this->asignarMenu(self::MENU_TIPO_SERVICIO_PROVEEDOR, self::SLUGS_TIPO_SERVICIO_PROVEEDOR);

        $menuPeriodicidadId = $this->menuId(self::MENU_PERIODICIDAD);
        foreach (self::PERIODICIDAD_SLUG_RENAME as $slugViejo => $slugNuevo) {
            $update = ['updated_at' => now()];
            if ($menuPeriodicidadId > 0) {
                $update['menu_id'] = $menuPeriodicidadId;
            }
            if (! DB::table('permiso')->where('slug', $slugNuevo)->exists()) {
                $update['slug'] = $slugNuevo;
            }
            DB::table('permiso')->where('slug', $slugViejo)->update($update);
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        $this->asignarMenu(self::MENU_CONDICION_COMPRA, self::SLUGS_ARBOL_OC);
        $this->asignarMenu(self::MENU_CONDICION_PAGO, self::SLUGS_ARBOL_SP);
        $this->asignarMenu(self::MENU_ORDEN_VENTA, self::SLUGS_ARBOL_OV);
        $this->asignarMenu(self::MENU_CONDICION_COMPRA, self::SLUGS_ORDENCOMPRA_PROVEEDOR);
        $this->asignarMenu(self::MENU_CONDICION_COMPRA, self::SLUGS_TIPO_TRANSACCION_COMPRA);
        $this->asignarMenu(self::MENU_PROVEEDOR, self::SLUGS_TIPO_SERVICIO_PROVEEDOR);

        $menuCondicionCompraId = $this->menuId(self::MENU_CONDICION_COMPRA);
        foreach (self::PERIODICIDAD_SLUG_RENAME as $slugViejo => $slugNuevo) {
            $update = ['updated_at' => now()];
            if ($menuCondicionCompraId > 0) {
                $update['menu_id'] = $menuCondicionCompraId;
            }
            if (! DB::table('permiso')->where('slug', $slugViejo)->exists()) {
                $update['slug'] = $slugViejo;
            }
            DB::table('permiso')->where('slug', $slugNuevo)->update($update);
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    /** @param list<string> $slugs */
    private function asignarMenu(string $menuUrl, array $slugs): void
    {
        $menuId = $this->menuId($menuUrl);
        if ($menuId <= 0 || $slugs === []) {
            return;
        }

        DB::table('permiso')
            ->whereIn('slug', $slugs)
            ->update([
                'menu_id' => $menuId,
                'updated_at' => now(),
            ]);
    }

    private function menuId(string $url): int
    {
        return (int) (DB::table('menu')->where('url', $url)->value('id') ?? 0);
    }
};

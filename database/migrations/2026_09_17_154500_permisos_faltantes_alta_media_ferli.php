<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Ferli: crea permisos can() faltantes (confianza alta+media) y asigna menu_id.
 * Roles: los que ya tienen listar/crear en el mismo menú.
 */
return new class extends Migration
{
    /** @var list<array{slug: string, menu_url: string}> */
    private const CREAR = array (
  0 => 
  array (
    'slug' => 'actualizar-configuracion-puntoventa-estacionamiento',
    'menu_url' => 'caja/estacionamiento/configuracion-puntoventa',
  ),
  1 => 
  array (
    'slug' => 'actualizar-ingresos-egresos-caja',
    'menu_url' => 'caja/ingresoegreso',
  ),
  2 => 
  array (
    'slug' => 'anular-ingresos-egresos-caja',
    'menu_url' => 'caja/ingresoegreso',
  ),
  3 => 
  array (
    'slug' => 'revertir-ingresos-egresos-caja',
    'menu_url' => 'caja/ingresoegreso',
  ),
  4 => 
  array (
    'slug' => 'ver-movimientos-cuenta-interbanking',
    'menu_url' => 'caja/interbanking/transferencias-persistidas',
  ),
  5 => 
  array (
    'slug' => 'ver-transferencias-cuenta-interbanking',
    'menu_url' => 'caja/interbanking/transferencias-persistidas',
  ),
  6 => 
  array (
    'slug' => 'actualizar-tipo-de-cuenta-de-caja',
    'menu_url' => 'caja/tipocuentacaja',
  ),
  7 => 
  array (
    'slug' => 'borrar-tipo-de-cuenta-de-caja',
    'menu_url' => 'caja/tipocuentacaja',
  ),
  8 => 
  array (
    'slug' => 'crear-tipo-de-cuenta-de-caja',
    'menu_url' => 'caja/tipocuentacaja',
  ),
  9 => 
  array (
    'slug' => 'editar-tipo-de-cuenta-de-caja',
    'menu_url' => 'caja/tipocuentacaja',
  ),
  10 => 
  array (
    'slug' => 'listar-tipo-de-cuenta-de-caja',
    'menu_url' => 'caja/tipocuentacaja',
  ),
  11 => 
  array (
    'slug' => 'cambiar-articulo-cumplir-requisicion-compra',
    'menu_url' => 'compras/cumplir-requisicion-compra',
  ),
  12 => 
  array (
    'slug' => 'marcar-conciliada-pagoproveedor',
    'menu_url' => 'compras/pagoproveedor',
  ),
  13 => 
  array (
    'slug' => 'marcar-pagada-pagoproveedor',
    'menu_url' => 'compras/pagoproveedor',
  ),
  14 => 
  array (
    'slug' => 'listar-encuesta-proveedor',
    'menu_url' => 'compras/proveedor',
  ),
  15 => 
  array (
    'slug' => 'listar-todas-requisicion',
    'menu_url' => 'compras/requisicion',
  ),
  16 => 
  array (
    'slug' => 'seguimiento-aprobacion-requisicion',
    'menu_url' => 'compras/requisicion',
  ),
  17 => 
  array (
    'slug' => 'usuario-requisicion-compras',
    'menu_url' => 'compras/requisicion',
  ),
  18 => 
  array (
    'slug' => 'usuario-requisicion-resto',
    'menu_url' => 'compras/requisicion',
  ),
  19 => 
  array (
    'slug' => 'volver-compras-requisicion',
    'menu_url' => 'compras/requisicion',
  ),
  20 => 
  array (
    'slug' => 'actualizar-retencion-de-iva',
    'menu_url' => 'compras/retencioniva',
  ),
  21 => 
  array (
    'slug' => 'borrar-retencion-de-iva',
    'menu_url' => 'compras/retencioniva',
  ),
  22 => 
  array (
    'slug' => 'borrar-retenciones-de-iva',
    'menu_url' => 'compras/retencioniva',
  ),
  23 => 
  array (
    'slug' => 'crear-retencion-de-iva',
    'menu_url' => 'compras/retencioniva',
  ),
  24 => 
  array (
    'slug' => 'crear-retenciones-de-iva',
    'menu_url' => 'compras/retencioniva',
  ),
  25 => 
  array (
    'slug' => 'editar-retencion-de-iva',
    'menu_url' => 'compras/retencioniva',
  ),
  26 => 
  array (
    'slug' => 'editar-retenciones-de-iva',
    'menu_url' => 'compras/retencioniva',
  ),
  27 => 
  array (
    'slug' => 'listar-retencion-de-iva',
    'menu_url' => 'compras/retencioniva',
  ),
  28 => 
  array (
    'slug' => 'configurar-suscripcion',
    'menu_url' => 'compras/suscripciones/comprobantes-pendientes',
  ),
  29 => 
  array (
    'slug' => 'configurar-salidas',
    'menu_url' => 'configuracion/salida',
  ),
  30 => 
  array (
    'slug' => 'borrar-ultimo-cierre-periodo-contable',
    'menu_url' => 'contable/cierre-periodo',
  ),
  31 => 
  array (
    'slug' => 'actualizar-movimientos-orden-trabajo',
    'menu_url' => 'produccion/movimientoordentrabajo',
  ),
  32 => 
  array (
    'slug' => 'editar-materialavio',
    'menu_url' => 'stock/materialavio',
  ),
  33 => 
  array (
    'slug' => 'actualizar-capelladas',
    'menu_url' => 'stock/materialcapellada',
  ),
  34 => 
  array (
    'slug' => 'revertir-movimientos-de-stock',
    'menu_url' => 'stock/movimientostock',
  ),
  35 => 
  array (
    'slug' => 'cambiar-cotizacion-recepcion-proveedor',
    'menu_url' => 'stock/recepcion-proveedor',
  ),
  36 => 
  array (
    'slug' => 'modificar-precio-recepcion-proveedor',
    'menu_url' => 'stock/recepcion-proveedor',
  ),
  37 => 
  array (
    'slug' => 'borrar-tipo-numeracion',
    'menu_url' => 'stock/tiponumeracion',
  ),
  38 => 
  array (
    'slug' => 'crear-tipo-numeracion',
    'menu_url' => 'stock/tiponumeracion',
  ),
  39 => 
  array (
    'slug' => 'editar-tipo-numeracion',
    'menu_url' => 'stock/tiponumeracion',
  ),
  40 => 
  array (
    'slug' => 'actualizar-movimientos-de-stock',
    'menu_url' => 'stock/transferencia-mercaderia',
  ),
  41 => 
  array (
    'slug' => 'aprobar-transferencia-mercaderia',
    'menu_url' => 'stock/transferencia-mercaderia',
  ),
  42 => 
  array (
    'slug' => 'listar-transferencias-pendientes',
    'menu_url' => 'stock/transferencia-mercaderia',
  ),
  43 => 
  array (
    'slug' => 'borrar-vigencia-categoria-sueldos',
    'menu_url' => 'sueldos/categoria',
  ),
  44 => 
  array (
    'slug' => 'listar-descuento-fallo-sueldos',
    'menu_url' => 'sueldos/fallo-reporte',
  ),
  45 => 
  array (
    'slug' => 'informar-arca-caea',
    'menu_url' => 'ventas/arca-caea',
  ),
  46 => 
  array (
    'slug' => 'listar-asignacion-remito-factura',
    'menu_url' => 'ventas/factura',
  ),
  47 => 
  array (
    'slug' => 'corregir-arqueo-cierre-turno-gastronomia',
    'menu_url' => 'ventas/gastronomia/cierres-turno',
  ),
  48 => 
  array (
    'slug' => 'anular-cierre-turno-gastronomia',
    'menu_url' => 'ventas/gastronomia/habilitacion-turno',
  ),
  49 => 
  array (
    'slug' => 'modificar-monto-habilitacion-turno-gastronomia',
    'menu_url' => 'ventas/gastronomia/habilitacion-turno',
  ),
  50 => 
  array (
    'slug' => 'actualizar-maquinavending-rendicion-gastronomia',
    'menu_url' => 'ventas/gastronomia/maquinas-vending/rendiciones',
  ),
  51 => 
  array (
    'slug' => 'borrar-maquinavending-rendicion-gastronomia',
    'menu_url' => 'ventas/gastronomia/maquinas-vending/rendiciones',
  ),
  52 => 
  array (
    'slug' => 'editar-maquinavending-rendicion-gastronomia',
    'menu_url' => 'ventas/gastronomia/maquinas-vending/rendiciones',
  ),
  53 => 
  array (
    'slug' => 'ver-comprobante-maquinavending-rendicion-gastronomia',
    'menu_url' => 'ventas/gastronomia/maquinas-vending/rendiciones',
  ),
  54 => 
  array (
    'slug' => 'actualizar-motivos-cierre-pedido',
    'menu_url' => 'ventas/motivocierrepedido',
  ),
  55 => 
  array (
    'slug' => 'listar-importar-pedido-anita',
    'menu_url' => 'ventas/pedido',
  ),
  56 => 
  array (
    'slug' => 'ejecutar-importar-remito-anita',
    'menu_url' => 'ventas/remito',
  ),
  57 => 
  array (
    'slug' => 'listar-importar-remito-anita',
    'menu_url' => 'ventas/remito',
  ),
  58 => 
  array (
    'slug' => 'cargar-coeficiente-cliente',
    'menu_url' => 'ventas/repcliente',
  ),
  59 => 
  array (
    'slug' => 'gestionar-notas-suitecrm-cliente',
    'menu_url' => 'ventas/repcliente',
  ),
  60 => 
  array (
    'slug' => 'modificar-descuento-cliente',
    'menu_url' => 'ventas/repcliente',
  ),
  61 => 
  array (
    'slug' => 'entregar-articulo-sin-cargo-pedido-venta',
    'menu_url' => 'ventas/repconsumomaterial',
  ),
  62 => 
  array (
    'slug' => 'actualizar-tipos-suspension-clientes',
    'menu_url' => 'ventas/tiposuspensioncliente',
  ),
);

    /** @var list<array{slug: string, menu_url: string}> */
    private const ASIGNAR_MENU = array (
  0 => 
  array (
    'slug' => 'borrar-motivos-cierre-pedido',
    'menu_url' => 'ventas/motivocierrepedido',
  ),
  1 => 
  array (
    'slug' => 'crear-motivos-cierre-pedido',
    'menu_url' => 'ventas/motivocierrepedido',
  ),
  2 => 
  array (
    'slug' => 'editar-motivos-cierre-pedido',
    'menu_url' => 'ventas/motivocierrepedido',
  ),
  3 => 
  array (
    'slug' => 'empacar-ordenes-de-trabajo',
    'menu_url' => 'produccion/movimientoordentrabajo',
  ),
  4 => 
  array (
    'slug' => 'listar-motivos-cierre-pedido',
    'menu_url' => 'ventas/motivocierrepedido',
  ),
);

    public function up(): void
    {
        if (! EntornoEmpresaSupport::esFerli()) {
            return;
        }

        $now = now();
        $menuIdPorUrl = DB::table('menu')
            ->whereIn('url', array_values(array_unique(array_merge(
                array_column(self::CREAR, 'menu_url'),
                array_column(self::ASIGNAR_MENU, 'menu_url')
            ))))
            ->pluck('id', 'url')
            ->map(static fn ($id) => (int) $id)
            ->all();

        foreach (self::CREAR as $row) {
            $slug = $row['slug'];
            $menuUrl = $row['menu_url'];
            $menuId = (int) ($menuIdPorUrl[$menuUrl] ?? 0);
            if ($menuId <= 0) {
                continue;
            }

            $permisoId = (int) (DB::table('permiso')->where('slug', $slug)->value('id') ?? 0);
            if ($permisoId <= 0) {
                $permisoId = (int) DB::table('permiso')->insertGetId([
                    'nombre' => $this->nombreDesdeSlug($slug),
                    'slug' => $slug,
                    'menu_id' => $menuId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            } else {
                DB::table('permiso')->where('id', $permisoId)->where(function ($q) {
                    $q->whereNull('menu_id')->orWhere('menu_id', 0);
                })->update([
                    'menu_id' => $menuId,
                    'updated_at' => $now,
                ]);
            }

            $this->asignarRolesDelMenu($permisoId, $menuId);
        }

        foreach (self::ASIGNAR_MENU as $row) {
            $slug = $row['slug'];
            $menuUrl = $row['menu_url'];
            $menuId = (int) ($menuIdPorUrl[$menuUrl] ?? 0);
            if ($menuId <= 0) {
                continue;
            }
            $permisoId = (int) (DB::table('permiso')->where('slug', $slug)->value('id') ?? 0);
            if ($permisoId <= 0) {
                continue;
            }
            DB::table('permiso')->where('id', $permisoId)->update([
                'menu_id' => $menuId,
                'updated_at' => $now,
            ]);
            $this->asignarRolesDelMenu($permisoId, $menuId);
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esFerli()) {
            return;
        }

        $slugsCrear = array_column(self::CREAR, 'slug');
        $ids = DB::table('permiso')->whereIn('slug', $slugsCrear)->pluck('id')->all();
        if ($ids !== []) {
            DB::table('permiso_rol')->whereIn('permiso_id', $ids)->delete();
            DB::table('permiso')->whereIn('id', $ids)->delete();
        }

        foreach (self::ASIGNAR_MENU as $row) {
            DB::table('permiso')->where('slug', $row['slug'])->update([
                'menu_id' => null,
                'updated_at' => now(),
            ]);
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    private function nombreDesdeSlug(string $slug): string
    {
        $n = str_replace('-', ' ', $slug);
        $n = preg_replace('/\s+/', ' ', $n) ?? $n;

        return mb_convert_case($n, MB_CASE_TITLE, 'UTF-8');
    }

    private function asignarRolesDelMenu(int $permisoId, int $menuId): void
    {
        if ($permisoId <= 0 || $menuId <= 0) {
            return;
        }

        $rolIds = DB::table('permiso_rol as pr')
            ->join('permiso as p', 'p.id', '=', 'pr.permiso_id')
            ->where('p.menu_id', $menuId)
            ->where(function ($q) {
                $q->where('p.slug', 'like', 'listar-%')
                    ->orWhere('p.slug', 'like', 'lista-%')
                    ->orWhere('p.slug', 'like', 'crear-%')
                    ->orWhere('p.slug', 'like', 'crea-%');
            })
            ->pluck('pr.rol_id')
            ->map(static fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        if ($rolIds === []) {
            $rolIds = DB::table('rol')
                ->whereIn('nombre', ['administrador', 'Enc-admin', 'Admin-ventas', 'Ventas', 'Oficina', 'Enc-contaduría'])
                ->pluck('id')
                ->map(static fn ($id) => (int) $id)
                ->all();
        }

        foreach ($rolIds as $rolId) {
            if ($rolId <= 0) {
                continue;
            }
            $existe = DB::table('permiso_rol')
                ->where('permiso_id', $permisoId)
                ->where('rol_id', $rolId)
                ->exists();
            if ($existe) {
                continue;
            }
            DB::table('permiso_rol')->insert([
                'permiso_id' => $permisoId,
                'rol_id' => $rolId,
            ]);
        }
    }
};

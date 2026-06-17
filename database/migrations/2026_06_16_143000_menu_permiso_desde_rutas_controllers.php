<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** @var array<string, list<string>> menu.url => slugs (desde routes + can() en controllers/vistas) */
    private const PERMISOS_POR_MENU = array (
  'caja/caja' => 
  array (
    0 => 'actualiza-caja',
    1 => 'borra-caja',
    2 => 'crea-caja',
    3 => 'edita-caja',
    4 => 'lista-caja',
  ),
  'caja/chequera' => 
  array (
    0 => 'actualiza-chequera',
    1 => 'borra-chequera',
    2 => 'crea-chequera',
    3 => 'edita-chequera',
    4 => 'lista-chequera',
  ),
  'caja/estadocheque_banco' => 
  array (
    0 => 'actualiza-estado-cheque-banco',
    1 => 'borra-estado-cheque-banco',
    2 => 'crea-estado-cheque-banco',
    3 => 'edita-estado-cheque-banco',
    4 => 'lista-estado-cheque-banco',
  ),
  'caja/rendicionreceptivo' => 
  array (
    0 => 'actualiza-rendicion-receptivo',
    1 => 'borra-rendicion-receptivo',
    2 => 'crea-rendicion-receptivo',
    3 => 'edita-rendicion-receptivo',
    4 => 'lista-rendicion-receptivo',
  ),
  'caja/tipocuentacaja' => 
  array (
    0 => 'actualizar-tipo-de-cuenta-de-caja',
    1 => 'borrar-tipo-de-cuenta-de-caja',
    2 => 'crear-tipo-de-cuenta-de-caja',
    3 => 'editar-tipo-de-cuenta-de-caja',
    4 => 'listar-tipo-de-cuenta-de-caja',
  ),
  'caja/tipotransaccion_caja' => 
  array (
    0 => 'actualizar-tipo-transaccion-caja',
    1 => 'borrar-tipo-transaccion-caja',
    2 => 'crear-tipo-transaccion-caja',
    3 => 'editar-tipo-transaccion-caja',
    4 => 'listar-tipo-transaccion-caja',
  ),
  'compras/columna_ivacompra' => 
  array (
    0 => 'crear-columna-iva-compra',
  ),
  'compras/concepto_ivacompra' => 
  array (
    0 => 'crear-concepto-iva-compra',
  ),
  'compras/proveedor' => 
  array (
    0 => 'actualizar-proveedor',
    1 => 'borrar-proveedor',
    2 => 'crear-proveedor',
    3 => 'editar-proveedor',
    4 => 'listar-cuentacorriente-proveedor',
    5 => 'listar-proveedor',
  ),
  'compras/retencioniva' => 
  array (
    0 => 'actualizar-retencion-de-iva',
    1 => 'borrar-retencion-de-iva',
    2 => 'crear-retencion-de-iva',
    3 => 'editar-retencion-de-iva',
    4 => 'listar-retencion-de-iva',
  ),
  'configuracion/condicionIIBB' => 
  array (
    0 => 'actualizar-condicion-de-ingreso-bruto',
    1 => 'borrar-condicion-de-ingreso-bruto',
    2 => 'crear-condicion-de-ingreso-bruto',
    3 => 'editar-condicion-de-ingreso-bruto',
    4 => 'listar-condicion-de-ingreso-bruto',
  ),
  'configuracion/oficinacompra' => 
  array (
    0 => 'actualiza-oficina-de-compras',
    1 => 'borra-oficina-de-compras',
    2 => 'crea-oficina-de-compras',
    3 => 'edita-oficina-de-compras',
    4 => 'lista-oficina-de-compras',
  ),
  'configuracion/retencion_impositiva_arca' => 
  array (
    0 => 'actualizar-retencion-impositiva-arca',
    1 => 'borrar-retencion-impositiva-arca',
    2 => 'conciliar-retencion-impositiva-arca',
    3 => 'crear-retencion-impositiva-arca',
    4 => 'editar-retencion-impositiva-arca',
    5 => 'importar-retencion-impositiva-arca',
    6 => 'listar-retencion-impositiva-arca',
  ),
  'configuracion/tipodocumento' => 
  array (
    0 => 'actualizar-tipo-de-documento',
    1 => 'borrar-tipo-de-documento',
    2 => 'crear-tipo-de-documento',
    3 => 'editar-tipo-de-documento',
    4 => 'listar-tipo-de-documento',
  ),
  'contable/asiento' => 
  array (
    0 => 'crear-asiento',
  ),
  'contable/centrocosto' => 
  array (
    0 => 'actualizar-centro-costo',
    1 => 'borrar-centro-costo',
    2 => 'crear-centro-costo',
    3 => 'editar-centro-costo',
    4 => 'listar-centro-costo',
  ),
  'contable/cuentacontable' => 
  array (
    0 => 'actualizar-cuentas-contables',
    1 => 'borrar-cuentas-contables',
    2 => 'crear-cuentas-contables',
    3 => 'editar-cuentas-contables',
    4 => 'listar-cuentas-contables',
  ),
  'contable/usuario_cuentacontable' => 
  array (
    0 => 'actualizar-usuario-cuentacontable',
    1 => 'borrar-usuario-cuentacontable',
    2 => 'crear-usuario-cuentacontable',
    3 => 'editar-usuario-cuentacontable',
    4 => 'listar-usuario-cuentacontable',
  ),
  'ordenventa/concepto_ordenventa' => 
  array (
    0 => 'actualizar-concepto-de-orden-de-venta',
    1 => 'borrar-concepto-de-orden-de-venta',
    2 => 'crear-concepto-de-orden-de-venta',
    3 => 'editar-concepto-de-orden-de-venta',
    4 => 'listar-concepto-de-orden-de-venta',
  ),
  'ordenventa/ordenventa' => 
  array (
    0 => 'actualizar-orden-de-venta',
    1 => 'borrar-orden-de-venta',
    2 => 'editar-orden-de-venta',
    3 => 'ingresar-orden-de-venta',
    4 => 'listar-orden-de-venta',
  ),
  'presupuesto/presupuesto' => 
  array (
    0 => 'actualizar-presupuesto',
    1 => 'borrar-presupuesto',
    2 => 'crear-presupuesto',
    3 => 'editar-presupuesto',
    4 => 'listar-presupuesto',
  ),
  'receptivo/comision_servicioterrestre' => 
  array (
    0 => 'actualizar-comision-servicio-terrestre',
    1 => 'borrar-comision-servicio-terrestre',
    2 => 'crear-comision-servicio-terrestre',
    3 => 'editar-comision-servicio-terrestre',
    4 => 'listar-comision-servicio-terrestre',
  ),
  'receptivo/movil' => 
  array (
    0 => 'actualiza-movil',
    1 => 'borra-movil',
    2 => 'crea-movil',
    3 => 'edita-movil',
    4 => 'lista-movil',
  ),
  'receptivo/proveedor_servicioterrestre' => 
  array (
    0 => 'actualizar-servicio-por-proveedor',
    1 => 'borrar-servicio-por-proveedor',
    2 => 'crear-servicio-por-proveedor',
    3 => 'editar-servicio-por-proveedor',
    4 => 'listar-servicio-por-proveedor',
  ),
  'receptivo/servicioterrestre' => 
  array (
    0 => 'actualizar-servicio-terrestre',
    1 => 'borrar-servicio-terrestre',
    2 => 'crear-servicio-terrestre',
    3 => 'editar-servicio-terrestre',
    4 => 'listar-servicio-terrestre',
  ),
  'receptivo/tiposervicioterrestre' => 
  array (
    0 => 'actualizar-tipo-servicio-terrestre',
    1 => 'borrar-tipo-servicio-terrestre',
    2 => 'crear-tipo-servicio-terrestre',
    3 => 'editar-tipo-servicio-terrestre',
    4 => 'listar-tipo-servicio-terrestre',
  ),
  'stock/articulo' => 
  array (
    0 => 'actualizar-compras-articulos',
    1 => 'editar-compras-articulos',
  ),
  'stock/movimientostock' => 
  array (
    0 => 'editar-movimientos-de-stock',
  ),
  'stock/transferencia-mercaderia' => 
  array (
    0 => 'listar-transferencias-pendientes',
  ),
  'ticket/administracion_ticket' => 
  array (
    0 => 'supervisor-ticket',
  ),
  'ticket/ticket' => 
  array (
    0 => 'actualizar-ticket',
    1 => 'borrar-ticket',
    2 => 'crear-ticket',
    3 => 'editar-ticket',
    4 => 'listar-ticket',
  ),
  'ventas/tiposuspensioncliente' => 
  array (
    0 => 'actualizar-tipos-suspension-clientes',
    1 => 'borrar-tipos-suspension-clientes',
    2 => 'crear-tipos-suspension-clientes',
    3 => 'editar-tipos-suspension-clientes',
    4 => 'listar-tipos-suspension-clientes',
  ),
);

    public function up(): void
    {
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

    public function down(): void
    {
        $slugs = [];
        foreach (self::PERMISOS_POR_MENU as $rows) {
            $slugs = array_merge($slugs, $rows);
        }

        DB::table('permiso')
            ->whereIn('slug', array_values(array_unique($slugs)))
            ->update([
                'menu_id' => null,
                'updated_at' => now(),
            ]);

        SuitecrmPermiso::flushCachePermisos();
    }
};

<?php

namespace App\Support\Stock;

/**
 * Consulta de artículo desde modales compartidos (includes.stock.modalconsultaarticulo).
 */
class ArticuloConsultaDesdeModal
{
    public static function puedeConsultar(): bool
    {
        return can('listar-articulos', false)
            || can('editar-articulos', false)
            || can('crear-requisicion', false)
            || can('editar-requisicion', false)
            || can('actualizar-requisicion', false)
            || can('listar-requisicion', false)
            || can('crear-ordencompra', false)
            || can('editar-ordencompra', false)
            || can('actualizar-ordencompra', false)
            || can('listar-ordencompra', false)
            || can('crear-recuento', false)
            || can('editar-recuento', false)
            || can('actualizar-recuento', false)
            || can('ver-recuento', false)
            || can('listar-movimientos-de-stock', false)
            || can('crear-movimientos-de-stock', false)
            || can('editar-movimientos-de-stock', false)
            || can('actualizar-movimientos-de-stock', false)
            || can('crear-transferencia-mercaderia', false)
            || can('listar-transferencia-mercaderia', false)
            || can('listar-transferencias-pendientes', false)
            || can('listar-formula-articulo', false)
            || can('crear-formula-articulo', false)
            || can('editar-formula-articulo', false)
            || can('crear-partidagasto', false)
            || can('editar-partidagasto', false)
            || can('actualizar-partidagasto', false)
            || can('listar-partidagasto', false)
            || can('crear-orden-produccion', false)
            || can('editar-orden-produccion', false)
            || can('actualizar-orden-produccion', false)
            || can('listar-orden-produccion', false)
            || can('usar-proceso-facturacion-gastronomia', false)
            || can('crear-precios', false)
            || can('editar-precios', false)
            || can('listar-precios', false)
            || can('crear-prestamo', false)
            || can('editar-prestamo', false)
            || can('actualizar-prestamo', false)
            || can('listar-prestamo', false)
            || can('cumplir-requisicion-compra', false)
            || can('cumplir-requisicion-sala', false)
            || can('crear-requisicion-sala', false)
            || can('editar-requisicion-sala', false)
            || can('actualizar-requisicion-sala', false)
            || can('listar-requisicion-sala', false)
            || can('cambiar-articulo-cumplir-requisicion-compra', false)
            || can('cambiar-articulo-cumplir-requisicion-sala', false)
            || can('listar-prenda-sueldos', false)
            || can('crear-prenda-sueldos', false)
            || can('editar-prenda-sueldos', false)
            || can('actualizar-prenda-sueldos', false)
            || can('listar-recepcion-proveedor-surmar', false)
            || can('crear-recepcion-proveedor-surmar', false)
            || can('editar-recepcion-proveedor-surmar', false)
            || can('actualizar-recepcion-proveedor-surmar', false)
            || can('crear-comprobante-proveedor', false)
            || can('editar-comprobante-proveedor', false)
            || can('actualizar-comprobante-proveedor', false)
            || can('listar-comprobante-proveedor', false)
            || can('crear-certificado-sanitario', false)
            || can('listar-certificado-sanitario', false);
    }

    public static function urlEditar(int $id): string
    {
        return route('editar_articulo', [
            'id' => $id,
            'origen' => 'modal_consulta',
            'vista' => 'consulta',
        ]);
    }
}

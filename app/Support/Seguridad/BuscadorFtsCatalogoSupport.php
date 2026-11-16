<?php

namespace App\Support\Seguridad;

/**
 * Fuentes de adjuntos indexables para el buscador global.
 * ruta: carpeta bajo public/storage/archivos/{carpeta}/{padre_id}/{nombre}
 */
class BuscadorFtsCatalogoSupport
{
    /**
     * @return list<array{
     *   origen_tipo: string,
     *   tabla: string,
     *   padre_fk: string,
     *   nombre_col: string,
     *   carpeta: string,
     *   padre_tipo: string,
     *   permiso: string,
     *   ruta_editar: string,
     *   etiqueta: string,
     *   icono: string,
     *   empresa_via: string|null
     * }>
     */
    public static function fuentes(): array
    {
        return [
            [
                'origen_tipo' => 'ordencompra_archivo',
                'tabla' => 'ordencompra_archivo',
                'padre_fk' => 'ordencompra_id',
                'nombre_col' => 'nombrearchivo',
                'carpeta' => 'ordencompras',
                'padre_tipo' => 'ordencompra',
                'permiso' => 'listar-ordencompra',
                'ruta_editar' => 'editar_ordencompra',
                'etiqueta' => 'Orden de compra',
                'icono' => 'fa fa-shopping-cart',
                'empresa_via' => 'ordencompra.empresa_id',
            ],
            [
                'origen_tipo' => 'comprobante_proveedor_archivo',
                'tabla' => 'comprobante_proveedor_archivo',
                'padre_fk' => 'comprobante_proveedor_id',
                'nombre_col' => 'nombrearchivo',
                'carpeta' => 'comprobantes_proveedor',
                'padre_tipo' => 'comprobante_proveedor',
                'permiso' => 'listar-comprobante-proveedor',
                'ruta_editar' => 'editar_comprobante_proveedor',
                'etiqueta' => 'Comp. proveedor',
                'icono' => 'fa fa-file-text-o',
                'empresa_via' => 'comprobante_proveedor.empresa_id',
            ],
            [
                'origen_tipo' => 'pagoproveedor_archivo',
                'tabla' => 'pagoproveedor_archivo',
                'padre_fk' => 'pagoproveedor_id',
                'nombre_col' => 'nombrearchivo',
                'carpeta' => 'pagoproveedores',
                'padre_tipo' => 'pagoproveedor',
                'permiso' => 'listar-pagoproveedor',
                'ruta_editar' => 'editar_pagoproveedor',
                'etiqueta' => 'Orden de pago',
                'icono' => 'fa fa-credit-card',
                'empresa_via' => 'pagoproveedor.empresa_id',
            ],
            [
                'origen_tipo' => 'requisicion_archivo',
                'tabla' => 'requisicion_archivo',
                'padre_fk' => 'requisicion_id',
                'nombre_col' => 'nombrearchivo',
                'carpeta' => 'requisiciones',
                'padre_tipo' => 'requisicion',
                'permiso' => 'listar-requisicion',
                'ruta_editar' => 'editar_requisicion',
                'etiqueta' => 'Requisición',
                'icono' => 'fa fa-list-alt',
                'empresa_via' => 'requisicion.empresa_id',
            ],
            [
                'origen_tipo' => 'ticket_archivo',
                'tabla' => 'ticket_archivo',
                'padre_fk' => 'ticket_id',
                'nombre_col' => 'nombrearchivo',
                'carpeta' => 'tickets',
                'padre_tipo' => 'ticket',
                'permiso' => 'listar-ticket',
                'ruta_editar' => 'edita_administracion_ticket',
                'etiqueta' => 'Ticket',
                'icono' => 'fa fa-ticket',
                'empresa_via' => 'ticket.empresa_id',
            ],
            [
                'origen_tipo' => 'cobranza_archivo',
                'tabla' => 'cobranza_archivo',
                'padre_fk' => 'cobranza_id',
                'nombre_col' => 'nombrearchivo',
                'carpeta' => 'cobranzas',
                'padre_tipo' => 'cobranza',
                'permiso' => 'listar-cobranza',
                'ruta_editar' => 'editar_cobranza',
                'etiqueta' => 'Cobranza',
                'icono' => 'fa fa-usd',
                'empresa_via' => 'cobranza.empresa_id',
            ],
            [
                'origen_tipo' => 'cliente_archivo',
                'tabla' => 'cliente_archivo',
                'padre_fk' => 'cliente_id',
                'nombre_col' => 'nombrearchivo',
                'carpeta' => 'clientes',
                'padre_tipo' => 'cliente',
                'permiso' => 'listar-clientes',
                'ruta_editar' => 'editar_cliente',
                'etiqueta' => 'Cliente',
                'icono' => 'fa fa-user',
                'empresa_via' => null,
            ],
            [
                'origen_tipo' => 'proveedor_archivo',
                'tabla' => 'proveedor_archivo',
                'padre_fk' => 'proveedor_id',
                'nombre_col' => 'nombrearchivo',
                'carpeta' => 'proveedores',
                'padre_tipo' => 'proveedor',
                'permiso' => 'listar-proveedor',
                'ruta_editar' => 'editar_proveedor',
                'etiqueta' => 'Proveedor',
                'icono' => 'fa fa-truck',
                'empresa_via' => null,
            ],
            [
                'origen_tipo' => 'asiento_archivo',
                'tabla' => 'asiento_archivo',
                'padre_fk' => 'asiento_id',
                'nombre_col' => 'nombrearchivo',
                'carpeta' => 'asientos',
                'padre_tipo' => 'asiento',
                'permiso' => 'listar-asiento',
                'ruta_editar' => 'editar_asiento',
                'etiqueta' => 'Asiento',
                'icono' => 'fa fa-book',
                'empresa_via' => 'asiento.empresa_id',
            ],
            [
                'origen_tipo' => 'capex_archivo',
                'tabla' => 'capex_archivo',
                'padre_fk' => 'capex_id',
                'nombre_col' => 'nombrearchivo',
                'carpeta' => 'capex',
                'padre_tipo' => 'capex',
                'permiso' => 'listar-capex',
                'ruta_editar' => 'editar_capex',
                'etiqueta' => 'Capex',
                'icono' => 'fa fa-line-chart',
                'empresa_via' => null,
            ],
            [
                'origen_tipo' => 'empleado_archivo_sueldos',
                'tabla' => 'empleado_archivo_sueldos',
                'padre_fk' => 'empleado_id',
                'nombre_col' => 'nombrearchivo',
                'carpeta' => 'empleados',
                'padre_tipo' => 'empleado_sueldos',
                'permiso' => 'listar-empleado-sueldos',
                'ruta_editar' => 'editar_empleado_sueldos',
                'etiqueta' => 'Empleado',
                'icono' => 'fa fa-id-card',
                'empresa_via' => null,
            ],
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function porOrigenTipo(string $origenTipo): ?array
    {
        foreach (self::fuentes() as $fuente) {
            if ($fuente['origen_tipo'] === $origenTipo) {
                return $fuente;
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function porPadreTipo(string $padreTipo): ?array
    {
        foreach (self::fuentes() as $fuente) {
            if ($fuente['padre_tipo'] === $padreTipo) {
                return $fuente;
            }
        }

        return null;
    }
}

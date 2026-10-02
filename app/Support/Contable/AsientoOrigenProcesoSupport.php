<?php

declare(strict_types=1);

namespace App\Support\Contable;

/**
 * FKs de asiento que apuntan a la operación de un subsistema.
 * Esos asientos no deben revertirse/borrarse desde el ABM de asientos:
 * la anulación corresponde al proceso origen (remesa, IE, cobranza, etc.).
 *
 * ordencompra_id es una referencia (también acompaña recepción o comprobante).
 * Un asiento contable puede quedar asociado a una OC anterior sin que la OC
 * lo haya generado: en ese caso se revierte y se borra desde Contable.
 */
final class AsientoOrigenProcesoSupport
{
    /**
     * @var array<string, array{label: string, route: string|null, permiso: list<string>, bloquea?: bool}>
     */
    public const FKS = [
        'remesa_id' => [
            'label' => 'Remesa',
            'route' => 'editar_remesa',
            'permiso' => ['listar-remesa', 'editar-remesa'],
        ],
        'jornada_gastronomia_id' => [
            'label' => 'Cierre jornada gastronomía',
            'route' => 'waitry_cierre_jornada',
            'permiso' => ['listar-waitry-cierre-jornada-caja'],
        ],
        'rendicion_estacionamiento_caja_id' => [
            'label' => 'Rendición estacionamiento',
            'route' => 'editar_rendicionestacionamiento',
            'permiso' => ['listar-rendicion-estacionamiento-caja', 'editar-rendicion-estacionamiento-caja'],
        ],
        'transferencia_mercaderia_id' => [
            'label' => 'Transferencia mercadería',
            'route' => 'transferencia_mercaderia',
            'permiso' => ['crear-transferencia-mercaderia', 'listar-transferencias-pendientes'],
        ],
        'caja_movimiento_id' => [
            'label' => 'Movimiento de caja',
            'route' => 'editar_ingresoegreso',
            'permiso' => ['listar-ingresos-egresos-caja', 'editar-ingresos-egresos-caja'],
        ],
        'solicitudpago_id' => [
            'label' => 'Solicitud de pago',
            'route' => 'editar_solicitudpago',
            'permiso' => ['listar-solicitud-pago', 'editar-solicitud-pago'],
        ],
        'cobranza_id' => [
            'label' => 'Cobranza',
            'route' => 'editar_cobranza',
            'permiso' => ['listar-cobranza', 'editar-cobranza'],
        ],
        'pagoproveedor_id' => [
            'label' => 'Pago a proveedor',
            'route' => 'editar_pagoproveedor',
            'permiso' => ['listar-pagoproveedor', 'editar-pagoproveedor'],
        ],
        'recepcionproveedor_id' => [
            'label' => 'Recepción proveedor',
            'route' => 'editar_recepcion_proveedor',
            'permiso' => ['listar-recepcion-proveedor', 'editar-recepcion-proveedor'],
        ],
        'movimientostock_id' => [
            'label' => 'Movimiento de stock',
            'route' => 'editar_movimientostock',
            'permiso' => ['listar-movimientos-de-stock', 'editar-movimientos-de-stock'],
        ],
        'venta_id' => [
            'label' => 'Factura de venta',
            'route' => 'editar_factura',
            'permiso' => ['listar-factura', 'editar-factura'],
        ],
        'comprobante_proveedor_id' => [
            'label' => 'Comprobante proveedor',
            'route' => 'editar_comprobante_proveedor',
            'permiso' => ['listar-comprobante-proveedor', 'editar-comprobante-proveedor'],
        ],
        // Referencia del asiento, no el proceso que lo genera. No bloquea revertir/borrar.
        'ordencompra_id' => [
            'label' => 'Orden de compra',
            'route' => 'editar_ordencompra',
            'permiso' => ['listar-ordencompra', 'editar-ordencompra'],
            'bloquea' => false,
        ],
        'compra_id' => [
            'label' => 'Compra',
            'route' => null,
            'permiso' => [],
        ],
    ];

    /**
     * @return list<string>
     */
    public static function columnasFk(): array
    {
        return array_keys(self::FKS);
    }

    /**
     * @param  object|array<string, mixed>  $asiento
     */
    public static function tieneOrigenProceso(object|array $asiento): bool
    {
        return self::fksOrigenProceso($asiento) !== [];
    }

    /**
     * FKs con id, incluidas las de referencia (orden de compra).
     *
     * @param  object|array<string, mixed>  $asiento
     * @return array<string, int> fk => id
     */
    public static function fksActivas(object|array $asiento): array
    {
        $out = [];
        foreach (self::columnasFk() as $fk) {
            $id = (int) (is_array($asiento) ? ($asiento[$fk] ?? 0) : ($asiento->{$fk} ?? 0));
            if ($id > 0) {
                $out[$fk] = $id;
            }
        }

        return $out;
    }

    /**
     * FKs del proceso que generó el asiento. La orden de compra no entra:
     * es referencia de un asiento contable o acompañante de recepción/CP.
     *
     * @param  object|array<string, mixed>  $asiento
     * @return array<string, int> fk => id
     */
    public static function fksOrigenProceso(object|array $asiento): array
    {
        $out = [];
        foreach (self::fksActivas($asiento) as $fk => $id) {
            if ((self::FKS[$fk]['bloquea'] ?? true) === false) {
                continue;
            }
            $out[$fk] = $id;
        }

        return $out;
    }

    /**
     * @param  object|array<string, mixed>  $asiento
     */
    public static function mensajeBloqueo(object|array $asiento, string $accion = 'revertir'): string
    {
        $activas = self::fksOrigenProceso($asiento);
        if ($activas === []) {
            return '';
        }

        $etiquetas = [];
        foreach ($activas as $fk => $id) {
            $meta = self::FKS[$fk];
            $etiquetas[] = $meta['label'].' #'.$id;
        }

        $lista = implode(', ', $etiquetas);

        return 'No se puede '.$accion.' este asiento desde Contable porque está asociado a: '.$lista
            .'. Debe '.$accion.'lo desde la operación del subsistema que lo generó.';
    }

    /**
     * Quita FKs de proceso al copiar un asiento (el copia queda como asiento manual).
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function limpiarFksOrigenEnPayload(array $data): array
    {
        foreach (self::columnasFk() as $fk) {
            $data[$fk] = null;
        }

        return $data;
    }
}

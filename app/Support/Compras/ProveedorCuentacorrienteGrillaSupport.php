<?php

namespace App\Support\Compras;

use App\Models\Compras\Comprobante_Proveedor;
use App\Models\Compras\Proveedor_Cuentacorriente;
use App\Models\Compras\Proveedor_Cuentacorriente_Aplicacion;

final class ProveedorCuentacorrienteGrillaSupport
{
    public static function etiquetaComprobante(Proveedor_Cuentacorriente $fila): string
    {
        // Crédito de OP: priorizar pago / etiqueta de aplicación (no la factura linkeada).
        if ((int) ($fila->pagoproveedor_id ?? 0) > 0 && $fila->pagoproveedores) {
            return $fila->pagoproveedores->etiquetaComprobante();
        }

        // Imports Anita: el crédito del pago queda linkeado a la factura (FDT) y total < 0.
        // No tocar créditos reales (CDT/NC/etc.): ahí la etiqueta es el propio comprobante.
        if ((float) ($fila->total ?? 0) < 0 && self::comprobanteEsDeudaFactura($fila)) {
            $etiquetaOp = self::etiquetaCreditoDesdeAplicacion($fila);
            if ($etiquetaOp !== null) {
                return $etiquetaOp;
            }
        }

        if ((int) ($fila->comprobante_proveedor_id ?? 0) > 0 && $fila->comprobante_proveedores) {
            $comprobante = $fila->comprobante_proveedores;
            $tipo = $comprobante->tipotransaccion_compras->nombre ?? 'Comprobante';

            return trim($tipo.' '.$comprobante->letra.$comprobante->sucursal.'-'.$comprobante->numerocomprobante);
        }

        return 'Movimiento #'.(int) $fila->id;
    }

    /** True si el comprobante linkeado es deuda (FDT/etc.), no crédito (CDT/NC). */
    private static function comprobanteEsDeudaFactura(Proveedor_Cuentacorriente $fila): bool
    {
        if ((int) ($fila->comprobante_proveedor_id ?? 0) <= 0 || ! $fila->comprobante_proveedores) {
            return true;
        }

        $abr = strtoupper(trim((string) ($fila->comprobante_proveedores->tipotransaccion_compras->abreviatura ?? '')));
        if ($abr === '') {
            return true;
        }

        // Abreviaturas de crédito empiezan en C (CDT, CGA, CNC, …); FDT/FACT = deuda.
        return ! str_starts_with($abr, 'C');
    }

    private static function etiquetaCreditoDesdeAplicacion(Proveedor_Cuentacorriente $fila): ?string
    {
        // En imports Anita la etiqueta OPP suele estar en la aplicación de la deuda
        // que apunta a este crédito (aplicado_id), no en las filas del propio crédito.
        $candidatos = Proveedor_Cuentacorriente_Aplicacion::query()
            ->where(function ($q) use ($fila) {
                $q->where('proveedor_cuentacorriente_aplicado_id', (int) $fila->id)
                    ->orWhere('proveedor_cuentacorriente_id', (int) $fila->id);
            })
            ->orderBy('id')
            ->get(['comprobanteaplicado', 'total']);

        foreach ($candidatos as $apl) {
            $raw = trim((string) ($apl->comprobanteaplicado ?? ''));
            if ($raw === '') {
                continue;
            }
            if (preg_match('/^(OPP|OPA|AOP)\b/i', $raw) === 1) {
                return self::normalizarEtiquetaOp($raw);
            }
        }

        return null;
    }

    /** "OPP A 2-57657" / "OPP A0002-57719" → "OPP 2-57657". */
    private static function normalizarEtiquetaOp(string $raw): string
    {
        if (preg_match('/^(OPP|OPA|AOP)\s+[A-Z]?\s*0*(\d+)\s*[-–]\s*0*(\d+)\s*$/i', trim($raw), $m) === 1) {
            return strtoupper($m[1]).' '.(int) $m[2].'-'.$m[3];
        }
        if (preg_match('/^(OPP|OPA|AOP)\s+[A-Z]?(\d+)\s*[-–]\s*(\d+)/i', trim($raw), $m) === 1) {
            return strtoupper($m[1]).' '.(int) $m[2].'-'.$m[3];
        }

        return $raw;
    }

    public static function saldoPendiente(float $total, ?float $aplicado): float
    {
        $aplicadoSum = (float) ($aplicado ?? 0);

        if ($total >= 0) {
            return max(0, $total + $aplicadoSum);
        }

        return min(0, $total + $aplicadoSum);
    }

    public static function saldoPendienteAbsoluto(float $total, ?float $aplicado): float
    {
        return abs(self::saldoPendiente($total, $aplicado));
    }

    /**
     * PDF imprimible: OP generada, o factura (escaneo / índice tracking / interno).
     *
     * @return array{tipo: string, id: int, titulo: string}|null
     */
    public static function destinoImpresion(Proveedor_Cuentacorriente $fila): ?array
    {
        // OP primero: en imports Anita el crédito puede tener también factura linkeada.
        if ((int) ($fila->pagoproveedor_id ?? 0) > 0) {
            return [
                'tipo' => 'pagoproveedor',
                'id' => (int) $fila->pagoproveedor_id,
                'titulo' => 'Imprimir orden de pago',
            ];
        }

        if ((int) ($fila->comprobante_proveedor_id ?? 0) <= 0 || ! $fila->comprobante_proveedores) {
            return null;
        }

        if (! self::comprobanteTienePdfDisponible($fila->comprobante_proveedores)) {
            return null;
        }

        return [
            'tipo' => 'comprobante_proveedor',
            'id' => (int) $fila->comprobante_proveedor_id,
            'titulo' => 'Ver PDF de la factura',
        ];
    }

    /**
     * Misma cascada que el tracking: adjunto/precarga, índice sincronizado, PDF interno.
     */
    public static function comprobanteTienePdfDisponible(Comprobante_Proveedor $comprobante): bool
    {
        if (ComprobanteProveedorArchivoPathSupport::referenciaPdfPrecarga($comprobante) !== null) {
            return true;
        }

        $comprobante->loadMissing(['tracking_indice', 'tipotransaccion_compras']);

        if ((bool) ($comprobante->tracking_indice?->pdf_disponible ?? false)) {
            return true;
        }

        $abrev = (string) ($comprobante->tipotransaccion_compras?->abreviatura ?? '');

        return ComprobanteProveedorInternoTipos::esInterno($abrev);
    }

    public static function urlImpresion(Proveedor_Cuentacorriente $fila): ?string
    {
        $destino = self::destinoImpresion($fila);
        if ($destino === null) {
            return null;
        }

        if ($destino['tipo'] === 'pagoproveedor') {
            return route('imprimir_pagoproveedor', ['id' => $destino['id']]);
        }

        return route('comprobante_proveedor_factura_pdf', [
            'id' => $destino['id'],
            'inline' => 1,
        ]);
    }

    public static function puedeImprimirComprobante(Proveedor_Cuentacorriente $fila): bool
    {
        $destino = self::destinoImpresion($fila);
        if ($destino === null) {
            return false;
        }

        if ($destino['tipo'] === 'pagoproveedor') {
            return can('listar-pagoproveedor', false)
                || can('editar-pagoproveedor', false)
                || can('listar-cuentacorriente-proveedor', false);
        }

        return can('editar-comprobante-proveedor', false)
            || can('listar-comprobante-proveedor', false)
            || can('listar-cuentacorriente-proveedor', false);
    }

    /**
     * Destino de edición del movimiento (OP o comprobante). Prioriza pago.
     *
     * @return array{tipo: string, id: int, titulo: string, route: string}|null
     */
    public static function destinoEdicion(Proveedor_Cuentacorriente $fila): ?array
    {
        if ((int) ($fila->pagoproveedor_id ?? 0) > 0) {
            return [
                'tipo' => 'pagoproveedor',
                'id' => (int) $fila->pagoproveedor_id,
                'titulo' => 'Editar '.self::etiquetaComprobante($fila),
                'route' => 'editar_pagoproveedor',
            ];
        }

        if ((int) ($fila->comprobante_proveedor_id ?? 0) > 0) {
            return [
                'tipo' => 'comprobante_proveedor',
                'id' => (int) $fila->comprobante_proveedor_id,
                'titulo' => 'Editar '.self::etiquetaComprobante($fila),
                'route' => 'editar_comprobante_proveedor',
            ];
        }

        return null;
    }

    public static function urlEdicion(Proveedor_Cuentacorriente $fila): ?string
    {
        $destino = self::destinoEdicion($fila);
        if ($destino === null) {
            return null;
        }

        return route($destino['route'], ['id' => $destino['id']]);
    }

    public static function puedeEditarComprobante(Proveedor_Cuentacorriente $fila): bool
    {
        $destino = self::destinoEdicion($fila);
        if ($destino === null) {
            return false;
        }

        if ($destino['tipo'] === 'pagoproveedor') {
            return can('editar-pagoproveedor', false)
                || can('listar-pagoproveedor', false);
        }

        return can('editar-comprobante-proveedor', false)
            || can('listar-comprobante-proveedor', false);
    }
}

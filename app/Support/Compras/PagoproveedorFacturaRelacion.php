<?php

declare(strict_types=1);

namespace App\Support\Compras;

use App\Support\Listado\ListadoQbeSupport;
use Illuminate\Contracts\Database\Query\Builder;

/**
 * Relación pago → factura aplicada. La registra ListadoRelacionCatalogo.
 * En ingresos y egresos solo entra por OPP y OPA que tienen pagoproveedor_id.
 */
final class PagoproveedorFacturaRelacion
{
    /**
     * @return array<string, array{column: string, type: string, label: string}>
     */
    public static function campos(): array
    {
        return [
            'factura_numero' => [
                'column' => '',
                'type' => 'texto',
                'label' => 'Nro. factura aplicada',
            ],
            'factura_fecha' => [
                'column' => '',
                'type' => 'fecha',
                'label' => 'Fecha factura aplicada',
            ],
            'factura_total' => [
                'column' => '',
                'type' => 'decimal',
                'label' => 'Total factura aplicada',
            ],
        ];
    }

    public static function aplicar(
        Builder $query,
        string $campoKey,
        string $operador,
        string $valor,
        string $valorHasta,
        string $origenLiteral
    ): void {
        if ($origenLiteral !== PagoproveedorListadoFila::ORIGEN_PAGOPROVEEDOR) {
            $query->whereRaw('1 = 0');

            return;
        }

        $columna = match ($campoKey) {
            'factura_fecha' => 'cp.fechacomprobante',
            'factura_total' => 'cp.total',
            default => 'cp.numerocomprobante',
        };
        $tipo = match ($campoKey) {
            'factura_fecha' => 'fecha',
            'factura_total' => 'decimal',
            default => 'texto',
        };

        $query->whereExists(function (Builder $sub) use ($campoKey, $columna, $tipo, $operador, $valor, $valorHasta) {
            self::subconsultaFactura($sub, 'pp.id', $campoKey, $columna, $tipo, $operador, $valor, $valorHasta);
        });
    }

    /**
     * OPP y OPA de caja que apuntan a una orden de pago. El resto del movimiento no entra.
     */
    public static function aplicarEnCajaMovimiento(
        Builder $query,
        string $campoKey,
        string $operador,
        string $valor,
        string $valorHasta
    ): void {
        $columna = match ($campoKey) {
            'factura_fecha' => 'cp.fechacomprobante',
            'factura_total' => 'cp.total',
            default => 'cp.numerocomprobante',
        };
        $tipo = match ($campoKey) {
            'factura_fecha' => 'fecha',
            'factura_total' => 'decimal',
            default => 'texto',
        };

        $query->whereRaw('UPPER(TRIM(tipotransaccion_caja.abreviatura)) IN (?, ?)', ['OPP', 'OPA'])
            ->whereNotNull('caja_movimiento.pagoproveedor_id')
            ->whereExists(function (Builder $sub) use ($campoKey, $columna, $tipo, $operador, $valor, $valorHasta) {
                self::subconsultaFactura($sub, 'caja_movimiento.pagoproveedor_id', $campoKey, $columna, $tipo, $operador, $valor, $valorHasta);
            });
    }

    private static function subconsultaFactura(
        Builder $sub,
        string $columnaPago,
        string $campoKey,
        string $columna,
        string $tipo,
        string $operador,
        string $valor,
        string $valorHasta
    ): void {
        $sub->selectRaw('1')
            ->from('pagoproveedor_comprobante as ppc')
            ->join('proveedor_cuentacorriente as deuda', 'deuda.id', '=', 'ppc.proveedor_cuentacorriente_id')
            ->join('comprobante_proveedor as cp', 'cp.id', '=', 'deuda.comprobante_proveedor_id')
            ->whereColumn('ppc.pagoproveedor_id', $columnaPago);

        if ($tipo === 'fecha') {
            ListadoQbeSupport::aplicarFecha($sub, $columna, $operador, $valor, $valorHasta);

            return;
        }
        if ($tipo === 'decimal') {
            PagoproveedorListadoFiltros::aplicarExpresionRelacion($sub, 'decimal', $columna, $operador, $valor, $valorHasta);

            return;
        }
        if ($campoKey === 'factura_numero' && preg_match('/^\s*(\d+)\s*-\s*(\d+)\s*$/', $valor, $partes) === 1) {
            if (in_array($operador, ['igual', 'contiene', 'empieza'], true)) {
                $sub->whereRaw('CAST(cp.sucursal AS CHAR) = ?', [$partes[1]])
                    ->whereRaw('CAST(cp.numerocomprobante AS CHAR) = ?', [$partes[2]]);

                return;
            }
        }
        if ($campoKey === 'factura_numero' && $operador === 'contiene' && $valor !== '') {
            $sub->where(function (Builder $texto) use ($columna, $operador, $valor, $valorHasta): void {
                PagoproveedorListadoFiltros::aplicarExpresionRelacion($texto, 'texto', $columna, $operador, $valor, $valorHasta);
                $texto->orWhereRaw(
                    "CONCAT(CAST(cp.sucursal AS CHAR), '-', CAST(cp.numerocomprobante AS CHAR)) LIKE ?",
                    ['%'.$valor.'%']
                );
            });

            return;
        }
        PagoproveedorListadoFiltros::aplicarExpresionRelacion($sub, 'texto', $columna, $operador, $valor, $valorHasta);
    }
}

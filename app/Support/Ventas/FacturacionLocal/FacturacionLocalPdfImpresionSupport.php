<?php

declare(strict_types=1);

namespace App\Support\Ventas\FacturacionLocal;

use Illuminate\Support\Facades\DB;

/**
 * Impresión de la factura del POS local.
 *
 * venta_emision.precio ya es el unitario con el descuento de línea aplicado
 * (IVA incluido). El porcentaje queda en descuento. No hay que volver a
 * descontarlo al armar el PDF: el unitario de lista se recupera al revés.
 */
final class FacturacionLocalPdfImpresionSupport
{
    /**
     * @return array{unitario: float, descuento: float, importe: float}
     */
    public static function importesLinea(float $precioGuardado, float $descuentoPct, float $cantidad): array
    {
        $precioGuardado = round($precioGuardado, 2);
        $cantidad = round($cantidad, 4);
        $unitario = self::unitarioLista($precioGuardado, $descuentoPct);
        $importe = round($precioGuardado * $cantidad, 2);
        $bruto = round($unitario * $cantidad, 2);

        return [
            'unitario' => $unitario,
            'descuento' => round(max(0., $bruto - $importe), 2),
            'importe' => $importe,
        ];
    }

    /**
     * @param  list<array{nombre?:string,monto?:float,observacion?:string}>  $medios
     */
    public static function textoDesdeMedios(array $medios): string
    {
        $filas = [];
        foreach ($medios as $medio) {
            $nombre = trim((string) ($medio['nombre'] ?? ''));
            $monto = round((float) ($medio['monto'] ?? 0), 2);
            if ($nombre === '' || $monto <= 0.) {
                continue;
            }
            $filas[] = [
                'nombre' => $nombre,
                'monto' => $monto,
                'cupon' => self::cuponDesdeObservacion((string) ($medio['observacion'] ?? '')),
            ];
        }
        if ($filas === []) {
            return '';
        }

        $mostrarMonto = count($filas) > 1;
        $textos = [];
        foreach ($filas as $fila) {
            $texto = $fila['nombre'];
            if ($mostrarMonto) {
                $texto .= ' $ '.number_format($fila['monto'], 2);
            }
            if ($fila['cupon'] !== '') {
                $texto .= ' — Cupón '.$fila['cupon'];
            }
            $textos[] = $texto;
        }

        return implode(' · ', $textos);
    }

    public static function cuponDesdeObservacion(string $observacion): string
    {
        if (preg_match('/Cup[oó]n\s+(\S+)/iu', $observacion, $m) !== 1) {
            return '';
        }

        return trim((string) ($m[1] ?? ''));
    }

    public static function textoFormaPago(int $ventaId): string
    {
        if ($ventaId <= 0) {
            return '';
        }

        $rows = DB::table('caja_movimiento_cuentacaja as l')
            ->join('caja_movimiento as cm', 'cm.id', '=', 'l.caja_movimiento_id')
            ->leftJoin('cuentacaja as c', 'c.id', '=', 'l.cuentacaja_id')
            ->where('cm.venta_id', $ventaId)
            ->whereNull('cm.caja_movimiento_revertido_por_id')
            ->orderBy('l.id')
            ->get(['c.nombre', 'l.monto', 'l.observacion']);

        $medios = [];
        foreach ($rows as $row) {
            $medios[] = [
                'nombre' => (string) ($row->nombre ?? ''),
                'monto' => (float) ($row->monto ?? 0),
                'observacion' => (string) ($row->observacion ?? ''),
            ];
        }

        return self::textoDesdeMedios($medios);
    }

    private static function unitarioLista(float $precioGuardado, float $descuentoPct): float
    {
        if ($descuentoPct <= 0.00001 || $descuentoPct >= 100.) {
            return $precioGuardado;
        }

        $factor = 1. - ($descuentoPct / 100.);
        if ($factor <= 0.) {
            return $precioGuardado;
        }

        $crudo = $precioGuardado / $factor;
        $entero = round($crudo);
        // El alta redondea el precio ya descontado a 2 decimales; al invertir,
        // un precio de lista entero (típico del local) puede quedar a unos centavos.
        if (abs($crudo - $entero) < 0.2) {
            return (float) $entero;
        }

        return round($crudo, 2);
    }
}

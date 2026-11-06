<?php

namespace App\Support\Compras;

use App\Models\Compras\Proveedor;
use App\Services\Compras\RetencionesPagoCalculator;
use App\Services\Compras\RetencionesPagoContextoBuilder;

/**
 * Estima retenciones de un pago del programa (mismo motor que la OP).
 * Sirve para asignar CHT al neto y dejar en TRANSF solo la transferencia.
 */
final class ProgramaPagoRetencionEstimacionSupport
{
    /**
     * @return array{bruto: float, retenciones: float, neto: float}
     */
    public static function estimar(
        int $empresaId,
        int $proveedorId,
        float $bruto,
        ?string $fecha = null,
    ): array {
        $bruto = round(max(0, $bruto), 2);
        if ($empresaId <= 0 || $proveedorId <= 0 || $bruto < 0.01) {
            return ['bruto' => $bruto, 'retenciones' => 0.0, 'neto' => $bruto];
        }

        $fecha = self::normalizarFecha($fecha);
        $aplicaciones = ProgramaPagoOpPlanSupport::aplicarFifo(
            $empresaId,
            $proveedorId,
            $bruto,
            $fecha
        );
        $retenciones = self::calcularRetenciones(
            $empresaId,
            $proveedorId,
            $aplicaciones,
            $fecha,
            $bruto
        );
        $neto = round(max(0, $bruto - $retenciones), 2);

        return [
            'bruto' => $bruto,
            'retenciones' => $retenciones,
            'neto' => $neto,
        ];
    }

    /**
     * Cuando la matriz ya tiene medios (cheques + TRANSF = neto), recupera el bruto
     * a aplicar en la OP: medios + retenciones.
     *
     * @return array{bruto: float, retenciones: float, neto: float}
     */
    public static function estimarDesdeMedios(
        int $empresaId,
        int $proveedorId,
        float $medios,
        ?string $fecha = null,
    ): array {
        $medios = round(max(0, $medios), 2);
        if ($medios < 0.01) {
            return ['bruto' => 0.0, 'retenciones' => 0.0, 'neto' => 0.0];
        }

        $bruto = $medios;
        $retenciones = 0.0;
        for ($i = 0; $i < 4; $i++) {
            $est = self::estimar($empresaId, $proveedorId, $bruto, $fecha);
            $retenciones = $est['retenciones'];
            $nuevo = round($medios + $retenciones, 2);
            if (abs($nuevo - $bruto) < 0.02) {
                $bruto = $nuevo;
                break;
            }
            $bruto = $nuevo;
        }
        $est = self::estimar($empresaId, $proveedorId, $bruto, $fecha);

        return [
            'bruto' => round($est['bruto'], 2),
            'retenciones' => round($est['retenciones'], 2),
            'neto' => round(max(0, $est['bruto'] - $est['retenciones']), 2),
        ];
    }

    /**
     * Decide si la suma de la fila es todavía el bruto programado o ya el neto
     * (después de alinear cheques). Usa el saldo adeudado como referencia de bruto.
     *
     * @return array{bruto: float, retenciones: float, neto: float, ya_neto: bool}
     */
    public static function resolverBrutoNeto(
        int $empresaId,
        int $proveedorId,
        float $totalFila,
        float $saldoAdeudado,
        ?string $fecha = null,
    ): array {
        $totalFila = round(max(0, $totalFila), 2);
        $saldoAdeudado = round(max(0, $saldoAdeudado), 2);
        if ($totalFila < 0.01) {
            return ['bruto' => 0.0, 'retenciones' => 0.0, 'neto' => 0.0, 'ya_neto' => false];
        }

        $brutoRef = $saldoAdeudado >= 0.01 ? $saldoAdeudado : $totalFila;
        $estSaldo = self::estimar($empresaId, $proveedorId, $brutoRef, $fecha);

        // Ya alineado al neto: la fila está cerca del neto del saldo (no del saldo bruto).
        if (
            $estSaldo['retenciones'] > 0.01
            && abs($totalFila - $estSaldo['neto']) + 0.05 < abs($totalFila - $brutoRef)
        ) {
            return [
                'bruto' => (float) $estSaldo['bruto'],
                'retenciones' => (float) $estSaldo['retenciones'],
                'neto' => $totalFila,
                'ya_neto' => true,
            ];
        }

        $est = self::estimar($empresaId, $proveedorId, $totalFila, $fecha);

        return [
            'bruto' => (float) $est['bruto'],
            'retenciones' => (float) $est['retenciones'],
            'neto' => (float) $est['neto'],
            'ya_neto' => false,
        ];
    }

    /**
     * Reparte el neto entre los meses a prorrata del bruto. El último ajusta el redondeo.
     *
     * @param  array<string, float>  $brutosPorClave
     * @return array<string, float>
     */
    public static function repartirNetoPorClave(array $brutosPorClave, float $neto): array
    {
        $neto = round(max(0, $neto), 2);
        $filtrados = [];
        foreach ($brutosPorClave as $clave => $monto) {
            $monto = round((float) $monto, 2);
            if ($monto >= 0.01) {
                $filtrados[(string) $clave] = $monto;
            }
        }
        if ($filtrados === [] || $neto < 0.01) {
            return array_fill_keys(array_keys($filtrados), 0.0);
        }

        $totalBruto = round(array_sum($filtrados), 2);
        if ($totalBruto < 0.01) {
            return array_fill_keys(array_keys($filtrados), 0.0);
        }
        if (abs($neto - $totalBruto) < 0.015) {
            return $filtrados;
        }

        // Prorratea el neto (puede ser menor o mayor que la suma de meses si había TRANSF).
        $out = [];
        $acum = 0.0;
        $claves = array_keys($filtrados);
        $ultima = $claves[count($claves) - 1];
        foreach ($claves as $clave) {
            if ($clave === $ultima) {
                $out[$clave] = round(max(0, $neto - $acum), 2);
                break;
            }
            $parte = round($neto * ($filtrados[$clave] / $totalBruto), 2);
            $out[$clave] = $parte;
            $acum = round($acum + $parte, 2);
        }

        return $out;
    }

    private static function normalizarFecha(?string $fecha): string
    {
        $fecha = trim((string) $fecha);
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
            return $fecha;
        }

        return date('Y-m-d');
    }

    /**
     * @param  list<array<string, mixed>>  $aplicaciones
     */
    private static function calcularRetenciones(
        int $empresaId,
        int $proveedorId,
        array $aplicaciones,
        string $fecha,
        float $brutoFallback,
    ): float {
        $proveedor = Proveedor::query()
            ->with(['condicionivas', 'condicionIIBBs'])
            ->find($proveedorId);
        if ($proveedor === null) {
            return 0.0;
        }

        try {
            $ctx = app(RetencionesPagoContextoBuilder::class)->armarInput(
                proveedor: $proveedor,
                aplicaciones: $aplicaciones,
                fecha: $fecha,
                empresaId: $empresaId,
                monedaPagoId: 1,
                cotizacionPago: 1.0,
                overrides: [
                    'calcular_ganancias' => true,
                    'calcular_iva' => true,
                    'calcular_suss' => true,
                    'calcular_iibb' => true,
                ],
                importeNetoFallback: $brutoFallback,
                importeIvaFallback: 0.0,
            );
            $resultado = app(RetencionesPagoCalculator::class)->calcular($ctx['input']);

            return round($resultado->totalRetenciones(), 2);
        } catch (\Throwable) {
            return 0.0;
        }
    }
}

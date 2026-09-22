<?php

namespace App\Support\Compras;

use App\Models\Compras\Condicionpago;
use Carbon\Carbon;

/**
 * Vencimientos de cuotas de factura proveedor desde la condición de pago.
 *
 * Para plazos en días (D/O) sin vencimiento fijo: F.Comp. + plazo de cada cuota
 * (días corridos desde fecha de factura, no fechas absolutas de la OC).
 */
final class ComprobanteProveedorVencimientoCondicionSupport
{
    /**
     * @return list<\App\Models\Compras\Condicionpagocuota>|null  null = no aplicable
     */
    public static function plantillaDiasSiAplicable(?int $condicionpagoId): ?array
    {
        if (! $condicionpagoId) {
            return null;
        }

        $cp = Condicionpago::query()
            ->with(['condicionpagocuotas' => fn ($q) => $q->orderBy('cuota')])
            ->find($condicionpagoId);

        if (! $cp || $cp->condicionpagocuotas->isEmpty()) {
            return null;
        }

        $filas = $cp->condicionpagocuotas->values()->all();
        foreach ($filas as $fila) {
            $tipo = (string) ($fila->tipoplazo ?? '');
            if ($tipo === 'F') {
                return null;
            }
            if (! empty($fila->fechavencimiento)) {
                return null;
            }
            if ($tipo !== '' && $tipo !== 'D' && $tipo !== 'O') {
                return null;
            }
        }

        return $filas;
    }

    /**
     * Fechas Y-m-d por índice de cuota de la plantilla (0-based).
     *
     * @param  list<object>  $plantilla
     * @return list<string>
     */
    public static function fechasDesdePlantilla(array $plantilla, string $fechaBaseYmd): array
    {
        $base = Carbon::parse($fechaBaseYmd)->startOfDay();
        $fechas = [];
        foreach ($plantilla as $fila) {
            $fechas[] = $base->copy()->addDays((int) ($fila->plazo ?? 0))->format('Y-m-d');
        }

        return $fechas;
    }

    /**
     * Reescribe fechavencimiento de las cuotas cuando la condición es días F.Fact.
     * Solo si la cantidad de cuotas coincide con la plantilla (evita pisar planes e-cheq de la OC).
     *
     * @param  list<array<string, mixed>>  $cuotas
     * @return list<array<string, mixed>>
     */
    public static function aplicarACuotas(array $cuotas, ?int $condicionpagoId, string $fechaBaseYmd): array
    {
        if ($cuotas === []) {
            return $cuotas;
        }

        $plantilla = self::plantillaDiasSiAplicable($condicionpagoId);
        if ($plantilla === null) {
            return $cuotas;
        }

        if (count($plantilla) !== count($cuotas)) {
            return $cuotas;
        }

        $fechas = self::fechasDesdePlantilla($plantilla, $fechaBaseYmd);
        foreach ($cuotas as $i => $cuota) {
            $cuotas[$i]['fechavencimiento'] = $fechas[$i];
        }

        return $cuotas;
    }

    /**
     * Una cuota por fila de plantilla, montos por porcentaje (o partes iguales).
     *
     * @return list<array<string, mixed>>
     */
    public static function armarCuotasDesdeCondicion(
        ?int $condicionpagoId,
        string $fechaBaseYmd,
        float $totalComprobante,
        int $monedaId,
        float $cotizacion,
        int $formapagoId = 1,
    ): array {
        $plantilla = self::plantillaDiasSiAplicable($condicionpagoId);
        if ($plantilla === null) {
            return [];
        }

        $fechas = self::fechasDesdePlantilla($plantilla, $fechaBaseYmd);
        $n = count($plantilla);
        $sumPct = 0.0;
        foreach ($plantilla as $fila) {
            $sumPct += (float) ($fila->porcentaje ?? 0);
        }

        $cuotas = [];
        $asignado = 0.0;
        foreach ($plantilla as $i => $fila) {
            $esUltima = ($i === $n - 1);
            if ($sumPct > 0 && (float) ($fila->porcentaje ?? 0) > 0 && ! $esUltima) {
                $monto = round($totalComprobante * (float) $fila->porcentaje / $sumPct, 2);
                $asignado += $monto;
            } elseif ($esUltima) {
                $monto = round($totalComprobante - $asignado, 2);
            } else {
                $monto = round($totalComprobante / max(1, $n), 2);
                $asignado += $monto;
            }

            $cuotas[] = [
                'numero_cuota' => $i + 1,
                'fechavencimiento' => $fechas[$i],
                'monto' => $monto,
                'moneda_id' => $monedaId,
                'cotizacion' => $cotizacion,
                'formapago_id' => $formapagoId,
                'detalle' => null,
                'ordencompra_comprobante_cuota_id' => null,
            ];
        }

        return $cuotas;
    }
}

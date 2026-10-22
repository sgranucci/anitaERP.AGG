<?php

namespace App\Support\Caja;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Promedio de días de un recibo: días entre la fecha de cada factura y la fecha
 * del cobro, ponderados por el importe aplicado (en pesos).
 *
 * Es el mismo cálculo que figura en el PDF del recibo.
 */
final class CobranzaPromedioPagoSupport
{
    /**
     * @return array{dias: ?int, peso: float}
     */
    public static function diasYPeso(
        $fechaCobro,
        $fechaDeuda,
        float $montoAplicado,
        $monedaId,
        $cotizacionAplicado,
        $cotizacionDeuda,
    ): array {
        $monto = abs($montoAplicado);
        if (! $fechaCobro || ! $fechaDeuda || $monto < 0.01) {
            return ['dias' => null, 'peso' => 0.0];
        }

        $dias = self::diasEntre($fechaCobro, $fechaDeuda);
        $cotiz = self::cotizacionParaPeso($cotizacionAplicado, $cotizacionDeuda);
        $peso = $monto * (float) calculaCoeficienteMoneda(1, $monedaId, $cotiz);

        return ['dias' => $dias, 'peso' => $peso];
    }

    public static function cerrar(float $pesoDias, float $pesoTotal): ?int
    {
        return $pesoTotal >= 0.01 ? (int) round($pesoDias / $pesoTotal) : null;
    }

    /**
     * @param  \Illuminate\Support\Collection|\Illuminate\Contracts\Pagination\Paginator|iterable  $cobranzas
     */
    public static function aplicar($cobranzas): void
    {
        $items = $cobranzas instanceof \Illuminate\Contracts\Pagination\Paginator
            ? $cobranzas->getCollection()
            : $cobranzas;

        if ($items === null || (is_countable($items) && count($items) === 0)) {
            return;
        }

        $ids = [];
        foreach ($items as $item) {
            $ids[] = (int) $item->id;
        }

        $promedios = self::porIds($ids);
        foreach ($items as $item) {
            $item->promedio_pago_dias = $promedios[(int) $item->id] ?? null;
        }
    }

    /**
     * @param  list<int>  $ids
     * @return array<int, ?int>
     */
    public static function porIds(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        $out = [];
        foreach (array_chunk($ids, 2000) as $lote) {
            $rows = DB::table('cobranza_comprobante as cc')
                ->join('cobranza as c', 'c.id', '=', 'cc.cobranza_id')
                ->join('cliente_cuentacorriente as ccc', 'ccc.id', '=', 'cc.cliente_cuentacorriente_id')
                ->whereIn('cc.cobranza_id', $lote)
                ->get([
                    'cc.cobranza_id',
                    'cc.montoaplicado',
                    'cc.cotizacion as cotizacion_aplicado',
                    'c.fecha as fecha_cobro',
                    'ccc.fecha as fecha_deuda',
                    'ccc.cotizacion as cotizacion_deuda',
                    'ccc.moneda_id as moneda_id',
                ]);

            $grupos = [];
            foreach ($rows as $row) {
                $grupos[(int) $row->cobranza_id][] = $row;
            }

            foreach ($lote as $id) {
                $out[$id] = self::promedioDesdeFilas($grupos[$id] ?? []);
            }
        }

        return $out;
    }

    public static function subtituloListado(?string $busqueda, ?string $fechaDesde, ?string $fechaHasta): string
    {
        $partes = [
            'Promedio de días: desde la fecha de cada factura hasta el recibo, ponderado por el importe cobrado.',
        ];
        if ($fechaDesde || $fechaHasta) {
            $desdeTxt = $fechaDesde ? Carbon::parse($fechaDesde)->format('d/m/Y') : '…';
            $hastaTxt = $fechaHasta ? Carbon::parse($fechaHasta)->format('d/m/Y') : '…';
            $partes[] = 'Fecha '.$desdeTxt.' a '.$hastaTxt;
        }
        $busqueda = trim((string) $busqueda);
        if ($busqueda !== '') {
            $partes[] = 'Búsqueda: '.$busqueda;
        }

        return implode(' · ', $partes);
    }

    /**
     * @param  list<object>  $filas
     */
    private static function promedioDesdeFilas(array $filas): ?int
    {
        $pesoDias = 0.0;
        $pesoTotal = 0.0;
        foreach ($filas as $fila) {
            $aporte = self::diasYPeso(
                $fila->fecha_cobro,
                $fila->fecha_deuda,
                (float) $fila->montoaplicado,
                $fila->moneda_id,
                $fila->cotizacion_aplicado,
                $fila->cotizacion_deuda,
            );
            if ($aporte['dias'] === null || $aporte['peso'] < 0.01) {
                continue;
            }
            $pesoDias += $aporte['dias'] * $aporte['peso'];
            $pesoTotal += $aporte['peso'];
        }

        return self::cerrar($pesoDias, $pesoTotal);
    }

    private static function diasEntre($fechaCobro, $fechaDeuda): int
    {
        $cobro = Carbon::parse($fechaCobro)->copy()->startOfDay();
        $deuda = Carbon::parse($fechaDeuda)->copy()->startOfDay();

        return (int) round(($cobro->getTimestamp() - $deuda->getTimestamp()) / 86400);
    }

    private static function cotizacionParaPeso($cotizacionAplicado, $cotizacionDeuda): float
    {
        $cotiz = (float) ($cotizacionAplicado ?: ($cotizacionDeuda ?: 1));

        return $cotiz > 0 ? $cotiz : 1.0;
    }
}

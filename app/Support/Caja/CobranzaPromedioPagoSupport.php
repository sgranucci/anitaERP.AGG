<?php

namespace App\Support\Caja;

use App\Models\Caja\Cobranza;
use App\Support\Configuracion\CotizacionVigenteSupport;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Promedio de días de un recibo: diferencia entre la fecha promedio de las
 * facturas aplicadas y la fecha promedio en que se cobra cada valor, ponderadas
 * por el importe en pesos.
 *
 * El cheque y el e-cheq cuentan en su fecha de pago. El efectivo, la transferencia
 * y la retención cuentan el día del recibo, porque ahí ya están cobrados.
 * Si el recibo no tiene valores, se usa la fecha del recibo.
 */
final class CobranzaPromedioPagoSupport
{
    public static function desdeCobranza(Cobranza $cobranza): ?int
    {
        $fechaRecibo = $cobranza->fecha;

        $facturas = [];
        foreach ($cobranza->cobranza_comprobantes as $comprobante) {
            $cc = $comprobante->cliente_cuentacorrientes;
            if ($cc === null) {
                continue;
            }
            $punto = self::punto(
                $cc->fecha,
                (float) ($comprobante->montoaplicado ?? 0),
                $cc->moneda_id,
                $comprobante->cotizacion ?: $cc->cotizacion,
            );
            if ($punto !== null) {
                $facturas[] = $punto;
            }
        }

        $valores = [];
        foreach ($cobranza->cheques as $cheque) {
            if (strtoupper(trim((string) ($cheque->estado ?? ''))) === 'A') {
                continue;
            }
            $punto = self::punto(
                $cheque->fechapago ?: ($cheque->fechaemision ?: $fechaRecibo),
                (float) ($cheque->monto ?? 0),
                $cheque->moneda_id,
                $cheque->cotizacion,
            );
            if ($punto !== null) {
                $valores[] = $punto;
            }
        }
        foreach ($cobranza->cobranza_retenciones as $retencion) {
            $punto = self::punto(
                $fechaRecibo,
                (float) ($retencion->monto ?? 0),
                $retencion->moneda_id,
                $retencion->cotizacion,
            );
            if ($punto !== null) {
                $valores[] = $punto;
            }
        }
        foreach ($cobranza->caja_movimientos as $movimiento) {
            foreach ($movimiento->caja_movimiento_cuentacajas as $linea) {
                $punto = self::punto(
                    $linea->fecha ?: ($movimiento->fecha ?: $fechaRecibo),
                    (float) ($linea->monto ?? 0),
                    $linea->moneda_id,
                    $linea->cotizacion,
                );
                if ($punto !== null) {
                    $valores[] = $punto;
                }
            }
        }

        return self::promedio($facturas, $valores, $fechaRecibo);
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
            $out += self::promediosDelLote($lote);
        }

        return $out;
    }

    public static function subtituloListado(?string $busqueda, ?string $fechaDesde, ?string $fechaHasta): string
    {
        $partes = [
            'Promedio de días: desde la fecha de cada factura hasta la fecha de pago de cada valor (e-cheq o cheque; el día del recibo si es efectivo o retención), ponderado por el importe.',
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
     * @param  list<int>  $lote
     * @return array<int, ?int>
     */
    private static function promediosDelLote(array $lote): array
    {
        $fechasRecibo = DB::table('cobranza')->whereIn('id', $lote)->pluck('fecha', 'id');

        $facturas = [];
        $filasFactura = DB::table('cobranza_comprobante as cc')
            ->join('cliente_cuentacorriente as ccc', 'ccc.id', '=', 'cc.cliente_cuentacorriente_id')
            ->whereIn('cc.cobranza_id', $lote)
            ->get([
                'cc.cobranza_id',
                'cc.montoaplicado',
                'cc.cotizacion as cotizacion_aplicado',
                'ccc.fecha as fecha_deuda',
                'ccc.cotizacion as cotizacion_deuda',
                'ccc.moneda_id as moneda_id',
            ]);
        foreach ($filasFactura as $fila) {
            $punto = self::punto(
                $fila->fecha_deuda,
                (float) $fila->montoaplicado,
                $fila->moneda_id,
                $fila->cotizacion_aplicado ?: $fila->cotizacion_deuda,
            );
            if ($punto !== null) {
                $facturas[(int) $fila->cobranza_id][] = $punto;
            }
        }

        $valores = [];
        $filasCheque = DB::table('cheque')
            ->whereIn('cobranza_id', $lote)
            ->where(function ($q) {
                $q->whereNull('estado')->orWhere('estado', '<>', 'A');
            })
            ->get(['cobranza_id', 'fechapago', 'fechaemision', 'monto', 'cotizacion', 'moneda_id']);
        foreach ($filasCheque as $fila) {
            $id = (int) $fila->cobranza_id;
            $punto = self::punto(
                $fila->fechapago ?: ($fila->fechaemision ?: ($fechasRecibo[$id] ?? null)),
                (float) $fila->monto,
                $fila->moneda_id,
                $fila->cotizacion,
            );
            if ($punto !== null) {
                $valores[$id][] = $punto;
            }
        }

        $filasRetencion = DB::table('cobranza_retencion as r')
            ->join('cobranza as c', 'c.id', '=', 'r.cobranza_id')
            ->whereIn('r.cobranza_id', $lote)
            ->get(['r.cobranza_id', 'c.fecha', 'r.monto', 'r.cotizacion', 'r.moneda_id']);
        foreach ($filasRetencion as $fila) {
            $punto = self::punto($fila->fecha, (float) $fila->monto, $fila->moneda_id, $fila->cotizacion);
            if ($punto !== null) {
                $valores[(int) $fila->cobranza_id][] = $punto;
            }
        }

        $filasCaja = DB::table('caja_movimiento as cm')
            ->join('caja_movimiento_cuentacaja as cmc', 'cmc.caja_movimiento_id', '=', 'cm.id')
            ->whereIn('cm.cobranza_id', $lote)
            ->get([
                'cm.cobranza_id',
                'cm.fecha as fecha_movimiento',
                'cmc.fecha as fecha_linea',
                'cmc.monto',
                'cmc.cotizacion',
                'cmc.moneda_id',
            ]);
        foreach ($filasCaja as $fila) {
            $id = (int) $fila->cobranza_id;
            $punto = self::punto(
                $fila->fecha_linea ?: ($fila->fecha_movimiento ?: ($fechasRecibo[$id] ?? null)),
                (float) $fila->monto,
                $fila->moneda_id,
                $fila->cotizacion,
            );
            if ($punto !== null) {
                $valores[$id][] = $punto;
            }
        }

        $out = [];
        foreach ($lote as $id) {
            $out[$id] = self::promedio(
                $facturas[$id] ?? [],
                $valores[$id] ?? [],
                $fechasRecibo[$id] ?? null,
            );
        }

        return $out;
    }

    /**
     * @param  list<array{fecha: mixed, peso: float}>  $facturas
     * @param  list<array{fecha: mixed, peso: float}>  $valores
     */
    private static function promedio(array $facturas, array $valores, $fechaRecibo): ?int
    {
        $facturasMomento = self::momento($facturas);
        if ($facturasMomento === null) {
            return null;
        }

        $valoresMomento = self::momento($valores);
        if ($valoresMomento === null && $fechaRecibo) {
            $valoresMomento = self::momento([
                ['fecha' => $fechaRecibo, 'peso' => $facturasMomento['peso']],
            ]);
        }
        if ($valoresMomento === null) {
            return null;
        }

        $dias = ($valoresMomento['momento'] / $valoresMomento['peso'])
            - ($facturasMomento['momento'] / $facturasMomento['peso']);

        return (int) round($dias);
    }

    /**
     * @param  list<array{fecha: mixed, peso: float}>  $puntos
     * @return array{peso: float, momento: float}|null
     */
    private static function momento(array $puntos): ?array
    {
        $peso = 0.0;
        $momento = 0.0;
        foreach ($puntos as $punto) {
            $ordinal = self::diaOrdinal($punto['fecha'] ?? null);
            $pesoPunto = (float) ($punto['peso'] ?? 0);
            if ($ordinal === null || $pesoPunto < 0.01) {
                continue;
            }
            $peso += $pesoPunto;
            $momento += $ordinal * $pesoPunto;
        }

        return $peso >= 0.01 ? ['peso' => $peso, 'momento' => $momento] : null;
    }

    /**
     * @return array{fecha: mixed, peso: float}|null
     */
    private static function punto($fecha, float $monto, $monedaId, $cotizacion): ?array
    {
        if (! $fecha || $monto < 0.01) {
            return null;
        }

        $monedaId = (int) $monedaId > 0 ? (int) $monedaId : 1;
        $cotiz = CotizacionVigenteSupport::ventaEfectiva($cotizacion, $monedaId, $fecha);
        $peso = $monto * $cotiz;
        if ($peso < 0.01) {
            return null;
        }

        return ['fecha' => $fecha, 'peso' => $peso];
    }

    private static function diaOrdinal($fecha): ?int
    {
        if (! $fecha) {
            return null;
        }

        try {
            $dia = Carbon::parse($fecha)->startOfDay();
        } catch (\Throwable) {
            return null;
        }

        return (int) round(Carbon::parse('2000-01-01')->startOfDay()->diffInDays($dia, false));
    }
}

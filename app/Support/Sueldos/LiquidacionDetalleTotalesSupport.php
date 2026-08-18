<?php

namespace App\Support\Sueldos;

use App\Models\Sueldos\Liquidacion_Recibo_Sueldos;
use App\Models\Sueldos\Liquidacion_Sueldos;

/**
 * Totales de recibo/cabecera compartidos entre motor ERP e import auxconf.
 */
final class LiquidacionDetalleTotalesSupport
{
    public static function columnaParaTipo(?string $tipo): string
    {
        return match ($tipo) {
            'descuento', 'aporte', 'retencion' => 'descuento',
            'contribucion' => 'contribucion',
            'informativo' => 'informativo',
            'neto' => 'neto',
            default => 'haber',
        };
    }

    /**
     * @param  array<int, array<string, mixed>>|\Illuminate\Support\Collection  $lineas
     * @return array{rem:float,norem:float,desc:float,aportes:float,contrib:float,asig:float,bruto:float,neto:float}
     */
    public static function totalesRecibo($lineas): array
    {
        $t = ['rem' => 0.0, 'norem' => 0.0, 'desc' => 0.0, 'aportes' => 0.0, 'contrib' => 0.0, 'asig' => 0.0];
        foreach ($lineas as $l) {
            $l = is_array($l) ? $l : (array) $l;
            if (empty($l['va_recibo'])) {
                continue;
            }
            switch ($l['tipo'] ?? '') {
                case 'remunerativo':
                    $t['rem'] += (float) $l['importe'];
                    break;
                case 'no_remunerativo':
                    $t['norem'] += (float) $l['importe'];
                    break;
                case 'asignacion':
                    $t['asig'] += (float) $l['importe'];
                    break;
                case 'descuento':
                    $t['desc'] += (float) $l['importe'];
                    break;
                case 'aporte':
                    $t['desc'] += (float) $l['importe'];
                    $t['aportes'] += (float) $l['importe'];
                    break;
                case 'retencion':
                    $t['desc'] += (float) $l['importe'];
                    break;
                case 'contribucion':
                    $t['contrib'] += (float) $l['importe'];
                    break;
            }
        }
        $t['bruto'] = $t['rem'] + $t['norem'];
        $t['neto'] = round($t['rem'] + $t['norem'] + $t['asig'] - $t['desc'], 2);

        return $t;
    }

    public static function recalcularCabecera(Liquidacion_Sueldos $liquidacion): void
    {
        $agg = Liquidacion_Recibo_Sueldos::query()
            ->where('liquidacion_id', $liquidacion->id)
            ->selectRaw(
                'COUNT(*) as cantidad,
                 COALESCE(SUM(total_remunerativo),0) as rem,
                 COALESCE(SUM(total_no_remunerativo),0) as norem,
                 COALESCE(SUM(total_descuentos),0) as descuentos,
                 COALESCE(SUM(neto_a_pagar),0) as neto'
            )
            ->first();

        $rem = (float) ($agg->rem ?? 0);
        $norem = (float) ($agg->norem ?? 0);

        $liquidacion->update([
            'cantidad_recibos' => (int) ($agg->cantidad ?? 0),
            'total_remunerativo' => $rem,
            'total_no_remunerativo' => $norem,
            'total_bruto' => $rem + $norem,
            'total_descuentos' => (float) ($agg->descuentos ?? 0),
            'total_neto' => (float) ($agg->neto ?? 0),
        ]);
    }

    public static function renumerarRecibos(int $liquidacionId): void
    {
        $recibos = Liquidacion_Recibo_Sueldos::query()
            ->where('liquidacion_id', $liquidacionId)
            ->orderBy('legajo')
            ->orderBy('id')
            ->get(['id']);

        $n = 0;
        foreach ($recibos as $r) {
            $n++;
            Liquidacion_Recibo_Sueldos::query()->where('id', $r->id)->update(['numero_recibo' => $n]);
        }
    }

    /**
     * Clave estable de una fila Anita confidencial (sin tabla/serial).
     */
    public static function claveOrigenDetalle(
        int $empresaAnita,
        int $liquidacionAnita,
        int $legajo,
        int $codigo,
        int $nroInterno
    ): string {
        return hash('sha256', implode('|', [
            'confidencial',
            $empresaAnita,
            $liquidacionAnita,
            $legajo,
            $codigo,
            $nroInterno,
        ]));
    }

    /**
     * Fingerprint del recibo completo (ordenado por claves de detalle).
     *
     * @param  list<string>  $clavesDetalle
     */
    public static function fingerprintRecibo(array $clavesDetalle): string
    {
        sort($clavesDetalle);

        return hash('sha256', implode(';', $clavesDetalle));
    }
}

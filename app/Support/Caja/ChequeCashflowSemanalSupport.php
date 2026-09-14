<?php

namespace App\Support\Caja;

use App\Models\Caja\Cheque;
use Carbon\Carbon;

/**
 * Cashflow semanal de cheques (CHT cartera + CHP pendientes de débito).
 */
final class ChequeCashflowSemanalSupport
{
    /**
     * @return array{
     *   desde:string,
     *   hasta:string,
     *   semanas: list<array{clave:string, label:string, desde:string, hasta:string, cht:array, chp:array, total_monto:float, total_cantidad:int}>,
     *   totales: array{cht_monto:float, chp_monto:float, monto:float, cantidad:int}
     * }
     */
    public static function resumir(?int $empresaId = null, ?string $desdeYmd = null, int $semanas = 8): array
    {
        $semanas = max(1, min(26, $semanas));
        $desde = $desdeYmd && preg_match('/^\d{4}-\d{2}-\d{2}$/', $desdeYmd)
            ? Carbon::parse($desdeYmd)->startOfWeek(Carbon::MONDAY)
            : Carbon::today()->startOfWeek(Carbon::MONDAY);
        $hasta = (clone $desde)->addWeeks($semanas)->subDay();

        $bloques = [];
        for ($i = 0; $i < $semanas; $i++) {
            $ini = (clone $desde)->addWeeks($i);
            $fin = (clone $ini)->endOfWeek(Carbon::SUNDAY);
            $clave = $ini->format('o-\WW');
            $bloques[$clave] = [
                'clave' => $clave,
                'label' => 'Sem '.$ini->format('W').' ('.$ini->format('d/m').'–'.$fin->format('d/m').')',
                'desde' => $ini->toDateString(),
                'hasta' => $fin->toDateString(),
                'cht' => ['cantidad' => 0, 'monto' => 0.],
                'chp' => ['cantidad' => 0, 'monto' => 0.],
                'total_monto' => 0.,
                'total_cantidad' => 0,
                'filas' => [],
            ];
        }

        $q = Cheque::query()
            ->with(['bancos:id,nombre', 'clientes:id,nombre', 'proveedores:id,nombre', 'monedas:id,abreviatura', 'empresas:id,nombre'])
            ->whereBetween('fechapago', [$desde->toDateString(), $hasta->toDateString()])
            ->where(function ($w) {
                // CHT en cartera libre
                $w->where(function ($cht) {
                    $cht->where('origen', 'R')
                        ->whereNull('pagoproveedor_id')
                        ->where(function ($e) {
                            $e->whereNull('estado')->orWhereIn('estado', [' ', 'N', '']);
                        })
                        ->where(function ($c) {
                            $c->whereNull('nro_caucion')->orWhere('nro_caucion', '')->orWhere('nro_caucion', '0');
                        })
                        ->whereNull('fecha_deposito');
                })->orWhere(function ($chp) {
                    // CHP emitidos aún diferidos / no debitados
                    $chp->where('origen', 'E')
                        ->where(function ($e) {
                            $e->whereNull('estado')->orWhereIn('estado', [' ', 'N', '']);
                        });
                });
            })
            ->orderBy('fechapago')
            ->orderBy('id');

        if ($empresaId && $empresaId > 0) {
            $q->where('empresa_id', $empresaId);
        }

        $totales = ['cht_monto' => 0., 'chp_monto' => 0., 'monto' => 0., 'cantidad' => 0];

        foreach ($q->get() as $c) {
            $pago = Carbon::parse((string) $c->fechapago)->startOfWeek(Carbon::MONDAY);
            $clave = $pago->format('o-\WW');
            if (! isset($bloques[$clave])) {
                continue;
            }
            $monto = round((float) $c->monto, 2);
            $tipo = ((string) $c->origen === 'E') ? 'chp' : 'cht';
            $bloques[$clave][$tipo]['cantidad']++;
            $bloques[$clave][$tipo]['monto'] = round($bloques[$clave][$tipo]['monto'] + $monto, 2);
            $bloques[$clave]['total_cantidad']++;
            $bloques[$clave]['total_monto'] = round($bloques[$clave]['total_monto'] + $monto, 2);
            $bloques[$clave]['filas'][] = [
                'id' => (int) $c->id,
                'origen' => (string) $c->origen,
                'numerocheque' => (string) $c->numerocheque,
                'fechapago' => (string) $c->fechapago,
                'monto' => $monto,
                'moneda' => (string) ($c->monedas->abreviatura ?? ''),
                'banco' => (string) ($c->bancos->nombre ?? ''),
                'contraparte' => (string) ($c->origen === 'E'
                    ? ($c->proveedores->nombre ?? $c->anombrede ?? '')
                    : ($c->clientes->nombre ?? '')),
                'empresa' => (string) ($c->empresas->nombre ?? ''),
                'echeq' => strtoupper((string) ($c->negociable ?? '')) === 'E',
            ];

            $totales[$tipo.'_monto'] = round($totales[$tipo.'_monto'] + $monto, 2);
            $totales['monto'] = round($totales['monto'] + $monto, 2);
            $totales['cantidad']++;
        }

        return [
            'desde' => $desde->toDateString(),
            'hasta' => $hasta->toDateString(),
            'semanas' => array_values($bloques),
            'totales' => $totales,
        ];
    }
}

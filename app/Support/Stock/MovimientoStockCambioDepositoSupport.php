<?php

namespace App\Support\Stock;

use Illuminate\Support\Facades\DB;

/**
 * El alta de un lote queda en el depósito donde se cargó. Si después hubo
 * reubicación o consumo en ese depósito, cambiar el depósito del alta no
 * mueve el saldo: lo duplica en el depósito nuevo y deja el anterior en negativo.
 */
final class MovimientoStockCambioDepositoSupport
{
    /**
     * @return list<array{lote: string, deposito_id: int, deposito_codigo: string, conceptos: list<string>}>
     */
    public static function gruposConMovimientosEnDepositoActual(int $movimientoStockId, int $depositoNuevoId): array
    {
        if ($movimientoStockId <= 0 || $depositoNuevoId <= 0) {
            return [];
        }

        $rows = DB::table('articulo_movimiento as am')
            ->join('articulo_movimiento as otro', function ($join) {
                $join->on('otro.articulo_id', '=', 'am.articulo_id')
                    ->on('otro.combinacion_id', '=', 'am.combinacion_id')
                    ->on('otro.deposito_id', '=', 'am.deposito_id')
                    ->on('otro.lote', '=', 'am.lote')
                    ->where(function ($q) {
                        $q->whereNull('otro.movimientostock_id')
                            ->orWhereColumn('otro.movimientostock_id', '<>', 'am.movimientostock_id');
                    });
            })
            ->leftJoin('depmae as d', 'd.id', '=', 'am.deposito_id')
            ->where('am.movimientostock_id', $movimientoStockId)
            ->where('am.deposito_id', '<>', $depositoNuevoId)
            ->whereNotNull('am.deposito_id')
            ->where('am.lote', '>', '0')
            ->groupBy('am.lote', 'am.deposito_id', 'd.codigo', 'otro.concepto')
            ->select([
                'am.lote',
                'am.deposito_id',
                'd.codigo as deposito_codigo',
                'otro.concepto',
            ])
            ->get();

        $grupos = [];
        foreach ($rows as $row) {
            $lote = trim((string) ($row->lote ?? ''));
            $depId = (int) ($row->deposito_id ?? 0);
            if ($lote === '' || $depId <= 0) {
                continue;
            }
            $clave = $lote.'|'.$depId;
            if (! isset($grupos[$clave])) {
                $grupos[$clave] = [
                    'lote' => $lote,
                    'deposito_id' => $depId,
                    'deposito_codigo' => trim((string) ($row->deposito_codigo ?? '')),
                    'conceptos' => [],
                ];
            }
            $concepto = trim((string) ($row->concepto ?? ''));
            if ($concepto !== '' && ! in_array($concepto, $grupos[$clave]['conceptos'], true)) {
                $grupos[$clave]['conceptos'][] = $concepto;
            }
        }

        return array_values($grupos);
    }

    /**
     * @param  list<array{lote: string, deposito_id: int, deposito_codigo: string, conceptos: list<string>}>  $grupos
     */
    public static function mensajeBloqueo(int $depositoNuevoId, string $depositoNuevoCodigo, array $grupos): ?string
    {
        if ($grupos === [] || $depositoNuevoId <= 0) {
            return null;
        }

        $lotes = [];
        $viejos = [];
        $conceptos = [];
        foreach ($grupos as $grupo) {
            $lote = trim((string) ($grupo['lote'] ?? ''));
            if ($lote !== '') {
                $lotes[$lote] = true;
            }
            $codigo = trim((string) ($grupo['deposito_codigo'] ?? ''));
            $viejos[$codigo !== '' ? $codigo : '#'.(int) ($grupo['deposito_id'] ?? 0)] = true;
            foreach ($grupo['conceptos'] ?? [] as $concepto) {
                $concepto = trim((string) $concepto);
                if ($concepto !== '') {
                    $conceptos[$concepto] = true;
                }
            }
        }

        $listaLotes = self::listaCorta(array_keys($lotes));
        $listaViejos = self::listaCorta(array_keys($viejos));
        $depNuevo = trim($depositoNuevoCodigo) !== '' ? trim($depositoNuevoCodigo) : '#'.$depositoNuevoId;
        $listaConceptos = self::listaCorta(array_keys($conceptos), 3);

        $msg = 'No se puede cambiar el depósito de '.$listaViejos.' a '.$depNuevo
            .': el lote '.$listaLotes.' ya tiene movimientos en '.$listaViejos;
        if ($listaConceptos !== '') {
            $msg .= ' ('.$listaConceptos.')';
        }
        $msg .= '. El alta queda en el depósito donde se cargó; el saldo que se ve en Stock por OT'
            .' ya está en el depósito de la reubicación. Cambiar el depósito del alta duplica los pares en '
            .$depNuevo.' y deja '.$listaViejos.' en negativo. Prepará el picking en el depósito del reporte.';

        return $msg;
    }

    public static function mensajeSiCambiaDeposito(int $movimientoStockId, int $depositoNuevoId): ?string
    {
        $grupos = self::gruposConMovimientosEnDepositoActual($movimientoStockId, $depositoNuevoId);
        if ($grupos === []) {
            return null;
        }

        $codigoNuevo = trim((string) (DB::table('depmae')->where('id', $depositoNuevoId)->value('codigo') ?? ''));

        return self::mensajeBloqueo($depositoNuevoId, $codigoNuevo, $grupos);
    }

    /**
     * @param  list<string>  $valores
     */
    private static function listaCorta(array $valores, int $max = 8): string
    {
        $valores = array_values(array_filter($valores, static fn ($v) => trim((string) $v) !== ''));
        if ($valores === []) {
            return '';
        }
        $mostrar = array_slice($valores, 0, $max);
        $texto = implode(', ', $mostrar);
        $resto = count($valores) - count($mostrar);
        if ($resto > 0) {
            $texto .= ' y '.$resto.' más';
        }

        return $texto;
    }
}

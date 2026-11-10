<?php

namespace App\Support\Caja;

use App\Models\Caja\TesoreriaCrownBoveda;
use App\Models\Caja\TesoreriaCrownJornada;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Retiros de moneda extranjera de bóveda (depósito dólares 8 / euros 9) en pesos.
 *
 * El importe en pesos queda grabado en la operación (`importe_pesos`):
 * - Importados de Anita: el FIFO de partidas que calculó Anita (monemov × cotización de cada partida).
 * - Nativos del ERP: cotización de venta del día de la jornada, grabada la primera vez que se valoriza.
 * Así el Anexo I no cambia aunque después se toque la cotización o el ledger de partidas.
 */
final class TesoreriaCrownBovedaMonedaExtranjeraPesosSupport
{
    public const CODIGO_DOLARES = 8;

    public const CODIGO_EUROS = 9;

    public const MONEDAS = [
        self::CODIGO_DOLARES => ['USD', 'DOL'],
        self::CODIGO_EUROS => ['EUR', 'EURO'],
    ];

    private static ?bool $conColumna = null;

    /** @var array<int, array<int, list<object>>>|null jornada_id => código => filas */
    private static ?array $precarga = null;

    /**
     * Precarga los retiros de moneda extranjera de varias jornadas (Anexo I del año).
     *
     * @param  list<int>  $jornadaIds
     */
    public static function precargar(array $jornadaIds): void
    {
        self::$precarga = array_fill_keys($jornadaIds, []);
        if ($jornadaIds === []) {
            return;
        }
        foreach (self::consulta($jornadaIds, array_keys(self::MONEDAS)) as $fila) {
            $codigo = (int) $fila->codigo;
            if (! in_array(strtoupper(trim((string) $fila->moneda)), self::MONEDAS[$codigo] ?? [], true)) {
                continue;
            }
            self::$precarga[(int) $fila->jornada_id][$codigo][] = $fila;
        }
    }

    public static function olvidarPrecarga(): void
    {
        self::$precarga = null;
    }

    private static function conColumna(): bool
    {
        return self::$conColumna ??= Schema::hasColumn('tesoreria_crown_boveda', 'importe_pesos');
    }

    /**
     * @param  list<int>  $jornadaIds
     * @param  list<int>  $codigos
     * @return list<object>
     */
    private static function consulta(array $jornadaIds, array $codigos): array
    {
        $monedas = [];
        foreach ($codigos as $codigo) {
            $monedas = array_merge($monedas, self::MONEDAS[$codigo] ?? []);
        }
        if ($monedas === []) {
            return [];
        }

        $filas = DB::table('tesoreria_crown_boveda as b')
            ->join('tesoreria_crown_boveda_tipo as t', 't.id', '=', 'b.tipo_id')
            ->join('tesoreria_crown_boveda_linea as l', 'l.boveda_id', '=', 'b.id')
            ->join('tesoreria_crown_valor as v', 'v.id', '=', 'l.valor_id')
            ->join('tesoreria_crown_tipo_valor as tv', 'tv.id', '=', 'v.tipo_valor_id')
            ->whereIn('b.jornada_id', $jornadaIds)
            ->where('b.estado', TesoreriaCrownBoveda::CONFIRMADO)
            ->whereNull('b.revierte_id')
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('tesoreria_crown_boveda as r')
                    ->whereColumn('r.revierte_id', 'b.id');
            })
            ->whereIn('t.codigo', $codigos)
            ->whereIn('tv.codigo', $monedas)
            ->groupBy('b.jornada_id', 't.codigo', 'tv.codigo', 'b.id', 'v.tipo_valor_id')
            ->orderBy('b.id')
            ->get(array_merge(
                ['b.jornada_id', 't.codigo as codigo', 'tv.codigo as moneda', 'b.id', 'v.tipo_valor_id', DB::raw('SUM(l.cantidad * v.denominacion) as monto')],
                self::conColumna() ? ['b.importe_pesos'] : []
            ));

        return $filas->all();
    }

    /**
     * Total en pesos de los retiros confirmados y vigentes del código en la jornada.
     *
     * @param  list<string>  $avisos
     */
    public static function totalJornada(TesoreriaCrownJornada $jornada, int $codigo, array &$avisos = []): float
    {
        $monedas = self::MONEDAS[$codigo] ?? [];
        if ($monedas === []) {
            return 0.0;
        }
        $conColumna = self::conColumna();
        $jornadaId = (int) $jornada->id;
        $filas = self::$precarga !== null && array_key_exists($jornadaId, self::$precarga)
            ? (self::$precarga[$jornadaId][$codigo] ?? [])
            : self::consulta([$jornadaId], [$codigo]);

        $porMovimiento = [];
        foreach ($filas as $fila) {
            $id = (int) $fila->id;
            $porMovimiento[$id]['importe_pesos'] = $conColumna && $fila->importe_pesos !== null ? (float) $fila->importe_pesos : null;
            $porMovimiento[$id]['monedas'][(int) $fila->tipo_valor_id] = (float) $fila->monto;
        }

        $fecha = $jornada->fecha->format('Y-m-d');
        $total = 0.0;
        foreach ($porMovimiento as $id => $mov) {
            if ($mov['importe_pesos'] !== null) {
                $total += $mov['importe_pesos'];

                continue;
            }
            $pesos = self::alCambioDelDia((int) $jornada->sala_id, $fecha, $mov['monedas']);
            if ($pesos === null) {
                $avisos[] = 'Falta la cotización del '.$jornada->fecha->format('d/m/Y').' para valorizar el retiro de moneda extranjera.';

                continue;
            }
            if ($conColumna) {
                DB::table('tesoreria_crown_boveda')->where('id', $id)->update(['importe_pesos' => $pesos]);
            }
            $total += $pesos;
        }

        return round($total, 2);
    }

    /**
     * @param  array<int, float>  $montosPorTipoValor  tipo_valor_id => monto en moneda extranjera
     */
    public static function alCambioDelDia(int $salaId, string $fechaYmd, array $montosPorTipoValor): ?float
    {
        $pesos = 0.0;
        foreach ($montosPorTipoValor as $tipoValorId => $monto) {
            $cot = TesoreriaCrownCierreCalculo::cotizacionVenta($salaId, $fechaYmd, (int) $tipoValorId);
            $tasa = (float) ($cot['tasa'] ?? 0);
            if ($tasa <= 0) {
                return null;
            }
            $pesos += abs((float) $monto) * $tasa;
        }

        return round($pesos, 2);
    }
}

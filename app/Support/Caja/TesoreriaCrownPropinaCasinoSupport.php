<?php

namespace App\Support\Caja;

use App\Models\Caja\TesoreriaCrownBoveda;
use App\Models\Caja\TesoreriaCrownMesa;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Informe comparativo de propinas por casino.
 * Fuentes ERP: movimientos de bóveda tipificados (para_reporte_propina)
 * + propina de estados finales de mesa confirmados.
 */
final class TesoreriaCrownPropinaCasinoSupport
{
    /**
     * @param  array{fecha_desde?: string, fecha_hasta?: string}  $filtros
     * @return array{
     *   casinos: list<array{id: int, codigo: string, nombre: string, etiqueta: string}>,
     *   filas: list<array<string, mixed>>,
     *   totales: array{total: float, por_casino: array<int, float>},
     *   total_dias: int,
     *   subtitulo: string
     * }
     */
    public static function generar(array $filtros): array
    {
        $desde = (string) ($filtros['fecha_desde'] ?? date('Y-m-01'));
        $hasta = (string) ($filtros['fecha_hasta'] ?? date('Y-m-d'));
        if ($hasta < $desde) {
            $hasta = $desde;
        }

        $casinos = self::casinosColumnas();
        $salaIds = array_column($casinos, 'id');
        $porCasinoVacio = [];
        foreach ($salaIds as $salaId) {
            $porCasinoVacio[(int) $salaId] = 0.0;
        }

        if ($salaIds === []) {
            return [
                'casinos' => [],
                'filas' => [],
                'totales' => ['total' => 0.0, 'por_casino' => $porCasinoVacio],
                'total_dias' => 0,
                'subtitulo' => TesoreriaCrownPropinaCasinoFiltros::subtitulo($filtros),
            ];
        }

        /** @var array<string, array{total: float, por_casino: array<int, float>}> $porFecha */
        $porFecha = [];
        self::acumular($porFecha, self::movimientosBoveda($desde, $hasta, $salaIds), $porCasinoVacio);
        self::acumular($porFecha, self::movimientosMesa($desde, $hasta, $salaIds), $porCasinoVacio);

        ksort($porFecha);

        $filas = [];
        $totales = [
            'total' => 0.0,
            'por_casino' => $porCasinoVacio,
        ];

        foreach ($porFecha as $fechaYmd => $datos) {
            if (! self::tieneMonto($datos)) {
                continue;
            }

            $carbon = Carbon::createFromFormat('Y-m-d', $fechaYmd)->startOfDay();
            $filas[] = [
                'es_total' => false,
                'fecha' => $fechaYmd,
                'fecha_fmt' => $carbon->format('d/m/Y'),
                'dia' => self::etiquetaDia($carbon),
                'total' => $datos['total'],
                'por_casino' => $datos['por_casino'],
            ];

            $totales['total'] = round($totales['total'] + $datos['total'], 2);
            foreach ($datos['por_casino'] as $salaId => $monto) {
                $totales['por_casino'][$salaId] = round(
                    $totales['por_casino'][$salaId] + (float) $monto,
                    2
                );
            }
        }

        return [
            'casinos' => $casinos,
            'filas' => $filas,
            'totales' => $totales,
            'total_dias' => count($filas),
            'subtitulo' => TesoreriaCrownPropinaCasinoFiltros::subtitulo($filtros),
        ];
    }

    /**
     * @return list<array{id: int, codigo: string, nombre: string, etiqueta: string}>
     */
    public static function casinosColumnas(): array
    {
        return TesoreriaCrownSalaSupport::salas()
            ->map(function ($sala): array {
                $nombre = trim((string) ($sala->nombre ?? ''));
                $codigo = strtoupper(trim((string) ($sala->codigo ?? '')));

                return [
                    'id' => (int) $sala->id,
                    'codigo' => $codigo,
                    'nombre' => $nombre,
                    'etiqueta' => $nombre !== '' ? $nombre : $codigo,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param  list<int>  $salaIds
     * @return Collection<int, object{fecha: string, sala_id: int, monto: float}>
     */
    private static function movimientosBoveda(string $desde, string $hasta, array $salaIds): Collection
    {
        if (! Schema::hasTable('tesoreria_crown_boveda')
            || ! Schema::hasTable('tesoreria_crown_boveda_tipo')
            || ! Schema::hasColumn('tesoreria_crown_boveda_tipo', 'para_reporte_propina')) {
            return collect();
        }

        $importeLineas = DB::table('tesoreria_crown_boveda_linea as l')
            ->join('tesoreria_crown_valor as v', 'v.id', '=', 'l.valor_id')
            ->groupBy('l.boveda_id')
            ->select('l.boveda_id')
            ->selectRaw('SUM(l.cantidad * v.denominacion) as importe_lineas');

        $query = DB::table('tesoreria_crown_boveda as b')
            ->join('tesoreria_crown_boveda_tipo as t', 't.id', '=', 'b.tipo_id')
            ->join('tesoreria_crown_jornada as j', 'j.id', '=', 'b.jornada_id')
            ->leftJoinSub($importeLineas, 'il', 'il.boveda_id', '=', 'b.id')
            ->where('b.estado', TesoreriaCrownBoveda::CONFIRMADO)
            ->where('t.para_reporte_propina', true)
            ->whereIn('b.sala_id', $salaIds)
            ->whereBetween('j.fecha', [$desde, $hasta])
            ->selectRaw('j.fecha as fecha')
            ->selectRaw('b.sala_id as sala_id')
            ->selectRaw(
                'CASE'
                .' WHEN il.importe_lineas IS NOT NULL AND ABS(il.importe_lineas) >= 0.00001'
                .' THEN il.importe_lineas'
                .' ELSE COALESCE(b.importe_pesos, 0)'
                .' END as monto'
            );

        // Evita doble conteo de PROP/Mesas legado (sigue vinculado al estado final).
        if (Schema::hasTable('tesoreria_crown_mesa')
            && Schema::hasColumn('tesoreria_crown_mesa', 'boveda_id')) {
            $query->whereNotExists(function ($q) {
                $q->selectRaw('1')
                    ->from('tesoreria_crown_mesa as m')
                    ->whereColumn('m.boveda_id', 'b.id');
            });
        }

        return $query->get()->map(function ($fila) {
            return (object) [
                'fecha' => substr((string) $fila->fecha, 0, 10),
                'sala_id' => (int) $fila->sala_id,
                'monto' => round((float) ($fila->monto ?? 0), 2),
            ];
        });
    }

    /**
     * @param  list<int>  $salaIds
     * @return Collection<int, object{fecha: string, sala_id: int, monto: float}>
     */
    private static function movimientosMesa(string $desde, string $hasta, array $salaIds): Collection
    {
        if (! Schema::hasTable('tesoreria_crown_mesa')) {
            return collect();
        }

        return DB::table('tesoreria_crown_mesa as m')
            ->join('tesoreria_crown_jornada as j', 'j.id', '=', 'm.jornada_id')
            ->where('m.tipo', 'F')
            ->where('m.estado', TesoreriaCrownMesa::CONFIRMADO)
            ->whereNull('m.revierte_id')
            ->whereNotExists(function ($q) {
                $q->selectRaw('1')
                    ->from('tesoreria_crown_mesa as rev')
                    ->whereColumn('rev.revierte_id', 'm.id');
            })
            ->whereIn('m.sala_id', $salaIds)
            ->whereBetween('j.fecha', [$desde, $hasta])
            ->whereRaw('ABS(COALESCE(m.propina, 0)) >= 0.00001')
            ->selectRaw('j.fecha as fecha')
            ->selectRaw('m.sala_id as sala_id')
            ->selectRaw('m.propina as monto')
            ->get()
            ->map(function ($fila) {
                return (object) [
                    'fecha' => substr((string) $fila->fecha, 0, 10),
                    'sala_id' => (int) $fila->sala_id,
                    'monto' => round((float) ($fila->monto ?? 0), 2),
                ];
            });
    }

    /**
     * @param  array<string, array{total: float, por_casino: array<int, float>}>  $porFecha
     * @param  Collection<int, object{fecha: string, sala_id: int, monto: float}>  $movimientos
     * @param  array<int, float>  $porCasinoVacio
     */
    private static function acumular(array &$porFecha, Collection $movimientos, array $porCasinoVacio): void
    {
        foreach ($movimientos as $mov) {
            $fecha = (string) ($mov->fecha ?? '');
            if ($fecha === '') {
                continue;
            }
            if (! isset($porFecha[$fecha])) {
                $porFecha[$fecha] = [
                    'total' => 0.0,
                    'por_casino' => $porCasinoVacio,
                ];
            }
            $salaId = (int) $mov->sala_id;
            if (! array_key_exists($salaId, $porFecha[$fecha]['por_casino'])) {
                continue;
            }
            $monto = round((float) ($mov->monto ?? 0), 2);
            $porFecha[$fecha]['por_casino'][$salaId] = round(
                $porFecha[$fecha]['por_casino'][$salaId] + $monto,
                2
            );
            $porFecha[$fecha]['total'] = round($porFecha[$fecha]['total'] + $monto, 2);
        }
    }

    /**
     * @param  array{total: float, por_casino: array<int, float>}  $datos
     */
    private static function tieneMonto(array $datos): bool
    {
        if (abs((float) ($datos['total'] ?? 0)) >= 0.00001) {
            return true;
        }
        foreach ($datos['por_casino'] as $monto) {
            if (abs((float) $monto) >= 0.00001) {
                return true;
            }
        }

        return false;
    }

    private static function etiquetaDia(Carbon $fecha): string
    {
        $es = mb_substr(mb_strtoupper($fecha->copy()->locale('es')->isoFormat('ddd')), 0, 3);
        $en = mb_substr(mb_strtoupper($fecha->copy()->locale('en')->isoFormat('ddd')), 0, 3);

        return $es.'/'.$en;
    }
}

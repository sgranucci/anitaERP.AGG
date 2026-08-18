<?php

declare(strict_types=1);

namespace App\Support\Contable;

use App\Support\Database\SqlDialectSupport;
use Illuminate\Support\Facades\DB;

/**
 * Control de integridad de cuentacontable_saldo_mes contra asiento + asiento_movimiento.
 *
 * El snapshot mensual lo mantiene Asiento_MovimientoObserver fila por fila: cualquier
 * escritura masiva que no dispare eventos Eloquent lo desincroniza en silencio y el
 * balance impreso sale mal sin que nadie se entere. Este support recalcula el agregado
 * desde los asientos y compara, sin escribir nada.
 */
class CuentacontableSaldoMesIntegridadSupport
{
    /** Diferencia máxima tolerada por fila (redondeo de conversión a moneda local). */
    public const TOLERANCIA = 0.05;

    /**
     * @return array{
     *   empresas: list<array{empresa_id: int, nombre: string, periodos_con_desvio: int, suma_abs_desvio: float,
     *                        snapshot_desbalance: float, asientos_desbalance: float, periodos: list<array<string, mixed>>}>,
     *   resumen: array{empresas_con_desvio: int, periodos_con_desvio: int, suma_abs_desvio: float, peor: array<string, mixed>|null},
     *   parametros: array{empresa_ids: list<int>, periodo_desde: int|null, periodo_hasta: int|null, tolerancia: float},
     * }
     */
    public function analizar(
        array $empresaIds = [],
        ?int $periodoDesde = null,
        ?int $periodoHasta = null,
        int $limitePeriodos = 12,
        int $limiteCuentas = 10,
    ): array {
        $empresaIds = array_values(array_filter(array_map('intval', $empresaIds), fn ($v) => $v > 0));

        $snapshot = $this->agregadoSnapshot($empresaIds, $periodoDesde, $periodoHasta);
        $asientos = $this->agregadoAsientos($empresaIds, $periodoDesde, $periodoHasta);

        $nombres = $this->nombresEmpresa(array_unique(array_merge(
            array_map(fn ($k) => (int) explode('|', $k)[0], array_keys($snapshot)),
            array_map(fn ($k) => (int) explode('|', $k)[0], array_keys($asientos)),
        )));

        // La clave incluye moneda: el snapshot guarda una fila por moneda de origen y
        // sumar monedas distintas escondería desvíos que se compensan entre sí.
        $porPeriodo = [];
        $porEmpresa = [];
        foreach (array_unique(array_merge(array_keys($snapshot), array_keys($asientos))) as $key) {
            [$empresaId, $periodo, $monedaId] = array_map('intval', explode('|', (string) $key));
            $valorSnapshot = round((float) ($snapshot[$key] ?? 0.0), 2);
            $valorAsientos = round((float) ($asientos[$key] ?? 0.0), 2);
            $desvio = round($valorSnapshot - $valorAsientos, 2);

            $porEmpresa[$empresaId]['snapshot_desbalance'] = ($porEmpresa[$empresaId]['snapshot_desbalance'] ?? 0.0) + $valorSnapshot;
            $porEmpresa[$empresaId]['asientos_desbalance'] = ($porEmpresa[$empresaId]['asientos_desbalance'] ?? 0.0) + $valorAsientos;

            if (abs($desvio) <= self::TOLERANCIA) {
                continue;
            }

            $pk = $empresaId.'|'.$periodo;
            if (! isset($porPeriodo[$pk])) {
                $porPeriodo[$pk] = [
                    'periodo' => $periodo,
                    'snapshot' => 0.0,
                    'asientos' => 0.0,
                    'desvio' => 0.0,
                    'monedas' => [],
                ];
            }
            $porPeriodo[$pk]['snapshot'] += $valorSnapshot;
            $porPeriodo[$pk]['asientos'] += $valorAsientos;
            $porPeriodo[$pk]['desvio'] += $desvio;
            $porPeriodo[$pk]['monedas'][] = $monedaId;
        }

        foreach ($porPeriodo as $pk => $datos) {
            $empresaId = (int) explode('|', (string) $pk)[0];
            $datos['snapshot'] = round((float) $datos['snapshot'], 2);
            $datos['asientos'] = round((float) $datos['asientos'], 2);
            $datos['desvio'] = round((float) $datos['desvio'], 2);
            $datos['monedas'] = array_values(array_unique($datos['monedas']));
            $porEmpresa[$empresaId]['periodos'][] = $datos;
        }

        $empresas = [];
        $periodosConDesvio = 0;
        $sumaAbs = 0.0;
        $peor = null;

        foreach ($porEmpresa as $empresaId => $datos) {
            $periodos = $datos['periodos'] ?? [];
            usort($periodos, fn ($a, $b) => abs($b['desvio']) <=> abs($a['desvio']));

            foreach ($periodos as $i => $periodo) {
                $sumaAbs += abs($periodo['desvio']);
                if ($peor === null || abs($periodo['desvio']) > abs($peor['desvio'])) {
                    $peor = array_merge($periodo, ['empresa_id' => $empresaId]);
                }
                if ($i < $limiteCuentas) {
                    $periodos[$i]['cuentas'] = $this->cuentasConDesvio($empresaId, (int) $periodo['periodo'], $limiteCuentas);
                }
            }
            $periodosConDesvio += count($periodos);

            $empresas[] = [
                'empresa_id' => $empresaId,
                'nombre' => (string) ($nombres[$empresaId] ?? ('Empresa '.$empresaId)),
                'periodos_con_desvio' => count($periodos),
                'suma_abs_desvio' => round(array_sum(array_map(fn ($p) => abs($p['desvio']), $periodos)), 2),
                'snapshot_desbalance' => round((float) ($datos['snapshot_desbalance'] ?? 0), 2),
                'asientos_desbalance' => round((float) ($datos['asientos_desbalance'] ?? 0), 2),
                'periodos' => array_slice($periodos, 0, $limitePeriodos),
            ];
        }

        usort($empresas, fn ($a, $b) => $b['suma_abs_desvio'] <=> $a['suma_abs_desvio']);

        return [
            'empresas' => $empresas,
            'resumen' => [
                'empresas_con_desvio' => count(array_filter($empresas, fn ($e) => $e['periodos_con_desvio'] > 0)),
                'periodos_con_desvio' => $periodosConDesvio,
                'suma_abs_desvio' => round($sumaAbs, 2),
                'peor' => $peor,
            ],
            'parametros' => [
                'empresa_ids' => $empresaIds,
                'periodo_desde' => $periodoDesde,
                'periodo_hasta' => $periodoHasta,
                'tolerancia' => self::TOLERANCIA,
            ],
        ];
    }

    /**
     * Cuentas que explican el desvío de un período (snapshot vs asientos).
     *
     * @return list<array{cuentacontable_id: int, codigo: string, nombre: string, snapshot: float, asientos: float, desvio: float}>
     */
    public function cuentasConDesvio(int $empresaId, int $periodo, int $limite = 10): array
    {
        $snapshot = DB::table('cuentacontable_saldo_mes')
            ->where('empresa_id', $empresaId)
            ->where('anio_mes', $periodo)
            ->groupBy('cuentacontable_id')
            ->selectRaw('cuentacontable_id, sum(monto) valor')
            ->pluck('valor', 'cuentacontable_id');

        $asientos = DB::table('asiento_movimiento as am')
            ->join('asiento as a', 'a.id', '=', 'am.asiento_id')
            ->where('a.empresa_id', $empresaId)
            ->whereRaw(SqlDialectSupport::anioMes('a.fecha').' = ?', [$periodo])
            ->whereNotNull('am.cuentacontable_id')
            ->groupBy('am.cuentacontable_id')
            ->selectRaw('am.cuentacontable_id, sum(am.monto) valor')
            ->pluck('valor', 'cuentacontable_id');

        $ids = array_unique(array_merge(
            array_map('intval', array_keys($snapshot->all())),
            array_map('intval', array_keys($asientos->all()))
        ));

        $desvios = [];
        foreach ($ids as $id) {
            $a = round((float) ($snapshot[$id] ?? 0), 2);
            $b = round((float) ($asientos[$id] ?? 0), 2);
            $desvio = round($a - $b, 2);
            if (abs($desvio) > self::TOLERANCIA) {
                $desvios[$id] = ['snapshot' => $a, 'asientos' => $b, 'desvio' => $desvio];
            }
        }

        uasort($desvios, fn ($x, $y) => abs($y['desvio']) <=> abs($x['desvio']));
        $desvios = array_slice($desvios, 0, $limite, true);
        if ($desvios === []) {
            return [];
        }

        $cuentas = DB::table('cuentacontable')
            ->whereIn('id', array_keys($desvios))
            ->get(['id', 'codigo', 'nombre'])
            ->keyBy('id');

        $out = [];
        foreach ($desvios as $id => $valores) {
            $out[] = array_merge($valores, [
                'cuentacontable_id' => (int) $id,
                'codigo' => (string) ($cuentas[$id]->codigo ?? ''),
                'nombre' => (string) ($cuentas[$id]->nombre ?? ''),
            ]);
        }

        return $out;
    }

    /**
     * @return array<string, float> clave "empresa|periodo"
     */
    private function agregadoSnapshot(array $empresaIds, ?int $periodoDesde, ?int $periodoHasta): array
    {
        $query = DB::table('cuentacontable_saldo_mes')
            ->groupBy('empresa_id', 'anio_mes', 'moneda_id')
            ->selectRaw('empresa_id, anio_mes, moneda_id, sum(monto) valor');

        if ($empresaIds !== []) {
            $query->whereIn('empresa_id', $empresaIds);
        }
        if ($periodoDesde !== null) {
            $query->where('anio_mes', '>=', $periodoDesde);
        }
        if ($periodoHasta !== null) {
            $query->where('anio_mes', '<=', $periodoHasta);
        }

        $out = [];
        foreach ($query->get() as $row) {
            $out[((int) $row->empresa_id).'|'.((int) $row->anio_mes).'|'.((int) $row->moneda_id)] = (float) $row->valor;
        }

        return $out;
    }

    /**
     * @return array<string, float> clave "empresa|periodo|moneda"
     */
    private function agregadoAsientos(array $empresaIds, ?int $periodoDesde, ?int $periodoHasta): array
    {
        $anioMes = SqlDialectSupport::anioMes('a.fecha');
        $query = DB::table('asiento_movimiento as am')
            ->join('asiento as a', 'a.id', '=', 'am.asiento_id')
            ->whereNotNull('am.cuentacontable_id')
            ->whereNotNull('am.moneda_id')
            ->groupByRaw('a.empresa_id, '.$anioMes.', am.moneda_id')
            ->selectRaw('a.empresa_id, '.$anioMes.' as anio_mes, am.moneda_id, sum(am.monto) valor');

        if ($empresaIds !== []) {
            $query->whereIn('a.empresa_id', $empresaIds);
        }
        if ($periodoDesde !== null) {
            $query->whereRaw($anioMes.' >= ?', [$periodoDesde]);
        }
        if ($periodoHasta !== null) {
            $query->whereRaw($anioMes.' <= ?', [$periodoHasta]);
        }

        $out = [];
        foreach ($query->get() as $row) {
            $out[((int) $row->empresa_id).'|'.((int) $row->anio_mes).'|'.((int) $row->moneda_id)] = (float) $row->valor;
        }

        return $out;
    }

    /**
     * @param  list<int>  $empresaIds
     * @return array<int, string>
     */
    private function nombresEmpresa(array $empresaIds): array
    {
        if ($empresaIds === []) {
            return [];
        }

        return DB::table('empresa')
            ->whereIn('id', $empresaIds)
            ->pluck('nombre', 'id')
            ->map(fn ($v) => (string) $v)
            ->all();
    }
}

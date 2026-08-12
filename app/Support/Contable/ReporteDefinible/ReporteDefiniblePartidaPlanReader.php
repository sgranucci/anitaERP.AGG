<?php

namespace App\Support\Contable\ReporteDefinible;

use App\Models\Presupuesto\Presupuesto;
use App\Models\Presupuesto\Presupuesto_Escenario;
use Illuminate\Support\Facades\DB;

/**
 * Plan del comparativo desde partidas de gasto (cuenta + c.costo), no empresa+10.
 */
class ReporteDefiniblePartidaPlanReader
{
    /**
     * @param  list<int>  $empresaIds
     * @param  list<int>  $codigosCuenta
     * @return array{movimientos: list<array{codigo: int, ccosto: int, monto: float, fecha: string}>, advertencias: list<string>, escenario_id: int|null, presupuesto_id: int|null}
     */
    public function listarMovimientosPlan(
        array $empresaIds,
        string $periodoDesdeYm,
        string $periodoHastaYm,
        array $codigosCuenta,
        ?int $presupuestoEscenarioId,
        int $monedaId,
    ): array {
        $advertencias = [];
        if ($empresaIds === [] || $codigosCuenta === [] || $periodoDesdeYm === '' || $periodoHastaYm === '') {
            return [
                'movimientos' => [],
                'advertencias' => ['Sin empresas, cuentas o período para leer Plan de partidas.'],
                'escenario_id' => null,
                'presupuesto_id' => null,
            ];
        }

        if ($periodoDesdeYm > $periodoHastaYm) {
            [$periodoDesdeYm, $periodoHastaYm] = [$periodoHastaYm, $periodoDesdeYm];
        }

        $anio = (int) substr($periodoDesdeYm, 0, 4);
        $presupuesto = Presupuesto::query()->where('anio', $anio)->orderByDesc('id')->first();
        if (! $presupuesto) {
            return [
                'movimientos' => [],
                'advertencias' => [sprintf('No hay presupuesto ERP para el año %d.', $anio)],
                'escenario_id' => null,
                'presupuesto_id' => null,
            ];
        }

        $escenarioId = $presupuestoEscenarioId && $presupuestoEscenarioId > 0
            ? $presupuestoEscenarioId
            : null;

        if ($escenarioId === null) {
            $escenarios = Presupuesto_Escenario::query()
                ->where('presupuesto_id', (int) $presupuesto->id)
                ->orderBy('id')
                ->get(['id', 'nombre', 'codigo']);
            if ($escenarios->count() === 1) {
                $escenarioId = (int) $escenarios->first()->id;
            } elseif ($escenarios->count() > 1) {
                $escenarioId = (int) $escenarios->first()->id;
                $advertencias[] = sprintf(
                    'Varios escenarios en %d; se usa «%s». Elegí escenario en el filtro si corresponde.',
                    $anio,
                    $escenarios->first()->nombre ?: $escenarios->first()->codigo
                );
            } else {
                return [
                    'movimientos' => [],
                    'advertencias' => [sprintf('Presupuesto %d sin escenarios.', $anio)],
                    'escenario_id' => null,
                    'presupuesto_id' => (int) $presupuesto->id,
                ];
            }
        }

        $codigosStr = array_map('strval', $codigosCuenta);

        $rows = DB::table('partidagasto as p')
            ->join('partidagasto_monto as m', 'm.partidagasto_id', '=', 'p.id')
            ->join('cuentacontable as c', 'c.id', '=', 'p.cuentacontable_id')
            ->join('centrocosto as cc', 'cc.id', '=', 'p.centrocosto_id')
            ->whereIn('p.empresa_id', $empresaIds)
            ->where('p.presupuesto_id', (int) $presupuesto->id)
            ->where('p.presupuesto_escenario_id', $escenarioId)
            ->where('p.estado', 'ACTIVA')
            ->whereNotNull('p.cuentacontable_id')
            ->whereIn('c.codigo', $codigosStr)
            ->whereBetween('m.periodo', [$periodoDesdeYm, $periodoHastaYm])
            ->where('m.monto', '!=', 0)
            ->select([
                'c.codigo as codigo_cuenta',
                'cc.codigo as codigo_ccosto',
                'm.periodo',
                'm.monto',
                'p.moneda_id',
            ])
            ->cursor();

        $out = [];
        $omitidasOtraMoneda = 0;
        foreach ($rows as $row) {
            $monedaPartida = (int) ($row->moneda_id ?? 0);
            $monto = (float) $row->monto;
            if ($monedaPartida > 0 && $monedaPartida !== $monedaId) {
                $omitidasOtraMoneda++;
                continue;
            }

            $periodo = (string) $row->periodo;
            $fecha = strlen($periodo) === 7
                ? $periodo.'-01'
                : substr($periodo, 0, 10);

            $out[] = [
                'codigo' => (int) $row->codigo_cuenta,
                'ccosto' => (int) ($row->codigo_ccosto ?? 0),
                'monto' => round($monto, 2),
                'fecha' => $fecha,
            ];
        }

        if ($omitidasOtraMoneda > 0) {
            $advertencias[] = sprintf(
                'Plan partidas: se omitieron %d renglones en otra moneda distinta a la del reporte.',
                $omitidasOtraMoneda
            );
        }
        if ($out === []) {
            $advertencias[] = sprintf(
                'Sin montos de partida ACTIVA (cta+ccosto) en %s–%s para el escenario elegido.',
                $periodoDesdeYm,
                $periodoHastaYm
            );
        } else {
            $advertencias[] = sprintf(
                'Plan leído de partidas de gasto (presupuesto %d, escenario id %d).',
                (int) $presupuesto->anio,
                $escenarioId
            );
        }

        return [
            'movimientos' => $out,
            'advertencias' => $advertencias,
            'escenario_id' => $escenarioId,
            'presupuesto_id' => (int) $presupuesto->id,
        ];
    }

    /**
     * @return list<array{id: int, presupuesto_id: int, anio: int, nombre: string, codigo: string}>
     */
    public function listarEscenariosParaSelector(?int $anio = null): array
    {
        $q = DB::table('presupuesto_escenario as e')
            ->join('presupuesto as p', 'p.id', '=', 'e.presupuesto_id')
            ->orderByDesc('p.anio')
            ->orderBy('e.id')
            ->select([
                'e.id',
                'e.presupuesto_id',
                'p.anio',
                'e.nombre',
                'e.codigo',
            ]);
        if ($anio !== null && $anio > 0) {
            $q->where('p.anio', $anio);
        }

        $out = [];
        foreach ($q->get() as $row) {
            $out[] = [
                'id' => (int) $row->id,
                'presupuesto_id' => (int) $row->presupuesto_id,
                'anio' => (int) $row->anio,
                'nombre' => trim((string) ($row->nombre ?: $row->codigo)),
                'codigo' => (string) $row->codigo,
            ];
        }

        return $out;
    }

    public static function periodoYmDesdeFecha(string $fechaYmd): string
    {
        if (strlen($fechaYmd) >= 7) {
            return substr($fechaYmd, 0, 7);
        }

        return '';
    }

    public static function periodoYmDesdePeriodoAaaamm(int $periodo): string
    {
        $y = intdiv($periodo, 100);
        $m = $periodo % 100;

        return sprintf('%04d-%02d', $y, $m);
    }
}

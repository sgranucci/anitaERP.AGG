<?php

namespace App\Services\Sueldos;

use App\Models\Caja\PerdidaPersonal;
use App\Models\Sueldos\Dtofallo_Sueldos;
use App\Models\Sueldos\Empleado_Sueldos;
use App\Support\Sueldos\DtoFalloTipoOper;
use App\Support\Sueldos\EmpleadoEstados;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Cuenta corriente de fallos (Anita: l-fallo.c).
 * Combina faltantes (perdida_personal conceptos Faltante*) con dtofallo.
 */
class FalloCuentaCorrienteReporteService
{
    public const ORDEN_LEGAJO = 'legajo';

    public const ORDEN_ALFABETICO = 'alfabetico';

    public const MODO_MOVIMIENTOS = 'movimientos';

    public const MODO_TOTALES = 'totales';

    /**
     * @param  array<string, mixed>  $filtros
     * @return array{
     *   filas: list<array<string, mixed>>,
     *   total_debe: float,
     *   total_haber: float,
     *   total_saldo: float,
     *   total_empleados: int,
     *   subtitulo: string
     * }
     */
    public function generar(array $filtros): array
    {
        $empresaId = (int) ($filtros['empresa_id'] ?? 0);
        $desde = Carbon::parse((string) ($filtros['fecha_desde'] ?? date('Y-m-01')))->startOfDay();
        $hasta = Carbon::parse((string) ($filtros['fecha_hasta'] ?? date('Y-m-d')))->endOfDay();
        $orden = (string) ($filtros['orden'] ?? self::ORDEN_LEGAJO);
        $modo = (string) ($filtros['modo'] ?? self::MODO_MOVIMIENTOS);
        $legajoDesdeRaw = $filtros['legajo_desde'] ?? '';
        $legajoHastaRaw = $filtros['legajo_hasta'] ?? '';
        $legajoDesde = trim((string) $legajoDesdeRaw) === '' ? 1 : max(1, (int) $legajoDesdeRaw);
        $legajoHasta = trim((string) $legajoHastaRaw) === ''
            ? 99999999
            : max($legajoDesde, (int) $legajoHastaRaw);

        $empleadosQ = Empleado_Sueldos::query()
            ->with(['categoria', 'agrupamiento', 'lugartrabajo'])
            ->whereBetween('legajo', [$legajoDesde, $legajoHasta])
            ->where(function ($q) {
                $q->whereNull('estado')->orWhere('estado', '!=', EmpleadoEstados::BAJA);
            });
        if ($empresaId > 0) {
            $empleadosQ->where('empresa_id', $empresaId);
        }
        /** @var Collection<int, Empleado_Sueldos> $empleados */
        $empleados = $empleadosQ->get();
        if ($orden === self::ORDEN_ALFABETICO) {
            $empleados = $empleados->sortBy(fn ($e) => mb_strtoupper((string) $e->nombre))->values();
        } else {
            $empleados = $empleados->sortBy(fn ($e) => (int) $e->legajo)->values();
        }

        $empIds = $empleados->pluck('id')->all();

        $perdidas = PerdidaPersonal::query()
            ->with('conceptoPerdida')
            ->whereIn('empleado_sueldos_id', $empIds)
            ->whereBetween('fecha', [$desde->toDateString(), $hasta->toDateString()])
            ->whereHas('conceptoPerdida', fn ($q) => $q->where('nombre', 'like', 'Faltante%'))
            ->orderBy('fecha')
            ->get()
            ->groupBy('empleado_sueldos_id');

        $dtos = Dtofallo_Sueldos::query()
            ->whereIn('empleado_sueldos_id', $empIds)
            ->whereBetween('fecha', [$desde->toDateString(), $hasta->toDateString()])
            ->orderBy('fecha')
            ->get()
            ->groupBy('empleado_sueldos_id');

        $filas = [];
        $totalDebe = 0.0;
        $totalHaber = 0.0;
        $empleadosConMov = 0;

        foreach ($empleados as $empleado) {
            $movs = [];
            foreach ($perdidas->get($empleado->id, collect()) as $p) {
                $movs[] = [
                    'fecha' => $p->fecha?->format('Y-m-d'),
                    'fecha_fmt' => $p->fecha?->format('d/m/Y') ?? '',
                    'concepto' => (string) ($p->conceptoPerdida->nombre ?? 'Faltante'),
                    'debe' => round((float) $p->importe, 2),
                    'haber' => 0.0,
                    'observacion' => (string) ($p->leyenda ?? ''),
                    'origen' => 'perdida',
                ];
            }
            foreach ($dtos->get($empleado->id, collect()) as $d) {
                $esHaber = DtoFalloTipoOper::esHaber((string) $d->tipo_oper);
                $movs[] = [
                    'fecha' => $d->fecha?->format('Y-m-d'),
                    'fecha_fmt' => $d->fecha?->format('d/m/Y') ?? '',
                    'concepto' => DtoFalloTipoOper::etiqueta($d->tipo_oper),
                    'debe' => $esHaber ? 0.0 : round((float) $d->importe, 2),
                    'haber' => $esHaber ? round((float) $d->importe, 2) : 0.0,
                    'observacion' => (string) ($d->observacion ?? ''),
                    'origen' => 'dtofallo',
                ];
            }

            if ($movs === []) {
                continue;
            }

            usort($movs, fn ($a, $b) => strcmp((string) $a['fecha'], (string) $b['fecha']));
            $empleadosConMov++;
            $debeEmp = round(array_sum(array_column($movs, 'debe')), 2);
            $haberEmp = round(array_sum(array_column($movs, 'haber')), 2);
            $totalDebe += $debeEmp;
            $totalHaber += $haberEmp;

            if ($modo === self::MODO_TOTALES) {
                $filas[] = $this->filaBase($empleado, true) + [
                    'fecha' => '',
                    'concepto' => '',
                    'debe' => $debeEmp,
                    'haber' => $haberEmp,
                    'observacion' => '',
                    'es_total' => true,
                ];
                continue;
            }

            foreach ($movs as $m) {
                $filas[] = $this->filaBase($empleado, false) + $m + ['es_total' => false];
            }
            $filas[] = $this->filaBase($empleado, true) + [
                'fecha' => '',
                'concepto' => '',
                'debe' => $debeEmp,
                'haber' => $haberEmp,
                'observacion' => '',
                'es_total' => true,
                'nombre' => 'Total empleado '.$empleado->nombre,
            ];
        }

        return [
            'filas' => $filas,
            'total_debe' => round($totalDebe, 2),
            'total_haber' => round($totalHaber, 2),
            'total_saldo' => round($totalDebe - $totalHaber, 2),
            'total_empleados' => $empleadosConMov,
            'subtitulo' => sprintf(
                'Período %s a %s · %s',
                $desde->format('d/m/Y'),
                $hasta->format('d/m/Y'),
                $modo === self::MODO_TOTALES ? 'Totales x empleado' : 'Movimientos'
            ),
        ];
    }

    /**
     * Panel del padrón: pérdidas + dtofallo + saldo del legajo.
     *
     * @return array<string, mixed>
     */
    public function resumenEmpleado(Empleado_Sueldos $empleado, ?string $fechaDesde = null, ?string $fechaHasta = null): array
    {
        $desde = $fechaDesde
            ? Carbon::parse($fechaDesde)->startOfDay()
            : Carbon::now()->subYear()->startOfDay();
        $hasta = $fechaHasta
            ? Carbon::parse($fechaHasta)->endOfDay()
            : Carbon::now()->endOfDay();

        $resultado = $this->generar([
            'empresa_id' => (int) $empleado->empresa_id,
            'fecha_desde' => $desde->toDateString(),
            'fecha_hasta' => $hasta->toDateString(),
            'legajo_desde' => (int) $empleado->legajo,
            'legajo_hasta' => (int) $empleado->legajo,
            'modo' => self::MODO_MOVIMIENTOS,
            'orden' => self::ORDEN_LEGAJO,
        ]);

        $cierres = Dtofallo_Sueldos::query()
            ->with('cierre')
            ->where('empleado_sueldos_id', $empleado->id)
            ->whereBetween('fecha', [$desde->toDateString(), $hasta->toDateString()])
            ->orderByDesc('fecha')
            ->limit(100)
            ->get();

        return [
            'filas' => array_values(array_filter(
                $resultado['filas'],
                fn ($f) => empty($f['es_total'])
            )),
            'total_debe' => $resultado['total_debe'],
            'total_haber' => $resultado['total_haber'],
            'total_saldo' => $resultado['total_saldo'],
            'movimientos_ledger' => $cierres,
            'fecha_desde' => $desde->toDateString(),
            'fecha_hasta' => $hasta->toDateString(),
            'fallo_tipo' => (string) ($empleado->agrupamiento->fallo_tipo ?? ''),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function filaBase(Empleado_Sueldos $empleado, bool $esTotal): array
    {
        return [
            'legajo' => $esTotal ? '' : (int) $empleado->legajo,
            'nombre' => $esTotal ? '' : (string) $empleado->nombre,
            'fecha_ingreso' => $empleado->fecha_ingreso
                ? Carbon::parse($empleado->fecha_ingreso)->format('d/m/Y')
                : '',
            'categoria' => (string) ($empleado->categoria->descripcion ?? ''),
            'agrupamiento' => (string) ($empleado->agrupamiento->descripcion ?? ''),
            'lugar_trabajo' => (string) ($empleado->lugartrabajo->descripcion ?? ''),
            'empleado_id' => (int) $empleado->id,
        ];
    }
}

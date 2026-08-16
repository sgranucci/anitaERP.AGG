<?php

namespace App\Services\Caja;

use App\Models\Caja\ConceptoPerdida;
use App\Models\Caja\PerdidaPersonal;
use App\Models\Sueldos\Empleado_Sueldos;
use App\Support\Sueldos\EmpleadoVigenciaSupport;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Informe de pérdidas de empleados (Anita: l-perdempl.c).
 */
class PerdidaPersonalReporteService
{
    public const ORDEN_LEGAJO = 'legajo';

    public const ORDEN_ALFABETICO = 'alfabetico';

    public const FILTRO_ACTIVOS = 'activos';

    public const FILTRO_BAJAS = 'bajas';

    public const FILTRO_TODOS = 'todos';

    public const MODO_MOVIMIENTOS = 'movimientos';

    public const MODO_TOTALES = 'totales';

    /**
     * @param  array<string, mixed>  $filtros
     * @return array{
     *   filas: list<array<string, mixed>>,
     *   total_importe: float,
     *   total_registros: int,
     *   total_empleados: int,
     *   filtros: array<string, mixed>,
     *   subtitulo: string
     * }
     */
    public function generar(array $filtros): array
    {
        $empresaId = (int) ($filtros['empresa_id'] ?? 0);
        $desde = Carbon::parse((string) ($filtros['fecha_desde'] ?? date('Y-m-01')))->startOfDay();
        $hasta = Carbon::parse((string) ($filtros['fecha_hasta'] ?? date('Y-m-d')))->endOfDay();
        $conceptoId = (int) ($filtros['concepto_perdida_id'] ?? 0);
        $soloFaltantes = $conceptoId <= 0;
        $orden = (string) ($filtros['orden'] ?? self::ORDEN_LEGAJO);
        $filtroEmp = (string) ($filtros['filtro_empleado'] ?? self::FILTRO_ACTIVOS);
        $modo = (string) ($filtros['modo'] ?? self::MODO_MOVIMIENTOS);
        $legajoDesde = max(0, (int) ($filtros['legajo_desde'] ?? 0));
        $legajoHasta = (int) ($filtros['legajo_hasta'] ?? 99999999);
        if ($legajoHasta < $legajoDesde) {
            $legajoHasta = $legajoDesde;
        }

        $query = PerdidaPersonal::query()
            ->with([
                'empleado.categoria',
                'empleado.agrupamiento',
                'empleado.lugartrabajo',
                'conceptoPerdida',
                'empresa',
            ])
            ->whereBetween('fecha', [$desde->toDateString(), $hasta->toDateString()]);

        if ($empresaId > 0) {
            $query->where('empresa_id', $empresaId);
        }

        if ($conceptoId > 0) {
            $query->where('concepto_perdida_id', $conceptoId);
        } elseif ($soloFaltantes) {
            $query->whereHas('conceptoPerdida', fn ($q) => $q->where('nombre', 'like', 'Faltante%'));
        }

        $query->whereHas('empleado', function ($q) use ($legajoDesde, $legajoHasta, $filtroEmp, $hasta) {
            $q->whereBetween('legajo', [$legajoDesde > 0 ? $legajoDesde : 1, $legajoHasta]);
            EmpleadoVigenciaSupport::aplicar($q, $filtroEmp, $hasta);
        });

        /** @var Collection<int, PerdidaPersonal> $perdidas */
        $perdidas = $query->get();

        $agrupadas = $perdidas->groupBy(fn (PerdidaPersonal $p) => (int) $p->empleado_sueldos_id);

        $empleadosOrden = $agrupadas->keys()->map(function ($empId) use ($agrupadas) {
            /** @var PerdidaPersonal $first */
            $first = $agrupadas[$empId]->first();

            return $first->empleado;
        })->filter()->values();

        if ($orden === self::ORDEN_ALFABETICO) {
            $empleadosOrden = $empleadosOrden->sortBy(fn (Empleado_Sueldos $e) => mb_strtoupper((string) $e->nombre))->values();
        } else {
            $empleadosOrden = $empleadosOrden->sortBy(fn (Empleado_Sueldos $e) => (int) $e->legajo)->values();
        }

        $filas = [];
        $totalImporte = 0.0;

        foreach ($empleadosOrden as $empleado) {
            $grupo = $agrupadas->get((int) $empleado->id, collect())
                ->sortBy(fn (PerdidaPersonal $p) => $p->fecha?->format('Ymd').'|'.$p->numero);

            $subtotal = round((float) $grupo->sum('importe'), 2);
            $totalImporte += $subtotal;

            if ($modo === self::MODO_TOTALES) {
                $filas[] = $this->filaEmpleado($empleado, null, $subtotal, true);
                continue;
            }

            foreach ($grupo as $perdida) {
                $filas[] = $this->filaEmpleado($empleado, $perdida, (float) $perdida->importe, false);
            }
            $filas[] = [
                'es_total' => true,
                'legajo' => '',
                'nombre' => 'Total empleado '.$empleado->nombre,
                'fecha_ingreso' => '',
                'categoria' => '',
                'agrupamiento' => '',
                'lugar_trabajo' => '',
                'fecha' => '',
                'concepto' => '',
                'importe' => $subtotal,
                'perdida_id' => null,
                'empleado_id' => (int) $empleado->id,
            ];
        }

        return [
            'filas' => $filas,
            'total_importe' => round($totalImporte, 2),
            'total_registros' => $perdidas->count(),
            'total_empleados' => $empleadosOrden->count(),
            'filtros' => $filtros,
            'subtitulo' => $this->subtitulo($filtros, $conceptoId, $soloFaltantes, $desde, $hasta),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function filaEmpleado(
        Empleado_Sueldos $empleado,
        ?PerdidaPersonal $perdida,
        float $importe,
        bool $esTotal
    ): array {
        return [
            'es_total' => $esTotal,
            'legajo' => (int) $empleado->legajo,
            'nombre' => (string) $empleado->nombre,
            'fecha_ingreso' => $empleado->fecha_ingreso
                ? Carbon::parse($empleado->fecha_ingreso)->format('d/m/Y')
                : '',
            'categoria' => (string) ($empleado->categoria->descripcion ?? ''),
            'agrupamiento' => (string) ($empleado->agrupamiento->descripcion ?? ''),
            'lugar_trabajo' => (string) ($empleado->lugartrabajo->descripcion ?? ''),
            'fecha' => $perdida?->fecha ? $perdida->fecha->format('d/m/Y') : '',
            'concepto' => $perdida ? (string) ($perdida->conceptoPerdida->nombre ?? '') : '',
            'importe' => round($importe, 2),
            'perdida_id' => $perdida?->id,
            'empleado_id' => (int) $empleado->id,
        ];
    }

    private function subtitulo(
        array $filtros,
        int $conceptoId,
        bool $soloFaltantes,
        Carbon $desde,
        Carbon $hasta
    ): string {
        $partes = [];
        $partes[] = 'Período '.$desde->format('d/m/Y').' a '.$hasta->format('d/m/Y');

        if ($conceptoId > 0) {
            $c = ConceptoPerdida::query()->find($conceptoId);
            $partes[] = 'Concepto: '.($c ? $c->codigo.' '.$c->nombre : '#'.$conceptoId);
        } elseif ($soloFaltantes) {
            $partes[] = 'Concepto: Todos los faltantes';
        }

        $orden = ($filtros['orden'] ?? self::ORDEN_LEGAJO) === self::ORDEN_ALFABETICO
            ? 'Alfabético'
            : 'Por legajo';
        $partes[] = 'Orden: '.$orden;

        $modo = ($filtros['modo'] ?? self::MODO_MOVIMIENTOS) === self::MODO_TOTALES
            ? 'Totales x empleado'
            : 'Movimientos';
        $partes[] = $modo;

        return implode(' · ', $partes);
    }
}

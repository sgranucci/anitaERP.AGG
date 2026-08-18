<?php

namespace App\Services\Sueldos;

use App\Models\Caja\PerdidaPersonal;
use App\Models\Sueldos\Agrupamiento_Sueldos;
use App\Models\Sueldos\CierreDescuentoFallo_Sueldos;
use App\Models\Sueldos\Concepto_Sueldos;
use App\Models\Sueldos\DescuentoFallo_Sueldos;
use App\Models\Sueldos\Empleado_Sueldos;
use App\Models\Sueldos\Fallocaja_Sueldos;
use App\Models\Sueldos\Novedad_Sueldos;
use App\Support\Sueldos\DescuentoFalloTipoOperacion;
use App\Support\Sueldos\EmpleadoEstados;
use App\Support\Sueldos\NovedadSueldosCatalogo;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;

/**
 * Proceso Anita p-dtofallo.c: genera cuotas de descuento por faltantes
 * según la tabla de fallos del agrupamiento del empleado, y opcionalmente
 * novedades de liquidación para cobrarlas en el recibo.
 */
class DescuentoFalloProcesoService
{
    /**
     * @param  array{
     *   empresa_id:int,
     *   periodo_descuento:int,
     *   fecha_fallo_desde:string,
     *   fecha_fallo_hasta:string,
     *   legajo_desde?:int,
     *   legajo_hasta?:int,
     *   generar_novedades?:bool
     * }  $input
     * @return array{cierre: CierreDescuentoFallo_Sueldos, resumen: array<string, mixed>}
     */
    public function generar(array $input): array
    {
        $empresaId = (int) ($input['empresa_id'] ?? 0);
        $periodo = (int) ($input['periodo_descuento'] ?? 0);
        $desde = Carbon::parse((string) ($input['fecha_fallo_desde'] ?? ''))->startOfDay();
        $hasta = Carbon::parse((string) ($input['fecha_fallo_hasta'] ?? ''))->startOfDay();
        $legajoDesde = max(1, (int) ($input['legajo_desde'] ?? 1));
        $legajoHasta = max($legajoDesde, (int) ($input['legajo_hasta'] ?? 99999999));
        $generarNovedades = (bool) ($input['generar_novedades'] ?? true);

        if ($empresaId <= 0) {
            throw new InvalidArgumentException('Debe indicar la empresa.');
        }
        if ($periodo < 190001 || $periodo > 299912) {
            throw new InvalidArgumentException('Período de descuento inválido (YYYYMM).');
        }
        if ($hasta->lt($desde)) {
            throw new InvalidArgumentException('La fecha hasta del fallo no puede ser menor que desde.');
        }

        $mesesPlan = max(1, (int) config('sueldos.meses_descuento_fallo', 10));
        $concepto = $this->resolverConceptoDescuento();

        return DB::transaction(function () use (
            $empresaId,
            $periodo,
            $desde,
            $hasta,
            $legajoDesde,
            $legajoHasta,
            $generarNovedades,
            $mesesPlan,
            $concepto
        ) {
            $cierre = CierreDescuentoFallo_Sueldos::create([
                'numero_cierre' => $this->siguienteNumeroCierre(),
                'empresa_id' => $empresaId,
                'periodo_descuento' => $periodo,
                'fecha_fallo_desde' => $desde->toDateString(),
                'fecha_fallo_hasta' => $hasta->toDateString(),
                'legajo_desde' => $legajoDesde,
                'legajo_hasta' => $legajoHasta,
                'usuario_id' => Auth::id(),
                'estado' => CierreDescuentoFallo_Sueldos::ESTADO_GENERADO,
                'observacion' => sprintf(
                    'Proceso descuento por fallo %s a %s · período %s',
                    $desde->format('d/m/Y'),
                    $hasta->format('d/m/Y'),
                    $periodo
                ),
            ]);

            $empleados = Empleado_Sueldos::query()
                ->with(['agrupamiento'])
                ->where('empresa_id', $empresaId)
                ->whereBetween('legajo', [$legajoDesde, $legajoHasta])
                ->where(function ($q) {
                    $q->whereNull('estado')
                        ->orWhere('estado', '!=', EmpleadoEstados::BAJA);
                })
                ->orderBy('legajo')
                ->get();

            $empleadosProcesados = 0;
            $movimientos = 0;
            $novedades = 0;
            $totalPerdida = 0.0;
            $totalDescuento = 0.0;
            $totalSancion = 0.0;
            $detalle = [];

            foreach ($empleados as $empleado) {
                $resultado = $this->procesarEmpleado(
                    $empleado,
                    $cierre,
                    $desde,
                    $hasta,
                    $periodo,
                    $mesesPlan,
                    $generarNovedades,
                    $concepto
                );
                if ($resultado === null) {
                    continue;
                }

                $empleadosProcesados++;
                $movimientos += $resultado['movimientos'];
                $novedades += $resultado['novedades'];
                $totalPerdida += $resultado['tot_perdida'];
                $totalDescuento += $resultado['tot_descuento'];
                $totalSancion += $resultado['tot_sancion'];
                $detalle[] = $resultado['resumen'];
            }

            $cierre->update([
                'empleados_procesados' => $empleadosProcesados,
                'movimientos_generados' => $movimientos,
                'novedades_generadas' => $novedades,
                'total_perdida' => round($totalPerdida, 2),
                'total_descuento' => round($totalDescuento, 2),
                'total_sancion' => round($totalSancion, 2),
            ]);

            return [
                'cierre' => $cierre->fresh(['empresa', 'usuario']),
                'resumen' => [
                    'empleados_procesados' => $empleadosProcesados,
                    'movimientos' => $movimientos,
                    'novedades' => $novedades,
                    'total_perdida' => round($totalPerdida, 2),
                    'total_descuento' => round($totalDescuento, 2),
                    'total_sancion' => round($totalSancion, 2),
                    'detalle' => $detalle,
                    'concepto_codigo' => $concepto?->codigo,
                    'concepto_descripcion' => $concepto?->descripcion,
                ],
            ];
        });
    }

    public function anularCierre(int $cierreId): CierreDescuentoFallo_Sueldos
    {
        return DB::transaction(function () use ($cierreId) {
            /** @var CierreDescuentoFallo_Sueldos $cierre */
            $cierre = CierreDescuentoFallo_Sueldos::query()->lockForUpdate()->findOrFail($cierreId);
            if ($cierre->estaAnulado()) {
                return $cierre;
            }

            $movs = DescuentoFallo_Sueldos::query()
                ->where('cierre_descuento_fallo_id', $cierre->id)
                ->get();

            foreach ($movs as $mov) {
                if ($mov->novedad_id && Schema::hasColumn('novedad_sueldos', 'descuento_fallo_id')) {
                    Novedad_Sueldos::query()
                        ->where('id', $mov->novedad_id)
                        ->where('estado', '!=', NovedadSueldosCatalogo::ESTADO_ANULADA)
                        ->update([
                            'estado' => NovedadSueldosCatalogo::ESTADO_ANULADA,
                            'observacion' => trim((string) ($mov->observacion ?? '')).' · Anulado cierre #'.$cierre->numero_cierre,
                        ]);
                }
                $mov->delete();
            }

            $cierre->update([
                'estado' => CierreDescuentoFallo_Sueldos::ESTADO_ANULADO,
                'observacion' => trim((string) ($cierre->observacion ?? '')).' · Anulado '.now()->format('d/m/Y H:i'),
            ]);

            return $cierre->fresh();
        });
    }

    /**
     * @return array{
     *   movimientos:int,
     *   novedades:int,
     *   tot_perdida:float,
     *   tot_descuento:float,
     *   tot_sancion:float,
     *   resumen: array<string, mixed>
     * }|null
     */
    private function procesarEmpleado(
        Empleado_Sueldos $empleado,
        CierreDescuentoFallo_Sueldos $cierre,
        Carbon $desde,
        Carbon $hasta,
        int $periodoInicio,
        int $mesesPlan,
        bool $generarNovedades,
        ?Concepto_Sueldos $concepto
    ): ?array {
        $agrupamiento = $empleado->agrupamiento;
        if (! $agrupamiento instanceof Agrupamiento_Sueldos || ! $agrupamiento->fallo_tipo) {
            return null;
        }

        $tramos = Fallocaja_Sueldos::query()
            ->where('tipo', $agrupamiento->fallo_tipo)
            ->orderBy('desde')
            ->orderBy('orden')
            ->get();
        if ($tramos->isEmpty()) {
            return null;
        }

        $totPerdida = (float) PerdidaPersonal::query()
            ->where('empleado_sueldos_id', $empleado->id)
            ->whereBetween('fecha', [$desde->toDateString(), $hasta->toDateString()])
            ->whereHas('conceptoPerdida', function ($q) {
                $q->where('nombre', 'like', 'Faltante%');
            })
            ->sum('importe');

        if ($totPerdida <= 0.009) {
            return null;
        }

        $totDescuentoPrevio = (float) DescuentoFallo_Sueldos::query()
            ->where('empleado_sueldos_id', $empleado->id)
            ->where('tipo_operacion', DescuentoFalloTipoOperacion::DESCUENTO)
            ->whereDate('fecha', '>=', $desde->toDateString())
            ->sum('importe');

        $primerTramo = $tramos->first();
        $umbral = (float) $primerTramo->desde;
        $maximoMensual = $umbral > 0 ? round($umbral / 10, 2) : 0.0;

        $totSancion = 0.0;
        $totADescontar = 0.0;
        $detalleSancion = '';

        $baseComparacion = $totPerdida + $totDescuentoPrevio;
        if ($baseComparacion > $umbral) {
            foreach ($tramos as $tramo) {
                if ((float) $tramo->desde <= $baseComparacion && $baseComparacion <= (float) $tramo->hasta) {
                    $totSancion = round($baseComparacion - $umbral, 2);
                    $totADescontar = $umbral;
                    $detalleSancion = (string) ($tramo->sancion ?? '');
                    break;
                }
            }
        } else {
            $totADescontar = round($totPerdida, 2);
        }

        if ($totADescontar <= 0.009 && $totSancion <= 0.009) {
            return null;
        }

        $obsBase = sprintf(
            'Descuento por fallo %s a %s',
            $desde->format('d/m/Y'),
            $hasta->format('d/m/Y')
        );

        $movimientos = 0;
        $novedades = 0;
        $totDescuentoGenerado = 0.0;

        if ($totADescontar > 0.009) {
            $descuentaPorMes = $umbral > 0 && $umbral <= $totADescontar
                ? round($totADescontar / $mesesPlan, 2)
                : round(($umbral > 0 ? $umbral : $totADescontar) / $mesesPlan, 2);

            $anio = (int) floor($periodoInicio / 100);
            $mes = (int) ($periodoInicio % 100);
            $totDescontado = 0.0;
            $mesesIntentados = 0;
            $maxMesesIntentados = max(24, $mesesPlan * 3);

            while (abs($totDescontado - $totADescontar) > 1 && $totADescontar > 0) {
                if ($mes > 12) {
                    $mes = 1;
                    $anio++;
                }

                $mesesIntentados++;
                if ($mesesIntentados > $maxMesesIntentados || $movimientos > 120) {
                    break;
                }

                $periodoMes = $anio * 100 + $mes;
                $fechaMov = Carbon::create($anio, $mes, 1)->startOfDay();
                $desdeMes = $fechaMov->copy()->startOfMonth()->toDateString();
                $hastaMes = $fechaMov->copy()->endOfMonth()->toDateString();

                $yaDescontadoMes = (float) DescuentoFallo_Sueldos::query()
                    ->where('empleado_sueldos_id', $empleado->id)
                    ->where('tipo_operacion', DescuentoFalloTipoOperacion::DESCUENTO)
                    ->whereBetween('fecha', [$desdeMes, $hastaMes])
                    ->sum('importe');

                $descuenta = 0.0;
                if ($yaDescontadoMes > 0) {
                    if ($maximoMensual > 0 && $yaDescontadoMes < $maximoMensual) {
                        $descuenta = round($maximoMensual - $yaDescontadoMes, 2);
                        if ($totDescontado + $descuenta > $totADescontar) {
                            $descuenta = round($totADescontar - $totDescontado, 2);
                        }
                    }
                } else {
                    $descuenta = $descuentaPorMes;
                    if ($totDescontado + $descuenta > $totADescontar) {
                        $descuenta = round($totADescontar - $totDescontado, 2);
                    }
                }

                if ($descuenta > 0.009) {
                    $mov = $this->grabarMovimiento(
                        $empleado,
                        $cierre,
                        $fechaMov,
                        $periodoMes,
                        DescuentoFalloTipoOperacion::DESCUENTO,
                        $descuenta,
                        $obsBase
                    );
                    $movimientos++;
                    $totDescontado += $descuenta;
                    $totDescuentoGenerado += $descuenta;

                    if ($generarNovedades && $concepto) {
                        if ($this->generarNovedad($mov, $empleado, $concepto, $periodoMes)) {
                            $novedades++;
                        }
                    }
                }

                $mes++;
            }
        }

        if ($totSancion > 0.009) {
            $anio = (int) floor($periodoInicio / 100);
            $mes = (int) ($periodoInicio % 100);
            $fechaMov = Carbon::create($anio, $mes, 1)->startOfDay();
            $this->grabarMovimiento(
                $empleado,
                $cierre,
                $fechaMov,
                $periodoInicio,
                DescuentoFalloTipoOperacion::SANCION,
                $totSancion,
                mb_substr($detalleSancion !== '' ? $detalleSancion : 'Sanción por fallo', 0, 80)
            );
            $movimientos++;
        }

        return [
            'movimientos' => $movimientos,
            'novedades' => $novedades,
            'tot_perdida' => round($totPerdida, 2),
            'tot_descuento' => round($totDescuentoGenerado, 2),
            'tot_sancion' => round($totSancion, 2),
            'resumen' => [
                'empleado_id' => (int) $empleado->id,
                'legajo' => (int) $empleado->legajo,
                'nombre' => (string) $empleado->nombre,
                'fallo_tipo' => (string) $agrupamiento->fallo_tipo,
                'tot_perdida' => round($totPerdida, 2),
                'tot_descuento' => round($totDescuentoGenerado, 2),
                'tot_sancion' => round($totSancion, 2),
                'sancion' => $detalleSancion,
            ],
        ];
    }

    private function grabarMovimiento(
        Empleado_Sueldos $empleado,
        CierreDescuentoFallo_Sueldos $cierre,
        Carbon $fecha,
        int $periodo,
        string $tipo,
        float $importe,
        string $observacion
    ): DescuentoFallo_Sueldos {
        return DescuentoFallo_Sueldos::create([
            'empresa_id' => (int) $empleado->empresa_id,
            'empleado_sueldos_id' => (int) $empleado->id,
            'cierre_descuento_fallo_id' => (int) $cierre->id,
            'fecha' => $fecha->toDateString(),
            'periodo' => $periodo,
            'tipo_operacion' => $tipo,
            'importe' => round($importe, 2),
            'observacion' => mb_substr($observacion, 0, 80),
        ]);
    }

    private function generarNovedad(
        DescuentoFallo_Sueldos $mov,
        Empleado_Sueldos $empleado,
        Concepto_Sueldos $concepto,
        int $periodo
    ): bool {
        if (! Schema::hasColumn('novedad_sueldos', 'descuento_fallo_id')) {
            return false;
        }
        if ($mov->tipo_operacion !== DescuentoFalloTipoOperacion::DESCUENTO) {
            return false;
        }

        $existente = Novedad_Sueldos::query()->where('descuento_fallo_id', $mov->id)->first();
        $anio = (int) floor($periodo / 100);
        $mes = (int) ($periodo % 100);
        $desde = Carbon::create($anio, $mes, 1)->startOfDay();
        $hasta = $desde->copy()->endOfMonth();

        $payload = [
            'empresa_id' => (int) $empleado->empresa_id,
            'liquidacion_id' => null,
            'empleado_id' => (int) $empleado->id,
            'concepto_id' => (int) $concepto->id,
            'concepto_codigo' => (int) $concepto->codigo,
            'valor1' => (float) $mov->importe,
            'valor2' => 0,
            'estado' => NovedadSueldosCatalogo::ESTADO_PENDIENTE,
            'fecha_desde' => $desde->toDateString(),
            'fecha_hasta' => $hasta->toDateString(),
            'periodo' => $periodo,
            'origen' => NovedadSueldosCatalogo::ORIGEN_DESCUENTO_FALLO,
            'descuento_fallo_id' => (int) $mov->id,
            'usuario_id' => Auth::id(),
            'observacion' => (string) ($mov->observacion ?? 'Descuento por fallo de caja'),
        ];

        if ($existente) {
            $existente->update($payload);
            $novedad = $existente->fresh();
        } else {
            $novedad = Novedad_Sueldos::create($payload);
        }

        $mov->update(['novedad_id' => (int) $novedad->id]);

        return true;
    }

    private function resolverConceptoDescuento(): ?Concepto_Sueldos
    {
        $codigo = (int) config('sueldos.concepto_descuento_fallo_codigo', 192);
        if ($codigo <= 0) {
            return null;
        }

        return Concepto_Sueldos::query()->where('codigo', $codigo)->first();
    }

    private function siguienteNumeroCierre(): int
    {
        $ultimo = (int) (CierreDescuentoFallo_Sueldos::query()->max('numero_cierre') ?? 0);

        return $ultimo + 1;
    }
}

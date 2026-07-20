<?php

namespace App\Services\Sueldos;

use App\Models\Sueldos\Empleado_Sueldos;
use App\Models\Sueldos\Liquidacion_Sueldos;
use App\Support\Sueldos\Formula\ContextoLiquidacion;
use App\Support\Sueldos\Ganancias\FamiliaresGananciasCantidadesSupport;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Puente liquidación → plan de Ganancias.
 *
 * Toma acumuladores/conceptos ya calculados en el recibo en curso, arma las
 * entradas del mes (SUJETO_APORTES, aportes, etc.), suma cantidades de
 * familiares, persiste ganancia_movimiento_sueldos y corre el motor del plan.
 */
class GananciasPuenteLiquidacionService
{
    /**
     * Código de concepto de liquidación → código de entrada del plan.
     * Los aportes se esperan con signo negativo (restan en GAN_NETA_I).
     *
     * @var array<int, string>
     */
    public const MAPA_CONCEPTO_ENTRADA = [
        200 => 'APORTE_RNSS',
        210 => 'LEY_19032',
        220 => 'OBRA_SOCIAL',
    ];

    private GananciasCalculadorService $calculador;

    public function __construct(GananciasCalculadorService $calculador)
    {
        $this->calculador = $calculador;
    }

    /**
     * @return array{retencion_mes: float, matriz: array, errores: list<string>}
     */
    public function sincronizarDesdeContexto(
        ContextoLiquidacion $ctx,
        Empleado_Sueldos $empleado,
        Liquidacion_Sueldos $liquidacion
    ): array {
        $anio = (int) ($liquidacion->periodo_anio ?: now()->year);
        $mes = (int) ($liquidacion->periodo_mes ?: now()->month);
        $empresaId = $liquidacion->empresa_id ? (int) $liquidacion->empresa_id : null;

        $entradas = $this->armarEntradasMes($ctx);
        $cantidades = FamiliaresGananciasCantidadesSupport::paraMes((int) $empleado->id, $anio, $mes);

        $this->persistirMovimientosMes(
            (int) $empleado->id,
            $empresaId,
            $anio,
            $mes,
            $entradas,
            $cantidades
        );

        return $this->calculador->calcularYPersistir(
            (int) $empleado->id,
            $empresaId,
            $anio,
            $mes,
            [$mes => $entradas],
            [$mes => $cantidades],
            (int) $liquidacion->id
        );
    }

    /**
     * @return array<string, float>
     */
    private function armarEntradasMes(ContextoLiquidacion $ctx): array
    {
        $entradas = [
            'SUJETO_APORTES' => round((float) $ctx->funcion('acum', ['REM']), 2),
        ];

        foreach (self::MAPA_CONCEPTO_ENTRADA as $conceptoCodigo => $lineaCodigo) {
            // En el recibo el aporte es positivo; en el plan resta de la ganancia neta.
            $entradas[$lineaCodigo] = round(-abs((float) $ctx->funcion('concepto', [$conceptoCodigo])), 2);
        }

        return $entradas;
    }

    /**
     * @param  array<string, float>  $entradas
     * @param  array<string, float>  $cantidades
     */
    private function persistirMovimientosMes(
        int $empleadoId,
        ?int $empresaId,
        int $anio,
        int $mes,
        array $entradas,
        array $cantidades
    ): void {
        $periodo = $anio * 100 + $mes;
        $ahora = Carbon::now();

        foreach ($entradas as $codigo => $valor) {
            DB::table('ganancia_movimiento_sueldos')->updateOrInsert(
                [
                    'empleado_id' => $empleadoId,
                    'periodo' => $periodo,
                    'linea_codigo' => strtoupper($codigo),
                ],
                [
                    'empresa_id' => $empresaId ?? 0,
                    'valor' => $valor,
                    'cantidad' => 0,
                    'updated_at' => $ahora,
                    'created_at' => $ahora,
                ]
            );
        }

        foreach ($cantidades as $codigo => $cant) {
            DB::table('ganancia_movimiento_sueldos')->updateOrInsert(
                [
                    'empleado_id' => $empleadoId,
                    'periodo' => $periodo,
                    'linea_codigo' => strtoupper($codigo),
                ],
                [
                    'empresa_id' => $empresaId ?? 0,
                    'valor' => 0,
                    'cantidad' => $cant,
                    'updated_at' => $ahora,
                    'created_at' => $ahora,
                ]
            );
        }
    }
}

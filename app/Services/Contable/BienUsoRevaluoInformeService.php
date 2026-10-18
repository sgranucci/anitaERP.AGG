<?php

declare(strict_types=1);

namespace App\Services\Contable;

use App\Models\Contable\BienUso;
use App\Models\Contable\BienUsoIndice;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Orquesta el informe de revalúo contable (Anita l-biemae.c).
 *
 * Aplica filtros de empresa, rango de bienes, rubro, centro de costo,
 * fechas de alta y bajas; invoca {@see BienUsoRevaluoProcesador} por bien.
 *
 * Módulo estándar ERP — sin gates Crown/AGG.
 */
final class BienUsoRevaluoInformeService
{
    public function __construct(
        private readonly BienUsoRevaluoProcesador $procesador,
    ) {
    }

    /**
     * @param  array{
     *     empresa_id?: int|null,
     *     empresa_ids?: list<int>|null,
     *     codigo_bien_desde?: int|null,
     *     codigo_bien_hasta?: int|null,
     *     bien_uso_rubro_id?: int|null,
     *     bien_uso_rubro_desde?: int|null,
     *     bien_uso_rubro_hasta?: int|null,
     *     centrocosto_id?: int|null,
     *     centrocosto_desde?: int|null,
     *     centrocosto_hasta?: int|null,
     *     fecha_alta_desde?: string|CarbonInterface|null,
     *     fecha_alta_hasta?: string|CarbonInterface|null,
     *     fecha_revaluo: string|CarbonInterface,
     *     indice_id?: int|null,
     *     periodo_base?: string|null,
     *     moneda_expresion_id?: int|null,
     *     fl_mon_origen?: bool,
     *     filtrar_bajas?: bool,
     *     persistir?: bool,
     * }  $filtros
     * @return array{
     *     filas: list<array<string, mixed>>,
     *     totales: array<string, float>,
     *     meta: array<string, mixed>
     * }
     */
    public function generar(array $filtros): array
    {
        $fechaRevaluo = Carbon::parse($filtros['fecha_revaluo']);
        $indiceId = isset($filtros['indice_id']) ? (int) $filtros['indice_id'] : null;
        $periodoBase = (string) ($filtros['periodo_base'] ?? $fechaRevaluo->format('Ym'));
        $periodoBase = preg_replace('/\D/', '', $periodoBase) ?: $fechaRevaluo->format('Ym');
        if (strlen($periodoBase) === 4) {
            // Anita a veces guarda YYMM en 4 dígitos; normalizar a YYYYMM del siglo de la fecha
            $periodoBase = substr($fechaRevaluo->format('Y'), 0, 2).$periodoBase;
        }
        $monedaExpresion = isset($filtros['moneda_expresion_id'])
            ? (int) $filtros['moneda_expresion_id']
            : null;
        $flMonOrigen = (bool) ($filtros['fl_mon_origen'] ?? true);
        $filtrarBajas = (bool) ($filtros['filtrar_bajas'] ?? false);
        $persistir = (bool) ($filtros['persistir'] ?? true);

        $indice = $indiceId ? BienUsoIndice::query()->find($indiceId) : null;

        $query = $this->queryBienes($filtros);
        /** @var Collection<int, BienUso> $bienes */
        $bienes = $query->get();

        $filas = [];
        $totales = $this->totalesVacios();

        foreach ($bienes as $bien) {
            if ($flMonOrigen && $monedaExpresion !== null) {
                $monBien = (int) ($bien->moneda_id ?? 0);
                if ($monBien > 0 && $monBien !== $monedaExpresion) {
                    continue;
                }
            }

            $fila = $this->procesador->procesar(
                $bien,
                $fechaRevaluo,
                $indiceId,
                $periodoBase,
                $monedaExpresion,
                $flMonOrigen,
                $filtrarBajas,
                $persistir,
            );
            if ($fila === null) {
                continue;
            }
            $filas[] = $fila;
            $this->acumular($totales, $fila);
        }

        return [
            'filas' => $filas,
            'totales' => $totales,
            'meta' => [
                'fecha_revaluo' => $fechaRevaluo->format('Y-m-d'),
                'periodo_base' => $periodoBase,
                'indice_id' => $indiceId,
                'indice_nombre' => $indice?->nombre,
                'indice_tipo_valor' => $indice?->tipo_valor,
                'moneda_expresion_id' => $monedaExpresion,
                'fl_mon_origen' => $flMonOrigen,
                'filtrar_bajas' => $filtrarBajas,
                'cantidad' => count($filas),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    private function queryBienes(array $filtros): Builder
    {
        $q = BienUso::query()
            ->with(['proveedor:id,nombre', 'rubro:id,codigo,nombre', 'centrocostos:id,codigo,nombre'])
            ->whereNotNull('codigo_bien');

        if (! empty($filtros['empresa_id'])) {
            $q->where('empresa_id', (int) $filtros['empresa_id']);
        } elseif (! empty($filtros['empresa_ids']) && is_array($filtros['empresa_ids'])) {
            $q->whereIn('empresa_id', array_map('intval', $filtros['empresa_ids']));
        }

        if (isset($filtros['codigo_bien_desde']) && $filtros['codigo_bien_desde'] !== null && $filtros['codigo_bien_desde'] !== '') {
            $q->where('codigo_bien', '>=', (int) $filtros['codigo_bien_desde']);
        }
        if (isset($filtros['codigo_bien_hasta']) && $filtros['codigo_bien_hasta'] !== null && $filtros['codigo_bien_hasta'] !== '') {
            $q->where('codigo_bien', '<=', (int) $filtros['codigo_bien_hasta']);
        }

        if (! empty($filtros['bien_uso_rubro_id'])) {
            $q->where('bien_uso_rubro_id', (int) $filtros['bien_uso_rubro_id']);
        }
        if (isset($filtros['bien_uso_rubro_desde']) && $filtros['bien_uso_rubro_desde'] !== null && $filtros['bien_uso_rubro_desde'] !== '') {
            $q->whereHas('rubro', fn (Builder $r) => $r->where('codigo', '>=', (int) $filtros['bien_uso_rubro_desde']));
        }
        if (isset($filtros['bien_uso_rubro_hasta']) && $filtros['bien_uso_rubro_hasta'] !== null && $filtros['bien_uso_rubro_hasta'] !== '') {
            $q->whereHas('rubro', fn (Builder $r) => $r->where('codigo', '<=', (int) $filtros['bien_uso_rubro_hasta']));
        }

        if (! empty($filtros['centrocosto_id'])) {
            $q->where('centrocosto_id', (int) $filtros['centrocosto_id']);
        }
        if (isset($filtros['centrocosto_desde']) && $filtros['centrocosto_desde'] !== null && $filtros['centrocosto_desde'] !== '') {
            $q->whereHas('centrocostos', fn (Builder $c) => $c->where('codigo', '>=', (int) $filtros['centrocosto_desde']));
        }
        if (isset($filtros['centrocosto_hasta']) && $filtros['centrocosto_hasta'] !== null && $filtros['centrocosto_hasta'] !== '') {
            $q->whereHas('centrocostos', fn (Builder $c) => $c->where('codigo', '<=', (int) $filtros['centrocosto_hasta']));
        }

        if (! empty($filtros['fecha_alta_desde'])) {
            $q->whereDate('fecha_alta', '>=', Carbon::parse($filtros['fecha_alta_desde'])->format('Y-m-d'));
        }
        if (! empty($filtros['fecha_alta_hasta'])) {
            $q->whereDate('fecha_alta', '<=', Carbon::parse($filtros['fecha_alta_hasta'])->format('Y-m-d'));
        }

        // Anita: baja con fecha >= hasta_fecha_baja (fin de mes del revalúo) se incluye;
        // el procesador anula bajas posteriores a fecha_revaluo.
        $q->orderBy('codigo_bien');

        return $q;
    }

    /** @return array<string, float> */
    private function totalesVacios(): array
    {
        return [
            'valor_origen_inicio' => 0.0,
            'aumentos' => 0.0,
            'bajas' => 0.0,
            'valor_origen_cierre' => 0.0,
            'amort_acum_inicio' => 0.0,
            'amort_acum_baja' => 0.0,
            'amort_ejercicio' => 0.0,
            'amort_mensual' => 0.0,
            'amort_cierre' => 0.0,
            'valor_residual_contable' => 0.0,
            'amort_acum_inicio_act' => 0.0,
            'amort_ejercicio_act' => 0.0,
            'valor_origen_act' => 0.0,
            'valor_residual_contable_act' => 0.0,
            'valor_actual' => 0.0,
            'incr_revaluo' => 0.0,
        ];
    }

    /** @param  array<string, float>  $totales */
    private function acumular(array &$totales, array $fila): void
    {
        foreach (array_keys($totales) as $key) {
            $totales[$key] += (float) ($fila[$key] ?? 0);
        }
    }
}

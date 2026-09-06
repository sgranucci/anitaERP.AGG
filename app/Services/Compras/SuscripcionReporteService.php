<?php

namespace App\Services\Compras;

use App\Models\Compras\Ordencompra;
use App\Models\Compras\Suscripcion_Cargo;
use App\Models\Compras\Suscripcion_Conciliacion;
use App\Support\Compras\SuscripcionPresupuestoSupport;
use App\Support\Compras\SuscripcionSupport;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Base de reportes del módulo: el gasto recurrente completo y qué parte de él tiene orden.
 *
 * La cobertura —proporción del gasto de tarjeta respaldado por una suscripción aprobada—
 * es el indicador que hace visible el problema que el módulo viene a resolver.
 */
class SuscripcionReporteService
{
    public function __construct(
        private SuscripcionService $suscripcionService,
    ) {}

    /**
     * @param  array<string, mixed>  $filtros
     * @return Collection<int, Ordencompra>
     */
    public function base(array $filtros): Collection
    {
        return $this->suscripcionService->listar($filtros);
    }

    /**
     * Indicadores de cabecera: los cuatro del mockup más la cobertura.
     *
     * @param  array<string, mixed>  $filtros
     * @return array<string, float|int|string>
     */
    public function indicadores(array $filtros): array
    {
        $filas = $this->base($filtros);
        $kpis = $this->suscripcionService->kpis($filas);
        $cobertura = $this->cobertura($filtros);

        return $kpis + [
            'anualizado' => round($filas
                ->filter(fn (Ordencompra $oc) => in_array(
                    SuscripcionSupport::estadoNegocio($oc),
                    [SuscripcionSupport::ESTADO_VIGENTE, SuscripcionSupport::ESTADO_DESVIO],
                    true
                ))
                ->sum(fn (Ordencompra $oc) => SuscripcionSupport::montoAnualizado(
                    (float) $oc->suscripcion_monto_periodo,
                    $oc->suscripcion_periodicidad
                )), 2),
            'cobertura_pct' => $cobertura['cobertura_pct'],
            'cobertura_periodo' => $cobertura['periodo'],
            'gasto_sin_orden' => $cobertura['monto_sin_orden'],
        ];
    }

    /**
     * Cobertura del último período conciliado: cuánto del gasto real tenía una OC detrás.
     *
     * @param  array<string, mixed>  $filtros
     * @return array{periodo: string, cobertura_pct: float, monto_total: float, monto_sin_orden: float}
     */
    public function cobertura(array $filtros): array
    {
        $empresaId = (int) ($filtros['empresa_id'] ?? 0);

        $conciliacion = Suscripcion_Conciliacion::query()
            ->when($empresaId, fn ($q) => $q->where('empresa_id', $empresaId))
            ->orderByDesc('periodo')
            ->first();

        if (! $conciliacion) {
            return ['periodo' => '—', 'cobertura_pct' => 0.0, 'monto_total' => 0.0, 'monto_sin_orden' => 0.0];
        }

        $cargos = Suscripcion_Cargo::query()
            ->where('suscripcion_conciliacion_id', $conciliacion->id)
            ->where('estado', '!=', Suscripcion_Cargo::ESTADO_DESCARTADO)
            ->get(['monto', 'ordencompra_id']);

        $total = (float) $cargos->sum('monto');
        $sinOrden = (float) $cargos->filter(fn ($c) => (int) $c->ordencompra_id <= 0)->sum('monto');

        return [
            'periodo' => (string) $conciliacion->periodo,
            'cobertura_pct' => $total > 0 ? round((($total - $sinOrden) / $total) * 100, 1) : 0.0,
            'monto_total' => round($total, 2),
            'monto_sin_orden' => round($sinOrden, 2),
        ];
    }

    /**
     * Gasto mensualizado por área, para ver dónde se concentra el recurrente.
     *
     * @param  array<string, mixed>  $filtros
     * @return Collection<int, array{area: string, cantidad: int, mensualizado: float}>
     */
    public function porArea(array $filtros): Collection
    {
        return $this->base($filtros)
            ->groupBy(fn (Ordencompra $oc) => $oc->suscripcion_area ?: 'Sin área')
            ->map(fn (Collection $g, string $area) => [
                'area' => $area,
                'cantidad' => $g->count(),
                'mensualizado' => round($g->sum(fn (Ordencompra $oc) => SuscripcionSupport::montoMensualizado(
                    (float) $oc->suscripcion_monto_periodo,
                    $oc->suscripcion_periodicidad
                )), 2),
            ])
            ->sortByDesc('mensualizado')
            ->values();
    }

    /**
     * Gasto recurrente comprometido contra el presupuesto aprobado, por centro de costo y cuenta.
     *
     * Es el corte que importa: no cuánto se gasta en suscripciones, sino qué proporción del
     * presupuesto anual de esa cuenta ya está tomada antes de que nadie pida nada más.
     *
     * @param  array<string, mixed>  $filtros
     * @return Collection<int, array<string, mixed>>
     */
    public function compromisoContraPresupuesto(array $filtros): Collection
    {
        $anio = (int) date('Y');

        return $this->base($filtros)
            ->filter(fn (Ordencompra $oc) => in_array(
                SuscripcionSupport::estadoNegocio($oc),
                [SuscripcionSupport::ESTADO_VIGENTE, SuscripcionSupport::ESTADO_DESVIO],
                true
            ))
            ->filter(fn (Ordencompra $oc) => (int) $oc->centrocosto_id > 0)
            ->groupBy(fn (Ordencompra $oc) => $oc->empresa_id.'|'.$oc->centrocosto_id.'|'.$oc->contrato_cuentacontable_id)
            ->map(function (Collection $g) use ($anio) {
                $primera = $g->first();
                $mensual = round($g->sum(fn (Ordencompra $oc) => SuscripcionSupport::montoMensualizado(
                    (float) $oc->suscripcion_monto_periodo,
                    $oc->suscripcion_periodicidad
                )), 2);

                $impacto = SuscripcionPresupuestoSupport::impacto(
                    (int) $primera->empresa_id,
                    (int) $primera->centrocosto_id,
                    (int) $primera->contrato_cuentacontable_id,
                    $mensual,
                    (int) ($primera->contrato_moneda_id ?: 0) ?: null,
                    $anio
                );

                return [
                    'centrocosto' => trim(
                        (optional($primera->centrocostos)->codigo ?? '').' '.(optional($primera->centrocostos)->nombre ?? '')
                    ) ?: 'Sin centro de costo',
                    'cuenta' => trim(
                        (optional($primera->contrato_cuentacontables)->codigo ?? '').' '.(optional($primera->contrato_cuentacontables)->nombre ?? '')
                    ) ?: 'Sin cuenta',
                    'cantidad' => $g->count(),
                    'mensualizado' => $mensual,
                    'anualizado' => round($mensual * 12, 2),
                    'presupuesto_anual' => $impacto['presupuesto_anual'] ?? null,
                    'pct' => $impacto['pct'] ?? null,
                    'disponible_mensual' => $impacto['disponible_mensual'] ?? null,
                    'moneda_coincide' => $impacto['moneda_coincide'] ?? true,
                ];
            })
            ->sortByDesc(fn (array $f) => $f['pct'] ?? -1)
            ->values();
    }

    /**
     * Suscripciones que vencen dentro de la ventana, para revisarlas antes de que se renueven solas.
     *
     * @return Collection<int, Ordencompra>
     */
    public function proximasARenovar(?int $empresaId, int $dias = 60): Collection
    {
        $hoy = Carbon::today();

        return Ordencompra::query()
            ->with(['proveedores', 'centrocostos', 'suscripcion_owners'])
            ->where('es_suscripcion', true)
            ->where('suscripcion_borrador', false)
            ->when($empresaId, fn ($q) => $q->where('empresa_id', $empresaId))
            ->whereNotNull('contrato_vigencia_hasta')
            ->whereBetween('contrato_vigencia_hasta', [$hoy->toDateString(), $hoy->copy()->addDays($dias)->toDateString()])
            ->orderBy('contrato_vigencia_hasta')
            ->get();
    }

    /**
     * Comercios que aparecen mes a mes sin ninguna suscripción detrás. Son las candidatas
     * a suscripción fantasma: gasto instalado que nadie autorizó.
     *
     * @return Collection<int, object>
     */
    public function gastoSinOrden(?int $empresaId, int $periodos = 6): Collection
    {
        $desde = Suscripcion_Conciliacion::query()
            ->when($empresaId, fn ($q) => $q->where('empresa_id', $empresaId))
            ->orderByDesc('periodo')
            ->limit($periodos)
            ->pluck('id');

        if ($desde->isEmpty()) {
            return collect();
        }

        return DB::table('suscripcion_cargo')
            ->whereIn('suscripcion_conciliacion_id', $desde)
            ->whereNull('ordencompra_id')
            ->where('estado', '!=', Suscripcion_Cargo::ESTADO_DESCARTADO)
            ->selectRaw('comercio_normalizado, COUNT(*) AS apariciones, SUM(monto) AS total, MAX(fecha) AS ultima')
            ->groupBy('comercio_normalizado')
            ->orderByDesc('total')
            ->get();
    }
}

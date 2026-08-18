<?php

namespace App\Services\Sueldos;

use App\Models\Sueldos\Empleado_Sueldos;
use App\Models\Sueldos\Liquidacion_Recibo_Sueldos;
use App\Models\Sueldos\Liquidacion_Sueldos;
use App\Support\Sueldos\LiquidacionAlcanceRecibo;
use App\Support\Sueldos\LiquidacionConfidencialSeguridadSupport;
use Illuminate\Support\Collection;

/**
 * Emisión multiempresa: recibos del mismo legajo en otras empresas
 * (mismo período + tipo de corrida), como MULTIEMPRESA en l-recibolargo.c.
 */
class ReciboMultiempresaService
{
    /**
     * Subconsulta: legajos con más de una empresa (activos o con egreso).
     */
    public function queryLegajosMultiempresa()
    {
        return Empleado_Sueldos::query()
            ->select('legajo')
            ->whereNotNull('legajo')
            ->groupBy('legajo')
            ->havingRaw('COUNT(DISTINCT empresa_id) > 1');
    }

    /**
     * Aplica alcance Anita al query de empleados de la corrida.
     */
    public function aplicarAlcanceAlQueryEmpleados($query, Liquidacion_Sueldos $liq): void
    {
        $alcance = LiquidacionAlcanceRecibo::normalizar($liq->alcance);
        if ($alcance === LiquidacionAlcanceRecibo::TODOS) {
            return;
        }

        $sub = $this->queryLegajosMultiempresa();
        if ($alcance === LiquidacionAlcanceRecibo::MULTIEMPRESA) {
            $query->whereIn('legajo', $sub);
        } elseif ($alcance === LiquidacionAlcanceRecibo::EMPRESA_ACTUAL) {
            $query->whereNotIn('legajo', $sub);
        }
    }

    /**
     * Recibos del mismo legajo en otras empresas (período + tipo).
     *
     * @return Collection<int, Liquidacion_Recibo_Sueldos>
     */
    public function recibosHermanos(Liquidacion_Recibo_Sueldos $recibo): Collection
    {
        $mapa = $this->hermanosPorRecibos(collect([$recibo]));

        return $mapa->get($recibo->id, collect());
    }

    /**
     * Precarga hermanos para una página/lote de recibos (una consulta).
     *
     * @param  Collection<int, Liquidacion_Recibo_Sueldos>  $recibos
     * @return Collection<int, Collection<int, Liquidacion_Recibo_Sueldos>> keyed by recibo_id
     */
    public function hermanosPorRecibos(Collection $recibos): Collection
    {
        if ($recibos->isEmpty()) {
            return collect();
        }

        // Asegura relación liquidacion aunque venga Support\Collection.
        if (method_exists($recibos, 'loadMissing')) {
            $recibos->loadMissing('liquidacion');
        } else {
            $recibos->each(function (Liquidacion_Recibo_Sueldos $r) {
                $r->loadMissing('liquidacion');
            });
        }

        $legajos = $recibos->pluck('legajo')->filter()->unique()->values()->all();
        if ($legajos === []) {
            return collect();
        }

        $periodos = $recibos->map(fn ($r) => (string) optional($r->liquidacion)->periodo)->unique()->filter()->values();
        $tipos = $recibos->map(fn ($r) => (string) optional($r->liquidacion)->tipo)->unique()->filter()->values();

        $query = Liquidacion_Recibo_Sueldos::query()
            ->with(['liquidacion.empresa', 'empleado', 'detalles'])
            ->whereIn('legajo', $legajos)
            ->whereNotIn('id', $recibos->pluck('id')->all())
            ->whereHas('liquidacion', function ($q) use ($periodos, $tipos) {
                $q->whereIn('periodo', $periodos->all())
                    ->whereIn('tipo', $tipos->all())
                    ->where('estado', '!=', 'anulada');
                LiquidacionConfidencialSeguridadSupport::aplicarFiltroEmpresaQuery($q, 'empresa_id');
            });

        LiquidacionConfidencialSeguridadSupport::aplicarVisibilidadRecibos($query);

        $candidatos = $query->get();

        $out = collect();
        foreach ($recibos as $base) {
            $liq = $base->liquidacion;
            if (! $liq || ! $base->legajo) {
                $out->put($base->id, collect());

                continue;
            }
            $hermanos = $candidatos
                ->filter(function (Liquidacion_Recibo_Sueldos $h) use ($base, $liq) {
                    $hl = $h->liquidacion;
                    if (! $hl) {
                        return false;
                    }

                    return (int) $h->legajo === (int) $base->legajo
                        && (string) $hl->periodo === (string) $liq->periodo
                        && (string) $hl->tipo === (string) $liq->tipo
                        && (int) $hl->empresa_id !== (int) $liq->empresa_id;
                })
                ->sortBy([
                    fn ($h) => (int) optional($h->liquidacion)->empresa_id,
                    fn ($h) => (int) optional($h->liquidacion)->numero,
                    fn ($h) => (int) $h->numero_recibo,
                    fn ($h) => (int) $h->id,
                ])
                ->values();
            $out->put($base->id, $hermanos);
        }

        return $out;
    }

    /**
     * ¿Debe emitirse en modo multiempresa? Corrida + override de request.
     */
    public function emitirMultiempresa(Liquidacion_Sueldos $liq, ?bool $overrideRequest = null): bool
    {
        if ($overrideRequest !== null) {
            return $overrideRequest;
        }

        return LiquidacionAlcanceRecibo::esMultiempresa($liq->alcance);
    }

    /**
     * Lista ordenada: recibo principal + hermanos (si aplica).
     *
     * @return Collection<int, Liquidacion_Recibo_Sueldos>
     */
    public function cadenaEmision(Liquidacion_Recibo_Sueldos $recibo, bool $multiempresa): Collection
    {
        $cadena = collect([$recibo]);
        if (! $multiempresa) {
            return $cadena;
        }

        return $cadena->merge($this->recibosHermanos($recibo))->values();
    }

    /**
     * Cadenas para un lote, con deduplicación global por recibo_id.
     *
     * @param  Collection<int, Liquidacion_Recibo_Sueldos>  $recibos
     * @return Collection<int, Collection<int, Liquidacion_Recibo_Sueldos>>
     */
    public function cadenasPorRecibos(Collection $recibos, bool $multiempresa): Collection
    {
        $hermanos = $multiempresa ? $this->hermanosPorRecibos($recibos) : collect();
        $emitidos = [];
        $cadenas = collect();

        $ordenados = $recibos->sortBy([
            fn ($r) => (int) $r->numero_recibo,
            fn ($r) => (int) $r->id,
        ])->values();

        foreach ($ordenados as $base) {
            $cadena = collect([$base]);
            if ($multiempresa) {
                $cadena = $cadena->merge($hermanos->get($base->id, collect()));
            }
            $cadena = $cadena->filter(function ($r) use (&$emitidos) {
                if (isset($emitidos[$r->id])) {
                    return false;
                }
                $emitidos[$r->id] = true;

                return true;
            })->values();

            if ($cadena->isNotEmpty()) {
                $cadenas->put($base->id, $cadena);
            }
        }

        return $cadenas;
    }
}

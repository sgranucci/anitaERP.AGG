<?php

namespace App\Services\Sueldos;

use App\Models\Sueldos\Empleado_Sueldos;
use App\Models\Sueldos\Liquidacion_Recibo_Sueldos;
use App\Models\Sueldos\Liquidacion_Sueldos;
use App\Support\Sueldos\LiquidacionAlcanceRecibo;
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
        $recibo->loadMissing('liquidacion');
        $liq = $recibo->liquidacion;
        if (! $liq || ! $recibo->legajo) {
            return collect();
        }

        return Liquidacion_Recibo_Sueldos::query()
            ->with(['liquidacion.empresa', 'detalles'])
            ->where('legajo', $recibo->legajo)
            ->where('id', '!=', $recibo->id)
            ->whereHas('liquidacion', function ($q) use ($liq) {
                $q->where('periodo', $liq->periodo)
                    ->where('tipo', $liq->tipo)
                    ->where('empresa_id', '!=', $liq->empresa_id)
                    ->where('estado', '!=', 'anulada');
            })
            ->orderBy('id')
            ->get();
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

}

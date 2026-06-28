<?php

namespace App\Support\Configuracion\OcArbolTriggerEvaluators;

use App\Models\Compras\Ordencompra;
use App\Models\Compras\Ordencompra_Articulo;
use App\Models\Configuracion\Arbolaprobacion_OcTrigger;
use App\Support\Compras\OrdencompraEstados;
use App\Support\Configuracion\OcArbolTriggerCatalog;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Dispara cuando alguna línea con CAPEX supera el monto presupuestado del mes (capex_partida_monto).
 */
final class CapexMesExcedidoEvaluator implements OcArbolTriggerEvaluatorInterface
{
    /** @var list<string> */
    private const ESTADOS_OC_EXCLUIDOS = [
        OrdencompraEstados::SUSPENDIDA,
        OrdencompraEstados::CERRADA,
    ];

    public function codigo(): string
    {
        return OcArbolTriggerCatalog::EVALUADOR_CAPEX_MES_EXCEDIDO;
    }

    public function aplica(Ordencompra $ordencompra, Arbolaprobacion_OcTrigger $trigger): bool
    {
        $ordencompra->loadMissing('ordencompra_articulos');
        $periodo = Carbon::parse($ordencompra->fecha)->format('Y-m');

        foreach ($ordencompra->ordencompra_articulos as $linea) {
            if (empty($linea->capex_id) || (float) $linea->cantidad <= 0) {
                continue;
            }
            $capexId = (int) $linea->capex_id;
            $asignado = (float) DB::table('capex_partida_monto')
                ->where('capex_id', $capexId)
                ->where('periodo', $periodo)
                ->sum('monto');

            if ($asignado <= 0) {
                continue;
            }

            $comprometido = $this->montoComprometidoCapexMes($capexId, $periodo, (int) $ordencompra->id);
            $montoLinea = $this->montoLinea($linea);

            if ($comprometido + $montoLinea > $asignado + 0.0001) {
                return true;
            }
        }

        return false;
    }

    private function montoLinea(Ordencompra_Articulo $linea): float
    {
        $cantidad = (float) ($linea->cantidad ?? 0);
        $precio = (float) ($linea->precio ?? 0);
        $descuento = (float) ($linea->descuento ?? 0);
        $cotizacion = (float) ($linea->cotizacion ?? 1);
        if ($cotizacion <= 0) {
            $cotizacion = 1.0;
        }

        $bruto = $cantidad * $precio * (1 - $descuento / 100);

        return round($bruto * $cotizacion, 4);
    }

    private function montoComprometidoCapexMes(int $capexId, string $periodoYmd, int $excluirOrdencompraId): float
    {
        $inicio = $periodoYmd.'-01';
        $fin = Carbon::parse($inicio)->endOfMonth()->format('Y-m-d');

        $q = DB::table('ordencompra_articulo as oca')
            ->join('ordencompra as oc', 'oc.id', '=', 'oca.ordencompra_id')
            ->where('oca.capex_id', $capexId)
            ->whereBetween('oc.fecha', [$inicio, $fin])
            ->whereNotIn('oc.estadoordencompra', self::ESTADOS_OC_EXCLUIDOS);

        if ($excluirOrdencompraId > 0) {
            $q->where('oc.id', '!=', $excluirOrdencompraId);
        }

        $filas = $q->select([
            'oca.cantidad', 'oca.precio', 'oca.descuento', 'oca.cotizacion',
        ])->get();

        $total = 0.0;
        foreach ($filas as $f) {
            $cantidad = (float) ($f->cantidad ?? 0);
            $precio = (float) ($f->precio ?? 0);
            $descuento = (float) ($f->descuento ?? 0);
            $cotizacion = (float) ($f->cotizacion ?? 1);
            if ($cotizacion <= 0) {
                $cotizacion = 1.0;
            }
            $total += $cantidad * $precio * (1 - $descuento / 100) * $cotizacion;
        }

        return round($total, 4);
    }
}

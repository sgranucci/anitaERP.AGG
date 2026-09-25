<?php

namespace App\Support\Stock;

use App\Models\Stock\Articulo_Movimiento;
use App\Models\Stock\Depmae;
use App\Models\Ventas\Ordentrabajo;
use App\Repositories\Ventas\Ordentrabajo_Combinacion_TalleRepositoryInterface;
use App\Repositories\Ventas\Pedido_CombinacionRepositoryInterface;
use App\Services\Stock\Articulo_MovimientoService;
use App\Support\Configuracion\EntornoEmpresaSupport;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Ferli: al terminar (tarea 32) solo las OT del cliente STOCK entran a stock (ALTAP).
 * OT de clientes comerciales no van al stock.
 * Se identifica por ordentrabajo_id; lote=0 (los lotes son importados).
 * El Excel Stock por OT pasa de EN PRODUCCION a ENTREGA INMEDIATA.
 */
final class OtTerminadaAltaStockFerliSupport
{
    public function __construct(
        private Articulo_MovimientoService $articuloMovimientoService,
        private Ordentrabajo_Combinacion_TalleRepositoryInterface $ordentrabajoCombinacionTalleRepository,
        private Pedido_CombinacionRepositoryInterface $pedidoCombinacionRepository,
    ) {
    }

    public function alTerminar(Ordentrabajo $ordentrabajo): void
    {
        if (! EntornoEmpresaSupport::esFerli()) {
            return;
        }

        if ($this->yaTieneMovimientoStock($ordentrabajo)) {
            return;
        }

        $octs = $this->ordentrabajoCombinacionTalleRepository->findPorOrdenTrabajoId($ordentrabajo->id);
        if (! $this->esOtClienteStock($octs)) {
            Log::notice(
                'Ferli OT '.$ordentrabajo->codigo.' terminada sin ALTAP (no es cliente STOCK)'
            );

            return;
        }

        $tallesPorPedidoCombinacion = [];
        foreach ($octs as $oct) {
            $pct = $oct->pedido_combinacion_talles;
            if (! $pct) {
                continue;
            }
            $pcId = (int) $pct->pedido_combinacion_id;
            if ($pcId <= 0) {
                continue;
            }
            $tallesPorPedidoCombinacion[$pcId][] = $pct;
        }

        if ($tallesPorPedidoCombinacion === []) {
            throw new RuntimeException(
                'OT '.$ordentrabajo->codigo.' terminada sin renglones para alta de stock.'
            );
        }

        $depositoId = $this->depositoFabricaId();
        $ahora = Carbon::now();
        $tipoAlta = (int) config('consprod.TIPOTRANSACCION_ALTA_PRODUCCION', 3);

        foreach ($tallesPorPedidoCombinacion as $pedidoCombinacionId => $talles) {
            $pedidoCombinacion = $this->pedidoCombinacionRepository->find($pedidoCombinacionId);
            if (! $pedidoCombinacion) {
                throw new RuntimeException(
                    'OT '.$ordentrabajo->codigo.' sin pedido combinación '.$pedidoCombinacionId.' para alta de stock.'
                );
            }

            $cantidad = 0.0;
            foreach ($talles as $talle) {
                $cantidad += (float) $talle->cantidad;
            }
            if ($cantidad == 0.0) {
                $cantidad = (float) $pedidoCombinacion->cantidad;
            }

            $dataArticuloMovimiento = [
                'fecha' => $ahora,
                'fechajornada' => $ahora,
                'tipotransaccion_id' => $tipoAlta,
                'pedido_combinacion_id' => $pedidoCombinacion->id,
                'ordentrabajo_id' => $ordentrabajo->id,
                'lote' => 0,
                'articulo_id' => $pedidoCombinacion->articulo_id,
                'combinacion_id' => $pedidoCombinacion->combinacion_id,
                'modulo_id' => $pedidoCombinacion->modulo_id,
                'concepto' => 'Alta de produccion',
                'cantidad' => $cantidad,
                'precio' => $pedidoCombinacion->precio,
                'costo' => 0,
                'descuento' => $pedidoCombinacion->descuento,
                'descuentointegrado' => $pedidoCombinacion->descuentointegrado,
                'moneda_id' => $pedidoCombinacion->moneda_id,
                'incluyeimpuesto' => $pedidoCombinacion->incluyeimpuesto,
                'listaprecio_id' => $pedidoCombinacion->listaprecio_id,
                'deposito_id' => $depositoId,
            ];

            $this->articuloMovimientoService->guardaArticuloMovimiento(
                'create',
                $dataArticuloMovimiento,
                $talles
            );
        }

        Log::notice('Ferli ALTAP al terminar OT '.$ordentrabajo->id.' codigo '.$ordentrabajo->codigo);
    }

    private function yaTieneMovimientoStock(Ordentrabajo $ordentrabajo): bool
    {
        return Articulo_Movimiento::query()
            ->where('ordentrabajo_id', $ordentrabajo->id)
            ->exists();
    }

    /**
     * @param  iterable<int, object>  $octs
     */
    private function esOtClienteStock(iterable $octs): bool
    {
        $vioCliente = false;
        foreach ($octs as $oct) {
            $clienteId = (int) ($oct->cliente_id ?? 0);
            if ($clienteId <= 0) {
                continue;
            }
            $vioCliente = true;
            if (! ReporteStockOtSituacionSupport::esClienteStock($clienteId)) {
                return false;
            }
        }

        return $vioCliente;
    }

    private function depositoFabricaId(): int
    {
        $dep = Depmae::query()
            ->where('codigo', '1')
            ->orderBy('id')
            ->first();

        return $dep ? (int) $dep->id : 1;
    }
}

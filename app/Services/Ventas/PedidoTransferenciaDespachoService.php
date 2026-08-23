<?php

namespace App\Services\Ventas;

use App\Models\Stock\Depmae;
use App\Models\Ventas\Pedido;
use App\Models\Ventas\Pedido_Articulo;
use App\Repositories\Stock\Tipotransaccion_StockRepositoryInterface;
use App\Services\Stock\TransferenciaMercaderiaService;
use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\Ventas\ClienteDespachoSupport;
use App\Support\Ventas\PedidoDespachoAnitaCierreSupport;
use App\Support\Ventas\PedidoEstadoErpSupport;
use App\Support\Ventas\TransporteDepositoSupport;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class PedidoTransferenciaDespachoService
{
    public function __construct(
        private TransferenciaMercaderiaService $transferenciaService,
        private Tipotransaccion_StockRepositoryInterface $tipotransaccionStockRepository,
    ) {
    }

    /**
     * @return array{ok: bool, mensaje: string, transferencia_id?: int, requiere_aprobacion?: bool}
     */
    public function transferir(int $pedidoId): array
    {
        if (! EntornoEmpresaSupport::esElBierzo()) {
            return ['ok' => false, 'mensaje' => 'La transferencia al despacho solo aplica en El Bierzo.'];
        }
        if (! ClienteDespachoSupport::circuitoHabilitado()) {
            return ['ok' => false, 'mensaje' => 'Falta configurar CLIENTE_DESPACHO_ID.'];
        }

        $lock = Cache::lock('ventas:pedido:transferir-despacho:'.$pedidoId, 60);
        if (! $lock->get()) {
            return ['ok' => false, 'mensaje' => 'Ya hay una transferencia en curso de este pedido. Espere a que termine.'];
        }

        try {
            return $this->transferirConLock($pedidoId);
        } finally {
            $lock->release();
        }
    }

    /**
     * @return array{ok: bool, mensaje: string, transferencia_id?: int, requiere_aprobacion?: bool}
     */
    private function transferirConLock(int $pedidoId): array
    {
        $pedido = Pedido::query()->with('pedido_articulos')->find($pedidoId);
        if ($pedido === null) {
            return ['ok' => false, 'mensaje' => 'Pedido inexistente.'];
        }
        if (! ClienteDespachoSupport::es((int) $pedido->cliente_id)) {
            return ['ok' => false, 'mensaje' => 'Solo el pedido del cliente DESPACHO se transfiere. El resto se factura.'];
        }
        if (PedidoEstadoErpSupport::esTransferido($pedido->estado ?? null, $pedido->estadopedido ?? null)
            || (int) ($pedido->transferencia_mercaderia_id ?? 0) > 0) {
            return ['ok' => false, 'mensaje' => 'El pedido ya fue transferido al despacho.'];
        }

        $estado = PedidoEstadoErpSupport::normalizarEstadoCabecera(
            $pedido->estado ?? null,
            $pedido->estadopedido ?? null
        );
        if ($estado === PedidoEstadoErpSupport::FACTURADO) {
            return ['ok' => false, 'mensaje' => 'El pedido ya está facturado.'];
        }
        if ($estado === PedidoEstadoErpSupport::ANULADO) {
            return ['ok' => false, 'mensaje' => 'El pedido está anulado.'];
        }

        $transporteId = (int) ($pedido->transporte_id ?? 0);
        if (! TransporteDepositoSupport::tieneDepositoAsignado($transporteId)) {
            return ['ok' => false, 'mensaje' => 'El reparto no tiene depósito de despacho asignado.'];
        }

        $empresaId = $this->empresaIdPedido($pedido);
        $destinoId = TransporteDepositoSupport::depositoId($transporteId, $empresaId);
        $origenId = TransporteDepositoSupport::depositoId(0, $empresaId);
        if ($destinoId <= 0 || $origenId <= 0 || $destinoId === $origenId) {
            return ['ok' => false, 'mensaje' => 'No se pudo resolver origen y destino de la transferencia (reparto sin depósito distinto al default de ventas).'];
        }

        $lineas = $this->lineasDesdePedido($pedido);
        if ($lineas === []) {
            return ['ok' => false, 'mensaje' => 'No hay ítems con pesada o kilos para transferir. Guarde la pesada antes de transferir.'];
        }

        $tipoTransaccionId = $this->resolverTipoTransaccionId();

        $result = $this->transferenciaService->grabarTransferencia([
            'deposito_salida_id' => $origenId,
            'deposito_entrada_id' => $destinoId,
            'tipotransaccion_stock_id' => $tipoTransaccionId,
            'empresa_id' => $empresaId,
            'observacion' => 'Pedido DESPACHO #'.($pedido->codigo ?? $pedido->id),
        ], $lineas);

        if (! ($result['ok'] ?? false)) {
            return [
                'ok' => false,
                'mensaje' => (string) ($result['mensaje'] ?? 'Error al generar la transferencia de stock.'),
            ];
        }

        $transferenciaId = (int) ($result['transferencia_id'] ?? 0);
        $cabecera = PedidoEstadoErpSupport::cabeceraTransferido();

        DB::transaction(function () use ($pedido, $transferenciaId, $cabecera, $lineas): void {
            $pedido->update([
                'transferencia_mercaderia_id' => $transferenciaId > 0 ? $transferenciaId : null,
                'estado' => $cabecera['estado'],
                'estadopedido' => $cabecera['estadopedido'],
            ]);

            $articulosTransferidos = [];
            foreach ($lineas as $linea) {
                $articulosTransferidos[(int) $linea['articulo_id']] = true;
            }

            $items = Pedido_Articulo::query()->where('pedido_id', (int) $pedido->id)->get();
            foreach ($items as $item) {
                if (! isset($articulosTransferidos[(int) $item->articulo_id])) {
                    continue;
                }
                if (! PedidoEstadoErpSupport::esItemPendienteFacturable($item->estado ?? null)) {
                    continue;
                }
                $item->update(['estado' => PedidoEstadoErpSupport::ENTREGADO]);
            }
        });

        $codigoTm = (string) ($result['codigo'] ?? '');
        $mensaje = $codigoTm !== ''
            ? 'Transferencia '.$codigoTm.' generada. El pedido quedó Transferido.'
            : 'Transferencia al despacho generada. El pedido quedó Transferido.';
        if (! empty($result['requiere_aprobacion'])) {
            $mensaje .= ' La TM quedó pendiente de aprobación según el tipo de comprobante.';
        }

        $cierreAnita = PedidoDespachoAnitaCierreSupport::cerrarSiExiste($pedido->fresh() ?? $pedido);
        if (! ($cierreAnita['ok'] ?? false) && ($cierreAnita['mensaje'] ?? '') !== '') {
            $mensaje .= ' '.$cierreAnita['mensaje'];
        } elseif (! empty($cierreAnita['cerrado'])) {
            $mensaje .= ' El pedido se cerró en Anita (el circuito vive solo en ERP).';
        }

        return [
            'ok' => true,
            'mensaje' => $mensaje,
            'transferencia_id' => $transferenciaId,
            'requiere_aprobacion' => (bool) ($result['requiere_aprobacion'] ?? false),
        ];
    }

    private function empresaIdPedido(Pedido $pedido): int
    {
        $empresaId = (int) session('empresa_id');
        if ($empresaId <= 0) {
            $empresaId = (int) config('cliente.EMPRESA_DEFAULT_ID', 1);
        }

        $transporteId = (int) ($pedido->transporte_id ?? 0);
        $destinoId = TransporteDepositoSupport::depositoId($transporteId, $empresaId);
        $depEmpresa = (int) Depmae::query()->whereKey($destinoId)->value('empresa_id');

        return $depEmpresa > 0 ? $depEmpresa : $empresaId;
    }

    /**
     * @return list<array{articulo_id: int, cantidad: float, caja: float, pieza: float}>
     */
    private function lineasDesdePedido(Pedido $pedido): array
    {
        $map = [];
        foreach ($pedido->pedido_articulos as $item) {
            if (! PedidoEstadoErpSupport::esItemPendienteFacturable($item->estado ?? null)) {
                continue;
            }
            $articuloId = (int) ($item->articulo_id ?? 0);
            $pesada = (float) ($item->pesada ?? 0);
            $kilo = (float) ($item->kilo ?? 0);
            $cantidad = $pesada > 0 ? $pesada : $kilo;
            if ($articuloId <= 0 || $cantidad <= 0) {
                continue;
            }
            if (! isset($map[$articuloId])) {
                $map[$articuloId] = ['cantidad' => 0.0, 'caja' => 0.0, 'pieza' => 0.0];
            }
            $map[$articuloId]['cantidad'] += $cantidad;
            $map[$articuloId]['caja'] += (float) ($item->caja ?? 0);
            $map[$articuloId]['pieza'] += (float) ($item->pieza ?? 0);
        }

        $salida = [];
        foreach ($map as $articuloId => $totales) {
            $salida[] = [
                'articulo_id' => (int) $articuloId,
                'cantidad' => (float) $totales['cantidad'],
                'caja' => (float) $totales['caja'],
                'pieza' => (float) $totales['pieza'],
            ];
        }

        return $salida;
    }

    private function resolverTipoTransaccionId(): int
    {
        $configId = config('stock.transferencia_despacho_tipotransaccion_stock_id');
        if (! empty($configId)) {
            return (int) $configId;
        }

        $abrev = (string) config('stock.transferencia_despacho_tipotransaccion_abreviatura', 'TRA');
        try {
            return $this->tipotransaccionStockRepository->findIdPorAbreviatura($abrev);
        } catch (\Throwable $e) {
            throw new \RuntimeException(
                'No se pudo resolver el tipo de transacción de stock ("'.$abrev.'"). Configure STOCK_TRANSFERENCIA_DESPACHO_TIPOTRANSACCION_ID.'
            );
        }
    }
}

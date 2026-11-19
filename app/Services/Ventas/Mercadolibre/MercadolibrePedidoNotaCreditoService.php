<?php

namespace App\Services\Ventas\Mercadolibre;

use App\Models\Ventas\FacturacionLocalEmision;
use App\Models\Ventas\LocalVenta;
use App\Models\Ventas\MercadolibrePedido;
use App\Models\Ventas\Venta;
use App\Services\Ventas\FacturacionLocal\FacturacionLocalNotaCreditoService;
use App\Support\Ventas\FacturacionLocal\FacturacionLocalEmisionVinculoSupport;
use App\Support\Ventas\Mercadolibre\MercadolibreDefaultsSupport;
use App\Support\Ventas\Mercadolibre\MercadolibrePedidoEstadoSupport;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * NC automática cuando una venta MeLi ya facturada se cancela / reclama.
 */
final class MercadolibrePedidoNotaCreditoService
{
    public function __construct(
        private readonly FacturacionLocalNotaCreditoService $notaCreditoService,
    ) {
    }

    /**
     * @return array{ok:bool,skipped?:bool,error?:string,venta_nc_id?:int,mensaje?:string}
     */
    public function emitirSiCorresponde(MercadolibrePedido $pedido): array
    {
        $pedido->refresh();
        if ((int) ($pedido->venta_id ?? 0) <= 0) {
            return ['ok' => true, 'skipped' => true, 'mensaje' => 'Sin factura ERP: no hay NC.'];
        }

        $ventaId = (int) $pedido->venta_id;
        $existente = FacturacionLocalNotaCreditoService::notaCreditoExistenteParaFactura($ventaId);
        if ($existente) {
            $this->marcarGrupoCancelado($pedido, $existente);

            return ['ok' => true, 'skipped' => true, 'venta_nc_id' => $existente, 'mensaje' => 'NC ya existía.'];
        }

        $emision = FacturacionLocalEmision::query()->where('venta_id', $ventaId)->first();
        if ($emision && (int) ($emision->venta_nc_id ?? 0) > 0) {
            $this->marcarGrupoCancelado($pedido, (int) $emision->venta_nc_id);

            return ['ok' => true, 'skipped' => true, 'venta_nc_id' => (int) $emision->venta_nc_id];
        }

        $cuentaId = MercadolibreDefaultsSupport::cuentacajaId();
        if (! $cuentaId) {
            return ['ok' => false, 'error' => 'No está la cuenta de caja 608 para la NC MeLi.'];
        }

        $venta = Venta::query()->find($ventaId);
        if (! $venta) {
            return ['ok' => false, 'error' => 'Venta #'.$ventaId.' inexistente.'];
        }

        FacturacionLocalEmisionVinculoSupport::vincularVenta($ventaId, 'mercadolibre');

        $localId = $this->localVentaId($pedido);
        if ($localId <= 0) {
            return ['ok' => false, 'error' => 'No se resolvió local Mercadolibre / Full para la NC.'];
        }

        $motivoId = (int) config('mercadolibre.motivo_devolucion_id', 3);
        $monto = abs((float) $venta->total);
        $leyenda = 'NC MeLi cancelación/reclamo — '.(string) $venta->leyenda;

        try {
            $resultado = $this->notaCreditoService->generarDesdeFactura(
                $ventaId,
                null,
                mb_substr($leyenda, 0, 200),
                [
                    'local_venta_id' => $localId,
                    'motivo_devolucion_id' => $motivoId,
                    'medios_forzados' => [[
                        'cuentacaja_id' => $cuentaId,
                        'moneda_id' => 1,
                        'monto' => $monto,
                    ]],
                    'omitir_turno' => true,
                    'registrar_historial' => false,
                ]
            );
        } catch (Throwable $e) {
            Log::error('mercadolibre.nc.exception', [
                'order_id' => $pedido->order_id,
                'venta_id' => $ventaId,
                'error' => $e->getMessage(),
            ]);

            return ['ok' => false, 'error' => $e->getMessage()];
        }

        if (! ($resultado['ok'] ?? false)) {
            Log::warning('mercadolibre.nc.fail', [
                'order_id' => $pedido->order_id,
                'venta_id' => $ventaId,
                'error' => $resultado['error'] ?? 'NC falló',
            ]);

            return ['ok' => false, 'error' => $resultado['error'] ?? 'No se pudo emitir la NC'];
        }

        $ncId = (int) ($resultado['venta_id'] ?? 0);
        $this->marcarGrupoCancelado($pedido, $ncId);

        return [
            'ok' => true,
            'venta_nc_id' => $ncId,
            'mensaje' => $resultado['mensaje'] ?? 'NC emitida',
        ];
    }

    private function localVentaId(MercadolibrePedido $pedido): int
    {
        $codigo = $pedido->es_full ? '6' : '5';
        $id = (int) (LocalVenta::query()->where('codigo', $codigo)->value('id') ?? 0);
        if ($id > 0) {
            return $id;
        }

        // Fallback por nombre
        $nombre = $pedido->es_full ? 'Mercado Libre Full' : 'Mercadolibre';

        return (int) (LocalVenta::query()->where('nombre', $nombre)->value('id') ?? 0);
    }

    private function marcarGrupoCancelado(MercadolibrePedido $pedido, int $ventaNcId = 0): void
    {
        $q = MercadolibrePedido::query()->where('seller_id', $pedido->seller_id);
        if ($pedido->pack_id) {
            $q->where('pack_id', $pedido->pack_id);
        } else {
            $q->whereKey($pedido->id);
        }

        $msg = 'Cancelado/reclamo MeLi';
        if ($ventaNcId > 0) {
            $msg .= ' — NC venta #'.$ventaNcId;
        }

        $q->update([
            'estado_erp' => MercadolibrePedidoEstadoSupport::CANCELADO,
            'error_mensaje' => mb_substr($msg, 0, 1000),
            'updated_at' => now(),
        ]);
    }
}

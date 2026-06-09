<?php

namespace App\Services\Configuracion\Handlers;

use App\Contracts\Configuracion\ModuloAvisoHandlerInterface;
use App\Models\Ventas\Pedido;
use App\Services\Ventas\PedidoPdfService;
use App\Support\Stock\ArticuloEnviaAlarmaSupport;

class VentasPedidoProduccionAvisoHandler implements ModuloAvisoHandlerInterface
{
    public function __construct(
        private readonly PedidoPdfService $pdfService,
    ) {
    }

    public function contextoFiltro(int $entityId): array
    {
        return [
            'empresa_id' => 1,
            'centrocosto_id' => null,
        ];
    }

    public function placeholders(int $entityId): array
    {
        $pedido = $this->cargarPedido($entityId);

        return [
            'numero' => (string) ($pedido->codigo ?? $entityId),
            'cliente' => (string) (optional($pedido->clientes)->nombre ?? '—'),
            'vendedor' => (string) (optional($pedido->vendedores)->nombre ?? '—'),
            'fecha' => $pedido->fecha ? $pedido->fecha->format('d/m/Y') : '—',
            'fecha_entrega' => $pedido->fechaentrega ? date('d/m/Y', strtotime((string) $pedido->fechaentrega)) : '—',
            'estado' => (string) ($pedido->estadopedido ?? $pedido->estado ?? '—'),
            'usuario' => (string) (optional($pedido->usuarios)->nombre ?? '—'),
            'articulos_alarma' => $this->textoArticulosAlarma($pedido),
        ];
    }

    public function linkConsulta(int $entityId): ?string
    {
        return url('ventas/pedido/'.$entityId.'/editar');
    }

    public function generarPdf(int $entityId): ?array
    {
        try {
            return $this->pdfService->generarBytes($entityId);
        } catch (\Throwable) {
            return null;
        }
    }

    private function cargarPedido(int $entityId): Pedido
    {
        return Pedido::query()
            ->with([
                'clientes:id,nombre',
                'vendedores:id,nombre',
                'usuarios:id,nombre',
                'pedido_articulos.articulos:id,sku,descripcion,enviaalarma,salaproduccion_id',
                'pedido_articulos.articulos.salaproducciones:id,nombre',
            ])
            ->findOrFail($entityId);
    }

    private function textoArticulosAlarma(Pedido $pedido): string
    {
        $lineas = [];
        foreach ($pedido->pedido_articulos as $item) {
            $articulo = $item->articulos;
            if (! $articulo || ! ArticuloEnviaAlarmaSupport::enviaAlarmaActivo($articulo->enviaalarma)) {
                continue;
            }

            $cantidad = trim(implode(' ', array_filter([
                ((float) $item->caja) > 0 ? 'Cajas: '.$item->caja : null,
                ((float) $item->pieza) > 0 ? 'Piezas: '.$item->pieza : null,
                ((float) $item->kilo) > 0 ? 'Kilos: '.$item->kilo : null,
            ])));

            $sala = (string) (optional($articulo->salaproducciones)->nombre ?? '');
            $detalle = trim($articulo->sku.' — '.$articulo->descripcion.($cantidad !== '' ? ' ('.$cantidad.')' : ''));
            if ($sala !== '') {
                $detalle .= ' [Sala: '.$sala.']';
            }

            $lineas[] = $detalle;
        }

        return $lineas !== [] ? implode("\n", $lineas) : '—';
    }
}

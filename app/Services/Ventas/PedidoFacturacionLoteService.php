<?php

namespace App\Services\Ventas;

use App\Models\Ventas\Venta;
use App\Queries\Ventas\PedidoQueryInterface;
use App\Support\Ventas\PedidoEstadoErpSupport;
use App\Support\Ventas\PedidoFacturaAnitaArchivosSupport;
use App\Support\Ventas\PedidoListadoSupport;

final class PedidoFacturacionLoteService
{
    public function __construct(
        private FacturacionService $facturacionService,
        private PedidoQueryInterface $pedidoQuery,
    ) {}

    /**
     * @param  array<string, mixed>  $filtros
     * @return array{transporte_id: int, etiqueta: string, pedidos: list<array<string, mixed>>, totales: array{caja: float, unidad: float, kilo: float, pesada: float}}
     */
    public function contexto(array $filtros, int $transporteId): array
    {
        $pedidos = [];
        $etiqueta = '';
        $totalCaja = 0.0;
        $totalUnidad = 0.0;
        $totalKilo = 0.0;
        $totalPesada = 0.0;

        foreach ($this->pedidoQuery->pedidosIndexPorReparto($filtros, $transporteId) as $pedido) {
            if ($etiqueta === '') {
                $etiqueta = trim((string) (($pedido->transportes->codigo ?? '').' '.($pedido->transportes->nombre ?? '')));
            }
            if (! PedidoListadoSupport::puedeFacturarDesdeIndex($pedido)) {
                continue;
            }
            $cantidades = $this->cantidadesFacturables($pedido);
            $totalCaja += $cantidades['caja'];
            $totalUnidad += $cantidades['unidad'];
            $totalKilo += $cantidades['kilo'];
            $totalPesada += $cantidades['pesada'];
            $pedidos[] = [
                'id' => (int) $pedido->id,
                'codigo' => (string) ($pedido->codigo ?? ''),
                'cliente' => (string) ($pedido->clientes->nombre ?? ''),
                'caja' => $cantidades['caja'],
                'unidad' => $cantidades['unidad'],
                'kilo' => $cantidades['kilo'],
                'pesada' => $cantidades['pesada'],
            ];
        }

        return [
            'transporte_id' => $transporteId,
            'etiqueta' => $etiqueta !== '' ? $etiqueta : 'Sin reparto',
            'pedidos' => $pedidos,
            'totales' => [
                'caja' => $totalCaja,
                'unidad' => $totalUnidad,
                'kilo' => $totalKilo,
                'pesada' => $totalPesada,
            ],
        ];
    }

    /**
     * Factura uno por uno los pedidos elegibles del reparto, con el mismo tipo/PV.
     *
     * @param  array<string, mixed>  $filtros
     * @param  array<string, mixed>  $params
     * @return array{ok: list<array<string, mixed>>, errores: list<string>, venta_ids: list<int>, facturas: list<array<string, mixed>>}
     */
    public function facturar(array $filtros, int $transporteId, array $params): array
    {
        $ok = [];
        $errores = [];
        $ventaIds = [];

        foreach ($this->pedidoQuery->pedidosIndexPorReparto($filtros, $transporteId) as $pedido) {
            if (! PedidoListadoSupport::puedeFacturarDesdeIndex($pedido)) {
                continue;
            }

            $payload = $this->armarPayload($pedido, $params);
            if ($payload === null) {
                $errores[] = 'Pedido '.($pedido->codigo ?: $pedido->id).': no tiene ítems pesados.';
                continue;
            }

            $resultado = $this->facturacionService->generaFacturaPorPedido($payload);
            $ids = $this->extraerVentaIds($resultado);
            $error = $this->extraerError($resultado);

            if ($error !== null && $ids === []) {
                $errores[] = 'Pedido '.($pedido->codigo ?: $pedido->id).': '.$error;
                continue;
            }

            foreach ($ids as $ventaId) {
                $ventaIds[] = $ventaId;
            }
            $ok[] = [
                'pedido_id' => (int) $pedido->id,
                'codigo' => (string) ($pedido->codigo ?? ''),
                'cliente' => (string) ($pedido->clientes->nombre ?? ''),
                'venta_ids' => $ids,
            ];
            if ($error !== null) {
                $errores[] = 'Pedido '.($pedido->codigo ?: $pedido->id).': '.$error;
            }
        }

        $ventaIds = array_values(array_unique($ventaIds));

        return [
            'ok' => $ok,
            'errores' => $errores,
            'venta_ids' => $ventaIds,
            'facturas' => $this->listarFacturasEmitidas($ok, $ventaIds),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $ok
     * @param  list<int>  $ventaIds
     * @return list<array{venta_id: int, codigo: string, pedido: string, cliente: string}>
     */
    private function listarFacturasEmitidas(array $ok, array $ventaIds): array
    {
        if ($ventaIds === []) {
            return [];
        }

        $codigos = Venta::query()
            ->whereIn('id', $ventaIds)
            ->pluck('codigo', 'id');

        $porPedido = [];
        foreach ($ok as $row) {
            foreach ($row['venta_ids'] ?? [] as $ventaId) {
                $porPedido[(int) $ventaId] = [
                    'pedido' => (string) ($row['codigo'] ?? ''),
                    'cliente' => (string) ($row['cliente'] ?? ''),
                ];
            }
        }

        $facturas = [];
        foreach ($ventaIds as $ventaId) {
            $meta = $porPedido[$ventaId] ?? ['pedido' => '', 'cliente' => ''];
            $facturas[] = [
                'venta_id' => $ventaId,
                'codigo' => (string) ($codigos[$ventaId] ?? ('#'.$ventaId)),
                'pedido' => $meta['pedido'],
                'cliente' => $meta['cliente'],
            ];
        }

        return $facturas;
    }

    /**
     * Cantidades de los ítems que se van a facturar.
     *
     * @return array{caja: float, unidad: float, kilo: float, pesada: float, item_ids: list<int>}
     */
    private function cantidadesFacturables(object $pedido): array
    {
        $caja = 0.0;
        $unidad = 0.0;
        $kilo = 0.0;
        $pesadaTotal = 0.0;
        $itemIds = [];
        foreach ($pedido->pedido_articulos ?? [] as $item) {
            if (! PedidoEstadoErpSupport::esItemPendienteFacturable($item->estado ?? null)) {
                continue;
            }
            $pesada = (float) ($item->pesada ?? 0);
            if ($pesada <= 0) {
                continue;
            }
            $itemIds[] = (int) $item->id;
            $caja += (float) ($item->caja ?? 0);
            $unidad += (float) ($item->pieza ?? 0);
            $kilo += (float) ($item->kilo ?? 0);
            $pesadaTotal += $pesada;
        }

        return [
            'caja' => $caja,
            'unidad' => $unidad,
            'kilo' => $kilo,
            'pesada' => $pesadaTotal,
            'item_ids' => $itemIds,
        ];
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>|null
     */
    private function armarPayload(object $pedido, array $params): ?array
    {
        $cantidades = $this->cantidadesFacturables($pedido);
        $itemIds = $cantidades['item_ids'];
        $cajas = $cantidades['caja'];
        if ($itemIds === []) {
            return null;
        }

        return [
            'pedido_id' => (int) $pedido->id,
            'cliente_id' => (int) ($pedido->cliente_id ?? 0),
            'pedido_articulo_ids' => $itemIds,
            'tipotransaccion_id' => (int) ($params['tipotransaccion_id'] ?? 0),
            'puntoventa_id' => (int) ($params['puntoventa_id'] ?? 0),
            'puntoventaremito_id' => (int) ($params['puntoventaremito_id'] ?? 0),
            'actividad_arca_id' => (int) ($params['actividad_arca_id'] ?? 0),
            'fechafactura' => (string) ($params['fechafactura'] ?? date('Y-m-d')),
            'descuentopie' => $pedido->descuento ?? 0,
            'descuentoimportepie' => 0,
            'descuentolinea' => 0,
            'leyendafactura' => (string) ($pedido->leyenda ?? ''),
            'cantidadbulto' => (int) $cajas,
            'formapago_id' => '',
            'incoterm_id' => '',
            'mercaderia' => '',
            'leyendaexportacion' => '',
            'cliente_entrega_id' => $pedido->cliente_entrega_id ?? '',
            'lugarentrega' => (string) ($pedido->lugarentrega ?? ''),
            'retorno_index' => (string) ($params['retorno_index'] ?? ''),
        ];
    }

    /**
     * @return list<int>
     */
    private function extraerVentaIds(mixed $resultado): array
    {
        if (! is_array($resultado) || $resultado === []) {
            return [];
        }
        $items = array_is_list($resultado) ? $resultado : [$resultado];
        $ids = [];
        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }
            $ventaId = (int) ($item['venta_id'] ?? 0);
            if ($ventaId > 0 && PedidoFacturaAnitaArchivosSupport::esVentaIdVisible($ventaId)) {
                $ids[] = $ventaId;
            }
        }

        return $ids;
    }

    private function extraerError(mixed $resultado): ?string
    {
        if (! is_array($resultado)) {
            return 'Sin respuesta del servidor.';
        }
        if (! empty($resultado['error'])) {
            return (string) $resultado['error'];
        }
        if (array_is_list($resultado)) {
            foreach ($resultado as $item) {
                if (is_array($item) && ! empty($item['error'])) {
                    return (string) $item['error'];
                }
            }
        }

        return null;
    }
}

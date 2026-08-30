<?php

namespace App\Support\Ventas;

use Illuminate\Support\Collection;

final class PedidoListadoSupport
{
    public static function usuarioPuedeFacturarPedido(): bool
    {
        return can('crear-factura', false) || can('editar-pedidos', false);
    }

    public static function usuarioPuedeFacturarReparto(): bool
    {
        return can('facturar-reparto-pedidos', false);
    }

    /**
     * Path del index con filtros para volver desde impresión.
     *
     * @param  array<string, mixed>  $filtrosQuery
     */
    public static function pathRetornoIndex(array $filtrosQuery = []): string
    {
        return ComprobanteImpresionSesionUrlSupport::sanitizarRetornoPath(
            route('pedido', $filtrosQuery)
        );
    }

    /**
     * @param  Collection<string, object>|array<string, object>  $accionesPorReparto
     */
    public static function accionesReparto($pedido, $accionesPorReparto): ?object
    {
        $meta = $accionesPorReparto[self::claveReparto($pedido)] ?? null;

        return $meta !== null ? (object) $meta : null;
    }

    /**
     * @param  array<string, mixed>  $filtrosQuery
     * @return array<string, mixed>
     */
    public static function paraImpresionReparto(array $filtrosQuery, int $transporteId, bool $soloCopias = false, string $retornoPath = ''): array
    {
        $params = ['transporteId' => $transporteId] + $filtrosQuery;
        if ($soloCopias) {
            $params['solo_copias'] = 1;
        }
        if ($retornoPath !== '') {
            $params['retorno'] = $retornoPath;
        }

        return $params;
    }

    /**
     * Pedido pendiente, no despacho/transferido, con al menos un ítem pendiente pesado (> 0).
     */
    public static function puedeFacturarDesdeIndex($pedido): bool
    {
        if (! self::usuarioPuedeFacturarPedido()) {
            return false;
        }

        $etiqueta = trim((string) ($pedido->estadopedido ?? $pedido->estado ?? $pedido['estado'] ?? ''));
        $estadoErp = (string) ($pedido->estado_erp ?? '');

        if ($etiqueta !== 'Pendiente') {
            return false;
        }
        if (PedidoEstadoErpSupport::esTransferido($estadoErp !== '' ? $estadoErp : null, $etiqueta)) {
            return false;
        }
        if (ClienteDespachoSupport::esPedidoDespacho((int) ($pedido->cliente_id ?? 0))) {
            return false;
        }

        foreach ($pedido->pedido_articulos ?? [] as $item) {
            if (! PedidoEstadoErpSupport::esItemPendienteFacturable($item->estado ?? null)) {
                continue;
            }
            if ((float) ($item->pesada ?? 0) > 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return Collection<int, object>
     */
    public static function ventasVisiblesParaIndex($pedido): Collection
    {
        if (! can('listar-factura', false)) {
            return collect();
        }

        return PedidoFacturaAnitaArchivosSupport::ventasVisiblesEnPedido($pedido->ventas ?? []);
    }

    /**
     * Payload para hidratar el preview de facturación desde el index.
     *
     * @return array<string, mixed>
     */
    public static function contextoFacturacion($pedido): array
    {
        $totales = self::totalesPedido($pedido);
        $items = [];

        foreach ($pedido->pedido_articulos ?? [] as $item) {
            $um = $item->articulos->unidadesdemedidas ?? null;
            $items[] = [
                'id' => (int) ($item->id ?? 0),
                'estado' => (string) ($item->estado ?? PedidoEstadoErpSupport::PENDIENTE),
                'articulo_id' => (int) ($item->articulo_id ?? 0),
                'sku' => (string) ($item->articulos->sku ?? ''),
                'descripcion' => (string) ($item->articulos->descripcion ?? ''),
                'unidadmedida_id' => (int) ($item->unidadmedida_id ?? $um->id ?? 0),
                'unidadmedida' => (string) ($um->abreviatura ?? ''),
                'caja' => number_format((float) ($item->caja ?? 0), 2, '.', ''),
                'pieza' => number_format((float) ($item->pieza ?? 0), 2, '.', ''),
                'kilo' => number_format((float) ($item->kilo ?? 0), 2, '.', ''),
                'pesada' => number_format((float) ($item->pesada ?? 0), 2, '.', ''),
                'descuentoventa_id' => (int) ($item->descuentoventa_id ?? 0),
                'precio' => number_format((float) ($item->precio ?? 0), 2, '.', ''),
            ];
        }

        $codigoCliente = trim((string) ($pedido->clientes->codigo ?? ''));
        $nombreCliente = trim((string) ($pedido->clientes->nombre ?? ''));
        $nombreDisplay = $codigoCliente !== '' && $nombreCliente !== ''
            ? $codigoCliente.' - '.$nombreCliente
            : $nombreCliente;

        return [
            'pedido_id' => (int) $pedido->id,
            'codigo' => (string) ($pedido->codigo ?? ''),
            'estadopedido' => (string) ($pedido->estadopedido ?? ''),
            'cliente_id' => (int) ($pedido->cliente_id ?? 0),
            'nombrecliente' => $nombreDisplay,
            'estadocliente' => (string) ($pedido->clientes->estado ?? ''),
            'descuento' => (string) ($pedido->descuento ?? '0'),
            'cliente_entrega_id' => (string) ($pedido->cliente_entrega_id ?? ''),
            'lugarentrega' => (string) ($pedido->lugarentrega ?? ''),
            'entrega_nombre' => (string) ($pedido->entrega_nombre ?? ''),
            'items' => $items,
            'totales' => [
                'caja' => number_format($totales['caja'], 2, '.', ''),
                'pieza' => number_format($totales['pieza'], 2, '.', ''),
                'pesada' => number_format($totales['pesada'], 2, '.', ''),
            ],
        ];
    }

    /**
     * @return array{caja: float, pieza: float, kilo: float, pesada: float}
     */
    public static function totalesPedido($pedido): array
    {
        $caja = $pieza = $kilo = $pesada = 0.0;

        foreach ($pedido->pedido_articulos ?? [] as $item) {
            $caja += (float) ($item->caja ?? 0);
            $pieza += (float) ($item->pieza ?? 0);
            $kilo += (float) ($item->kilo ?? 0);
            $pesada += (float) ($item->pesada ?? 0);
        }

        return [
            'caja' => $caja,
            'pieza' => $pieza,
            'kilo' => $kilo,
            'pesada' => $pesada,
        ];
    }

    /**
     * @param  Collection<string, object>|array<string, object>  $totalesPorReparto
     */
    public static function claveReparto($pedido): string
    {
        return (string) ((int) ($pedido->transporte_id ?? $pedido['transporte_id'] ?? 0));
    }

    /**
     * @param  Collection<string, object>|array<string, object>  $totalesPorReparto
     */
    public static function metaReparto($pedido, $totalesPorReparto): ?object
    {
        $meta = $totalesPorReparto[self::claveReparto($pedido)] ?? null;

        return $meta !== null ? (object) $meta : null;
    }

    /**
     * @param  Collection<string, object>|array<string, object>  $totalesPorReparto
     */
    public static function esCierreReparto($pedido, $totalesPorReparto): bool
    {
        $meta = self::metaReparto($pedido, $totalesPorReparto);
        if ($meta === null) {
            return false;
        }

        $pedidoId = (int) ($pedido->id ?? $pedido['id'] ?? 0);

        return $pedidoId > 0 && $pedidoId === (int) ($meta->ultimo_pedido_id ?? 0);
    }

    public static function etiquetaSubtotalReparto(object $meta): string
    {
        $codigo = trim((string) ($meta->codigotransporte ?? ''));
        $nombre = trim((string) ($meta->nombretransporte ?? ''));
        $cantidad = (int) ($meta->cantidad_pedidos ?? 0);
        $kilos = (float) ($meta->kilo ?? 0);

        $reparto = trim($codigo.' '.$nombre);
        if ($reparto === '') {
            $reparto = 'Sin reparto';
        }

        $pedidosTxt = $cantidad === 1 ? '1 pedido' : $cantidad.' pedidos';

        return 'Reparto '.$reparto.' — '.$pedidosTxt.' — '.self::formatearTotal($kilos).' kg';
    }

    /**
     * Número de pedido para listados (sin PED- / letra / sucursal).
     */
    public static function codigoParaListado($pedido): string
    {
        $codigo = trim((string) ($pedido['codigo'] ?? $pedido->codigo ?? ''));
        if ($codigo === '') {
            return '';
        }

        $numero = PedidoReferenciaAnitaSupport::numero($codigo);

        return $numero > 0 ? (string) $numero : $codigo;
    }

    public static function formatearKilos(float $kilos): string
    {
        return self::formatearTotal($kilos);
    }

    public static function formatearTotal(float|int|string|null $valor): string
    {
        return number_format(round((float) $valor, 2), 2, ',', '.');
    }
}

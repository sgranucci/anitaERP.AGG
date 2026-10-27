<?php

namespace App\Services\Ventas;

use App\Models\Stock\Categoria;
use App\Models\Stock\Combinacion;
use App\Models\Stock\Talle;
use App\Models\Ventas\Pedido_Combinacion;
use App\Queries\Ventas\PedidoQueryFerli;
use App\Services\Stock\PrecioServiceFerli;
use App\Support\Ventas\PedidoEstadoCabeceraSupport;
use App\Support\Ventas\PedidoPickingFerliSupport;
use App\Support\Ventas\PuntoventaRemitoSupport;
use Cache;

/**
 * Facturación Ferli por OT / pedido_combinacion / talle.
 * Reutiliza emisión ARCA del FacturacionService; no toca kilos, CAEA salto ni reparto Bierzo.
 */
class FacturacionServiceFerli extends FacturacionService
{
    protected function asignaPrecioLineaItemOt($articulo, $combinacion_id, $talle, $fechaFactura)
    {
        return app(PrecioServiceFerli::class)->asignaPrecio(
            $articulo->id,
            $combinacion_id,
            $talle->id,
            $fechaFactura
        );
    }

    protected function aplicarVencimientosFacturaOt(array $cuentacorriente, $fechaFactura, $puntoventa): array
    {
        return $cuentacorriente;
    }

    protected function prepararSesionFacturaOt(array $data): void
    {
        Cache::forever(generaKey('tipotransaccion'), $data['tipotransaccion_id']);
        Cache::forever(generaKey('puntoventa'), $data['puntoventa_id']);
        Cache::forever(generaKey('puntoventaremito'), $data['puntoventaremito_id']);
    }

    protected function leePedidoFacturaOt($id)
    {
        return app(PedidoQueryFerli::class)->leePedidoporId($id);
    }

    protected function aplicarLugarEntregaFacturaOt($cliente, $pedido, bool $persistirEntrega = true)
    {
        $mismoCliente = (int) $cliente->id === (int) $pedido->cliente_id;
        if (! $mismoCliente) {
            $cliente_entrega = $this->cliente_entregaRepository->leeClienteEntrega($cliente->id);

            if ($cliente_entrega) {
                $pedido->lugarentrega = $cliente_entrega[0]->nombre;
                $pedido->cliente_entrega_id = $cliente_entrega[0]->id ?? null;
            } else {
                $pedido->cliente_entrega_id = null;
            }
        }

        // Si la factura va a otra razón social, el lugar queda solo en el comprobante.
        // El preview no persiste: solo necesita la provincia en memoria para IIBB.
        return $this->resolverLugarEntregaPedido($cliente, $pedido, [], $persistirEntrega && $mismoCliente);
    }

    protected function sincronizarLugarEntregaFacturaOt($pedido): void
    {
        $this->sincronizarLugarEntregaPedido($pedido);
    }

    protected function transporteIdFacturaOt(array $data, $pedido)
    {
        $transporteId = (int) ($data['transporte_id'] ?? 0);

        return $transporteId > 0 ? $transporteId : $pedido->transporte_id;
    }

    public function generaFacturaPorItemOt(array $data)
    {
        if ($error = PuntoventaRemitoSupport::errorSiNoHabilitado($data['puntoventaremito_id'] ?? null)) {
            return ['error' => $error];
        }

        if (($data['origen'] ?? '') === 'picking') {
            return $this->generaFacturaPorPickingPedido($data);
        }

        return parent::generaFacturaPorItemOt($data);
    }

    /**
     * Factura líneas de pedido marcadas para picking (sin crear OT de consumo).
     */
    public function generaFacturaPorPickingPedido(array $data)
    {
        $this->prepararSesionFacturaOt($data);

        $armado = $this->armarDataFacturaPicking($data, true);
        if (isset($armado['error'])) {
            return ['error' => $armado['error']];
        }

        return $this->emitirFacturaOtDesdeDataFactura(
            $data,
            $armado['dataFactura'],
            $armado['cliente'],
            $armado['pedido'],
            $armado['moneda_id'],
            $armado['fechaFactura'],
            $armado['leyenda'],
            $armado['actividad_arca_id'],
            $armado['deposito'],
            $armado['puntoventa_id'],
            $armado['tipoTransaccion_id'],
            $armado['pedidos_combinacion_id'],
            $armado['ordenestrabajo_id'],
            $armado['despuesGrabar']
        );
    }

    /**
     * Subtotal, IVA y percepción del picking, con el mismo cálculo que la emisión.
     *
     * @return array{conceptostotales?: list<array{concepto: string, tasa: float, importe: float}>, error?: string}
     */
    public function previewTotalesPickingPedido(array $data): array
    {
        $armado = $this->armarDataFacturaPicking($data, false);
        if (isset($armado['error'])) {
            return ['error' => $armado['error']];
        }

        $puntoventa = null;
        $puntoventaId = (int) ($data['puntoventa_id'] ?? 0);
        if ($puntoventaId > 0) {
            $puntoventa = $this->puntoventaRepository->find($puntoventaId);
        }

        $cliente = $armado['cliente'];
        $pedido = $armado['pedido'];
        $entrega = null;
        if ($pedido && (int) ($pedido->cliente_entrega_id ?? 0) > 0) {
            $entrega = $this->cliente_entregaRepository->find($pedido->cliente_entrega_id);
        }

        $datosCliente = [
            'condicioniva_id' => $cliente->condicioniva_id,
            'numerodocumento' => $cliente->numerodocumento,
            'retieneiva' => $cliente->retieneiva,
            'condicioniibb_id' => $cliente->condicioniibb_id,
            'provincia' => \App\Support\Ventas\ClienteProvinciaIibbSupport::idParaPercepcionAdmin($cliente, $entrega),
            'descuentoimportepie' => $this->descuentoImportePie,
            'id' => $cliente->id,
            'empresa_id' => $puntoventa->empresa_id ?? null,
        ];

        $dataFactura = $armado['dataFactura'];
        try {
            $conceptos = $this->impuestoService->calculaImpuestoVenta(
                $dataFactura,
                $datosCliente,
                $armado['fechaFactura']
            );
        } catch (\Throwable $e) {
            report($e);

            return ['error' => 'No se pudieron calcular los importes de la factura'];
        }

        return [
            'conceptostotales' => $this->conceptosPreviewPicking($conceptos),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $conceptos
     * @return list<array{concepto: string, tasa: float, importe: float}>
     */
    private function conceptosPreviewPicking(array $conceptos): array
    {
        $visibles = [];
        foreach ($conceptos as $concepto) {
            $nombre = (string) ($concepto['concepto'] ?? '');
            $importe = round((float) ($concepto['importe'] ?? 0), 2);
            $nombreLower = mb_strtolower($nombre);
            $esTotal = $nombreLower === 'total';
            $esSubtotal = $nombreLower === 'subtotal';
            $esIva = str_starts_with($nombreLower, 'iva');
            $esPercepcion = str_contains($nombreLower, 'perc');
            $esDescuento = str_starts_with($nombreLower, 'descuento');
            if (! $esTotal && ! $esSubtotal && ! $esIva && ! $esPercepcion && ! $esDescuento) {
                continue;
            }
            if (! $esTotal && abs($importe) < 0.005) {
                continue;
            }
            $visibles[] = [
                'concepto' => $nombre,
                'tasa' => round((float) ($concepto['tasa'] ?? 0), 2),
                'importe' => $importe,
            ];
        }

        return $visibles;
    }

    /**
     * Arma las líneas de la factura de picking. Con $persistirEntrega en false no graba el pedido.
     *
     * @return array<string, mixed>
     */
    private function armarDataFacturaPicking(array $data, bool $persistirEntrega): array
    {
        $pedidos_combinacion_id = array_values(array_map('intval', (array) ($data['pedido_combinacion_id'] ?? [])));
        $ordenestrabajo_id = array_values(array_map('intval', (array) ($data['ordentrabajo_id'] ?? [])));

        if ($pedidos_combinacion_id === []) {
            return ['error' => 'Seleccione al menos una línea de picking'];
        }

        while (count($ordenestrabajo_id) < count($pedidos_combinacion_id)) {
            $ordenestrabajo_id[] = 0;
        }

        $puntoventa_id = $data['puntoventa_id'] ?? null;
        $tipoTransaccion_id = $data['tipotransaccion_id'] ?? null;
        $fechaFactura = $data['fechafactura'] ?? null;
        $leyenda = $data['leyendafactura'] ?? '';

        $actividad_arca_id = null;
        if (isset($data['actividad_arca_id'])) {
            $actividad_arca_id = $data['actividad_arca_id'];
        }

        $deposito = $this->depositoIdDesdePayload($data);

        $this->descuentoPie = $data['descuentopie'] ?? 0;
        $this->descuentoLinea = $data['descuentolinea'] ?? 0;
        $this->descuentoImportePie = $data['descuentoimportepie'] ?? 0;
        $this->cantidadBulto = $this->normalizarCantidadBulto($data['cantidadbulto'] ?? 0);
        $this->puntoventaremito_id = $data['puntoventaremito_id'] ?? null;
        $this->formapago_id = $data['formapago_id'] ?? null;
        $this->incoterm_id = $data['incoterm_id'] ?? null;
        $this->mercaderiaExportacion = $data['mercaderia'] ?? '';
        $this->leyendaExportacion = $data['leyendaexportacion'] ?? '';
        $this->numeroDespacho = '';
        $this->condicionVentaExportacion = '';
        $this->formaPagoExportacion = '';
        $this->monedaExportacion = '';
        $this->abreviaturaIncoterm = '';
        if ((int) $this->incoterm_id >= 1) {
            $incoterm = $this->incotermRepository->find($this->incoterm_id);
            if ($incoterm) {
                $this->condicionVentaExportacion = $incoterm->nombre;
                $this->abreviaturaIncoterm = $incoterm->abreviatura;
            }
            $formapago = $this->formapagoRepository->find($this->formapago_id);
            if ($formapago) {
                $this->formaPagoExportacion = $formapago->nombre;
            }
        }

        $lineas = Pedido_Combinacion::query()
            ->with([
                'pedidos',
                'articulos.unidadesdemedidas',
                'combinaciones',
                'pedido_combinacion_talles.talles',
            ])
            ->whereIn('id', $pedidos_combinacion_id)
            ->get()
            ->keyBy('id');

        if ($lineas->count() !== count($pedidos_combinacion_id)) {
            return ['error' => 'Hay líneas de picking inexistentes'];
        }

        $dataFactura = [];
        $cliente = null;
        $clienteResuelto = false;
        $pedido = null;
        $moneda_id = null;
        $lineasValidas = [];

        foreach ($pedidos_combinacion_id as $off => $pedido_combinacion_id) {
            $linea = $lineas->get($pedido_combinacion_id);
            if (! $linea) {
                return ['error' => 'Línea de picking inexistente'];
            }
            if (($linea->picking ?? PedidoPickingFerliSupport::NO_MARCADO) !== PedidoPickingFerliSupport::MARCADO) {
                return ['error' => 'La línea '.$pedido_combinacion_id.' no está marcada para picking'];
            }
            if (($linea->picking_facturado ?? PedidoPickingFerliSupport::NO_MARCADO) === PedidoPickingFerliSupport::FACTURADO) {
                return ['error' => 'La línea '.$pedido_combinacion_id.' ya fue facturada desde picking'];
            }
            if (($linea->estado ?? 'N') === 'A') {
                return ['error' => 'La línea '.$pedido_combinacion_id.' está anulada'];
            }

            $loteCodigo = trim((string) ($linea->picking_lote_codigo ?? ''));
            if ($loteCodigo === '' || $loteCodigo === '0') {
                return ['error' => 'La línea '.$pedido_combinacion_id.' no tiene lote/OT de picking'];
            }

            $ordentrabajo_id = (int) ($ordenestrabajo_id[$off] ?? ($linea->ot_id ?? 0));
            $ordenestrabajo_id[$off] = $ordentrabajo_id > 0 ? $ordentrabajo_id : 0;

            $articulo = $this->articuloQuery->traeArticuloPorId($linea->articulo_id);
            if (! $articulo) {
                return ['error' => 'Artículo inexistente'];
            }

            $combinacion_id = $linea->combinacion_id;
            $moneda_id = $linea->moneda_id;
            $this->mventa_id = $articulo->mventa_id;

            $combinacion = Combinacion::find($combinacion_id);
            if (! $combinacion) {
                return ['error' => 'Combinación inexistente'];
            }

            $categoria = Categoria::find($articulo->categoria_id);
            $codigoCategoria = $categoria ? $categoria->codigo : '';

            $pedido_query = $this->leePedidoFacturaOt($linea->pedido_id);
            if (! $pedido_query || ! isset($pedido_query[0])) {
                return ['error' => 'Pedido inexistente'];
            }
            $pedido = $pedido_query[0];

            if (! $clienteResuelto) {
                $cliente = $this->resolverClienteFacturaSesion($data, $pedido->cliente_id);
                if (is_array($cliente)) {
                    return $cliente;
                }
                $clienteResuelto = true;
            }

            $this->cuentacontable_id = $cliente->cuentacontable_id;
            $this->codigoCuentaContable = $cliente->cuentascontables?->codigo ?? '';

            $errorEntrega = $this->aplicarLugarEntregaFacturaOt($cliente, $pedido, $persistirEntrega);
            if ($errorEntrega) {
                return $errorEntrega;
            }

            $loteimportacion_id = $this->articulo_movimientoService->buscaLoteImportacion($loteCodigo);
            $this->numeroDespacho = '';
            if ($loteimportacion_id > 0 && $loteimportacion_id != null) {
                $lote = $this->loteRepository->find($loteimportacion_id);
                if ($lote) {
                    $this->numeroDespacho = $lote->numerodespacho;
                }
            }

            $codigoPedido = $pedido->codigo ?? '';

            foreach ($linea->pedido_combinacion_talles as $talleLinea) {
                $talle = Talle::find($talleLinea->talle_id);
                if (! $talle) {
                    continue;
                }

                $precio = $this->asignaPrecioLineaItemOt($articulo, $combinacion_id, $talle, $fechaFactura);
                if (! isset($precio[0])) {
                    $msg = 'Articulo '.$articulo->sku.' '.$articulo->descripcion.' Linea '.$articulo->linea_id
                        .' Talle '.$talle->nombre.' NO TIENE PRECIO';

                    return ['error' => $msg];
                }
                // El importe es el del talle del pedido (el mismo que muestra el picking).
                // La lista solo aporta si el talle no tiene precio, y el flag de impuesto.
                $precioPedido = round((float) $talleLinea->precio, 4);
                if ($precioPedido > 0) {
                    $precio[0]['precio'] = $precioPedido;
                }
                if ($precio[0]['precio'] == 0) {
                    $msg = 'Articulo '.$articulo->sku.' '.$articulo->descripcion.' Linea '.$articulo->linea_id
                        .' Talle '.$talle->nombre.' NO TIENE PRECIO';

                    return ['error' => $msg];
                }

                if ($this->descuentoLinea != 0) {
                    $precioUnitario = $precio[0]['precio'] * (1. - ($this->descuentoLinea / 100.));
                } else {
                    $precioUnitario = $precio[0]['precio'];
                }

                $flEncontro = false;
                $i = 0;
                for ($i = 0; $i < count($dataFactura); $i++) {
                    if ($dataFactura[$i]['precio'] == $precioUnitario
                        && $dataFactura[$i]['sku'] == $articulo->sku
                        && $dataFactura[$i]['combinacion_id'] == $combinacion_id
                        && (int) $dataFactura[$i]['pedido_combinacion_id'] === (int) $pedido_combinacion_id) {
                        $flEncontro = true;
                        break;
                    }
                }

                $medidaRow = [
                    'id' => $talleLinea->id,
                    'talle' => $talle->id,
                    'medida' => $talle->nombre,
                    'cantidad' => $talleLinea->cantidad,
                    'precio' => $precioUnitario,
                    'pedido' => $codigoPedido,
                    'descuento' => $this->descuentoLinea,
                ];

                if (! $flEncontro) {
                    $dataFactura[] = [
                        'cantidad' => $talleLinea->cantidad,
                        'precio' => $precioUnitario,
                        'descuento' => $this->descuentoLinea,
                        'descuentointegrado' => '',
                        'descuentofinal' => $this->descuentoPie,
                        'descuentointegradofinal' => '',
                        'incluyeimpuesto' => $precio[0]['incluyeimpuesto'],
                        'impuesto_id' => $articulo->impuesto_id,
                        'articulo_id' => $articulo->id,
                        'sku' => $articulo->sku,
                        'descripcion' => $articulo->descripcion,
                        'codigounidadmedida' => $articulo->unidadesdemedidas->codigo ?? 1,
                        'categoria' => $codigoCategoria,
                        'combinacion_id' => $combinacion_id,
                        'codigocombinacion' => $combinacion->codigo,
                        'modulo_id' => $linea->modulo_id,
                        'moneda_id' => $linea->moneda_id,
                        'listaprecio_id' => $linea->listaprecio_id,
                        'despacho' => $this->numeroDespacho,
                        'loteimportacion_id' => $loteimportacion_id,
                        // Picking de lote importado no tiene OT: 0 viola fk_venta_emision_ordentrabajo.
                        'ordentrabajo_id' => $ordenestrabajo_id[$off] > 0 ? $ordenestrabajo_id[$off] : null,
                        'pedido_combinacion_id' => $pedido_combinacion_id,
                        'cuentacontable_id' => $articulo->cuentacontableventa_id,
                        'medidas' => [$medidaRow],
                    ];
                } else {
                    $dataFactura[$i]['cantidad'] += $talleLinea->cantidad;
                    $dataFactura[$i]['medidas'][] = $medidaRow;
                }
            }

            $lineasValidas[] = $linea;
        }

        if ($dataFactura === [] || ! $cliente || ! $pedido) {
            return ['error' => 'No hay cantidades para facturar'];
        }

        if ($persistirEntrega) {
            $this->sincronizarLugarEntregaFacturaOt($pedido);
        }

        $lineasParaPost = $lineasValidas;

        return [
            'dataFactura' => $dataFactura,
            'cliente' => $cliente,
            'pedido' => $pedido,
            'moneda_id' => $moneda_id,
            'fechaFactura' => $fechaFactura,
            'leyenda' => $leyenda,
            'actividad_arca_id' => $actividad_arca_id,
            'deposito' => $deposito,
            'puntoventa_id' => $puntoventa_id,
            'tipoTransaccion_id' => $tipoTransaccion_id,
            'pedidos_combinacion_id' => $pedidos_combinacion_id,
            'ordenestrabajo_id' => $ordenestrabajo_id,
            'despuesGrabar' => function ($vta) use ($lineasParaPost, $fechaFactura, $pedido) {
                foreach ($lineasParaPost as $linea) {
                    PedidoPickingFerliSupport::marcarFacturado((int) $linea->id, (int) $vta->id);
                    PedidoPickingFerliSupport::asegurarConsumoStockPickingAlFacturar(
                        $linea,
                        (string) $fechaFactura,
                        (int) $vta->id
                    );
                }
                // marcarFacturado usa Query Builder (sin observer): refrescar cabecera
                PedidoEstadoCabeceraSupport::refrescar((int) ($pedido->id ?? 0));
            },
        ];
    }

    /**
     * El Anita del local ya no recibe stock. El movimiento queda en anitaERP
     * (articulo_movimiento). La firma se conserva por si queda algún caller.
     */
    public function grabaStockLocal($puntoventa, $letra, $venta, $datatalle,
        $codigoCliente = '', $vendedor = 1, $zonavta_id = 0, $provincia_id = 902,
        $subzonavta_id = 0, $servidor = 'LOCAL_IP', $ifx_server = 'IFX_SERVER_LOCAL')
    {
        return 'Success';
    }
}

<?php

namespace App\Services\Ventas;

use App\Models\Ventas\Venta;
use App\Models\Ventas\Venta_Emision;
use App\Queries\Ventas\ClienteQueryInterface;
use App\Queries\Ventas\PedidoQueryInterface;
use App\Queries\Ventas\RemitoQueryInterface;
use App\Queries\Stock\ArticuloQueryInterface;
use App\Repositories\Ventas\Cliente_EntregaRepositoryInterface;
use App\Repositories\Ventas\Pedido_ArticuloRepositoryInterface;
use App\Repositories\Ventas\PuntoventaRepositoryInterface;
use App\Repositories\Ventas\RemitoRepositoryInterface;
use App\Repositories\Ventas\Remito_ArticuloRepositoryInterface;
use App\Repositories\Ventas\VentaRepositoryInterface;
use App\Repositories\Stock\Tipotransaccion_StockRepositoryInterface;
use App\Services\Stock\MovimientoStockService;
use App\Support\Ventas\PedidoEstadoErpSupport;
use App\Support\Ventas\UsuarioPreferenciaFacturacionSupport;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\View;
use Carbon\Carbon;
use Auth;

class RemitoService
{
    protected $remitoRepository;

    protected $remito_articuloRepository;

    protected $remitoQuery;

    protected $clienteQuery;

    protected $cliente_entregaRepository;

    protected $ventaRepository;

    protected $puntoventaRepository;

    protected $pedidoQuery;

    protected $pedido_articuloRepository;

    protected $articuloQuery;

    protected $tipotransaccionStockRepository;

    protected $movimientoStockService;

    public function __construct(
        RemitoRepositoryInterface $remitorepository,
        Remito_ArticuloRepositoryInterface $remitoarticulorepository,
        RemitoQueryInterface $remitoquery,
        ClienteQueryInterface $clientequery,
        Cliente_EntregaRepositoryInterface $cliente_entregarepository,
        VentaRepositoryInterface $ventarepository,
        PuntoventaRepositoryInterface $puntoventarepository,
        PedidoQueryInterface $pedidoquery,
        Pedido_ArticuloRepositoryInterface $pedido_articulorepository,
        ArticuloQueryInterface $articuloquery,
        Tipotransaccion_StockRepositoryInterface $tipotransaccionstockrepository,
        MovimientoStockService $movimientoStockservice,
    ) {
        $this->remitoRepository = $remitorepository;
        $this->remito_articuloRepository = $remitoarticulorepository;
        $this->remitoQuery = $remitoquery;
        $this->clienteQuery = $clientequery;
        $this->cliente_entregaRepository = $cliente_entregarepository;
        $this->ventaRepository = $ventarepository;
        $this->puntoventaRepository = $puntoventarepository;
        $this->pedidoQuery = $pedidoquery;
        $this->pedido_articuloRepository = $pedido_articulorepository;
        $this->articuloQuery = $articuloquery;
        $this->tipotransaccionStockRepository = $tipotransaccionstockrepository;
        $this->movimientoStockService = $movimientoStockservice;
    }

    public function leeRemito($id)
    {
        try {
            return $this->remitoRepository->find($id);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return null;
        }
    }

    public function leeRemitosPorEstadoPaginando($busqueda, $estado, $reparto, $fechaentrega)
    {
        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        return $this->remitoQuery->allRemitoIndexPaginando($busqueda, $estado, $reparto, $fechaentrega);
    }

    public function leeRemitosPorEstadoSinPaginar($busqueda, $estado = '', $reparto = '', $fechaentrega = '')
    {
        ini_set('memory_limit', '512M');
        ini_set('max_execution_time', '2400');

        return $this->remitoQuery->allRemitoIndexSinPaginar($busqueda, $estado, $reparto, $fechaentrega);
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    public function leeRemitosIndex(array $filtros, bool $flPaginando = true)
    {
        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        return $this->remitoQuery->allRemitoIndexFiltros($filtros, $flPaginando);
    }

    public function listarRemitoPdf($id)
    {
        ini_set('memory_limit', '512M');

        $data = $this->remitoQuery->leeRemitoporId($id);
        $remito = $data[0];
        $nombre_pdf = 'remito-'.$id.'-'.$remito->clientes->nombre;

        $view = View::make('exports.ventas.remito', compact('remito'))->render();
        $path = storage_path('pdf/remito');
        if (! is_dir($path) && ! mkdir($path, 0777, true) && ! is_dir($path)) {
            throw new \RuntimeException('No se pudo crear el directorio de PDF de remitos.');
        }

        $pdf = App::make('dompdf.wrapper');
        $pdf->setPaper('legal', 'landscape');
        $pdf->loadHTML($view)->save($path.'/'.$nombre_pdf.'.pdf');

        return response()->download($path.'/'.$nombre_pdf.'.pdf');
    }

    public function guardaRemito($data, $funcion, $id = null)
    {
        ini_set('memory_limit', '512M');

        $cliente = $this->clienteQuery->traeClienteporId($data['cliente_id']);

        if (! $cliente) {
            return ['error' => 'Cliente inexistente'];
        }

        $errorEntrega = \App\Support\Ventas\ClienteEntregaPedidoSupport::validarSeleccionParaCliente(
            (int) $data['cliente_id'],
            (int) ($data['cliente_entrega_id'] ?? 0) ?: null
        );
        if ($errorEntrega !== null) {
            return $errorEntrega;
        }

        $errorListaprecio = \App\Support\Ventas\ArticuloListaprecioLineaVentasSupport::validarLineas(
            $data['articulo_ids'] ?? null,
            $data['listasprecios_id'] ?? null,
            $data['codigoarticulos'] ?? null,
        );
        if ($errorListaprecio !== null) {
            return $errorListaprecio;
        }

        $entregasCliente = $this->cliente_entregaRepository->leeClienteEntrega($data['cliente_id']);
        if ($entregasCliente->count() > 0) {
            $entrega = $entregasCliente->firstWhere('id', (int) $data['cliente_entrega_id']);
            $data['lugarentrega'] = $entrega->nombre;
        } else {
            $data['cliente_entrega_id'] = null;
        }

        $data['usuario_id'] = Auth::user()->id;
        $data['descuentointegrado'] = $data['descuentointegrado'] ?? ' ';

        if (! array_key_exists('leyenda', $data)) {
            $data['leyenda'] = ' ';
        }

        DB::beginTransaction();

        try {
            if ($funcion == 'create') {
                $puntoventaId = (int) ($data['puntoventa_id'] ?? 0);
                if ($puntoventaId <= 0) {
                    $puntoventaId = (int) (UsuarioPreferenciaFacturacionSupport::leer()['puntoventaremito_id'] ?? 0);
                }
                $puntoventa = $this->puntoventaRepository->find($puntoventaId);
                if (! $puntoventa) {
                    throw new \RuntimeException('Punto de venta de remito inexistente.');
                }

                $numero = $this->ventaRepository->traeUltimoNumeroRemito('REM', 'R', $puntoventa->codigo);
                if ($numero === 'error') {
                    throw new \RuntimeException('No se pudo obtener numeración de remito desde Anita.');
                }

                $data['tipocomprobante'] = 'REM';
                $data['letra'] = 'R';
                $data['puntoventa_id'] = $puntoventaId;
                $data['numero'] = $numero;
                $data['codigo'] = 'REM R '.$puntoventa->codigo.'-'.$numero;
                $data['estado'] = 'P';
                $data['estadoremito'] = 'Pendiente';
                $data['origen'] = $data['origen'] ?? 'manual';

                $remito = $this->remitoRepository->create($data);
                $id = $remito->id;
            } else {
                $existente = $this->remitoRepository->find($id);
                $data['codigo'] = $data['codigo'] ?? $existente->codigo;
                $this->remitoRepository->update($data, $id);
            }

            if ($id) {
                $data['remito_id'] = $id;

                if ($funcion == 'update') {
                    $remito_articulo = $this->remito_articuloRepository->findPorRemitoId($id)->toArray();
                    $q_remito_articulo = count($remito_articulo);
                }

                if (isset($data['articulo_ids'])) {
                    $articulos = $data['articulo_ids'];
                    $unidadmedida_ids = $data['unidadmedida_ids'];
                    $numeroitems = $data['items'];
                    $cajas = $data['cajas'];
                    $piezas = $data['piezas'];
                    $kilos = $data['kilos'];
                    $precios = $data['precios'];
                    $listaprecios = $data['listasprecios_id'];
                    $incluyeimpuestos = $data['incluyeimpuestos'];
                    $monedas = $data['monedas_id'];
                    $descuentoventa_ids = $data['descuentoventaanterior_ids'];
                    $descuentos = $data['descuentos'];
                    $loteids = $data['loteids'];
                    $observaciones = $data['observaciones'];

                    if ($funcion == 'update') {
                        $_id = $remito_articulo;

                        if ($q_remito_articulo > count($articulos)) {
                            for ($d = count($articulos); $d < $q_remito_articulo; $d++) {
                                $this->remito_articuloRepository->delete($_id[$d]['id']);
                            }
                        }

                        for ($i = 0; $i < $q_remito_articulo && $i < count($articulos); $i++) {
                            $this->remito_articuloRepository->update([
                                'remito_id' => $id,
                                'articulo_id' => $articulos[$i],
                                'unidadmedida_id' => $unidadmedida_ids[$i],
                                'numeroitem' => $numeroitems[$i],
                                'caja' => $cajas[$i],
                                'pieza' => $piezas[$i],
                                'kilo' => $kilos[$i],
                                'precio' => $precios[$i] ?? 0,
                                'listaprecio_id' => $listaprecios[$i],
                                'incluyeimpuesto' => $incluyeimpuestos[$i],
                                'moneda_id' => $monedas[$i],
                                'descuentoventa_id' => $descuentoventa_ids[$i],
                                'descuento' => $descuentos[$i],
                                'descuentointegrado' => '',
                                'lote_id' => $loteids[$i],
                                'observacion' => $observaciones[$i],
                                'estado' => $data['estado'] ?? 'P',
                            ], $_id[$i]['id']);
                        }

                        if ($q_remito_articulo > count($articulos)) {
                            $i = $d;
                        }
                    } else {
                        $i = 0;
                    }

                    for ($i_movimiento = $i; $i_movimiento < count($articulos); $i_movimiento++) {
                        if ($articulos[$i_movimiento] != '') {
                            $this->remito_articuloRepository->create([
                                'remito_id' => $id,
                                'articulo_id' => $articulos[$i_movimiento],
                                'unidadmedida_id' => $unidadmedida_ids[$i_movimiento],
                                'numeroitem' => $numeroitems[$i_movimiento],
                                'caja' => $cajas[$i_movimiento],
                                'pieza' => $piezas[$i_movimiento],
                                'kilo' => $kilos[$i_movimiento],
                                'precio' => $precios[$i_movimiento] ?? 0,
                                'listaprecio_id' => $listaprecios[$i_movimiento],
                                'incluyeimpuesto' => $incluyeimpuestos[$i_movimiento],
                                'moneda_id' => $monedas[$i_movimiento],
                                'descuentoventa_id' => $descuentoventa_ids[$i_movimiento],
                                'descuento' => $descuentos[$i_movimiento],
                                'descuentointegrado' => '',
                                'lote_id' => $loteids[$i_movimiento],
                                'observacion' => $observaciones[$i_movimiento],
                                'estado' => $data['estado'] ?? 'P',
                            ]);
                        }
                    }
                } else {
                    $this->remito_articuloRepository->deleteporremito($id);
                }
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollback();

            return ['error' => $e->getMessage()];
        }

        // TODO: integrar escritura Anita (pendmae/pendmov) vía hook en RemitoRepository si se requiere sync legacy.

        return ['id' => $id, 'codigo' => $data['codigo']];
    }

    public function borraRemito($id)
    {
        $remito = $this->remitoRepository->find($id);
        if ($remito->estadoremito !== 'Pendiente') {
            return false;
        }

        $this->remito_articuloRepository->deleteporremito($id);

        return (bool) $this->remitoRepository->delete($id);
    }

    /**
     * Persiste remito ERP al facturar pedido Bierzo (numeración ya asignada en venta).
     * Solo uso administrativo Bierzo — no gastronomía ni estacionamiento.
     */
    public function persistirDesdeFactura(array $ctx): array
    {
        $ownTx = empty($ctx['sin_transaction']);

        try {
            $venta = $ctx['venta'] ?? null;
            if (! $venta || empty($ctx['numero']) || (int) $ctx['numero'] <= 0) {
                return ['error' => 'Datos insuficientes para grabar remito'];
            }

            $puntoventaId = (int) ($ctx['puntoventa_id'] ?? 0);
            $puntoventa = $this->puntoventaRepository->find($puntoventaId);
            if (! $puntoventa) {
                return ['error' => 'Punto de venta de remito inexistente'];
            }

            $pedido = $ctx['pedido'] ?? null;
            $items = $ctx['items'] ?? [];
            if ($items === []) {
                return ['error' => 'Remito sin ítems'];
            }

            $numero = (int) $ctx['numero'];
            $codigo = 'REM R '.$puntoventa->codigo.'-'.$numero;
            $conVenta = ! empty($ctx['venta_id']) || ! empty($venta->id);

            $header = [
                'fecha' => $ctx['fecha'] ?? ($venta->fecha ?? date('Y-m-d')),
                'fechaentrega' => $ctx['fechaentrega'] ?? ($pedido->fechaentrega ?? $venta->fecha ?? date('Y-m-d')),
                'cliente_id' => $ctx['cliente_id'] ?? $venta->cliente_id,
                'condicionventa_id' => $ctx['condicionventa_id'] ?? ($pedido->condicionventa_id ?? $venta->condicionventa_id),
                'vendedor_id' => $ctx['vendedor_id'] ?? ($pedido->vendedor_id ?? $venta->vendedor_id),
                'transporte_id' => $ctx['transporte_id'] ?? ($pedido->transporte_id ?? $venta->transporte_id),
                'mventa_id' => $ctx['mventa_id'] ?? ($pedido->mventa_id ?? null),
                'zonavta_id' => $ctx['zonavta_id'] ?? ($pedido->zonavta_id ?? null),
                'cliente_entrega_id' => $ctx['cliente_entrega_id'] ?? ($pedido->cliente_entrega_id ?? $venta->cliente_entrega_id),
                'lugarentrega' => $ctx['lugarentrega'] ?? ($pedido->lugarentrega ?? $venta->lugarentrega),
                'estado' => $ctx['estado'] ?? ($conVenta ? 'F' : 'P'),
                'estadoremito' => $ctx['estadoremito'] ?? ($conVenta ? 'Facturado' : 'Pendiente'),
                'usuario_id' => Auth::id(),
                'leyenda' => $ctx['leyenda'] ?? ($pedido->leyenda ?? $venta->leyenda ?? ' '),
                'descuento' => $ctx['descuento'] ?? ($pedido->descuento ?? $venta->descuento ?? 0),
                'descuentointegrado' => $ctx['descuentointegrado'] ?? ' ',
                'codigo' => $codigo,
                'tipocomprobante' => 'REM',
                'letra' => 'R',
                'puntoventa_id' => $puntoventaId,
                'numero' => $numero,
                'pedido_id' => $ctx['pedido_id'] ?? ($pedido->id ?? null),
                'venta_id' => $ctx['venta_id'] ?? ($venta->id ?? null),
                'origen' => $ctx['origen'] ?? 'factura',
                'oblea' => $ctx['oblea'] ?? null,
            ];

            if ($ownTx) {
                DB::beginTransaction();
            }

            $remito = $this->remitoRepository->create($header);
            $nItem = 0;
            foreach ($items as $item) {
                $kilo = (float) ($item['cantidad'] ?? $item['kilo'] ?? 0);
                if ($kilo == 0.0) {
                    continue;
                }
                $nItem++;
                $this->remito_articuloRepository->create([
                    'remito_id' => $remito->id,
                    'articulo_id' => $item['articulo_id'],
                    'unidadmedida_id' => $item['unidadmedida_id'] ?? null,
                    'numeroitem' => $item['numeroitem'] ?? $nItem,
                    'caja' => $item['caja'] ?? 0,
                    'pieza' => $item['pieza'] ?? 0,
                    'kilo' => $kilo,
                    'precio' => $item['preciosindescuento'] ?? $item['precio'] ?? 0,
                    'listaprecio_id' => $item['listaprecio_id'] ?? 1,
                    'incluyeimpuesto' => $item['incluyeimpuesto'] ?? 'N',
                    'moneda_id' => $item['moneda_id'] ?? ($venta->moneda_id ?? 1),
                    'descuentoventa_id' => $item['descuentoventa_id'] ?? null,
                    'descuento' => $item['descuento'] ?? 0,
                    'descuentointegrado' => $item['descuentointegrado'] ?? '',
                    'lote_id' => $item['lote_id'] ?? null,
                    'observacion' => $item['observacion'] ?? null,
                    'estado' => $header['estado'] === 'F' ? 'F' : 'P',
                    'pedido_articulo_id' => $item['pedido_articulo_id'] ?? null,
                ]);
            }

            if ($nItem === 0) {
                throw new \RuntimeException('Remito sin kilos en ítems');
            }

            if (! empty($venta->id)) {
                $this->ventaRepository->update(['remito_id' => $remito->id], $venta->id);
            }

            if ($ownTx) {
                DB::commit();
            }

            return ['id' => $remito->id, 'codigo' => $codigo];
        } catch (\Exception $e) {
            if ($ownTx) {
                DB::rollBack();
            }

            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Genera remito desde pedido Bierzo (sin pesada en remito: usa pesada/kilo del pedido).
     * No afecta gastronomía/estacionamiento.
     */
    public function crearDesdePedido(array $data): array
    {
        $pedidoQuery = $this->pedidoQuery->leePedidoporId($data['pedido_id'] ?? 0);
        if (! $pedidoQuery) {
            return ['error' => 'Pedido inexistente'];
        }
        $pedido = $pedidoQuery[0];

        if (in_array($pedido->estadopedido, ['Facturado', 'Suspendido'], true)) {
            return ['error' => 'El pedido no admite remito en estado '.$pedido->estadopedido];
        }

        $cliente = $this->clienteQuery->traeClienteporId($data['cliente_id'] ?? $pedido->cliente_id);
        if (! $cliente) {
            return ['error' => 'Cliente inexistente'];
        }

        $ids = $data['pedido_articulo_ids'] ?? [];
        if (! is_array($ids) || $ids === []) {
            return ['error' => 'Sin ítems para remito'];
        }

        $puntoventaId = (int) ($data['puntoventaremito_id'] ?? $data['puntoventa_id'] ?? 0);
        if ($puntoventaId <= 0) {
            $puntoventaId = (int) (UsuarioPreferenciaFacturacionSupport::leer()['puntoventaremito_id'] ?? 0);
        }
        $puntoventa = $this->puntoventaRepository->find($puntoventaId);
        if (! $puntoventa) {
            return ['error' => 'Punto de venta de remito inexistente'];
        }

        UsuarioPreferenciaFacturacionSupport::guardar([
            'puntoventaremito_id' => $puntoventaId,
        ]);

        $numero = $this->ventaRepository->traeUltimoNumeroRemito('REM', 'R', $puntoventa->codigo);
        if ($numero === 'error') {
            return ['error' => 'No se pudo obtener numeración de remito'];
        }

        $articulos_id = [];
        $skus = [];
        $numeroitems = [];
        $cantidades = [];
        $piezas = [];
        $cajas = [];
        $precios = [];
        $listaprecios_id = [];
        $incluyeimpuestos = [];
        $monedas_id = [];
        $descuentos = [];
        $lineasRemito = [];
        $totalCaja = $totalKilo = $totalPieza = $totalNeto = 0.0;
        $nItem = 0;

        foreach ($ids as $pedidoArticuloId) {
            $pa = $this->pedido_articuloRepository->find($pedidoArticuloId);
            if (! $pa || $pa->pedido_id != $pedido->id || ! PedidoEstadoErpSupport::esItemPendienteFacturable($pa->estado ?? null)) {
                continue;
            }
            $kilo = (float) $pa->pesada > 0 ? (float) $pa->pesada : (float) $pa->kilo;
            if ($kilo == 0.0) {
                $sku = $pa->articulos->sku ?? $pa->articulo_id;

                return ['error' => 'Artículo '.$sku.' sin kilos para remito'];
            }

            $nItem++;
            $articulos_id[] = $pa->articulo_id;
            $skus[] = $pa->articulos->sku ?? '';
            $numeroitems[] = $nItem;
            $cantidades[] = $kilo;
            $piezas[] = $pa->pieza;
            $cajas[] = $pa->caja;
            $precios[] = $pa->precio;
            $listaprecios_id[] = $pa->listaprecio_id;
            $incluyeimpuestos[] = $pa->incluyeimpuesto;
            $monedas_id[] = $pa->moneda_id;
            $descuentos[] = $pa->descuento;

            $totalCaja += (float) $pa->caja;
            $totalPieza += (float) $pa->pieza;
            $totalKilo += $kilo;
            $totalNeto += $kilo * (float) $pa->precio;

            $lineasRemito[] = [
                'articulo_id' => $pa->articulo_id,
                'unidadmedida_id' => $pa->unidadmedida_id,
                'cantidad' => $kilo,
                'pieza' => $pa->pieza,
                'caja' => $pa->caja,
                'preciosindescuento' => $pa->precio,
                'incluyeimpuesto' => $pa->incluyeimpuesto,
                'listaprecio_id' => $pa->listaprecio_id,
                'moneda_id' => $pa->moneda_id,
                'descuento' => $pa->descuento,
                'descuentoventa_id' => $pa->descuentoventa_id,
                'lote_id' => $pa->lote_id,
                'pedido_articulo_id' => $pa->id,
            ];
        }

        if ($lineasRemito === []) {
            return ['error' => 'No hay ítems pendientes para remito'];
        }

        DB::beginTransaction();
        try {
            $dummyVenta = (object) [
                'id' => null,
                'fecha' => $data['fecharemito'] ?? date('Y-m-d'),
                'cliente_id' => $pedido->cliente_id,
                'condicionventa_id' => $pedido->condicionventa_id,
                'vendedor_id' => $pedido->vendedor_id,
                'transporte_id' => $pedido->transporte_id,
                'cliente_entrega_id' => $pedido->cliente_entrega_id,
                'lugarentrega' => $pedido->lugarentrega,
                'leyenda' => $pedido->leyenda,
                'descuento' => $pedido->descuento,
                'moneda_id' => $lineasRemito[0]['moneda_id'] ?? 1,
            ];

            $persist = $this->persistirDesdeFactura([
                'venta' => $dummyVenta,
                'pedido' => $pedido,
                'puntoventa_id' => $puntoventaId,
                'numero' => $numero,
                'items' => $lineasRemito,
                'origen' => 'pedido',
                'estadoremito' => 'Pendiente',
                'estado' => 'P',
                'venta_id' => null,
                'pedido_id' => $pedido->id,
                'sin_transaction' => true,
            ]);
            if (! empty($persist['error'])) {
                throw new \RuntimeException($persist['error']);
            }

            // Espejo Anita pendmae/pendmov vía movimiento stock (solo Bierzo admin/pedido).
            $stockData = [
                'tipotransaccion_stock_id' => $this->tipotransaccionStockRepository
                    ->findIdPorAbreviatura(config('facturacion.TIPO_REMITO')),
                'lote' => '',
                'articulos_id' => $articulos_id,
                'skus' => $skus,
                'combinaciones_id' => null,
                'modulos_id' => null,
                'items' => $numeroitems,
                'cantidades' => $cantidades,
                'piezas' => $piezas,
                'cajas' => $cajas,
                'precios' => $precios,
                'listasprecios_id' => $listaprecios_id,
                'incluyeimpuestos' => $incluyeimpuestos,
                'monedas_id' => $monedas_id,
                'descuentos' => $descuentos,
                'loteids' => null,
                'medidas' => [],
                'fecha' => $dummyVenta->fecha,
                'fechaentrega' => $pedido->fechaentrega ?? $dummyVenta->fecha,
                'deposito_id' => config('facturacion.DEPOSITO_VENTA_ID'),
                'loteimportacion_id' => null,
                'codigo' => $persist['codigo'],
                'letra' => 'R',
                'puntoventa' => $puntoventa->codigo,
                'numerocomprobante' => $numero,
                'item' => 0,
                'tipofactura' => 'PED',
                'letrafactura' => 'X',
                'sucursalfactura' => '1',
                'numerofactura' => $pedido->codigo,
                'codigocliente' => $cliente->codigo,
                'codigotransporte' => $pedido->transportes->codigo ?? 0,
                'codigovendedor' => $pedido->vendedores->codigo ?? 0,
                'codigozona' => $pedido->zonavtas->codigo ?? 0,
                'codigoprovincia' => $cliente->provincias->codigo ?? 0,
                'codigosubzona' => $cliente->subzonavtas->id ?? '0',
                'condicionventa_id' => $cliente->condicionventa_id ?? 0,
                'vendedor_id' => $pedido->vendedor_id,
                'lugarentrega' => $pedido->lugarentrega,
                'transporte_id' => $pedido->transporte_id,
                'codigocombinacion' => '',
                'pedido' => $pedido->codigo,
                'partida' => 0,
                'empresa' => $puntoventa->empresas->codigo ?? config('app.empresa'),
                'codigoabasto' => $cliente->abastos->codigo ?? 0,
                'totalseguro' => $totalNeto,
                'totalneto' => $totalNeto,
                'totalcaja' => $totalCaja,
                'totalkilo' => $totalKilo,
                'totalpieza' => $totalPieza,
                'subzona' => $cliente->subzona_id ?? 0,
                'oblea' => '',
                'cantidadmodificada' => $totalKilo,
                'usuarioalta' => Auth::user()->name ?? Auth::user()->usuario ?? 'ERP',
                'omitir_stkmov_anita' => true,
                'omitir_validacion_saldo' => true,
            ];
            $this->movimientoStockService->guardaMovimientoStock($stockData, 'create');

            DB::commit();

            return $persist;
        } catch (\Exception $e) {
            DB::rollBack();

            return ['error' => $e->getMessage()];
        }
    }

    /**
     * F5 Anita: agrega kilos de facturas del día por reparto y aplica porcentaje.
     */
    public function asignarKilosVillafranca(int $transporteId, float $porcentaje): array
    {
        if ($transporteId <= 0) {
            return ['error' => 'Reparto inválido'];
        }
        if ($porcentaje < 0 || $porcentaje > 100) {
            return ['error' => 'Porcentaje inválido'];
        }

        $fecha = Carbon::today()->toDateString();
        $ventaIds = Venta::query()
            ->whereDate('fecha', $fecha)
            ->where('transporte_id', $transporteId)
            ->pluck('id');

        if ($ventaIds->isEmpty()) {
            return ['error' => 'No hay comprobantes del día para ese reparto'];
        }

        $agg = [];
        $emisiones = Venta_Emision::query()
            ->whereIn('venta_id', $ventaIds)
            ->with('articulos')
            ->get();

        foreach ($emisiones as $em) {
            $aid = (int) $em->articulo_id;
            if ($aid <= 0) {
                continue;
            }
            if (! isset($agg[$aid])) {
                $agg[$aid] = [
                    'articulo_id' => $aid,
                    'sku' => $em->articulos->sku ?? '',
                    'descripcion' => $em->detalle ?? ($em->articulos->descripcion ?? ''),
                    'kilo' => 0.0,
                    'pieza' => 0.0,
                    'caja' => 0.0,
                    'precio' => (float) $em->precio,
                    'incluyeimpuesto' => $em->incluyeimpuesto ?? 'N',
                    'moneda_id' => $em->moneda_id,
                    'listaprecio_id' => $em->listaprecio_id ?? null,
                    'descuento' => (float) ($em->descuento ?? 0),
                    'unidadmedida_id' => $em->articulos->unidadmedida_id ?? null,
                ];
            }
            $agg[$aid]['kilo'] += (float) $em->cantidad;
            $agg[$aid]['pieza'] += (float) ($em->pieza ?? 0);
            $agg[$aid]['caja'] += (float) ($em->caja ?? 0);
        }

        $factor = 1.0 - ($porcentaje / 100.0);
        $items = [];
        foreach ($agg as $row) {
            $row['kilo'] = round($row['kilo'] * $factor, 1);
            $row['pieza'] = round($row['pieza'] * $factor, 1);
            $row['caja'] = round($row['caja'] * $factor, 1);
            if ($row['kilo'] == 0.0 && $row['pieza'] == 0.0) {
                continue;
            }
            $items[] = $row;
        }

        if ($items === []) {
            return ['error' => 'Sin kilos resultantes tras aplicar el porcentaje'];
        }

        usort($items, static function ($a, $b) {
            return strcmp((string) $a['sku'], (string) $b['sku']);
        });

        return [
            'transporte_id' => $transporteId,
            'porcentaje' => $porcentaje,
            'fecha' => $fecha,
            'origen' => 'asignakilos',
            'items' => $items,
        ];
    }

    public function marcarFacturado(int $remitoId, int $ventaId): void
    {
        $this->remitoRepository->update([
            'estadoremito' => \App\Support\Ventas\RemitoEstadosSupport::ESTADOREMITO_FACTURADO,
            'estado' => 'F',
            'venta_id' => $ventaId,
        ], $remitoId);

        foreach ($this->remito_articuloRepository->findPorRemitoId($remitoId) as $linea) {
            $this->remito_articuloRepository->update([
                'estado' => \App\Support\Ventas\RemitoEstadosSupport::LINEA_FACTURADA,
            ], $linea->id);
        }

        $this->ventaRepository->update(['remito_id' => $remitoId], $ventaId);
    }
}

<?php

namespace App\Http\Controllers\Ventas;

use App\Models\Produccion\Tarea;
use App\Models\Stock\Combinacion;
use App\Models\Stock\Mventa;
use App\Models\Stock\Talle;
use App\Queries\Stock\ArticuloQueryInterface;
use App\Queries\Ventas\ClienteQueryInterface;
use App\Queries\Ventas\OrdentrabajoQueryInterface;
use App\Repositories\Ventas\FormapagoRepositoryInterface;
use App\Repositories\Ventas\IncotermRepositoryInterface;
use App\Repositories\Ventas\PuntoventaRepositoryInterface;
use App\Repositories\Ventas\TipotransaccionRepositoryInterface;
use App\Repositories\Ventas\TransporteRepositoryInterface;
use App\Services\Ventas\OrdentrabajoService;
use App\Support\Ventas\ClientePoliticaComercialSupport;

/**
 * OT Ferli (combinación / módulo / transporte L8).
 * No altera el circuito std de AGG / Bierzo / Interforming.
 */
class OrdentrabajoFerliController extends OrdentrabajoController
{
    private $transporteRepository;

    public function __construct(
        OrdentrabajoService $ordentrabajoservice,
        OrdentrabajoQueryInterface $ordentrabajoquery,
        ClienteQueryInterface $clientequery,
        ArticuloQueryInterface $articuloquery,
        PuntoventaRepositoryInterface $puntoventarepository,
        TipotransaccionRepositoryInterface $tipotransaccionrepository,
        IncotermRepositoryInterface $incotermrepository,
        FormapagoRepositoryInterface $formpagorepository,
        TransporteRepositoryInterface $transporterepository
    ) {
        parent::__construct(
            $ordentrabajoservice,
            $ordentrabajoquery,
            $clientequery,
            $articuloquery,
            $puntoventarepository,
            $tipotransaccionrepository,
            $incotermrepository,
            $formpagorepository
        );

        $this->transporteRepository = $transporterepository;
    }

    public function editar($id)
    {
        can('editar-ordenes-de-trabajo');

        $ordentrabajo = $this->ordentrabajoQuery->leeOrdenTrabajo($id);
        $mventa_id = $articulo_id = $combinacion_id = '';
        $this->armarTablasVistaFerli(
            $cliente_query,
            $mventa_query,
            $articulo_query,
            $combinacion_query,
            $talle_query,
            $tarea_query,
            $ordentrabajo,
            $mventa_id,
            $articulo_id,
            $combinacion_id,
            $puntoventa_query,
            $tipotransaccion_query,
            $formapago_query,
            $incoterm_query,
            $transporte_query
        );

        $data = [];
        $transporte_id = 0;
        foreach ($ordentrabajo->ordentrabajoCombinacionTallesVigentes() as $ot) {
            $pct = $ot->pedido_combinacion_talles;
            $item = $pct->pedidos_combinacion;

            $medidas = [
                'talle' => $pct->talle_id,
                'nombretalle' => $pct->talles?->nombre ?? '',
                'cantidad' => $pct->cantidad,
                'precio' => $pct->precio,
            ];

            $idItem = $item->id;
            $flEncontro = false;

            for ($ii = 0; $ii < count($data); $ii++) {
                if ($data[$ii]['id'] == $idItem) {
                    $flEncontro = true;
                    break;
                }
            }

            if (!$flEncontro) {
                $combinacion = Combinacion::find($item->combinacion_id);
                $data[] = [
                    'id' => $item->id,
                    'codigo' => $item->pedido_id,
                    'pedidocombinacion_id' => $item->id,
                    'descuentopie' => $item->pedidos?->descuento ?? 0,
                    'cliente' => $ot->clientes?->nombre ?? '',
                    'cliente_id' => $ot->clientes?->id ?? '',
                    'estadocliente' => $ot->clientes?->estado ?? '',
                    'tiposuspensioncliente_id' => $ot->clientes?->tiposuspension_id ?? '',
                    'nombretiposuspensioncliente' => $ot->clientes?->tipossuspensioncliente?->nombre ?? '',
                    'politica_comercial' => ClientePoliticaComercialSupport::payload($ot->clientes),
                    'articulo' => $item->articulos?->descripcion ?? '',
                    'sku' => $item->articulos?->sku ?? '',
                    'articulo_id' => $item->articulos?->id ?? $item->articulo_id,
                    'modulo_id' => $item->modulo_id,
                    'pares' => $item->cantidad,
                    'combinacion_id' => $item->combinacion_id,
                    'nombre_combinacion' => $combinacion?->nombre ?? '',
                    'medidas' => [$medidas],
                ];

                $transporte_id = $item->pedidos?->transporte_id ?? 0;
            } else {
                $data[$ii]['medidas'][] = $medidas;
            }
        }

        $puntoventadefault_id = cache()->get(generaKey('puntoventa'));
        $puntoventaremitodefault_id = cache()->get(generaKey('puntoventaremito'));
        $tipotransacciondefault_id = cache()->get(generaKey('tipotransaccion'));

        return view('ventas.ordentrabajo_ferli.editar', compact(
            'ordentrabajo',
            'cliente_query',
            'articulo_query',
            'combinacion_query',
            'mventa_query',
            'talle_query',
            'tarea_query',
            'mventa_id',
            'articulo_id',
            'combinacion_id',
            'puntoventa_query',
            'puntoventadefault_id',
            'transporte_id',
            'tipotransaccion_query',
            'tipotransacciondefault_id',
            'data',
            'puntoventaremitodefault_id',
            'formapago_query',
            'incoterm_query',
            'transporte_query'
        ));
    }

    private function armarTablasVistaFerli(
        &$cliente_query,
        &$mventa_query,
        &$articulo_query,
        &$combinacion_query,
        &$talle_query,
        &$tarea_query,
        $ordentrabajo,
        &$mventa_id,
        &$articulo_id,
        &$combinacion_id,
        &$puntoventa_query,
        &$tipotransaccion_query,
        &$formapago_query,
        &$incoterm_query,
        &$transporte_query
    ) {
        $cliente_query = $this->clienteQuery->allQueryPorContexto(['id', 'nombre', 'codigo'], ClientePoliticaComercialSupport::OP_BOLETA);
        $mventa_query = Mventa::all();
        $talle_query = Talle::all();
        $articulo_query = $this->articuloQuery->allQuery(['id', 'sku', 'descripcion', 'mventa_id']);
        $tarea_query = Tarea::all();
        $puntoventa_query = $this->puntoventaRepository->all('A');
        $tipotransaccion_query = $this->tipotransaccionRepository->all(['V', 'C'], ['A']);
        $formapago_query = $this->formapagoRepository->all();
        $incoterm_query = $this->incotermRepository->all();
        $transporte_query = $this->transporteRepository->all();

        $pedidoCombinacion = $ordentrabajo->pedidoCombinacionVigente();
        if ($pedidoCombinacion) {
            $mventa_id = $pedidoCombinacion->articulos?->mventa_id ?? '';
            $articulo_id = $pedidoCombinacion->articulo_id;
            $combinacion_id = $pedidoCombinacion->combinacion_id;
        } else {
            $mventa_id = $articulo_id = $combinacion_id = '';
        }

        $combinacion_query = Combinacion::where('articulo_id', $articulo_id)->get();
    }
}

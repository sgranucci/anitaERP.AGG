<?php

namespace App\Http\Controllers\Stock;

use App\Exports\Stock\PickingPedidoFerliExport;
use App\Http\Controllers\Controller;
use App\Models\Stock\Depmae;
use App\Queries\Ventas\ClienteQueryInterface;
use App\Repositories\Ventas\PuntoventaRepositoryInterface;
use App\Repositories\Ventas\TipotransaccionRepositoryInterface;
use App\Repositories\Ventas\FormapagoRepositoryInterface;
use App\Repositories\Ventas\IncotermRepositoryInterface;
use App\Repositories\Ventas\TransporteRepositoryInterface;
use App\Support\Stock\MovimientoStockFerliSupport;
use App\Support\Ventas\PedidoPickingFerliSupport;
use Illuminate\Http\Request;

class PickingPedidoFerliController extends Controller
{
    public function __construct(
        private ClienteQueryInterface $clienteQuery,
        private PuntoventaRepositoryInterface $puntoventaRepository,
        private TipotransaccionRepositoryInterface $tipotransaccionRepository,
        private FormapagoRepositoryInterface $formapagoRepository,
        private IncotermRepositoryInterface $incotermRepository,
        private TransporteRepositoryInterface $transporteRepository,
    ) {}

    public function index(Request $request)
    {
        $this->assertFerli();
        can('listar-reporte-picking-pedido');

        $clienteId = (int) $request->input('cliente_id', 0);
        $depositoId = (int) $request->input('deposito_id', 0);
        $loteDesde = trim((string) $request->input('lote_desde', ''));
        $loteHasta = trim((string) $request->input('lote_hasta', ''));
        $consultar = $request->boolean('consultar');

        $lineas = collect();
        if ($consultar) {
            $lineas = PedidoPickingFerliSupport::lineasPendientes(
                $clienteId > 0 ? $clienteId : null,
                $depositoId > 0 ? $depositoId : null,
                $loteDesde !== '' ? $loteDesde : null,
                $loteHasta !== '' ? $loteHasta : null,
            );
        }

        $cliente_query = $this->clienteQuery->allQueryCargaPedido(['id', 'nombre', 'codigo']);
        $deposito_query = Depmae::query()->paraUsuarioAutorizado()->orderBy('nombre')->get();
        $puntoventa_query = $this->puntoventaRepository->all('A');
        $tipotransaccion_query = $this->tipotransaccionRepository->all(['V'], ['A']);
        $formapago_query = $this->formapagoRepository->all();
        $incoterm_query = $this->incotermRepository->all();
        $transporte_query = $this->transporteRepository->all();

        return view('stock.picking_pedido.index', [
            'lineas' => $lineas,
            'consultar' => $consultar,
            'cliente_id' => $clienteId,
            'deposito_id' => $depositoId,
            'lote_desde' => $loteDesde,
            'lote_hasta' => $loteHasta,
            'cliente_query' => $cliente_query,
            'deposito_query' => $deposito_query,
            'puntoventa_query' => $puntoventa_query,
            'tipotransaccion_query' => $tipotransaccion_query,
            'formapago_query' => $formapago_query,
            'incoterm_query' => $incoterm_query,
            'transporte_query' => $transporte_query,
            'puede_facturar' => can('facturar-picking-pedido', false),
        ]);
    }

    public function exportarExcel(Request $request)
    {
        $this->assertFerli();
        can('listar-reporte-picking-pedido');

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $clienteId = (int) $request->input('cliente_id', 0);
        $depositoId = (int) $request->input('deposito_id', 0);
        $loteDesde = trim((string) $request->input('lote_desde', ''));
        $loteHasta = trim((string) $request->input('lote_hasta', ''));

        $lineas = PedidoPickingFerliSupport::lineasPendientes(
            $clienteId > 0 ? $clienteId : null,
            $depositoId > 0 ? $depositoId : null,
            $loteDesde !== '' ? $loteDesde : null,
            $loteHasta !== '' ? $loteHasta : null,
        );

        $tituloFiltros = $this->subtituloFiltros($request);

        return (new PickingPedidoFerliExport(
            PedidoPickingFerliSupport::filasExcelFragola($lineas),
            $tituloFiltros,
        ))->download('picking_pedido_fragola.xlsx');
    }

    public function payloadFactura(Request $request)
    {
        $this->assertFerli();
        can('facturar-picking-pedido');

        $ids = (array) $request->input('pedido_combinacion_id', []);
        $payload = PedidoPickingFerliSupport::payloadModalFactura($ids);

        if (! empty($payload['error'])) {
            return response()->json($payload, 422);
        }

        return response()->json($payload);
    }

    public function marcar(Request $request)
    {
        $this->assertFerli();
        can('listar-reporte-picking-pedido');

        $id = (int) $request->input('pedido_combinacion_id', 0);
        $lote = (string) $request->input('picking_lote_codigo', '');
        $depositoId = (int) $request->input('picking_deposito_id', 0);

        $result = PedidoPickingFerliSupport::marcar(
            $id,
            $lote,
            $depositoId > 0 ? $depositoId : null,
        );

        if (! empty($result['error'])) {
            return response()->json($result, 422);
        }

        return response()->json($result);
    }

    public function consultaLotesStock(Request $request)
    {
        $this->assertFerli();
        can('listar-reporte-picking-pedido');

        $articuloId = (int) $request->input('articulo_id', 0);
        $combinacionId = (int) $request->input('combinacion_id', 0);
        $moduloId = (int) $request->input('modulo_id', 0);
        $texto = trim((string) $request->input('texto', $request->input('consulta', '')));
        $soloModuloLinea = $request->boolean('solo_modulo_linea');

        $result = PedidoPickingFerliSupport::consultaLotesStockPendientes(
            $articuloId,
            $combinacionId,
            $soloModuloLinea && $moduloId > 0 ? $moduloId : null,
            $texto !== '' ? $texto : null,
        );

        if (! empty($result['error'])) {
            return response()->json($result, 422);
        }

        return response()->json($result);
    }

    public function desmarcar(Request $request)
    {
        $this->assertFerli();
        can('listar-reporte-picking-pedido');

        $id = (int) $request->input('pedido_combinacion_id', 0);
        $result = PedidoPickingFerliSupport::desmarcar($id);

        if (! empty($result['error'])) {
            return response()->json($result, 422);
        }

        return response()->json($result);
    }

    private function assertFerli(): void
    {
        if (! PedidoPickingFerliSupport::habilitado() && ! MovimientoStockFerliSupport::esCalzadosFerli()) {
            abort(404);
        }
    }

    private function subtituloFiltros(Request $request): string
    {
        $partes = [];
        if ((int) $request->input('cliente_id') > 0) {
            $partes[] = 'Cliente #'.(int) $request->input('cliente_id');
        }
        if ((int) $request->input('deposito_id') > 0) {
            $partes[] = 'Depósito #'.(int) $request->input('deposito_id');
        }
        $desde = trim((string) $request->input('lote_desde', ''));
        $hasta = trim((string) $request->input('lote_hasta', ''));
        if ($desde !== '' || $hasta !== '') {
            $partes[] = 'Lote '.$desde.' / '.$hasta;
        }

        return $partes === [] ? 'Pendientes de facturar' : implode(' | ', $partes);
    }
}

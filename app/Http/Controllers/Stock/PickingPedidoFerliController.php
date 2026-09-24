<?php

namespace App\Http\Controllers\Stock;

use App\Exports\Stock\PickingPedidoFerliExport;
use App\Http\Controllers\Controller;
use App\Models\Stock\Depmae;
use App\Queries\Ventas\ClienteQueryInterface;
use App\Repositories\Ventas\FormapagoRepositoryInterface;
use App\Repositories\Ventas\IncotermRepositoryInterface;
use App\Repositories\Ventas\PuntoventaRepositoryInterface;
use App\Repositories\Ventas\TipotransaccionRepositoryInterface;
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
        $pickingId = (int) $request->input('picking_id', 0);
        $pickingCodigo = (int) $request->input('picking_codigo', 0);
        $consultar = $request->boolean('consultar');

        if ($pickingId > 0) {
            PedidoPickingFerliSupport::setPickingActivoId($pickingId);
        } elseif ($pickingCodigo > 0) {
            $cab = PedidoPickingFerliSupport::findPicking(null, $pickingCodigo);
            if ($cab) {
                $pickingId = (int) $cab->id;
                PedidoPickingFerliSupport::setPickingActivoId($pickingId);
            }
        }

        $pickingActivo = PedidoPickingFerliSupport::findPicking(
            $pickingId > 0 ? $pickingId : PedidoPickingFerliSupport::pickingActivoId()
        );

        $lineas = collect();
        $tienePicking = $pickingId > 0 || $pickingCodigo > 0;
        if ($consultar) {
            $lineas = PedidoPickingFerliSupport::lineasPendientes(
                $clienteId > 0 ? $clienteId : null,
                $depositoId > 0 ? $depositoId : null,
                $loteDesde !== '' ? $loteDesde : null,
                $loteHasta !== '' ? $loteHasta : null,
                $pickingId > 0 ? $pickingId : null,
                $pickingId <= 0 && $pickingCodigo > 0 ? $pickingCodigo : null,
                $tienePicking,
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
            'picking_id' => $pickingActivo?->id ?? $pickingId,
            'picking_codigo' => $pickingActivo?->codigo ?? ($pickingCodigo > 0 ? $pickingCodigo : ''),
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
        $pickingId = (int) $request->input('picking_id', 0);
        $pickingCodigo = (int) $request->input('picking_codigo', 0);

        $tienePicking = $pickingId > 0 || $pickingCodigo > 0;
        $lineas = PedidoPickingFerliSupport::lineasPendientes(
            $clienteId > 0 ? $clienteId : null,
            $depositoId > 0 ? $depositoId : null,
            $loteDesde !== '' ? $loteDesde : null,
            $loteHasta !== '' ? $loteHasta : null,
            $pickingId > 0 ? $pickingId : null,
            $pickingId <= 0 && $pickingCodigo > 0 ? $pickingCodigo : null,
            $tienePicking,
        );

        $idsSeleccion = array_values(array_filter(
            array_map('intval', (array) $request->input('pedido_combinacion_id', [])),
            static fn (int $id) => $id > 0
        ));
        if ($idsSeleccion !== []) {
            $idsFlip = array_flip($idsSeleccion);
            $lineas = $lineas
                ->filter(static fn ($linea) => isset($idsFlip[(int) $linea->id]))
                ->values();
            if ($lineas->isEmpty()) {
                return redirect()
                    ->route('picking_pedido', $request->except('pedido_combinacion_id'))
                    ->with('error', 'Ninguna de las líneas seleccionadas está disponible para exportar.');
            }
        }

        $picking = PedidoPickingFerliSupport::findPicking(
            $pickingId > 0 ? $pickingId : null,
            $pickingCodigo > 0 ? $pickingCodigo : null,
        );
        $filas = PedidoPickingFerliSupport::filasExcelFragola($lineas);
        $encabezado = PedidoPickingFerliSupport::encabezadoExcel(
            $filas,
            $picking,
            $this->subtituloFiltrosExtra($request),
        );

        return (new PickingPedidoFerliExport(
            $filas,
            $encabezado['titulo'],
            $encabezado['lineas'],
        ))->download('picking_pedido.xlsx');
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
        $ordentrabajoId = (int) $request->input('picking_ordentrabajo_id', 0);
        $pickingId = (int) $request->input('picking_id', 0);
        $pickingCodigo = (int) $request->input('picking_codigo', 0);

        $result = PedidoPickingFerliSupport::marcar(
            $id,
            $lote,
            $depositoId > 0 ? $depositoId : null,
            $pickingId > 0 ? $pickingId : null,
            $pickingCodigo > 0 ? $pickingCodigo : null,
            $ordentrabajoId > 0 ? $ordentrabajoId : null,
        );

        if (! empty($result['error'])) {
            return response()->json($result, 422);
        }

        return response()->json($result);
    }

    public function crearPicking(Request $request)
    {
        $this->assertFerli();
        can('listar-reporte-picking-pedido');

        $fecha = trim((string) $request->input('fecha', ''));
        $obs = trim((string) $request->input('observacion', ''));
        $picking = PedidoPickingFerliSupport::crearPicking(
            $fecha !== '' ? $fecha : null,
            $obs !== '' ? $obs : null,
        );

        return response()->json([
            'ok' => true,
            'id' => (int) $picking->id,
            'codigo' => (int) $picking->codigo,
            'fecha' => $picking->fecha?->format('Y-m-d'),
        ]);
    }

    public function consultaPickingsDia(Request $request)
    {
        $this->assertFerli();
        can('listar-reporte-picking-pedido');

        $fecha = trim((string) $request->input('fecha', now()->toDateString()));
        $texto = trim((string) $request->input('texto', ''));

        return response()->json([
            'filas' => PedidoPickingFerliSupport::listarPendientesDia(
                $fecha !== '' ? $fecha : null,
                $texto !== '' ? $texto : null,
            ),
            'fecha' => $fecha !== '' ? $fecha : now()->toDateString(),
        ]);
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

    private function subtituloFiltrosExtra(Request $request): string
    {
        $partes = [];
        if ((int) $request->input('deposito_id') > 0) {
            $partes[] = 'Depósito #'.(int) $request->input('deposito_id');
        }
        $desde = trim((string) $request->input('lote_desde', ''));
        $hasta = trim((string) $request->input('lote_hasta', ''));
        if ($desde !== '' || $hasta !== '') {
            $partes[] = 'Lote '.$desde.' / '.$hasta;
        }

        return implode(' | ', $partes);
    }
}

<?php

namespace App\Http\Controllers\Ventas;

use App\Exports\Ventas\PedidoInterformingListadoExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\ValidacionPedidoInterforming;
use App\Models\Configuracion\Moneda;
use App\Models\Stock\Listaprecio;
use App\Models\Stock\Unidadmedida;
use App\Models\Ventas\Condicionventa;
use App\Models\Ventas\PedidoInterforming;
use App\Models\Ventas\Vendedor;
use App\Repositories\Ventas\MotivocierrepedidoRepositoryInterface;
use App\Services\Ventas\PedidoInterformingPdfService;
use App\Services\Ventas\PedidoInterformingService;
use App\Support\Ventas\PedidoEstadosInterforming;
use App\Support\Ventas\PedidoInterformingListadoFiltros;
use App\Support\Ventas\PedidoInterformingSupport;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Excel;

class PedidoInterformingController extends Controller
{
    private PedidoInterformingService $pedidoService;

    private PedidoInterformingPdfService $pedidoPdfService;

    private MotivocierrepedidoRepositoryInterface $motivocierrepedidoRepository;

    public function __construct(
        PedidoInterformingService $pedidoService,
        PedidoInterformingPdfService $pedidoPdfService,
        MotivocierrepedidoRepositoryInterface $motivocierrepedidoRepository
    ) {
        $this->pedidoService = $pedidoService;
        $this->pedidoPdfService = $pedidoPdfService;
        $this->motivocierrepedidoRepository = $motivocierrepedidoRepository;
    }

    public function index(Request $request)
    {
        PedidoInterformingSupport::abortSiNoInterforming();
        can('listar-pedidos');

        $filtros = PedidoInterformingListadoFiltros::resolverDesdeRequest($request);
        $datas = $this->pedidoService->leePedidos($filtros, true);

        return view(PedidoInterformingSupport::vista('index'), [
            'datas' => $datas,
            'filtros' => $filtros,
            'filtrosQuery' => PedidoInterformingListadoFiltros::paraQueryString($filtros),
            'camposFiltro' => PedidoInterformingListadoFiltros::CAMPOS,
        ]);
    }

    public function listar(Request $request, $formato = null, $busqueda = null)
    {
        PedidoInterformingSupport::abortSiNoInterforming();
        can('listar-pedidos');

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $filtros = PedidoInterformingListadoFiltros::resolverDesdeRequest($request, $busqueda);

        switch ($formato) {
            case 'PDF':
                $datas = $this->pedidoService->leePedidos($filtros, false);
                $view = \View::make(PedidoInterformingSupport::vista('listado'), [
                    'datas' => $datas,
                    'filtros' => $filtros,
                    'subtituloFiltros' => PedidoInterformingListadoFiltros::subtituloFiltros($filtros),
                ])->render();
                $path = storage_path('pdf/listados');
                if (! is_dir($path)) {
                    mkdir($path, 0755, true);
                }
                $nombre_pdf = 'listado_pedido_interforming';
                $pdf = \App::make('dompdf.wrapper');
                $pdf->setPaper('legal', 'landscape');
                $pdf->loadHTML($view)->save($path.'/'.$nombre_pdf.'.pdf');

                return response()->download($path.'/'.$nombre_pdf.'.pdf');

            case 'EXCEL':
                return (new PedidoInterformingListadoExport($this->pedidoService))
                    ->parametros($filtros)
                    ->download('pedido_interforming.xlsx');

            case 'CSV':
                return (new PedidoInterformingListadoExport($this->pedidoService))
                    ->parametros($filtros)
                    ->download('pedido_interforming.csv', Excel::CSV);
        }

        return redirect()->route('pedido', PedidoInterformingListadoFiltros::paraQueryString($filtros));
    }

    public function crear()
    {
        PedidoInterformingSupport::abortSiNoInterforming();
        can('crear-pedidos');

        $pedido = new PedidoInterforming([
            'fecha' => now()->toDateString(),
            'fechaentrega' => now()->toDateString(),
            'estadopedido' => PedidoEstadosInterforming::CAB_PENTREGAR,
            'tipo_comprobante' => 'PED',
            'letra_comprobante' => 'X',
            'sucursal_comprobante' => 1,
            'cotizacion' => 1,
        ]);
        $pedido->pedido_articulos = collect();

        return view(PedidoInterformingSupport::vista('crear'), $this->datosFormulario($pedido, 'crear'));
    }

    public function guardar(ValidacionPedidoInterforming $request)
    {
        PedidoInterformingSupport::abortSiNoInterforming();
        can('crear-pedidos');

        $data = $this->pedidoService->guardar($request->validated(), 'create');
        if (isset($data['error'])) {
            return back()->withInput()->with('errores', [$data['error']]);
        }

        return redirect()->route('pedido')->with(
            'mensaje',
            'Pedido '.$data['id'].' '.$data['codigo'].' creado con éxito'
        );
    }

    public function editar($id)
    {
        PedidoInterformingSupport::abortSiNoInterforming();
        $soloConsulta = request()->query('origen') === 'modal_consulta';
        if ($soloConsulta) {
            if (! can('editar-pedidos', false) && ! can('listar-pedidos', false)) {
                can('listar-pedidos');
            }
        } else {
            can('editar-pedidos');
        }

        $pedido = $this->pedidoService->leePedido((int) $id);
        if (! $pedido) {
            return redirect()->route('pedido')->with('errores', ['Pedido inexistente']);
        }

        $puedeActualizarPedido = can('actualizar-pedidos', false);
        $ocultarVolver = $soloConsulta;

        return view(
            PedidoInterformingSupport::vista('editar'),
            array_merge(
                $this->datosFormulario($pedido, 'editar'),
                compact('soloConsulta', 'puedeActualizarPedido', 'ocultarVolver')
            )
        );
    }

    public function actualizar(ValidacionPedidoInterforming $request, $id)
    {
        PedidoInterformingSupport::abortSiNoInterforming();
        can('actualizar-pedidos');

        $payload = $request->validated();
        $payload['id'] = (int) $id;
        $data = $this->pedidoService->guardar($payload, 'update');
        if (isset($data['error'])) {
            return back()->withInput()->with('errores', [$data['error']]);
        }

        return redirect()->route('pedido')->with(
            'mensaje',
            'Pedido '.$data['id'].' actualizado con éxito'
        );
    }

    public function eliminar($id)
    {
        PedidoInterformingSupport::abortSiNoInterforming();
        can('borrar-pedidos');

        $data = $this->pedidoService->eliminar((int) $id);
        if (isset($data['error'])) {
            return back()->with('errores', [$data['error']]);
        }

        return redirect()->route('pedido')->with('mensaje', 'Pedido eliminado');
    }

    public function limpiafiltro(Request $request)
    {
        PedidoInterformingSupport::abortSiNoInterforming();
        session()->forget('filtrosPedidosInterforming');

        return response()->json(['ok' => true]);
    }

    public function listarPedidoPdf($id)
    {
        PedidoInterformingSupport::abortSiNoInterforming();
        can('listar-pedidos');

        return $this->pedidoPdfService->descargar((int) $id);
    }

    /**
     * @return array<string, mixed>
     */
    private function datosFormulario(PedidoInterforming $pedido, string $funcion): array
    {
        return [
            'pedido' => $pedido,
            'datos' => ['funcion' => $funcion],
            'vendedor_query' => Vendedor::orderBy('nombre')->get(),
            'condicionventa_query' => Condicionventa::orderBy('nombre')->get(),
            'moneda_query' => Moneda::orderBy('nombre')->get(),
            'listaprecio_query' => Listaprecio::orderBy('nombre')->get(),
            'unidadmedida_query' => Unidadmedida::orderBy('nombre')->get(),
            'motivocierrepedido_query' => $this->motivocierrepedidoRepository->all(),
            'estadosCabecera' => PedidoEstadosInterforming::etiquetasCabecera(),
            'estadosItem' => PedidoEstadosInterforming::etiquetasItem(),
        ];
    }
}

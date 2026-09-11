<?php

namespace App\Http\Controllers\Compras;

use App\Exports\Compras\Listaprecio_ProveedorExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\ValidacionListaprecio_Proveedor;
use App\Models\Compras\Listaprecio_Proveedor;
use App\Models\Compras\Listaprecio_Proveedor_Estado;
use App\Queries\Compras\Listaprecio_ProveedorQueryInterface;
use App\Repositories\Compras\CondicioncompraRepositoryInterface;
use App\Repositories\Compras\CondicionentregaRepositoryInterface;
use App\Repositories\Compras\CondicionpagoRepositoryInterface;
use App\Repositories\Compras\Listaprecio_ProveedorRepositoryInterface;
use App\Repositories\Configuracion\MonedaRepositoryInterface;
use App\Services\Compras\Listaprecio_ProveedorService;
use App\Services\Compras\ListaprecioProveedorImportPreviewService;
use App\Support\Compras\ListaprecioProveedorConsultaDesdeModal;
use App\Support\Compras\ListaprecioProveedorListadoFiltros;
use App\Support\Listado\QueryRetornoListado;
use Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class Listaprecio_ProveedorController extends Controller
{
    public function __construct(
        private Listaprecio_ProveedorRepositoryInterface $repository,
        private Listaprecio_ProveedorQueryInterface $query,
        private Listaprecio_ProveedorService $service,
        private CondicionpagoRepositoryInterface $condicionpagoRepository,
        private CondicionentregaRepositoryInterface $condicionentregaRepository,
        private CondicioncompraRepositoryInterface $condicioncompraRepository,
        private MonedaRepositoryInterface $monedaRepository,
        private ListaprecioProveedorImportPreviewService $importPreviewService,
    ) {}

    public function index(Request $request)
    {
        can('listar-listaprecio-proveedor');

        if (! Listaprecio_Proveedor::query()->exists()) {
            $this->repository->sincronizarConAnita();
        }

        $filtros = ListaprecioProveedorListadoFiltros::resolverDesdeRequest($request);
        $listas = $this->query->leeListas($filtros, true);

        return view('compras.listaprecio_proveedor.index', [
            'listas' => $listas,
            'filtros' => $filtros,
            'filtrosQuery' => ListaprecioProveedorListadoFiltros::paraQueryString($filtros),
            'camposFiltro' => ListaprecioProveedorListadoFiltros::CAMPOS,
        ]);
    }

    public function listar(Request $request, $formato = null, $busqueda = null)
    {
        can('listar-listaprecio-proveedor');

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $filtros = ListaprecioProveedorListadoFiltros::resolverDesdeRequest($request, $busqueda);

        switch ($formato) {
            case 'PDF':
                $listas = $this->query->leeListas($filtros, false);

                $view = \View::make('compras.listaprecio_proveedor.listado', compact('listas', 'filtros'))
                    ->render();
                $path = storage_path('pdf/listados');
                $nombre_pdf = 'listado_listaprecio_proveedor';

                $pdf = \App::make('dompdf.wrapper');
                $pdf->setPaper('legal', 'landscape');
                $pdf->loadHTML($view)->save($path.'/'.$nombre_pdf.'.pdf');

                return response()->download($path.'/'.$nombre_pdf.'.pdf');

            case 'EXCEL':
                return (new Listaprecio_ProveedorExport($this->query))
                    ->parametros($filtros)
                    ->download('listaprecio_proveedor.xlsx');

            case 'CSV':
                return (new Listaprecio_ProveedorExport($this->query))
                    ->parametros($filtros)
                    ->download('listaprecio_proveedor.csv', \Maatwebsite\Excel\Excel::CSV);
        }

        return redirect()->route('consultar_listaprecio_proveedor', ListaprecioProveedorListadoFiltros::paraQueryString($filtros));
    }

    public function crear(Request $request)
    {
        can('crear-listaprecio-proveedor');

        $condicionpago_query = $this->condicionpagoRepository->all();
        $condicionentrega_query = $this->condicionentregaRepository->all();
        $condicioncompra_query = $this->condicioncompraRepository->all();
        $moneda_query = $this->monedaRepository->all();
        $estado_enum = Listaprecio_Proveedor_Estado::$enumEstado;
        $data = null;
        $filtrosQuery = QueryRetornoListado::desdeRequestSiIndex($request, ListaprecioProveedorListadoFiltros::class);

        return view('compras.listaprecio_proveedor.crear', compact(
            'data',
            'condicionpago_query',
            'condicionentrega_query',
            'condicioncompra_query',
            'moneda_query',
            'estado_enum',
            'filtrosQuery'
        ));
    }

    public function guardar(ValidacionListaprecio_Proveedor $request)
    {
        can('crear-listaprecio-proveedor');

        $ret = $this->service->guarda($request);
        if ($ret['mensaje'] === 'ok') {
            $msg = 'Lista de precios creada con éxito';
            if (isset($ret['importados'])) {
                $msg .= ' '.Listaprecio_ProveedorService::mensajeImportacion(
                    (int) $ret['importados'],
                    $ret['errores_excel'] ?? []
                );
            }

            return redirect()
                ->route('consultar_listaprecio_proveedor', QueryRetornoListado::desdeRequest($request, ListaprecioProveedorListadoFiltros::class))
                ->with('mensaje', $msg);
        }

        return redirect()->back()->withInput()->with('mensaje', $ret['errores'] ?? 'Error al guardar');
    }

    public function editar(Request $request, $id)
    {
        $soloConsulta = $request->query('origen') === 'modal_consulta';
        if ($soloConsulta) {
            if (! ListaprecioProveedorConsultaDesdeModal::puedeConsultar()) {
                abort(403);
            }
        } else {
            can('editar-listaprecio-proveedor');
        }

        $data = $this->repository->find($id);
        $condicionpago_query = $this->condicionpagoRepository->all();
        $condicionentrega_query = $this->condicionentregaRepository->all();
        $condicioncompra_query = $this->condicioncompraRepository->all();
        $moneda_query = $this->monedaRepository->all();
        $estado_enum = Listaprecio_Proveedor_Estado::$enumEstado;
        $ocultarVolver = $soloConsulta;
        $puedeModificarLista = $this->puedeModificarLista();
        $visualizar = $soloConsulta && ! $puedeModificarLista;
        $filtrosQuery = QueryRetornoListado::desdeRequestSiIndex($request, ListaprecioProveedorListadoFiltros::class);

        return view('compras.listaprecio_proveedor.editar', compact(
            'data',
            'condicionpago_query',
            'condicionentrega_query',
            'condicioncompra_query',
            'moneda_query',
            'estado_enum',
            'soloConsulta',
            'ocultarVolver',
            'puedeModificarLista',
            'visualizar',
            'filtrosQuery'
        ));
    }

    public function actualizar(ValidacionListaprecio_Proveedor $request, $id)
    {
        $this->autorizarModificarLista();

        $ret = $this->service->actualiza($request, (int) $id);
        if ($ret['mensaje'] === 'ok') {
            if ($request->input('origen') === 'modal_consulta') {
                return redirect()
                    ->route('editar_listaprecio_proveedor', [
                        'id' => $id,
                        'origen' => 'modal_consulta',
                        'vista' => 'consulta',
                    ])
                    ->with('mensaje', 'Lista de precios actualizada con éxito');
            }

            return redirect()->route(
                'consultar_listaprecio_proveedor',
                QueryRetornoListado::desdeRequest($request, ListaprecioProveedorListadoFiltros::class)
            )->with('mensaje', 'Lista de precios actualizada con éxito');
        }

        return redirect()->back()->withInput()->with('mensaje', $ret['errores'] ?? 'Error al actualizar');
    }

    public function eliminar(Request $request, $id)
    {
        can('borrar-listaprecio-proveedor');

        if ($request->ajax()) {
            if ($this->repository->delete($id)) {
                return response()->json(['mensaje' => 'ok']);
            }

            return response()->json(['mensaje' => 'ng']);
        }

        abort(404);
    }

    public function cambiarEstado(Request $request, $id)
    {
        can('actualizar-listaprecio-proveedor');

        $request->validate([
            'observacion' => 'nullable|string|max:65535',
        ]);

        $ret = $this->service->cambiarEstado((int) $id, (string) ($request->observacion ?? ''));

        if ($request->ajax()) {
            return response()->json($ret);
        }

        if ($ret['mensaje'] === 'ok') {
            return redirect()->route(
                'consultar_listaprecio_proveedor',
                QueryRetornoListado::desdeRequest($request, ListaprecioProveedorListadoFiltros::class)
            )->with('mensaje', 'Estado actualizado');
        }

        return redirect()->back()->with('mensaje', $ret['errores'] ?? 'No se pudo cambiar el estado');
    }

    public function leerHistoria($listaprecio_proveedor_id)
    {
        can('listar-listaprecio-proveedor');

        return $this->service->leeHistoriaJson((int) $listaprecio_proveedor_id);
    }

    public function previewImportacion(Request $request)
    {
        $this->autorizarImportarLista();

        $request->validate($this->reglasArchivoImportacion([
            'proveedor_id' => 'nullable|integer|min:1',
        ]));

        $archivo = $request->file('archivoexcel') ?? $request->file('archivo');

        try {
            $preview = $this->importPreviewService->previsualizar(
                $archivo,
                $request->filled('proveedor_id') ? (int) $request->input('proveedor_id') : null,
                $request->input('col_sku'),
                $request->input('col_descripcion'),
                $request->input('col_precio'),
                $request->input('col_descuento'),
                $request->input('col_codigo_proveedor'),
                $request->filled('fila_encabezado') ? (int) $request->input('fila_encabezado') : null,
                $request->filled('hoja_indice') ? (int) $request->input('hoja_indice') : null
            );

            return response()->json($preview);
        } catch (\Throwable $e) {
            Log::warning('listaprecio_proveedor.importar_excel.preview_fallo', [
                'mensaje' => $e->getMessage(),
                'archivo' => $archivo?->getClientOriginalName(),
            ]);

            return response()->json(['message' => 'Error al analizar el Excel: '.$e->getMessage()], 422);
        }
    }

    public function importarExcel(Request $request, $id)
    {
        $this->autorizarModificarLista();

        $request->validate(array_merge($this->reglasArchivoImportacion(), [
            'fechavigencia' => 'required|date',
        ]));

        $archivo = $request->file('archivoexcel') ?? $request->file('archivo');

        try {
            $retImp = $this->service->importarDesdeArchivo(
                $archivo,
                (string) $request->input('fechavigencia'),
                (int) $id,
                Auth::user()->id,
                Listaprecio_ProveedorService::opcionesImportacionDesdeRequest($request)
            );
            $this->repository->persistirEnAnita((int) $id);
        } catch (\Exception $e) {
            $mensaje = 'Error al leer el archivo: '.$e->getMessage();
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json(['mensaje' => $mensaje], 422);
            }

            return redirect()->back()->with('mensaje', $mensaje);
        }

        $msg = Listaprecio_ProveedorService::mensajeImportacion(
            $retImp['importados'],
            $retImp['errores']
        );

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'ok' => true,
                'mensaje' => $msg,
                'importados' => $retImp['importados'],
                'errores' => $retImp['errores'],
            ]);
        }

        return redirect()->route(
            'editar_listaprecio_proveedor',
            array_merge(['id' => (int) $id], QueryRetornoListado::desdeRequest($request, ListaprecioProveedorListadoFiltros::class))
        )->with('mensaje', $msg);
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function reglasArchivoImportacion(array $extra = []): array
    {
        return array_merge([
            'archivoexcel' => [
                'required_without:archivo',
                'nullable',
                'file',
                'max:10240',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if ($value === null) {
                        return;
                    }
                    $ext = strtolower((string) $value->getClientOriginalExtension());
                    if (! in_array($ext, ['xlsx', 'xls', 'csv'], true)) {
                        $fail('El archivo debe ser Excel (.xlsx, .xls) o CSV.');
                    }
                },
            ],
            'archivo' => [
                'required_without:archivoexcel',
                'nullable',
                'file',
                'max:10240',
            ],
            'col_sku' => 'nullable|string|max:100',
            'col_descripcion' => 'nullable|string|max:100',
            'col_precio' => 'nullable|string|max:100',
            'col_descuento' => 'nullable|string|max:100',
            'col_codigo_proveedor' => 'nullable|string|max:100',
            'fila_encabezado' => 'nullable|integer|min:1|max:50',
            'hoja_indice' => 'nullable|integer|min:1|max:50',
        ], $extra);
    }

    private function autorizarImportarLista(): void
    {
        if (
            can('crear-listaprecio-proveedor', false)
            || can('actualizar-listaprecio-proveedor', false)
            || can('editar-listaprecio-proveedor', false)
        ) {
            return;
        }

        abort(403);
    }

    private function puedeModificarLista(): bool
    {
        return can('actualizar-listaprecio-proveedor', false)
            || can('editar-listaprecio-proveedor', false);
    }

    private function autorizarModificarLista(): void
    {
        if (! $this->puedeModificarLista()) {
            abort(403);
        }
    }
}

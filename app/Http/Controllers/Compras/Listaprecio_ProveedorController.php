<?php

namespace App\Http\Controllers\Compras;

use App\Exports\Compras\Listaprecio_ProveedorExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\ValidacionListaprecio_Proveedor;
use App\Imports\Compras\Listaprecio_ProveedorArticuloImport;
use App\Models\Compras\Listaprecio_Proveedor;
use App\Models\Compras\Listaprecio_Proveedor_Estado;
use App\Models\Compras\Proveedor;
use App\Queries\Compras\Listaprecio_ProveedorQueryInterface;
use App\Repositories\Compras\CondicioncompraRepositoryInterface;
use App\Repositories\Compras\CondicionentregaRepositoryInterface;
use App\Repositories\Compras\CondicionpagoRepositoryInterface;
use App\Repositories\Compras\Listaprecio_Proveedor_ArticuloRepositoryInterface;
use App\Repositories\Compras\Listaprecio_ProveedorRepositoryInterface;
use App\Repositories\Configuracion\MonedaRepositoryInterface;
use App\Repositories\Stock\ArticuloRepositoryInterface;
use App\Services\Compras\Listaprecio_ProveedorService;
use Auth;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

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
        private Listaprecio_Proveedor_ArticuloRepositoryInterface $listaprecioArticuloRepository,
        private ArticuloRepositoryInterface $articuloRepository,
    ) {}

    public function index(Request $request)
    {
        can('listar-listaprecio-proveedor');

        if (! Listaprecio_Proveedor::query()->exists()) {
            $this->repository->sincronizarConAnita();
        }

        $busqueda = $request->busqueda;
        $listas = $this->query->leeListas($busqueda, true);
        $estado_enum = Listaprecio_Proveedor_Estado::$enumEstado;

        return view('compras.listaprecio_proveedor.index', compact('listas', 'busqueda', 'estado_enum'));
    }

    public function listar(Request $request, $formato = null, $busqueda = null)
    {
        can('listar-listaprecio-proveedor');

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        switch ($formato) {
            case 'PDF':
                $listas = $this->query->leeListas($busqueda, false);

                $view = \View::make('compras.listaprecio_proveedor.listado', compact('listas'))
                    ->render();
                $path = storage_path('pdf/listados');
                $nombre_pdf = 'listado_listaprecio_proveedor';

                $pdf = \App::make('dompdf.wrapper');
                $pdf->setPaper('legal', 'landscape');
                $pdf->loadHTML($view)->save($path.'/'.$nombre_pdf.'.pdf');

                return response()->download($path.'/'.$nombre_pdf.'.pdf');

            case 'EXCEL':
                return (new Listaprecio_ProveedorExport($this->query))
                    ->parametros($busqueda)
                    ->download('listaprecio_proveedor.xlsx');

            case 'CSV':
                return (new Listaprecio_ProveedorExport($this->query))
                    ->parametros($busqueda)
                    ->download('listaprecio_proveedor.csv', \Maatwebsite\Excel\Excel::CSV);
        }

        $listas = $this->query->leeListas($busqueda, true);
        $estado_enum = Listaprecio_Proveedor_Estado::$enumEstado;

        return view('compras.listaprecio_proveedor.index', compact('listas', 'busqueda', 'estado_enum'));
    }

    public function crear()
    {
        can('crear-listaprecio-proveedor');

        $proveedor_query = Proveedor::orderBy('nombre')->get();
        $condicionpago_query = $this->condicionpagoRepository->all();
        $condicionentrega_query = $this->condicionentregaRepository->all();
        $condicioncompra_query = $this->condicioncompraRepository->all();
        $moneda_query = $this->monedaRepository->all();
        $estado_enum = Listaprecio_Proveedor_Estado::$enumEstado;
        $data = null;

        return view('compras.listaprecio_proveedor.crear', compact(
            'data',
            'proveedor_query',
            'condicionpago_query',
            'condicionentrega_query',
            'condicioncompra_query',
            'moneda_query',
            'estado_enum'
        ));
    }

    public function guardar(ValidacionListaprecio_Proveedor $request)
    {
        can('crear-listaprecio-proveedor');

        $ret = $this->service->guarda($request);
        if ($ret['mensaje'] === 'ok') {
            return redirect()->route('consultar_listaprecio_proveedor')->with('mensaje', 'Lista de precios creada con éxito');
        }

        return redirect()->back()->withInput()->with('mensaje', $ret['errores'] ?? 'Error al guardar');
    }

    public function editar($id)
    {
        can('editar-listaprecio-proveedor');

        $data = $this->repository->find($id);
        $proveedor_query = Proveedor::orderBy('nombre')->get();
        $condicionpago_query = $this->condicionpagoRepository->all();
        $condicionentrega_query = $this->condicionentregaRepository->all();
        $condicioncompra_query = $this->condicioncompraRepository->all();
        $moneda_query = $this->monedaRepository->all();
        $estado_enum = Listaprecio_Proveedor_Estado::$enumEstado;

        return view('compras.listaprecio_proveedor.editar', compact(
            'data',
            'proveedor_query',
            'condicionpago_query',
            'condicionentrega_query',
            'condicioncompra_query',
            'moneda_query',
            'estado_enum'
        ));
    }

    public function actualizar(ValidacionListaprecio_Proveedor $request, $id)
    {
        can('actualizar-listaprecio-proveedor');

        $ret = $this->service->actualiza($request, (int) $id);
        if ($ret['mensaje'] === 'ok') {
            return redirect()->route('consultar_listaprecio_proveedor')->with('mensaje', 'Lista de precios actualizada con éxito');
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
            return redirect()->route('consultar_listaprecio_proveedor')->with('mensaje', 'Estado actualizado');
        }

        return redirect()->back()->with('mensaje', $ret['errores'] ?? 'No se pudo cambiar el estado');
    }

    public function leerHistoria($listaprecio_proveedor_id)
    {
        can('listar-listaprecio-proveedor');

        return $this->service->leeHistoriaJson((int) $listaprecio_proveedor_id);
    }

    public function importarExcel(Request $request, $id)
    {
        can('actualizar-listaprecio-proveedor');

        $request->validate([
            'fechavigencia' => 'required|date',
            'archivoexcel' => 'required|file|mimes:xls,xlsx,csv|max:10240',
        ]);

        $import = new Listaprecio_ProveedorArticuloImport(
            (int) $id,
            $request->input('fechavigencia'),
            Auth::user()->id,
            $this->listaprecioArticuloRepository,
            $this->articuloRepository
        );

        try {
            set_time_limit(0);
            Excel::import($import, $request->file('archivoexcel'));
            $this->repository->persistirEnAnita((int) $id);
        } catch (\Exception $e) {
            return redirect()->back()->with('mensaje', 'Error al leer el archivo: '.$e->getMessage());
        }

        $msg = 'Importación finalizada: '.$import->importados.' ítem(s) cargados.';
        if ($import->errores !== []) {
            $msg .= ' Advertencias: '.implode(' ', array_slice($import->errores, 0, 15));
            if (count($import->errores) > 15) {
                $msg .= '…';
            }
        }

        return redirect()->route('editar_listaprecio_proveedor', ['id' => $id])->with('mensaje', $msg);
    }
}

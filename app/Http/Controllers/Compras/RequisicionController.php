<?php

namespace App\Http\Controllers\Compras;

use App\Http\Controllers\Controller;
use App\Http\Requests\ValidacionRequisicion;
use App\Models\Compras\Requisicion_Estado;
use App\Models\Compras\Requisicion;
use App\Models\Configuracion\Oficinacompra;
use App\Repositories\Compras\RequisicionRepositoryInterface;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Repositories\Configuracion\MonedaRepositoryInterface;
use App\Repositories\Configuracion\Arbolaprobacion_MovimientoRepositoryInterface;
use App\Repositories\Contable\CentrocostoRepositoryInterface;
use App\Repositories\Ventas\FormapagoRepositoryInterface;
use App\Models\Compras\Proveedor;
use App\Services\Compras\RequisicionService;
use App\Services\Configuracion\ArbolaprobacionService;
use App\Queries\Compras\RequisicionQueryInterface;
use App\Exports\Compras\RequisicionExport;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class RequisicionController extends Controller
{
    private $empresaRepository;
    private $centrocostoRepository;
    private $monedaRepository;
    private $formapagoRepository;
    private $requisicionRepository;
    private $requisicionQuery;
    private $requisicionService;
    private $arbolaprobacion_movimientoRepository;
    private $arbolaprobacionService;

    public function __construct(
        RequisicionRepositoryInterface $requisicionrepository,
        EmpresaRepositoryInterface $empresarepository,
        CentrocostoRepositoryInterface $centrocostorepository,
        MonedaRepositoryInterface $monedarepository,
        FormapagoRepositoryInterface $formapagorepository,
        RequisicionService $requisicionservice,
        RequisicionQueryInterface $requisicionquery,
        Arbolaprobacion_MovimientoRepositoryInterface $arbolaprobacion_movimientorepository,
        ArbolaprobacionService $arbolaprobacionservice,
    ) {
        $this->requisicionRepository = $requisicionrepository;
        $this->empresaRepository = $empresarepository;
        $this->centrocostoRepository = $centrocostorepository;
        $this->monedaRepository = $monedarepository;
        $this->formapagoRepository = $formapagorepository;
        $this->requisicionService = $requisicionservice;
        $this->requisicionQuery = $requisicionquery;
        $this->arbolaprobacion_movimientoRepository = $arbolaprobacion_movimientorepository;
        $this->arbolaprobacionService = $arbolaprobacionservice;
    }

    public function index(Request $request)
    {
        can('listar-requisicion');
        //$this->requisicionService->sincronizarConAnita();
        $hay_requisiciones = $this->requisicionQuery->first();

        if (!$hay_requisiciones)
			$this->requisicionService->sincronizarConAnita();

        $busqueda = $request->busqueda;

        $requisicion = $this->requisicionQuery->leeRequisicion($busqueda, true);

        $datas = [
            'requisicion' => $requisicion,
            'busqueda' => $busqueda,
            'estado_enum' => Requisicion_Estado::$enumEstado,
            'estado_en_compras' => Requisicion_Estado::$enumEstado[array_search('K', array_column(Requisicion_Estado::$enumEstado, 'valor'))]['nombre'],
            'tratamiento_enum' => Requisicion::$enumTratamiento,
            'contratacionDirecta_enum' => Requisicion::$enumContratacionDirecta
        ];

        return view('compras.requisicion.index', $datas);
    }

    public function listar(Request $request, $formato = null, $busqueda = null)
    {
        can('listar-requisicion');

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        switch ($formato) {
            case 'PDF':
                $requisicion = $this->requisicionQuery->leeRequisicion($busqueda, false);

                $view = \View::make('compras.requisicion.listado', compact('requisicion'))
                    ->render();
                $path = storage_path('pdf/listados');
                $nombre_pdf = 'listado_requisicion';

                $pdf = \App::make('dompdf.wrapper');
                $pdf->setPaper('legal', 'landscape');
                $pdf->loadHTML($view)->save($path.'/'.$nombre_pdf.'.pdf');

                return response()->download($path.'/'.$nombre_pdf.'.pdf');

            case 'EXCEL':
                return (new RequisicionExport($this->requisicionQuery))
                    ->parametros($busqueda)
                    ->download('requisicion.xlsx');

            case 'CSV':
                return (new RequisicionExport($this->requisicionQuery))
                    ->parametros($busqueda)
                    ->download('requisicion.csv', \Maatwebsite\Excel\Excel::CSV);
        }

        $requisicion = $this->requisicionQuery->leeRequisicion($busqueda, true);
        $datas = [
            'requisicion' => $requisicion,
            'busqueda' => $busqueda,
            'estado_enum' => Requisicion_Estado::$enumEstado,
            'estado_en_compras' => Requisicion_Estado::$enumEstado[array_search('K', array_column(Requisicion_Estado::$enumEstado, 'valor'))]['nombre'],
            'tratamiento_enum' => Requisicion::$enumTratamiento,
            'contratacionDirecta_enum' => Requisicion::$enumContratacionDirecta
        ];

        return view('compras.requisicion.index', $datas);
    }

    public function crear()
    {
        can('crear-requisicion');

        $empresa_query = $this->empresaRepository->allFiltrado();
        $centrocosto_query = $this->centrocostoRepository->all();
        $formapago_query = $this->formapagoRepository->all();
        $moneda_query = $this->monedaRepository->all();
        $oficinacompra_query = Oficinacompra::orderBy('nombre')->get();
        $proveedor_query = Proveedor::orderBy('nombre')->get();
        $estado_enum = Requisicion_Estado::$enumEstado;
        $tratamiento_enum = Requisicion::$enumTratamiento;
        $contratacionDirecta_enum = Requisicion::$enumContratacionDirecta;
        $data = null;

        return view('compras.requisicion.crear', compact(
            'data',
            'empresa_query',
            'centrocosto_query',
            'formapago_query',
            'moneda_query',
            'oficinacompra_query',
            'proveedor_query',
            'estado_enum',
            'tratamiento_enum',
            'contratacionDirecta_enum'
        ));
    }

    public function guardar(ValidacionRequisicion $request)
    {
        $ret = $this->requisicionService->guardaRequisicion($request);

        if ($ret['mensaje'] == 'ok') {
            $mensaje = 'Requisición creada con éxito';
        } else {
            $mensaje = $ret['errores'];
        }

        return redirect('compras/requisicion')->with('mensaje', $mensaje);
    }

    public function imprimirPdf($id)
    {
        if (! can('listar-requisicion', false) && ! can('editar-requisicion', false)) {
            return redirect()->route('inicio')->with('mensaje', 'No tienes permisos para imprimir la requisición');
        }

        $data = $this->requisicionRepository->find($id);
        $data->loadMissing([
            'requisicion_estados.usuarios',
            'requisicion_articulos.centrocostos_destino',
        ]);

        $arbolMovimientos = $this->arbolaprobacion_movimientoRepository->findPorRequisicion((int) $id);

        $html = view('compras.requisicion.pdf', compact('data', 'arbolMovimientos'))->render();

        $pdf = \App::make('dompdf.wrapper');
        $pdf->setPaper('legal', 'landscape');
        $pdf->loadHTML($html);

        $nombreArchivo = 'Requisicion_'.preg_replace('/[^\w\-]+/', '_', (string) $data->numerorequisicion).'.pdf';

        return $pdf->download($nombreArchivo);
    }

    public function editar($id)
    {
        can('editar-requisicion');

        $data = $this->requisicionRepository->find($id);
        if (! $this->requisicionService->usuarioPuedeEditarRequisicionEnCompras($data)) {
            return redirect()->route('solo_consulta_requisicion', $id)
                ->with('mensaje', 'No puede modificar esta requisición en compras: su oficina de compra no coincide con la de la requisición.');
        }

        // No puede modificar una requisicion que no este como pendiente
        if ($data->estado !== 'PENDIENTE' && $data->estado !== 'EN_COMPRAS') {
            return redirect()->route('solo_consulta_requisicion', $id)
                ->with('mensaje', 'No puede modificar esta requisición por no estar pendiente o en compras.');
        }

        $empresa_query = $this->empresaRepository->allFiltrado();
        $centrocosto_query = $this->centrocostoRepository->all();
        $formapago_query = $this->formapagoRepository->all();
        $moneda_query = $this->monedaRepository->all();
        $oficinacompra_query = Oficinacompra::orderBy('nombre')->get();
        $proveedor_query = Proveedor::orderBy('nombre')->get();
        $estado_enum = Requisicion_Estado::$enumEstado;
        $estado_en_compras = Requisicion_Estado::$enumEstado[array_search('K', array_column(Requisicion_Estado::$enumEstado, 'valor'))]['nombre'];
        $tratamiento_enum = Requisicion::$enumTratamiento;
        $contratacionDirecta_enum = Requisicion::$enumContratacionDirecta;

        $acceso_visualizacion_por_hash = false;

        return view('compras.requisicion.editar', compact(
            'data',
            'empresa_query',
            'centrocosto_query',
            'formapago_query',
            'moneda_query',
            'oficinacompra_query',
            'proveedor_query',
            'estado_enum',
            'estado_en_compras',
            'tratamiento_enum',
            'contratacionDirecta_enum',
            'acceso_visualizacion_por_hash'
        ));
    }

    public function actualizar(ValidacionRequisicion $request, $id)
    {
        can('actualizar-requisicion');

        $ret = $this->requisicionService->actualizaRequisicion($request, $id);

        if ($ret['mensaje'] == 'ok') {
            $mensaje = 'Requisición actualizada con éxito';
        } else {
            $mensaje = $ret['errores'];
        }

        return redirect('compras/requisicion')->with('mensaje', $mensaje);
    }

    public function enviarArbolAprobacion(Request $request, $id)
    {
        can('editar-requisicion');

        $ret = $this->requisicionService->enviarArbolAprobacionDesdeEnCompras((int) $id);

        if ($ret['mensaje'] === 'ok') {
            $mensaje = 'Requisición enviada al árbol de aprobación; el circuito continúa con el siguiente nivel.';
        } else {
            $mensaje = $ret['errores'] ?? 'No se pudo enviar al árbol de aprobación.';
        }

        return redirect()->route('consultar_requisicion')->with('mensaje', $mensaje);
    }

    public function eliminar(Request $request, $id)
    {
        can('borrar-requisicion');

        if ($request->ajax()) {
            if ($this->requisicionRepository->delete($id)) {
                return response()->json(['mensaje' => 'ok']);
            }

            return response()->json(['mensaje' => 'ng']);
        }

        abort(404);
    }

    public function leerHistoriaRequisicion($requisicion_id)
    {
        return $this->requisicionService->leeHistoriaRequisicion($requisicion_id);
    }

    /**
     * Comprueba árbol de aprobación para alta (empresa) o edición en pendiente (requisición completa).
     */
    public function avisoArbolGrabacion(Request $request)
    {
        $requisicionId = (int) $request->query('requisicion_id', 0);
        $empresaId = (int) $request->query('empresa_id', 0);

        if ($requisicionId > 0) {
            can('editar-requisicion');
        } else {
            can('crear-requisicion');
        }

        $aviso = $this->arbolaprobacionService->avisoGrabacionRequisicionAjax($empresaId, $requisicionId);

        return response()->json(['aviso' => $aviso]);
    }

    public function soloConsulta($id)
    {
        return $this->visualizar($id, null);
    }

    public function visualizar($id, $hash = null)
    {
        $aprobacion_movimiento = $this->arbolaprobacion_movimientoRepository->findPorRequisicion($id);

        if ($hash) {
            $flEncontro = false;
            foreach ($aprobacion_movimiento as $movimiento) {
                if ($movimiento->hashvisualizar == $hash) {
                    $flEncontro = true;
                    break;
                }
            }
        }
        else
            $flEncontro = true;

        if ($flEncontro) {
            $data = $this->requisicionRepository->find($id);
            $empresa_query = $this->empresaRepository->allFiltrado();
            $centrocosto_query = $this->centrocostoRepository->all();
            $formapago_query = $this->formapagoRepository->all();
            $moneda_query = $this->monedaRepository->all();
            $oficinacompra_query = Oficinacompra::orderBy('nombre')->get();
            $proveedor_query = Proveedor::orderBy('nombre')->get();
            $estado_enum = Requisicion_Estado::$enumEstado;
            $estado_en_compras = Requisicion_Estado::$enumEstado[array_search('K', array_column(Requisicion_Estado::$enumEstado, 'valor'))]['nombre'];
            $tratamiento_enum = Requisicion::$enumTratamiento;
            $contratacionDirecta_enum = Requisicion::$enumContratacionDirecta;
            $visualizar = true;
            $acceso_visualizacion_por_hash = filled($hash);

            return view('compras.requisicion.editar', compact(
                'data',
                'empresa_query',
                'centrocosto_query',
                'formapago_query',
                'moneda_query',
                'oficinacompra_query',
                'proveedor_query',
                'estado_enum',
                'estado_en_compras',
                'tratamiento_enum',
                'contratacionDirecta_enum',
                'visualizar',
                'acceso_visualizacion_por_hash'
            ));
        }

        return redirect()->route('inicio')->with('mensaje', 'No tienes permisos para visualizar la requisición')->send();
    }
}

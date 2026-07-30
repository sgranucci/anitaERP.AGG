<?php

namespace App\Http\Controllers\Sala;

use App\Exports\Sala\RequisicionSalaListadoExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\ValidacionRequisicionSala;
use App\Http\Requests\ValidacionRequisicionSalaEdicionMenor;
use App\Http\Requests\ValidacionRequisicionSalaReabrir;
use App\Models\Sala\RequisicionSalaArchivo;
use App\Models\Sala\RequisicionSalaEstado;
use App\Queries\Sala\RequisicionSalaQueryInterface;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Repositories\Contable\CentrocostoRepositoryInterface;
use App\Repositories\Sala\CumplimientoRequisicionSalaRepositoryInterface;
use App\Repositories\Sala\PrioridadSalaRepositoryInterface;
use App\Repositories\Sala\RequisicionSalaRepositoryInterface;
use App\Repositories\Sala\ZonaSalaRepositoryInterface;
use App\Repositories\Stock\DepmaeRepositoryInterface;
use App\Services\Configuracion\ArbolaprobacionService;
use App\Services\Sala\RequisicionSalaArbolIntegracionService;
use App\Services\Sala\RequisicionSalaPdfService;
use App\Services\Sala\RequisicionSalaService;
use App\Support\Sala\RecpunicaAnitaSupport;
use App\Support\Sala\RequisicionSalaEdicionSupport;
use App\Support\Sala\RequisicionSalaListadoFiltros;
use App\Support\Navegacion\ModoConsultaUrlSupport;
use App\Support\Sala\RequisicionSalaTransferenciaAsociadaSupport;
use Illuminate\Http\Request;

class RequisicionSalaController extends Controller
{
    public function __construct(
        private RequisicionSalaRepositoryInterface $repository,
        private RequisicionSalaQueryInterface $query,
        private RequisicionSalaService $service,
        private EmpresaRepositoryInterface $empresaRepository,
        private CentrocostoRepositoryInterface $centrocostoRepository,
        private DepmaeRepositoryInterface $depmaeRepository,
        private ZonaSalaRepositoryInterface $zonaSalaRepository,
        private PrioridadSalaRepositoryInterface $prioridadSalaRepository,
        private ArbolaprobacionService $arbolaprobacionService,
        private RequisicionSalaArbolIntegracionService $arbolIntegracion,
        private RequisicionSalaPdfService $pdfService,
        private CumplimientoRequisicionSalaRepositoryInterface $cumplimientoRepository,
    ) {
    }

    public function index(Request $request)
    {
        can('listar-requisicion-sala');
        $empresaDefault = optional($this->empresaRepository->allFiltrado()->first())->id;
        $filtros = RequisicionSalaListadoFiltros::resolverDesdeRequest(
            $request,
            null,
            $empresaDefault ? (int) $empresaDefault : null
        );
        $coleccion = $this->query->leeRequisicionSala($filtros, true, true);

        return view('sala.requisicion_sala.index', [
            'requisicion_sala' => $coleccion,
            'filtros' => $filtros,
            'filtrosQuery' => RequisicionSalaListadoFiltros::paraQueryString($filtros),
            'camposFiltro' => RequisicionSalaListadoFiltros::CAMPOS,
            'empresa_query' => $this->empresaRepository->allFiltrado(),
            'estado_enum' => RequisicionSalaEstado::$enumEstado,
            'estado_en_laboratorio' => RequisicionSalaEstado::$enumEstado[array_search('5', array_column(RequisicionSalaEstado::$enumEstado, 'valor'))]['nombre'],
            'estado_pendiente' => RequisicionSalaEstado::$enumEstado[array_search('0', array_column(RequisicionSalaEstado::$enumEstado, 'valor'))]['nombre'],
            'estado_rechazada' => RequisicionSalaEstado::$enumEstado[array_search('Z', array_column(RequisicionSalaEstado::$enumEstado, 'valor'))]['nombre'],
        ]);
    }

    public function listar(Request $request, $formato = null, $busqueda = null)
    {
        can('listar-requisicion-sala');
        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');
        $empresaDefault = optional($this->empresaRepository->allFiltrado()->first())->id;
        $filtros = RequisicionSalaListadoFiltros::resolverDesdeRequest(
            $request,
            $busqueda,
            $empresaDefault ? (int) $empresaDefault : null
        );

        switch ($formato) {
            case 'PDF':
                $filas = $this->query->leeRequisicionSala($filtros, false, true);
                $view = \View::make('sala.requisicion_sala.listado', compact('filas'))->render();
                $path = storage_path('pdf/listados');
                $nombre_pdf = 'listado_requisicion_sala';
                $pdf = \App::make('dompdf.wrapper');
                $pdf->setPaper('legal', 'landscape');
                $pdf->loadHTML($view, 'UTF-8')->save($path.'/'.$nombre_pdf.'.pdf');

                return response()->download($path.'/'.$nombre_pdf.'.pdf');
            case 'EXCEL':
                return (new RequisicionSalaListadoExport($this->query))
                    ->parametros($filtros)
                    ->download('requisicion_sala.xlsx');
            case 'CSV':
                return (new RequisicionSalaListadoExport($this->query))
                    ->parametros($filtros)
                    ->download('requisicion_sala.csv', \Maatwebsite\Excel\Excel::CSV);
        }

        return redirect()->route('consultar_requisicion_sala', RequisicionSalaListadoFiltros::paraQueryString($filtros));
    }

    public function crear()
    {
        can('crear-requisicion-sala');

        return view('sala.requisicion_sala.crear', array_merge($this->datosFormulario(null), [
            'data' => null,
        ]));
    }

    public function guardar(ValidacionRequisicionSala $request)
    {
        can('crear-requisicion-sala');
        $resultado = $this->service->guardaRequisicionSala($request);
        if (($resultado['mensaje'] ?? '') === 'error') {
            return redirect()->back()->withInput()->with('mensaje_error', $resultado['errores'] ?? 'Error al guardar.');
        }

        return redirect('sala/requisicion-sala')->with('mensaje', 'Requisición de sala creada con éxito');
    }

    public function editar($id)
    {
        can('editar-requisicion-sala');
        $data = $this->repository->find($id);
        $modoConsulta = request()->input('vista') === 'consulta';
        $flagsEdicion = $this->flagsEdicion($data);

        return view('sala.requisicion_sala.editar', array_merge($this->datosFormulario($data), $this->datosVistaTransferencia($data), $flagsEdicion, [
            'data' => $data,
            'movimientos_arbol' => $this->arbolIntegracion->findPorRequisicionSala((int) $id),
            'cumplimientos_sala' => $this->cumplimientoRepository->listarPorRequisicion((int) $id),
            'soloConsulta' => $modoConsulta,
            'ocultarVolver' => $modoConsulta,
            'puedeActualizarRequisicionSala' => can('actualizar-requisicion-sala', false),
            'puedeReabrirRequisicionSala' => can('reabrir-requisicion-sala', false),
        ]));
    }

    public function actualizar(ValidacionRequisicionSala $request, $id)
    {
        can('actualizar-requisicion-sala');
        $resultado = $this->service->actualizaRequisicionSala($request, (int) $id);
        if (($resultado['mensaje'] ?? '') === 'error') {
            return redirect()->back()->withInput()->with('mensaje_error', $resultado['errores'] ?? 'Error al actualizar.');
        }

        return redirect('sala/requisicion-sala')->with('mensaje', 'Requisición de sala actualizada con éxito');
    }

    public function actualizarDatosMenores(ValidacionRequisicionSalaEdicionMenor $request, $id)
    {
        can('actualizar-requisicion-sala');
        $resultado = $this->service->actualizaDatosMenores($request, (int) $id);
        if (($resultado['mensaje'] ?? '') === 'error') {
            return redirect()->back()->withInput()->with('mensaje_error', $resultado['errores'] ?? 'Error al actualizar.');
        }

        return redirect()->route('editar_requisicion_sala', ['id' => $id])
            ->with('mensaje', 'Datos menores actualizados (la aprobación se mantiene).');
    }

    public function reabrir(ValidacionRequisicionSalaReabrir $request, $id)
    {
        can('reabrir-requisicion-sala');
        $resultado = $this->service->reabrirDesaprobar((int) $id, (string) $request->input('motivo', ''));
        if (($resultado['mensaje'] ?? '') === 'error') {
            return redirect()->back()->with('mensaje_error', $resultado['errores'] ?? 'Error al reabrir.');
        }

        return redirect()->route('editar_requisicion_sala', ['id' => $id])
            ->with('mensaje', 'Requisición reabierta en PENDIENTE. Corregí los datos y guardá para reenviar al árbol de aprobación.');
    }

    public function eliminar(Request $request, $id)
    {
        can('borrar-requisicion-sala');
        if ($request->ajax()) {
            $req = $this->repository->find($id);
            $pendiente = RequisicionSalaEstado::$enumEstado[array_search('0', array_column(RequisicionSalaEstado::$enumEstado, 'valor'))]['nombre'];
            if ($req->estado !== $pendiente) {
                return response()->json(['mensaje' => 'ng', 'error' => 'Solo se puede eliminar en estado PENDIENTE.']);
            }
            if ($this->repository->delete($id)) {
                return response()->json(['mensaje' => 'ok']);
            }

            return response()->json(['mensaje' => 'ng']);
        }
        abort(404);
    }

    public function descargarArchivo($id, $archivo)
    {
        can('listar-requisicion-sala');
        $arch = RequisicionSalaArchivo::where('requisicion_sala_id', $id)->where('id', $archivo)->firstOrFail();
        $path = public_path('storage/archivos/requisiciones_sala/'.$id.'/'.basename($arch->nombrearchivo));
        if (! is_file($path)) {
            abort(404);
        }
        if (request()->boolean('inline')) {
            return response()->file($path);
        }

        return response()->download($path, $arch->nombrearchivo);
    }

    public function enviarArbolAprobacion($id)
    {
        can('enviar-arbol-requisicion-sala');
        $resultado = $this->service->enviarArbolAprobacionDesdeEnLaboratorio((int) $id);
        if (($resultado['mensaje'] ?? '') === 'error') {
            return redirect()->back()->with('mensaje_error', $resultado['errores'] ?? 'Error.');
        }

        return redirect()->back()->with('mensaje', 'Requisición enviada al árbol de aprobación.');
    }

    public function leerHistoria($id)
    {
        can('listar-requisicion-sala');

        return response()->json($this->service->leeHistoria((int) $id));
    }

    public function imprimirPdf($id)
    {
        can('listar-requisicion-sala');
        $pdf = $this->pdfService->generarBytes((int) $id);

        return response($pdf['contenido'], 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$pdf['nombre'].'"',
        ]);
    }

    public function consultaNumeroParteUnica(Request $request)
    {
        if (! can('crear-requisicion-sala', false) && ! can('actualizar-requisicion-sala', false)) {
            can('crear-requisicion-sala');
        }
        $sku = trim((string) $request->input('sku', ''));
        if ($sku === '') {
            return response()->json(['encontrado' => false]);
        }
        $npu = RecpunicaAnitaSupport::buscarPorSku($sku);

        return response()->json([
            'encontrado' => $npu !== null,
            'numeroparte' => $npu !== null ? (string) $npu : '',
        ]);
    }

    public function visualizar($id, $hash = null)
    {
        $movimientos = $this->arbolIntegracion->findPorRequisicionSala((int) $id);
        $flEncontro = false;
        if ($hash) {
            foreach ($movimientos as $movimiento) {
                if ($movimiento->hashvisualizar === $hash) {
                    $flEncontro = true;
                    break;
                }
            }
        } else {
            $flEncontro = true;
        }
        if (! $flEncontro) {
            abort(403);
        }

        $data = $this->repository->find($id);
        $puedeActualizar = ModoConsultaUrlSupport::usuarioPuedeActualizarRequisicionSala();

        return view('sala.requisicion_sala.editar', array_merge($this->datosFormulario($data), $this->datosVistaTransferencia($data), $this->flagsEdicion($data), [
            'data' => $data,
            'movimientos_arbol' => $movimientos,
            'cumplimientos_sala' => $this->cumplimientoRepository->listarPorRequisicion((int) $id),
            'visualizar' => ! $puedeActualizar,
            'acceso_visualizacion_por_hash' => filled($hash),
            'soloConsulta' => true,
            'ocultarVolver' => true,
            'puedeActualizarRequisicionSala' => $puedeActualizar,
            'puedeReabrirRequisicionSala' => $puedeActualizar && can('reabrir-requisicion-sala', false),
        ]));
    }

    /** @return array{edicion_completa: bool, edicion_menor: bool, puede_reabrir: bool, cumplimientos_activos: int} */
    private function flagsEdicion(?\App\Models\Sala\RequisicionSala $data): array
    {
        $estado = $data->estado ?? null;
        $cumplimientosActivos = $data
            ? RequisicionSalaEdicionSupport::cantidadCumplimientosActivos($data)
            : 0;

        return [
            'edicion_completa' => RequisicionSalaEdicionSupport::permiteEdicionCompleta($estado),
            'edicion_menor' => RequisicionSalaEdicionSupport::permiteEdicionMenor($estado),
            'puede_reabrir' => RequisicionSalaEdicionSupport::permiteReabrir($estado),
            'puede_reabrir_sin_bloqueos' => RequisicionSalaEdicionSupport::permiteReabrir($estado) && $cumplimientosActivos === 0,
            'cumplimientos_activos' => $cumplimientosActivos,
        ];
    }

    /** @return array<string, mixed> */
    private function datosVistaTransferencia(?\App\Models\Sala\RequisicionSala $data): array
    {
        if ($data === null) {
            return [
                'tiene_transferencia_laboratorio' => false,
                'transferencia_laboratorio' => null,
                'lineas_articulo_bloqueadas_por_tm' => [],
            ];
        }

        $tieneTm = RequisicionSalaTransferenciaAsociadaSupport::tieneTransferenciaLaboratorio($data);

        return [
            'tiene_transferencia_laboratorio' => $tieneTm,
            'transferencia_laboratorio' => $tieneTm
                ? RequisicionSalaTransferenciaAsociadaSupport::transferenciaLaboratorio($data)
                : null,
            'lineas_articulo_bloqueadas_por_tm' => RequisicionSalaTransferenciaAsociadaSupport::idsLineasArticuloBloqueadas($data),
        ];
    }

    private function datosFormulario($data): array
    {
        return [
            'empresa_query' => $this->empresaRepository->allFiltrado(),
            'centrocosto_query' => $this->centrocostoRepository->all(),
            'deposito_query' => $this->depmaeRepository->allFiltrado(),
            'zona_sala_query' => $this->zonaSalaRepository->all(),
            'prioridad_sala_query' => $this->prioridadSalaRepository->all(),
            'estado_enum' => RequisicionSalaEstado::$enumEstado,
            'destino_enum' => \App\Models\Sala\RequisicionSalaArticulo::$enumDestino,
            'estado_linea_enum' => \App\Models\Sala\RequisicionSalaArticulo::$enumEstado,
            'estado_en_laboratorio' => RequisicionSalaEstado::$enumEstado[array_search('5', array_column(RequisicionSalaEstado::$enumEstado, 'valor'))]['nombre'],
            'estado_pendiente' => RequisicionSalaEstado::$enumEstado[array_search('0', array_column(RequisicionSalaEstado::$enumEstado, 'valor'))]['nombre'],
        ];
    }
}

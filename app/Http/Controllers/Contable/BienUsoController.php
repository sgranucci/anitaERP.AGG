<?php

namespace App\Http\Controllers\Contable;

use App\Exports\Contable\BienUsoListadoExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\ValidacionBienUso;
use App\Models\Contable\BienUso;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Repositories\Contable\BienUsoRepositoryInterface;
use App\Support\Contable\BienUsoListadoFiltros;
use App\Support\Listado\QueryRetornoListado;
use App\Support\Contable\BienUsoVisibilidadSupport;
use App\Support\Stock\BienUsoAsignacionSupport;
use Illuminate\Http\Request;

class BienUsoController extends Controller
{
    private BienUsoRepositoryInterface $repository;

    private EmpresaRepositoryInterface $empresaRepository;

    public function __construct(BienUsoRepositoryInterface $repository, EmpresaRepositoryInterface $empresaRepository)
    {
        $this->repository = $repository;
        $this->empresaRepository = $empresaRepository;
    }

    public function index(Request $request)
    {
        can('listar-bien-uso');

        $filtros = BienUsoListadoFiltros::resolverDesdeRequest($request);
        $datas = $this->repository->leeBienUso($filtros, true);
        $filtrosQuery = BienUsoListadoFiltros::paraQueryString($filtros);

        return view('contable.bien_uso.index', [
            'datas' => $datas,
            'filtros' => $filtros,
            'filtrosQuery' => $filtrosQuery,
            'camposFiltro' => BienUsoListadoFiltros::CAMPOS,
            'estado_enum' => BienUso::$enumEstado,
            'centrocosto_opciones' => BienUsoVisibilidadSupport::opcionesCentrocostoAbm(),
            'tipo_bien_enum' => BienUso::$enumTipoBien,
            'puede_ver_bien_uso' => can('editar-bien-uso', false) || can('listar-bien-uso', false),
            'alcance_centro_costo' => BienUsoVisibilidadSupport::etiquetaAlcanceActivo(),
            'filtro_centrocosto_restringido' => BienUsoVisibilidadSupport::tieneRestriccionPorPerfil(),
        ]);
    }

    public function listar(Request $request, $formato = null, $busqueda = null)
    {
        can('listar-bien-uso');

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $filtros = BienUsoListadoFiltros::resolverDesdeRequest($request, $busqueda);

        switch ($formato) {
            case 'PDF':
                $datas = $this->repository->leeBienUso($filtros, false);

                $view = \View::make('contable.bien_uso.listado', [
                    'datas' => $datas,
                    'estado_enum' => BienUso::$enumEstado,
                    'tipo_bien_enum' => BienUso::$enumTipoBien,
                ])->render();
                $path = storage_path('pdf/listados');
                $nombre_pdf = 'listado_bien_uso';

                $pdf = \App::make('dompdf.wrapper');
                $pdf->setPaper('legal', 'landscape');
                $pdf->loadHTML($view)->save($path.'/'.$nombre_pdf.'.pdf');

                return response()->download($path.'/'.$nombre_pdf.'.pdf');

            case 'EXCEL':
                return (new BienUsoListadoExport($this->repository))
                    ->parametros($filtros)
                    ->download('bien_uso.xlsx');

            case 'CSV':
                return (new BienUsoListadoExport($this->repository))
                    ->parametros($filtros)
                    ->download('bien_uso.csv', \Maatwebsite\Excel\Excel::CSV);
        }

        return redirect()->route('bien_uso', BienUsoListadoFiltros::paraQueryString($filtros));
    }

    public function crear(Request $request)
    {
        can('crear-bien-uso');

        $data = new BienUso;
        $filtrosQuery = QueryRetornoListado::desdeRequest($request, BienUsoListadoFiltros::class);

        return view('contable.bien_uso.crear', [
            'data' => $data,
            'estado_enum' => BienUso::$enumEstado,
            'centrocosto_opciones' => BienUsoVisibilidadSupport::opcionesCentrocostoAbm(),
            'tipo_bien_enum' => BienUso::$enumTipoBien,
            'empresa_query' => $this->empresaRepository->allFiltrado(),
            'filtrosQuery' => $filtrosQuery,
        ]);
    }

    public function guardar(ValidacionBienUso $request)
    {
        can('crear-bien-uso');

        $this->validarCentrocostoPermitido((int) $request->input('centrocosto_id'));

        $this->repository->create($request->all());

        return redirect()->route('bien_uso', QueryRetornoListado::desdeRequest($request, BienUsoListadoFiltros::class))
            ->with('mensaje', 'Bien de uso creado con éxito');
    }

    public function editar(Request $request, $id)
    {
        $soloConsulta = $request->query('origen') === 'modal_consulta';
        if ($soloConsulta) {
            can('listar-bien-uso');
        } else {
            can('editar-bien-uso');
        }

        $data = $this->repository->findOrFail($id);
        BienUsoVisibilidadSupport::abortSiNoPuedeAccederRegistro($data);

        $inventarioActual = BienUsoAsignacionSupport::inventarioActual((int) $data->id);
        $historial = BienUsoAsignacionSupport::historialMovimientos((int) $data->id);
        $transferenciasPendientes = BienUsoAsignacionSupport::transferenciasPendientesEntrada((int) $data->id);
        $transferenciasPendientesSalida = BienUsoAsignacionSupport::transferenciasPendientesSalida((int) $data->id);

        $filtrosQuery = QueryRetornoListado::desdeRequest($request, BienUsoListadoFiltros::class);

        return view('contable.bien_uso.editar', [
            'data' => $data,
            'estado_enum' => BienUso::$enumEstado,
            'centrocosto_opciones' => BienUsoVisibilidadSupport::opcionesCentrocostoAbm(),
            'tipo_bien_enum' => BienUso::$enumTipoBien,
            'empresa_query' => $this->empresaRepository->allFiltrado(),
            'soloConsulta' => $soloConsulta,
            'inventarioActual' => $inventarioActual,
            'historial' => $historial,
            'transferenciasPendientes' => $transferenciasPendientes,
            'transferenciasPendientesSalida' => $transferenciasPendientesSalida,
            'filtrosQuery' => $filtrosQuery,
        ]);
    }

    public function actualizar(ValidacionBienUso $request, $id)
    {
        if ($request->input('origen') === 'modal_consulta') {
            abort(403);
        }

        can('actualizar-bien-uso');

        $this->validarCentrocostoPermitido((int) $request->input('centrocosto_id'));
        $bien = $this->repository->findOrFail($id);
        BienUsoVisibilidadSupport::abortSiNoPuedeAccederRegistro($bien);

        $this->repository->update($request->all(), $id);

        return redirect()->route('bien_uso', QueryRetornoListado::desdeRequest($request, BienUsoListadoFiltros::class))
            ->with('mensaje', 'Bien de uso actualizado con éxito');
    }

    public function eliminar(Request $request, $id)
    {
        can('borrar-bien-uso');

        $bien = $this->repository->findOrFail($id);
        BienUsoVisibilidadSupport::abortSiNoPuedeAccederRegistro($bien);

        if ($request->ajax()) {
            if ($this->repository->delete($id)) {
                return response()->json(['mensaje' => 'ok']);
            }

            return response()->json(['mensaje' => 'ng']);
        }

        abort(404);
    }

    private function validarCentrocostoPermitido(int $centrocostoId): void
    {
        if (! BienUsoVisibilidadSupport::puedeAcceder($centrocostoId)) {
            abort(403, 'No puede asignar ese centro de costo al bien.');
        }
    }
}

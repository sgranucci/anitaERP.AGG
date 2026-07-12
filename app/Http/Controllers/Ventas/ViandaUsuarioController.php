<?php

namespace App\Http\Controllers\Ventas;

use App\Exports\Ventas\ViandaUsuarioListadoExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\ValidacionViandaUsuario;
use App\Models\Contable\Centrocosto;
use App\Models\Ventas\ViandaTipoMenu;
use App\Models\Ventas\ViandaUsuario;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Repositories\Ventas\ViandaUsuarioRepositoryInterface;
use App\Support\Ventas\Vianda\ViandaEmpresaSupport;
use App\Support\Ventas\ViandaUsuarioListadoFiltros;
use App\Support\Listado\QueryRetornoListado;
use App\Support\Ventas\ViandaUsuarioTipoSupport;
use Illuminate\Http\Request;

class ViandaUsuarioController extends Controller
{
    public function __construct(
        private ViandaUsuarioRepositoryInterface $repository,
        private EmpresaRepositoryInterface $empresaRepository,
    ) {
    }

    public function index(Request $request)
    {
        can('listar-vianda-usuario-gastronomia');

        $filtros = $this->resolverFiltrosListado($request);
        $datas = $this->repository->leeUsuarios($filtros, true);
        $sinRegistros = $datas->total() === 0 && ! ViandaUsuarioListadoFiltros::tieneCriteriosAplicados($filtros);

        return view('ventas.vianda_usuario.index', [
            'datas' => $datas,
            'filtros' => $filtros,
            'filtrosQuery' => ViandaUsuarioListadoFiltros::paraQueryString($filtros),
            'camposFiltro' => ViandaUsuarioListadoFiltros::CAMPOS,
            'sinRegistros' => $sinRegistros,
            'tiposUsuario' => ViandaUsuarioTipoSupport::ETIQUETAS,
            'empresa_query' => ViandaEmpresaSupport::empresasSeleccionables(),
        ]);
    }

    public function listar(Request $request, $formato = null, $busqueda = null)
    {
        can('listar-vianda-usuario-gastronomia');

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $filtros = $this->resolverFiltrosListado($request, $busqueda);

        switch ($formato) {
            case 'PDF':
                $datas = $this->repository->leeUsuarios($filtros, false);
                $view = \View::make('ventas.vianda_usuario.listado', compact('datas'))->render();
                $path = storage_path('pdf/listados');
                if (! is_dir($path)) {
                    @mkdir($path, 0775, true);
                }
                $nombrePdf = 'listado_vianda_usuario';

                $pdf = \App::make('dompdf.wrapper');
                $pdf->setPaper('legal', 'landscape');
                $pdf->loadHTML($view)->save($path.'/'.$nombrePdf.'.pdf');

                return response()->download($path.'/'.$nombrePdf.'.pdf');

            case 'EXCEL':
                return (new ViandaUsuarioListadoExport($this->repository))
                    ->parametros($filtros)
                    ->download('vianda_usuarios.xlsx');

            case 'CSV':
                return (new ViandaUsuarioListadoExport($this->repository))
                    ->parametros($filtros)
                    ->download('vianda_usuarios.csv', \Maatwebsite\Excel\Excel::CSV);
        }

        return redirect()->route('consultar_vianda_usuario_gastronomia', ViandaUsuarioListadoFiltros::paraQueryString($filtros));
    }

    public function crear(Request $request)
    {
        can('crear-vianda-usuario-gastronomia');

        $data = new ViandaUsuario(['estado' => 'A', 'tipo_usuario' => 'L', 'empresa_id' => 1]);
        $centrocosto_query = Centrocosto::query()->orderBy('codigo')->get(['id', 'codigo', 'nombre']);
        $empresa_query = ViandaEmpresaSupport::empresasSeleccionables();
        $tipo_menu_query = $this->tipoMenuParaSelect($empresa_query->pluck('id')->all());
        $filtrosQuery = QueryRetornoListado::desdeRequest($request, ViandaUsuarioListadoFiltros::class);

        return view('ventas.vianda_usuario.crear', compact(
            'data',
            'centrocosto_query',
            'tipo_menu_query',
            'empresa_query',
            'filtrosQuery',
        ));
    }

    public function guardar(ValidacionViandaUsuario $request)
    {
        can('crear-vianda-usuario-gastronomia');

        $this->repository->create($request->all());

        return redirect()->route('consultar_vianda_usuario_gastronomia', QueryRetornoListado::desdeRequest($request, ViandaUsuarioListadoFiltros::class))
            ->with('mensaje', 'Usuario de vianda creado con éxito');
    }

    public function editar(Request $request, $id)
    {
        $soloConsulta = $request->query('origen') === 'modal_consulta';
        if ($soloConsulta) {
            if (! can('listar-vianda-usuario-gastronomia', false)
                && ! can('editar-vianda-usuario-gastronomia', false)) {
                abort(403);
            }
        } else {
            can('editar-vianda-usuario-gastronomia');
        }

        $data = $this->repository->findOrFail($id);
        $centrocosto_query = Centrocosto::query()->orderBy('codigo')->get(['id', 'codigo', 'nombre']);
        $empresa_query = ViandaEmpresaSupport::empresasSeleccionables((int) $data->empresa_id);
        $tipo_menu_query = $this->tipoMenuParaSelect($empresa_query->pluck('id')->all(), true);
        $filtrosQuery = QueryRetornoListado::desdeRequest($request, ViandaUsuarioListadoFiltros::class);
        $ocultarVolver = $soloConsulta;
        $puedeActualizarUsuario = can('actualizar-vianda-usuario-gastronomia', false);

        return view('ventas.vianda_usuario.editar', compact(
            'data',
            'centrocosto_query',
            'tipo_menu_query',
            'empresa_query',
            'filtrosQuery',
            'soloConsulta',
            'ocultarVolver',
            'puedeActualizarUsuario',
        ));
    }

    public function actualizar(ValidacionViandaUsuario $request, $id)
    {
        can('actualizar-vianda-usuario-gastronomia');

        $this->repository->update($request->all(), $id);

        return redirect()->route('consultar_vianda_usuario_gastronomia', QueryRetornoListado::desdeRequest($request, ViandaUsuarioListadoFiltros::class))
            ->with('mensaje', 'Usuario de vianda actualizado con éxito');
    }

    public function eliminar(Request $request, $id)
    {
        can('borrar-vianda-usuario-gastronomia');

        if ($request->ajax()) {
            if ($this->repository->delete($id)) {
                return response()->json(['mensaje' => 'ok']);
            }

            return response()->json(['mensaje' => 'ng']);
        }

        abort(404);
    }

    /**
     * @return array<string, mixed>
     */
    private function resolverFiltrosListado(Request $request, ?string $busquedaRuta = null): array
    {
        $filtros = ViandaUsuarioListadoFiltros::resolverDesdeRequest($request, $busquedaRuta);
        $filtros['empresas_asignadas'] = $this->empresaRepository->traeEmpresasAsignadas();

        if (\App\Support\Listado\FiltrosListadoRequest::solicitudLimpiaFiltros($request)) {
            return $filtros;
        }

        $empresaId = (int) ($filtros['empresa_id'] ?? 0);
        if ($empresaId > 0 && ! ViandaEmpresaSupport::empresaPermitida($empresaId)) {
            $filtros['empresa_id'] = 0;
        }

        return $filtros;
    }

    /**
     * Tipos de menú seleccionables para el formulario de usuario, acotados a las empresas
     * del módulo que ve el operador. Cada opción se etiqueta con su empresa para que el
     * operador elija el menú de la empresa correcta (el alta habitual llega desde Anita).
     *
     * @param  list<int>  $empresaIds
     * @return \Illuminate\Support\Collection<int, ViandaTipoMenu>
     */
    private function tipoMenuParaSelect(array $empresaIds, bool $incluirInactivos = false): \Illuminate\Support\Collection
    {
        if ($empresaIds === []) {
            return collect();
        }

        return ViandaTipoMenu::query()
            ->with('empresa:id,nombre')
            ->whereIn('empresa_id', $empresaIds)
            ->when(! $incluirInactivos, fn ($q) => $q->where('estado', 'A'))
            ->orderBy('empresa_id')
            ->orderBy('nombre')
            ->get(['id', 'empresa_id', 'nombre', 'estado']);
    }
}

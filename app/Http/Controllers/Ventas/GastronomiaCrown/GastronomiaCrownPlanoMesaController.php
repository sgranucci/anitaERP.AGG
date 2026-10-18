<?php

namespace App\Http\Controllers\Ventas\GastronomiaCrown;

use App\Exports\Ventas\GastroPlanoMesaListadoExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\ValidacionGastroPlanoMesa;
use App\Models\Configuracion\Sala;
use App\Models\Ventas\GastronomiaCrown\GastroPlanoMesa;
use App\Models\Ventas\MesaGastronomia;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\Ventas\GastronomiaCrown\GastroPlanoMesaLayoutSupport;
use App\Support\Ventas\GastronomiaCrown\GastroPlanoMesaListadoFiltros;
use App\Support\Ventas\UsuarioSalaAsignadaSupport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class GastronomiaCrownPlanoMesaController extends Controller
{
    public function __construct(
        private EmpresaRepositoryInterface $empresaRepository,
    ) {
    }

    public function index(Request $request): View
    {
        $this->assertCrown();
        can('listar-plano-mesa-gastronomia-crown');

        $filtros = GastroPlanoMesaListadoFiltros::resolverDesdeRequest($request);
        $filtrosQuery = GastroPlanoMesaListadoFiltros::paraQueryString($filtros);

        $datas = GastroPlanoMesaListadoFiltros::consulta($filtros)->paginate(10);
        $datas->appends($filtrosQuery);

        return view('ventas.gastronomia_crown.plano_mesa.index', [
            'datas' => $datas,
            'filtros' => $filtros,
            'filtrosQuery' => $filtrosQuery,
            'camposFiltro' => GastroPlanoMesaListadoFiltros::CAMPOS,
            'sala_query' => $this->salasParaFiltro(),
            'empresa_query' => $this->empresaRepository->allFiltrado(),
        ]);
    }

    public function listar(Request $request, $formato = null, $busqueda = null)
    {
        $this->assertCrown();
        can('listar-plano-mesa-gastronomia-crown');

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $filtros = GastroPlanoMesaListadoFiltros::resolverDesdeRequest($request, $busqueda);

        switch ($formato) {
            case 'PDF':
                $datas = GastroPlanoMesaListadoFiltros::consulta($filtros)->get();
                $view = \View::make('ventas.gastronomia_crown.plano_mesa.listado', compact('datas', 'filtros'))->render();
                $path = storage_path('pdf/listados');
                if (! is_dir($path)) {
                    mkdir($path, 0755, true);
                }
                $pdf = \App::make('dompdf.wrapper');
                $pdf->setPaper('legal', 'landscape');
                $pdf->loadHTML($view)->save($path.'/listado_plano_mesa.pdf');

                return response()->download($path.'/listado_plano_mesa.pdf');

            case 'EXCEL':
                return (new GastroPlanoMesaListadoExport())
                    ->parametros($filtros)
                    ->download('plano_mesa.xlsx');

            case 'CSV':
                return (new GastroPlanoMesaListadoExport())
                    ->parametros($filtros)
                    ->download('plano_mesa.csv', \Maatwebsite\Excel\Excel::CSV);
        }

        return redirect()->route(
            'consultar_plano_mesa_gastronomia_crown',
            GastroPlanoMesaListadoFiltros::paraQueryString($filtros)
        );
    }

    public function crear(Request $request): View
    {
        $this->assertCrown();
        can('crear-plano-mesa-gastronomia-crown');

        $data = new GastroPlanoMesa([
            'activo' => true,
            'orden' => 0,
            'layout_json' => GastroPlanoMesaLayoutSupport::vacio(),
        ]);

        return view('ventas.gastronomia_crown.plano_mesa.crear', $this->datosFormulario($data, $request));
    }

    public function guardar(ValidacionGastroPlanoMesa $request)
    {
        $this->assertCrown();
        can('crear-plano-mesa-gastronomia-crown');

        $this->assertEmpresaPermitida((int) $request->input('empresa_id'));

        $plano = new GastroPlanoMesa();
        $this->asignarDesdeRequest($plano, $request);
        $plano->save();

        return redirect()->route('editar_plano_mesa_gastronomia_crown', $plano->id)
            ->with('mensaje', 'Plano creado con éxito. Ubique las mesas en el editor y guarde.');
    }

    public function editar(Request $request, $id): View
    {
        $this->assertCrown();
        can('editar-plano-mesa-gastronomia-crown');

        $data = GastroPlanoMesa::query()->findOrFail($id);
        $this->assertEmpresaPermitida((int) $data->empresa_id);

        return view('ventas.gastronomia_crown.plano_mesa.editar', $this->datosFormulario($data, $request));
    }

    public function actualizar(ValidacionGastroPlanoMesa $request, $id)
    {
        $this->assertCrown();
        can('actualizar-plano-mesa-gastronomia-crown');

        $plano = GastroPlanoMesa::query()->findOrFail($id);
        $this->assertEmpresaPermitida((int) $plano->empresa_id);
        $this->assertEmpresaPermitida((int) $request->input('empresa_id'));

        $this->asignarDesdeRequest($plano, $request);
        $plano->save();

        return redirect()->route('consultar_plano_mesa_gastronomia_crown', GastroPlanoMesaListadoFiltros::paraQueryString(
            GastroPlanoMesaListadoFiltros::resolverDesdeRequest($request)
        ))->with('mensaje', 'Plano actualizado con éxito');
    }

    public function eliminar(Request $request, $id)
    {
        $this->assertCrown();
        can('eliminar-plano-mesa-gastronomia-crown');

        $plano = GastroPlanoMesa::query()->findOrFail($id);
        $this->assertEmpresaPermitida((int) $plano->empresa_id);

        // Baja física + audits (sin SoftDeletes en la tabla).
        $plano->configuraciones()->detach();
        $plano->delete();

        if ($request->ajax()) {
            return response()->json(['mensaje' => 'ok']);
        }

        return redirect()->route('consultar_plano_mesa_gastronomia_crown')
            ->with('mensaje', 'Plano eliminado con éxito');
    }

    /**
     * Mesas de la sala/empresa del plano, con marca de cuáles ya están ubicadas.
     */
    public function apiMesasSala(Request $request): JsonResponse
    {
        $this->assertCrown();
        if (! can('crear-plano-mesa-gastronomia-crown', false)
            && ! can('editar-plano-mesa-gastronomia-crown', false)) {
            abort(403);
        }

        $empresaId = (int) $request->input('empresa_id');
        $salaId = $request->filled('sala_id') ? (int) $request->input('sala_id') : null;
        if ($empresaId <= 0) {
            return response()->json(['ok' => true, 'mesas' => []]);
        }
        $this->assertEmpresaPermitida($empresaId);

        $mesas = MesaGastronomia::query()
            ->where('empresa_id', $empresaId)
            ->when($salaId, fn ($q) => $q->where('sala_id', $salaId))
            ->orderBy('codigo')
            ->orderBy('nombre')
            ->get(['id', 'codigo', 'nombre', 'numeromesa', 'sala_id'])
            ->map(fn (MesaGastronomia $m) => [
                'id' => (int) $m->id,
                'codigo' => (string) ($m->codigo ?: $m->numeromesa ?: $m->id),
                'nombre' => (string) $m->nombre,
                'sala_id' => $m->sala_id ? (int) $m->sala_id : null,
            ])
            ->values();

        return response()->json(['ok' => true, 'mesas' => $mesas]);
    }

    /**
     * Guardado del layout por AJAX (botón «Guardar plano» del editor).
     */
    public function apiGuardarLayout(Request $request, int $id): JsonResponse
    {
        $this->assertCrown();
        can('actualizar-plano-mesa-gastronomia-crown');

        $plano = GastroPlanoMesa::query()->findOrFail($id);
        $this->assertEmpresaPermitida((int) $plano->empresa_id);

        $resultado = GastroPlanoMesaLayoutSupport::normalizarParaPlano(
            $request->input('layout_json'),
            (int) $plano->empresa_id,
            $plano->sala_id ? (int) $plano->sala_id : null,
        );

        $plano->layout_json = $resultado['layout'];
        $plano->save();

        return response()->json([
            'ok' => true,
            'mensaje' => $resultado['descartados'] > 0
                ? 'Plano guardado. Se descartaron '.$resultado['descartados'].' mesa(s) que no pertenecen a la sala.'
                : 'Plano guardado.',
            'layout' => $resultado['layout'],
            'descartados' => $resultado['descartados'],
        ]);
    }

    private function asignarDesdeRequest(GastroPlanoMesa $plano, ValidacionGastroPlanoMesa $request): void
    {
        $plano->empresa_id = (int) $request->input('empresa_id');
        $plano->sala_id = $request->input('sala_id') !== null ? (int) $request->input('sala_id') : null;
        $plano->codigo = $request->input('codigo');
        $plano->nombre = (string) $request->input('nombre');
        $plano->activo = $request->boolean('activo');
        $plano->orden = (int) ($request->input('orden') ?: 0);

        $resultado = GastroPlanoMesaLayoutSupport::normalizarParaPlano(
            $request->input('layout_json'),
            (int) $plano->empresa_id,
            $plano->sala_id,
        );
        $plano->layout_json = $resultado['layout'];
    }

    /**
     * @return array<string, mixed>
     */
    private function datosFormulario(GastroPlanoMesa $data, Request $request): array
    {
        $empresaQuery = $this->empresaRepository->allFiltrado();

        $empresaId = (int) (old('empresa_id', $data->empresa_id) ?: 0);
        if ($empresaId <= 0 && $empresaQuery->count() === 1) {
            // Una sola empresa asignada: el partial la fija en hidden, el editor ya puede listar mesas.
            $empresaId = (int) $empresaQuery->first()->id;
        }
        $salaId = old('sala_id', $data->sala_id);
        $salaId = $salaId !== null && $salaId !== '' ? (int) $salaId : null;

        $layout = GastroPlanoMesaLayoutSupport::paraVisor(old('layout_json', $data->layout_json));
        $mesaIdsEnPlano = collect($layout['nodes'])->pluck('mesa_id')->map(fn ($v) => (int) $v)->all();

        $mesas = $empresaId > 0
            ? MesaGastronomia::query()
                ->where('empresa_id', $empresaId)
                ->when($salaId, fn ($q) => $q->where('sala_id', $salaId))
                ->orderBy('codigo')
                ->orderBy('nombre')
                ->get(['id', 'codigo', 'nombre', 'numeromesa', 'sala_id'])
                ->map(fn (MesaGastronomia $m) => [
                    'id' => (int) $m->id,
                    'codigo' => (string) ($m->codigo ?: $m->numeromesa ?: $m->id),
                    'nombre' => (string) $m->nombre,
                    'sala_id' => $m->sala_id ? (int) $m->sala_id : null,
                ])
                ->values()
                ->all()
            : [];

        return [
            'data' => $data,
            'empresa_query' => $empresaQuery,
            'sala_query' => $this->salasParaFiltro(),
            'layout_editor' => $layout,
            'mesas_editor' => $mesas,
            'mesa_ids_en_plano' => $mesaIdsEnPlano,
            'filtrosQuery' => GastroPlanoMesaListadoFiltros::paraQueryString(
                GastroPlanoMesaListadoFiltros::resolverDesdeRequest($request)
            ),
        ];
    }

    /**
     * @return \Illuminate\Support\Collection<int, Sala>
     */
    private function salasParaFiltro()
    {
        $q = Sala::query()->orderBy('codigo')->orderBy('nombre');
        $ids = UsuarioSalaAsignadaSupport::idsDesdeSesion();
        if (count($ids) >= 1) {
            $q->whereIn('id', $ids);
        }

        return $q->get(['id', 'codigo', 'nombre']);
    }

    private function assertCrown(): void
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            abort(404);
        }
    }

    private function assertEmpresaPermitida(int $empresaId): void
    {
        if ($empresaId > 0 && ! $this->empresaRepository->empresaIdPermitida($empresaId)) {
            abort(403);
        }
    }
}

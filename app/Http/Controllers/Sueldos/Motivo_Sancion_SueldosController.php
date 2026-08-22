<?php

namespace App\Http\Controllers\Sueldos;

use App\Exports\Sueldos\MotivoSancionSueldosListadoExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\ValidacionMotivoSancion_Sueldos;
use App\Repositories\Sueldos\Motivo_Sancion_SueldosRepositoryInterface;
use App\Support\Sueldos\MotivoSancionSueldosListadoFiltros;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;

class Motivo_Sancion_SueldosController extends Controller
{
    public function __construct(private Motivo_Sancion_SueldosRepositoryInterface $repository)
    {
    }

    public function index(Request $request)
    {
        can('listar-motivo-sancion-sueldos');
        $filtros = MotivoSancionSueldosListadoFiltros::resolverDesdeRequest($request);
        $datas = $this->repository->leeMotivoSancion($filtros, true);

        return view('sueldos.motivo_sancion.index', [
            'datas' => $datas,
            'filtros' => $filtros,
            'filtrosQuery' => MotivoSancionSueldosListadoFiltros::paraQueryString($filtros),
            'camposFiltro' => MotivoSancionSueldosListadoFiltros::CAMPOS,
        ]);
    }

    public function listar(Request $request, $formato = null, $busqueda = null)
    {
        can('listar-motivo-sancion-sueldos');
        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');
        $filtros = MotivoSancionSueldosListadoFiltros::resolverDesdeRequest($request, $busqueda);

        switch ($formato) {
            case 'PDF':
                $datas = $this->repository->leeMotivoSancion($filtros, false);
                $view = \View::make('sueldos.motivo_sancion.listado', compact('datas'))->render();
                $path = storage_path('pdf/listados');
                if (! is_dir($path)) {
                    @mkdir($path, 0775, true);
                }
                $pdf = \App::make('dompdf.wrapper');
                $pdf->setPaper('legal', 'landscape');
                $pdf->loadHTML($view)->save($path.'/listado_motivo_sancion_sueldos.pdf');

                return response()->download($path.'/listado_motivo_sancion_sueldos.pdf');
            case 'EXCEL':
                return app(MotivoSancionSueldosListadoExport::class)
                    ->parametros($filtros)
                    ->download('motivo_sancion_sueldos.xlsx');
            case 'CSV':
                return app(MotivoSancionSueldosListadoExport::class)
                    ->parametros($filtros)
                    ->download('motivo_sancion_sueldos.csv', \Maatwebsite\Excel\Excel::CSV);
        }

        return redirect()->route('consultar_motivo_sancion_sueldos', MotivoSancionSueldosListadoFiltros::paraQueryString($filtros));
    }

    public function crear()
    {
        can('crear-motivo-sancion-sueldos');

        return view('sueldos.motivo_sancion.crear');
    }

    public function guardar(ValidacionMotivoSancion_Sueldos $request)
    {
        can('crear-motivo-sancion-sueldos');
        $this->repository->create($request->validated());

        return redirect()->route('consultar_motivo_sancion_sueldos')
            ->with('mensaje', 'Motivo de sanción creado con éxito');
    }

    public function editar($id)
    {
        can('editar-motivo-sancion-sueldos');

        return view('sueldos.motivo_sancion.editar', ['data' => $this->repository->findOrFail($id)]);
    }

    public function actualizar(ValidacionMotivoSancion_Sueldos $request, $id)
    {
        can('actualizar-motivo-sancion-sueldos');
        $this->repository->update($request->validated(), $id);

        return redirect()->route('consultar_motivo_sancion_sueldos')
            ->with('mensaje', 'Motivo de sanción actualizado con éxito');
    }

    public function eliminar(Request $request, $id)
    {
        can('borrar-motivo-sancion-sueldos');
        if ($request->ajax()) {
            return response()->json(['mensaje' => $this->repository->delete($id) ? 'ok' : 'ng']);
        }
        abort(404);
    }

    public function consulta(Request $request)
    {
        $this->assertPuedeConsultar();
        $data = $this->repository->listadoParaConsulta((string) $request->input('consulta', ''));
        $puedeAbrirAbm = can('editar-motivo-sancion-sueldos', false) || can('listar-motivo-sancion-sueldos', false);
        $output = ['data' => ''];
        if ($data->isEmpty()) {
            $output['data'] = '<tr><td colspan="4">Sin resultados</td></tr>';
        } else {
            foreach ($data as $row) {
                $output['data'] .= '<tr>';
                $output['data'] .= '<td class="motivo_sancion_id">'.e($row->id).'</td>';
                $output['data'] .= '<td class="codigomotivosancion">'.e($row->codigo).'</td>';
                $output['data'] .= '<td class="nombremotivosancion">'.e($row->nombre).'</td>';
                $output['data'] .= '<td class="text-nowrap"><a class="btn btn-warning btn-sm eligeconsultamotivo_sancion">Elegir</a>';
                if ($puedeAbrirAbm) {
                    $url = route('editar_motivo_sancion_sueldos', [
                        'id' => $row->id,
                        'origen' => 'modal_consulta',
                        'vista' => 'consulta',
                    ]);
                    $output['data'] .= ' <a class="btn btn-info btn-sm" href="'.e($url).'" target="_blank" rel="noopener">Consultar</a>';
                }
                $output['data'] .= '</td></tr>';
            }
        }

        return response()->json($output);
    }

    public function leerPorCodigo($codigo)
    {
        $this->assertPuedeConsultar();
        $motivo = $this->repository->findActivoPorCodigo((int) preg_replace('/\D+/', '', (string) $codigo));
        if ($motivo === null) {
            return response()->json(['error' => 'Motivo no encontrado'], 404);
        }

        return response()->json($this->payload($motivo));
    }

    public function leer($id)
    {
        $this->assertPuedeConsultar();
        try {
            $motivo = $this->repository->find((int) $id);
        } catch (ModelNotFoundException $e) {
            return response()->json(['error' => 'Motivo no encontrado'], 404);
        }
        if (! $motivo->activo) {
            return response()->json(['error' => 'Motivo inactivo'], 404);
        }

        return response()->json($this->payload($motivo));
    }

    private function payload($motivo): array
    {
        return [
            'id' => (int) $motivo->id,
            'codigo' => (int) $motivo->codigo,
            'nombre' => (string) $motivo->nombre,
        ];
    }

    private function assertPuedeConsultar(): void
    {
        if (
            can('listar-motivo-sancion-sueldos', false)
            || can('listar-sancion-empleado-sueldos', false)
            || can('crear-sancion-empleado-sueldos', false)
            || can('editar-empleado-sueldos', false)
        ) {
            return;
        }
        abort(403);
    }
}

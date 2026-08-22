<?php

namespace App\Http\Controllers\Sueldos;

use App\Exports\Sueldos\TipoSancionSueldosListadoExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\ValidacionTipoSancion_Sueldos;
use App\Repositories\Sueldos\Tipo_Sancion_SueldosRepositoryInterface;
use App\Support\Sueldos\TipoSancionSueldosListadoFiltros;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;

class Tipo_Sancion_SueldosController extends Controller
{
    public function __construct(private Tipo_Sancion_SueldosRepositoryInterface $repository)
    {
    }

    public function index(Request $request)
    {
        can('listar-tipo-sancion-sueldos');
        $filtros = TipoSancionSueldosListadoFiltros::resolverDesdeRequest($request);
        $datas = $this->repository->leeTipoSancion($filtros, true);

        return view('sueldos.tipo_sancion.index', [
            'datas' => $datas,
            'filtros' => $filtros,
            'filtrosQuery' => TipoSancionSueldosListadoFiltros::paraQueryString($filtros),
            'camposFiltro' => TipoSancionSueldosListadoFiltros::CAMPOS,
        ]);
    }

    public function listar(Request $request, $formato = null, $busqueda = null)
    {
        can('listar-tipo-sancion-sueldos');
        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');
        $filtros = TipoSancionSueldosListadoFiltros::resolverDesdeRequest($request, $busqueda);

        switch ($formato) {
            case 'PDF':
                $datas = $this->repository->leeTipoSancion($filtros, false);
                $view = \View::make('sueldos.tipo_sancion.listado', compact('datas'))->render();
                $path = storage_path('pdf/listados');
                if (! is_dir($path)) {
                    @mkdir($path, 0775, true);
                }
                $pdf = \App::make('dompdf.wrapper');
                $pdf->setPaper('legal', 'landscape');
                $pdf->loadHTML($view)->save($path.'/listado_tipo_sancion_sueldos.pdf');

                return response()->download($path.'/listado_tipo_sancion_sueldos.pdf');
            case 'EXCEL':
                return app(TipoSancionSueldosListadoExport::class)
                    ->parametros($filtros)
                    ->download('tipo_sancion_sueldos.xlsx');
            case 'CSV':
                return app(TipoSancionSueldosListadoExport::class)
                    ->parametros($filtros)
                    ->download('tipo_sancion_sueldos.csv', \Maatwebsite\Excel\Excel::CSV);
        }

        return redirect()->route('consultar_tipo_sancion_sueldos', TipoSancionSueldosListadoFiltros::paraQueryString($filtros));
    }

    public function crear()
    {
        can('crear-tipo-sancion-sueldos');

        return view('sueldos.tipo_sancion.crear');
    }

    public function guardar(ValidacionTipoSancion_Sueldos $request)
    {
        can('crear-tipo-sancion-sueldos');
        $this->repository->create($request->validated());

        return redirect()->route('consultar_tipo_sancion_sueldos')
            ->with('mensaje', 'Tipo de sanción creado con éxito');
    }

    public function editar($id)
    {
        can('editar-tipo-sancion-sueldos');

        return view('sueldos.tipo_sancion.editar', ['data' => $this->repository->findOrFail($id)]);
    }

    public function actualizar(ValidacionTipoSancion_Sueldos $request, $id)
    {
        can('actualizar-tipo-sancion-sueldos');
        $this->repository->update($request->validated(), $id);

        return redirect()->route('consultar_tipo_sancion_sueldos')
            ->with('mensaje', 'Tipo de sanción actualizado con éxito');
    }

    public function eliminar(Request $request, $id)
    {
        can('borrar-tipo-sancion-sueldos');
        if ($request->ajax()) {
            return response()->json(['mensaje' => $this->repository->delete($id) ? 'ok' : 'ng']);
        }
        abort(404);
    }

    public function consulta(Request $request)
    {
        $this->assertPuedeConsultar();
        $data = $this->repository->listadoParaConsulta((string) $request->input('consulta', ''));
        $puedeAbrirAbm = can('editar-tipo-sancion-sueldos', false) || can('listar-tipo-sancion-sueldos', false);
        $output = ['data' => ''];
        if ($data->isEmpty()) {
            $output['data'] = '<tr><td colspan="5">Sin resultados</td></tr>';
        } else {
            foreach ($data as $row) {
                $output['data'] .= '<tr>';
                $output['data'] .= '<td class="tipo_sancion_id">'.e($row->id).'</td>';
                $output['data'] .= '<td class="codigotiposancion">'.e($row->codigo).'</td>';
                $output['data'] .= '<td class="nombretiposancion">'.e($row->nombre).'</td>';
                $output['data'] .= '<td>'.e(\App\Models\Sueldos\Tipo_Sancion_Sueldos::etiquetaClase($row->clase)).'</td>';
                $output['data'] .= '<td class="text-nowrap"><a class="btn btn-warning btn-sm eligeconsultatipo_sancion">Elegir</a>';
                if ($puedeAbrirAbm) {
                    $url = route('editar_tipo_sancion_sueldos', [
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
        $tipo = $this->repository->findActivoPorCodigo((int) preg_replace('/\D+/', '', (string) $codigo));
        if ($tipo === null) {
            return response()->json(['error' => 'Tipo de sanción no encontrado'], 404);
        }

        return response()->json($this->payload($tipo));
    }

    public function leer($id)
    {
        $this->assertPuedeConsultar();
        try {
            $tipo = $this->repository->find((int) $id);
        } catch (ModelNotFoundException $e) {
            return response()->json(['error' => 'Tipo de sanción no encontrado'], 404);
        }
        if (! $tipo->activo) {
            return response()->json(['error' => 'Tipo de sanción inactivo'], 404);
        }

        return response()->json($this->payload($tipo));
    }

    private function payload($tipo): array
    {
        return [
            'id' => (int) $tipo->id,
            'codigo' => (int) $tipo->codigo,
            'nombre' => (string) $tipo->nombre,
            'clase' => (string) $tipo->clase,
            'requiere_dias' => (bool) $tipo->requiere_dias,
            'tipo_dias' => (string) $tipo->tipo_dias,
            'tope_dias' => $tipo->tope_dias,
            'genera_novedad' => (bool) $tipo->genera_novedad,
            'plazo_descargo_dias' => (int) $tipo->plazo_descargo_dias,
        ];
    }

    private function assertPuedeConsultar(): void
    {
        if (
            can('listar-tipo-sancion-sueldos', false)
            || can('listar-sancion-empleado-sueldos', false)
            || can('crear-sancion-empleado-sueldos', false)
            || can('editar-empleado-sueldos', false)
        ) {
            return;
        }
        abort(403);
    }
}

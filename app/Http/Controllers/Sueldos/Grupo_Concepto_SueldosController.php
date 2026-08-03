<?php

namespace App\Http\Controllers\Sueldos;

use App\Exports\Sueldos\GrupoConceptoSueldosListadoExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\ValidacionGrupo_Concepto_Sueldos;
use App\Models\Sueldos\Concepto_Sueldos;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Repositories\Sueldos\Grupo_Concepto_SueldosRepositoryInterface;
use App\Support\Sueldos\GrupoConceptoSueldosListadoFiltros;
use Illuminate\Http\Request;

class Grupo_Concepto_SueldosController extends Controller
{
    public function __construct(
        private Grupo_Concepto_SueldosRepositoryInterface $repository,
        private EmpresaRepositoryInterface $empresaRepository,
    ) {
    }

    public function index(Request $request)
    {
        can('listar-grupo-concepto-sueldos');

        $empresaDefault = optional($this->empresaRepository->allFiltrado()->first())->id;
        $filtros = GrupoConceptoSueldosListadoFiltros::resolverDesdeRequest(
            $request,
            null,
            $empresaDefault ? (int) $empresaDefault : null
        );
        $datas = $this->repository->leeGrupo($filtros, true);

        return view('sueldos.grupo_concepto.index', [
            'datas' => $datas,
            'filtros' => $filtros,
            'filtrosQuery' => GrupoConceptoSueldosListadoFiltros::paraQueryString($filtros),
            'camposFiltro' => GrupoConceptoSueldosListadoFiltros::CAMPOS,
            'empresa_query' => $this->empresaRepository->allFiltrado(),
        ]);
    }

    public function listar(Request $request, $formato = null, $busqueda = null)
    {
        can('listar-grupo-concepto-sueldos');

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $empresaDefault = optional($this->empresaRepository->allFiltrado()->first())->id;
        $filtros = GrupoConceptoSueldosListadoFiltros::resolverDesdeRequest(
            $request,
            $busqueda,
            $empresaDefault ? (int) $empresaDefault : null
        );

        switch ($formato) {
            case 'PDF':
                $datas = $this->repository->leeGrupo($filtros, false);

                $view = \View::make('sueldos.grupo_concepto.listado', compact('datas'))->render();
                $path = storage_path('pdf/listados');
                if (! is_dir($path)) {
                    @mkdir($path, 0775, true);
                }
                $nombre_pdf = 'listado_grupo_concepto_sueldos';

                $pdf = \App::make('dompdf.wrapper');
                $pdf->setPaper('legal', 'landscape');
                $pdf->loadHTML($view)->save($path.'/'.$nombre_pdf.'.pdf');

                return response()->download($path.'/'.$nombre_pdf.'.pdf');

            case 'EXCEL':
                return app(GrupoConceptoSueldosListadoExport::class)
                    ->parametros($filtros)
                    ->download('grupo_concepto_sueldos.xlsx');

            case 'CSV':
                return app(GrupoConceptoSueldosListadoExport::class)
                    ->parametros($filtros)
                    ->download('grupo_concepto_sueldos.csv', \Maatwebsite\Excel\Excel::CSV);
        }

        return redirect()->route(
            'consultar_grupo_concepto_sueldos',
            GrupoConceptoSueldosListadoFiltros::paraQueryString($filtros)
        );
    }

    public function crear()
    {
        can('crear-grupo-concepto-sueldos');

        return view('sueldos.grupo_concepto.crear', $this->formData());
    }

    public function guardar(ValidacionGrupo_Concepto_Sueldos $request)
    {
        can('crear-grupo-concepto-sueldos');
        $this->repository->create($request->validated());

        return redirect()->route('consultar_grupo_concepto_sueldos')->with('mensaje', 'Grupo creado con éxito');
    }

    public function editar(Request $request, $id)
    {
        $modoConsulta = $request->query('origen') === 'modal_consulta'
            || $request->query('vista') === 'consulta';
        if ($modoConsulta) {
            if (! $this->puedeConsultarGrupoOperativo()) {
                abort(403);
            }
        } else {
            can('editar-grupo-concepto-sueldos');
        }

        $data = $this->repository->findOrFail($id);
        $puedeActualizar = can('actualizar-grupo-concepto-sueldos', false);

        return view('sueldos.grupo_concepto.editar', array_merge($this->formData($data), [
            'data' => $data,
            'modoConsulta' => $modoConsulta,
            'soloConsulta' => $modoConsulta && ! $puedeActualizar,
        ]));
    }

    /** ABM grupos o pantallas que abren el grupo desde legajo / set de conceptos. */
    private function puedeConsultarGrupoOperativo(): bool
    {
        return can('listar-grupo-concepto-sueldos', false)
            || can('editar-grupo-concepto-sueldos', false)
            || can('crear-grupo-concepto-sueldos', false)
            || can('editar-empleado-sueldos', false)
            || can('actualizar-empleado-sueldos', false);
    }

    public function actualizar(ValidacionGrupo_Concepto_Sueldos $request, $id)
    {
        can('actualizar-grupo-concepto-sueldos');
        $this->repository->update($request->validated(), $id);

        return redirect()->route('consultar_grupo_concepto_sueldos')->with('mensaje', 'Grupo actualizado con éxito');
    }

    public function eliminar(Request $request, $id)
    {
        can('borrar-grupo-concepto-sueldos');
        if ($request->ajax()) {
            return response()->json(['mensaje' => $this->repository->delete($id) ? 'ok' : 'ng']);
        }
        abort(404);
    }

    public function sincronizarAnita()
    {
        can('crear-grupo-concepto-sueldos');
        $r = $this->repository->sincronizarConAnita();
        $msg = 'Anita: '.$r['en_anita'].' filas · Grupos nuevos: '.$r['grupos']
            .' · Ítems: '.$r['items'].' · Omitidos: '.$r['omitidos']
            .' · Códigos emp_grp*: '.($r['codigos_empleado'] ?? 0)
            .' · Empleados vinculados: '.($r['vinculados'] ?? 0);
        if ($r['errores'] !== []) {
            $msg .= ' · '.implode('; ', array_slice($r['errores'], 0, 3));
        }

        return redirect()->route('consultar_grupo_concepto_sueldos')->with('mensaje', $msg);
    }

    /** @return array<string, mixed> */
    private function formData($data = null): array
    {
        $seleccionados = [];
        if ($data) {
            $seleccionados = $data->items->pluck('concepto_id')->map(fn ($id) => (int) $id)->all();
        }

        return [
            'empresa_query' => $this->empresaRepository->allFiltrado(),
            'conceptos' => Concepto_Sueldos::query()->where('activo', true)->orderBy('codigo')->get(['id', 'codigo', 'descripcion', 'tipo']),
            'seleccionados' => $seleccionados,
        ];
    }
}

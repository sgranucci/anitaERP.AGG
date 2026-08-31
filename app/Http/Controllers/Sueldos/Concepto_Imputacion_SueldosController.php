<?php

namespace App\Http\Controllers\Sueldos;

use App\Exports\Sueldos\ConceptoImputacionSueldosListadoExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\ValidacionConceptoImputacion_Sueldos;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Repositories\Sueldos\Concepto_Imputacion_SueldosRepositoryInterface;
use App\Support\Sueldos\ConceptoImputacionSueldosListadoFiltros;
use App\Support\Sueldos\SueldosAsientoCoberturaSupport;
use App\Support\Sueldos\SueldosAsientoModoSupport;
use Illuminate\Http\Request;

class Concepto_Imputacion_SueldosController extends Controller
{
    public function __construct(
        private Concepto_Imputacion_SueldosRepositoryInterface $repository,
        private EmpresaRepositoryInterface $empresaRepository,
    ) {
    }

    public function index(Request $request)
    {
        can('listar-imputacion-concepto-sueldos');

        $empresaDefault = optional($this->empresaRepository->allFiltrado()->first())->id;
        $filtros = ConceptoImputacionSueldosListadoFiltros::resolverDesdeRequest(
            $request,
            null,
            $empresaDefault ? (int) $empresaDefault : null
        );
        $datas = $this->repository->leeImputacion($filtros, true);

        $cobertura = null;
        $modoAsiento = null;
        if (($filtros['empresa_scope'] ?? 'una') === 'una' && ! empty($filtros['empresa_id'])) {
            $cobertura = SueldosAsientoCoberturaSupport::informe((int) $filtros['empresa_id']);
            $modoAsiento = SueldosAsientoModoSupport::resolver((int) $filtros['empresa_id']);
        }

        return view('sueldos.imputacion_concepto.index', [
            'datas' => $datas,
            'filtros' => $filtros,
            'filtrosQuery' => ConceptoImputacionSueldosListadoFiltros::paraQueryString($filtros),
            'camposFiltro' => ConceptoImputacionSueldosListadoFiltros::CAMPOS,
            'empresa_query' => $this->empresaRepository->allFiltrado(),
            'cobertura' => $cobertura,
            'modoAsiento' => $modoAsiento,
        ]);
    }

    public function listar(Request $request, $formato = null, $busqueda = null)
    {
        can('listar-imputacion-concepto-sueldos');
        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $empresaDefault = optional($this->empresaRepository->allFiltrado()->first())->id;
        $filtros = ConceptoImputacionSueldosListadoFiltros::resolverDesdeRequest(
            $request,
            $busqueda,
            $empresaDefault ? (int) $empresaDefault : null
        );

        switch ($formato) {
            case 'PDF':
                $datas = $this->repository->leeImputacion($filtros, false);
                $view = \View::make('sueldos.imputacion_concepto.listado', compact('datas'))->render();
                $path = storage_path('pdf/listados');
                if (! is_dir($path)) {
                    @mkdir($path, 0775, true);
                }
                $pdf = \App::make('dompdf.wrapper');
                $pdf->setPaper('legal', 'landscape');
                $pdf->loadHTML($view)->save($path.'/listado_imputacion_concepto_sueldos.pdf');

                return response()->download($path.'/listado_imputacion_concepto_sueldos.pdf');
            case 'EXCEL':
                return app(ConceptoImputacionSueldosListadoExport::class)
                    ->parametros($filtros)
                    ->download('imputacion_concepto_sueldos.xlsx');
            case 'CSV':
                return app(ConceptoImputacionSueldosListadoExport::class)
                    ->parametros($filtros)
                    ->download('imputacion_concepto_sueldos.csv', \Maatwebsite\Excel\Excel::CSV);
        }

        return redirect()->route(
            'consultar_imputacion_concepto_sueldos',
            ConceptoImputacionSueldosListadoFiltros::paraQueryString($filtros)
        );
    }

    public function guardarModoAsiento(Request $request)
    {
        can('actualizar-imputacion-concepto-sueldos');

        $empresaId = (int) $request->input('empresa_id');
        if ($empresaId <= 0 || ! $this->empresaRepository->empresaIdPermitida($empresaId)) {
            abort(403);
        }

        SueldosAsientoModoSupport::guardar($empresaId, (string) $request->input('modo'));

        return redirect()->route('consultar_imputacion_concepto_sueldos', [
            'empresa_id' => $empresaId,
        ])->with('mensaje', 'Modo de asiento actualizado.');
    }

    public function crear()
    {
        can('crear-imputacion-concepto-sueldos');

        return view('sueldos.imputacion_concepto.crear', $this->formData());
    }

    public function guardar(ValidacionConceptoImputacion_Sueldos $request)
    {
        can('crear-imputacion-concepto-sueldos');
        $this->repository->create($request->validated());

        return redirect()->route('consultar_imputacion_concepto_sueldos')
            ->with('mensaje', 'Imputación contable creada con éxito');
    }

    public function editar(Request $request, $id)
    {
        $modoConsulta = $request->query('origen') === 'modal_consulta'
            || $request->query('vista') === 'consulta';
        if ($modoConsulta) {
            if (! $this->puedeConsultarOperativo()) {
                abort(403);
            }
        } else {
            can('editar-imputacion-concepto-sueldos');
        }

        $data = $this->repository->findOrFail($id);
        if (! $this->empresaRepository->empresaIdPermitida((int) $data->empresa_id)) {
            abort(403);
        }

        return view('sueldos.imputacion_concepto.editar', array_merge($this->formData($data), [
            'data' => $data,
            'modoConsulta' => $modoConsulta,
            'soloConsulta' => $modoConsulta && ! can('actualizar-imputacion-concepto-sueldos', false),
        ]));
    }

    public function actualizar(ValidacionConceptoImputacion_Sueldos $request, $id)
    {
        can('actualizar-imputacion-concepto-sueldos');
        $this->repository->update($request->validated(), $id);

        return redirect()->route('consultar_imputacion_concepto_sueldos')
            ->with('mensaje', 'Imputación contable actualizada con éxito');
    }

    public function eliminar(Request $request, $id)
    {
        can('borrar-imputacion-concepto-sueldos');
        if ($request->ajax()) {
            $data = $this->repository->findOrFail($id);
            if (! $this->empresaRepository->empresaIdPermitida((int) $data->empresa_id)) {
                return response()->json(['mensaje' => 'ng', 'error' => 'Empresa no permitida'], 403);
            }

            return response()->json(['mensaje' => $this->repository->delete($id) ? 'ok' : 'ng']);
        }

        abort(404);
    }

    private function puedeConsultarOperativo(): bool
    {
        return can('listar-imputacion-concepto-sueldos', false)
            || can('editar-imputacion-concepto-sueldos', false)
            || can('crear-imputacion-concepto-sueldos', false);
    }

    /** @return array<string, mixed> */
    private function formData($data = null): array
    {
        return [
            'empresa_query' => $this->empresaRepository->allFiltrado(),
            'data' => $data,
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Controllers\Configuracion;

use App\Exports\Configuracion\RegimenPercepcionListadoExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\ValidacionRegimenPercepcion;
use App\Models\Configuracion\RegimenPercepcion;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Repositories\Configuracion\RegimenPercepcionRepositoryInterface;
use App\Support\Configuracion\PercepcionNoCategorizadoSupport;
use App\Support\Configuracion\RegimenPercepcionListadoFiltros;
use App\Support\Configuracion\RegimenPercepcionSupport;
use App\Support\Listado\QueryRetornoListado;
use Illuminate\Http\Request;
use RuntimeException;

class RegimenPercepcionController extends Controller
{
    public function __construct(
        private readonly RegimenPercepcionRepositoryInterface $repository,
        private readonly EmpresaRepositoryInterface $empresaRepository,
    ) {}

    public function index(Request $request)
    {
        can('listar-regimen-percepcion');

        $filtros = RegimenPercepcionListadoFiltros::resolverDesdeRequest($request);
        $datas = $this->repository->leeRegimenPercepcion($filtros, true);

        return view('configuracion.regimen_percepcion.index', [
            'datas' => $datas,
            'filtros' => $filtros,
            'filtrosQuery' => RegimenPercepcionListadoFiltros::paraQueryString($filtros),
            'camposFiltro' => RegimenPercepcionListadoFiltros::CAMPOS,
        ]);
    }

    public function listar(Request $request, $formato = null, $busqueda = null)
    {
        can('listar-regimen-percepcion');

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $filtros = RegimenPercepcionListadoFiltros::resolverDesdeRequest($request, $busqueda);

        switch ($formato) {
            case 'PDF':
                $datas = $this->repository->leeRegimenPercepcion($filtros, false);
                $view = \View::make('configuracion.regimen_percepcion.listado', compact('datas'))->render();
                $path = storage_path('pdf/listados');
                $nombrePdf = 'listado_regimen_percepcion';

                if (! is_dir($path)) {
                    @mkdir($path, 0775, true);
                }

                $pdf = \App::make('dompdf.wrapper');
                $pdf->setPaper('legal', 'landscape');
                $pdf->loadHTML($view)->save($path.'/'.$nombrePdf.'.pdf');

                return response()->download($path.'/'.$nombrePdf.'.pdf');

            case 'EXCEL':
                return (new RegimenPercepcionListadoExport($this->repository))
                    ->parametros($filtros)
                    ->download('regimenes_percepcion.xlsx');

            case 'CSV':
                return (new RegimenPercepcionListadoExport($this->repository))
                    ->parametros($filtros)
                    ->download('regimenes_percepcion.csv', \Maatwebsite\Excel\Excel::CSV);
        }

        return redirect()->route('regimen_percepcion', RegimenPercepcionListadoFiltros::paraQueryString($filtros));
    }

    public function crear(Request $request)
    {
        can('crear-regimen-percepcion');
        $data = new RegimenPercepcion();
        $filtrosQuery = QueryRetornoListado::desdeRequest($request, RegimenPercepcionListadoFiltros::class);
        $empresa_query = $this->empresaRepository->allFiltrado();

        return view('configuracion.regimen_percepcion.crear', compact('data', 'filtrosQuery', 'empresa_query'));
    }

    public function guardar(ValidacionRegimenPercepcion $request)
    {
        can('crear-regimen-percepcion');
        $regimen = $this->repository->create($request->validated());
        $this->sincronizarCuentas($request, (int) $regimen->id);

        return redirect()->route('regimen_percepcion', QueryRetornoListado::desdeRequest($request, RegimenPercepcionListadoFiltros::class))
            ->with('mensaje', 'Régimen de percepción creado con éxito');
    }

    public function editar(Request $request, $id)
    {
        can('editar-regimen-percepcion');
        $data = $this->repository->findOrFail($id);
        $filtrosQuery = QueryRetornoListado::desdeRequest($request, RegimenPercepcionListadoFiltros::class);
        $empresa_query = $this->empresaRepository->allFiltrado();

        return view('configuracion.regimen_percepcion.editar', compact('data', 'filtrosQuery', 'empresa_query'));
    }

    public function actualizar(ValidacionRegimenPercepcion $request, $id)
    {
        can('actualizar-regimen-percepcion');
        $this->repository->update($request->validated(), $id);
        $this->sincronizarCuentas($request, (int) $id);

        return redirect()->route('regimen_percepcion', QueryRetornoListado::desdeRequest($request, RegimenPercepcionListadoFiltros::class))
            ->with('mensaje', 'Régimen de percepción actualizado con éxito');
    }

    public function eliminar(Request $request, $id)
    {
        can('borrar-regimen-percepcion');

        if (! $request->ajax()) {
            abort(404);
        }

        try {
            if ($this->repository->delete($id)) {
                return response()->json(['mensaje' => 'ok']);
            }

            return response()->json(['mensaje' => 'ng']);
        } catch (RuntimeException $e) {
            return response()->json(['mensaje' => 'ng', 'error' => $e->getMessage()], 422);
        }
    }

    private function sincronizarCuentas(ValidacionRegimenPercepcion $request, int $regimenId): void
    {
        $this->repository->sincronizarCuentas(
            $regimenId,
            $request->input('empresa_ids', []),
            $request->input('cuentacontable_ids', []),
            $request->input('creousuario_cuentacontable_ids', [])
        );
        $codigo = strtoupper(trim((string) $request->input('codigo', '')));
        if ($codigo === RegimenPercepcionSupport::CODIGO_PNC) {
            PercepcionNoCategorizadoSupport::olvidarCache();
        }
        RegimenPercepcionSupport::olvidarCache();
    }
}

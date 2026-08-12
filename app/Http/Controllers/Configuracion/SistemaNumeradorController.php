<?php

namespace App\Http\Controllers\Configuracion;

use App\Exports\Configuracion\SistemaNumeradorListadoExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\ValidacionSistemaNumerador;
use App\Models\Configuracion\SistemaNumerador;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Repositories\Configuracion\SistemaNumeradorRepositoryInterface;
use App\Support\Configuracion\SistemaNumeradorListadoFiltros;
use App\Support\Configuracion\SistemaNumeradorSupport;
use App\Support\Listado\QueryRetornoListado;
use Illuminate\Http\Request;

class SistemaNumeradorController extends Controller
{
    public function __construct(
        private readonly SistemaNumeradorRepositoryInterface $repository,
        private readonly EmpresaRepositoryInterface $empresaRepository,
    ) {}

    public function index(Request $request)
    {
        can('listar-sistema-numerador');

        $filtros = SistemaNumeradorListadoFiltros::resolverDesdeRequest($request);
        $datas = $this->repository->leeSistemaNumerador($filtros, true);

        return view('configuracion.sistema_numerador.index', [
            'datas' => $datas,
            'filtros' => $filtros,
            'filtrosQuery' => SistemaNumeradorListadoFiltros::paraQueryString($filtros),
            'camposFiltro' => SistemaNumeradorListadoFiltros::CAMPOS,
        ]);
    }

    public function listar(Request $request, $formato = null, $busqueda = null)
    {
        can('listar-sistema-numerador');

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $filtros = SistemaNumeradorListadoFiltros::resolverDesdeRequest($request, $busqueda);

        switch ($formato) {
            case 'PDF':
                $datas = $this->repository->leeSistemaNumerador($filtros, false);
                $view = \View::make('configuracion.sistema_numerador.listado', compact('datas'))->render();
                $path = storage_path('pdf/listados');
                if (! is_dir($path)) {
                    mkdir($path, 0755, true);
                }
                $nombrePdf = 'listado_sistema_numerador';

                $pdf = \App::make('dompdf.wrapper');
                $pdf->setPaper('legal', 'landscape');
                $pdf->loadHTML($view)->save($path.'/'.$nombrePdf.'.pdf');

                return response()->download($path.'/'.$nombrePdf.'.pdf');

            case 'EXCEL':
                return (new SistemaNumeradorListadoExport($this->repository))
                    ->parametros($filtros)
                    ->download('sistema_numerador.xlsx');

            case 'CSV':
                return (new SistemaNumeradorListadoExport($this->repository))
                    ->parametros($filtros)
                    ->download('sistema_numerador.csv', \Maatwebsite\Excel\Excel::CSV);
        }

        return redirect()->route('sistema_numerador', SistemaNumeradorListadoFiltros::paraQueryString($filtros));
    }

    public function crear(Request $request)
    {
        can('crear-sistema-numerador');
        $data = new SistemaNumerador([
            'modulo' => 'caja',
            'ultimo_numero' => 0,
            'activo' => true,
            'anita_sistema' => 'ventas',
            'anita_fuente' => 'numerador',
        ]);
        $empresa_query = $this->empresaRepository->allFiltrado();
        $filtrosQuery = QueryRetornoListado::desdeRequest($request, SistemaNumeradorListadoFiltros::class);

        return view('configuracion.sistema_numerador.crear', compact('data', 'empresa_query', 'filtrosQuery'));
    }

    public function guardar(ValidacionSistemaNumerador $request)
    {
        can('crear-sistema-numerador');
        $this->repository->create($request->validated());

        return redirect()->route('sistema_numerador', QueryRetornoListado::desdeRequest($request, SistemaNumeradorListadoFiltros::class))
            ->with('mensaje', 'Numerador creado con éxito');
    }

    public function editar(Request $request, $id)
    {
        can('editar-sistema-numerador');
        $data = $this->repository->findOrFail($id);
        $empresa_query = $this->empresaRepository->allFiltrado();
        $filtrosQuery = QueryRetornoListado::desdeRequest($request, SistemaNumeradorListadoFiltros::class);

        return view('configuracion.sistema_numerador.editar', compact('data', 'empresa_query', 'filtrosQuery'));
    }

    public function actualizar(ValidacionSistemaNumerador $request, $id)
    {
        can('actualizar-sistema-numerador');
        $this->repository->update($request->validated(), $id);

        return redirect()->route('sistema_numerador', QueryRetornoListado::desdeRequest($request, SistemaNumeradorListadoFiltros::class))
            ->with('mensaje', 'Numerador actualizado con éxito');
    }

    public function eliminar(Request $request, $id)
    {
        can('borrar-sistema-numerador');

        if ($request->ajax()) {
            if ($this->repository->delete($id)) {
                return response()->json(['mensaje' => 'ok']);
            }

            return response()->json(['mensaje' => 'ng']);
        }

        abort(404);
    }

    public function sincronizarAnita(Request $request, $id)
    {
        can('sincronizar-sistema-numerador');

        try {
            $ultimo = SistemaNumeradorSupport::sincronizarDesdeAnita((int) $id);
        } catch (\Throwable $e) {
            return redirect()
                ->route('editar_sistema_numerador', ['id' => $id] + QueryRetornoListado::desdeRequest($request, SistemaNumeradorListadoFiltros::class))
                ->with('error', $e->getMessage());
        }

        return redirect()
            ->route('editar_sistema_numerador', ['id' => $id] + QueryRetornoListado::desdeRequest($request, SistemaNumeradorListadoFiltros::class))
            ->with('mensaje', 'Último número sincronizado desde Anita: '.$ultimo);
    }
}

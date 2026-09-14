<?php

namespace App\Http\Controllers\Ventas\FacturacionLocal;

use App\Exports\Ventas\TurnoLocalListadoExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\ValidacionTurnoLocal;
use App\Models\Ventas\TurnoLocal;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Repositories\Ventas\TurnoLocalRepositoryInterface;
use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\Ventas\TurnoLocalListadoFiltros;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;

class TurnoLocalController extends Controller
{
    public function __construct(
        private readonly TurnoLocalRepositoryInterface $repository,
        private readonly EmpresaRepositoryInterface $empresaRepository,
    ) {
    }

    public function index(Request $request)
    {
        $this->assertFerli();
        $this->assertPuedeListar();

        $filtros = TurnoLocalListadoFiltros::resolverDesdeRequest($request);
        $datas = $this->repository->leeTurnos($filtros, true);

        return view('ventas.facturacion_local.turno_local.index', [
            'datas' => $datas,
            'filtros' => $filtros,
            'filtrosQuery' => TurnoLocalListadoFiltros::paraQueryString($filtros),
            'camposFiltro' => TurnoLocalListadoFiltros::CAMPOS,
        ]);
    }

    public function listar(Request $request, $formato = null, $busqueda = null)
    {
        $this->assertFerli();
        $this->assertPuedeListar();

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $filtros = TurnoLocalListadoFiltros::resolverDesdeRequest($request, $busqueda);

        switch ($formato) {
            case 'PDF':
                $datas = $this->repository->leeTurnos($filtros, false);
                $view = View::make('ventas.facturacion_local.turno_local.listado', compact('datas'))->render();
                $path = storage_path('pdf/listados');
                if (! is_dir($path)) {
                    mkdir($path, 0755, true);
                }
                $nombrePdf = 'listado_turno_local';
                $pdf = \App::make('dompdf.wrapper');
                $pdf->setPaper('legal', 'landscape');
                $pdf->loadHTML($view)->save($path.'/'.$nombrePdf.'.pdf');

                return response()->download($path.'/'.$nombrePdf.'.pdf');

            case 'EXCEL':
                return (new TurnoLocalListadoExport())
                    ->parametros($filtros)
                    ->download('turno_local.xlsx');

            case 'CSV':
                return (new TurnoLocalListadoExport())
                    ->parametros($filtros)
                    ->download('turno_local.csv', \Maatwebsite\Excel\Excel::CSV);
        }

        return redirect()->route('facturacion_local_turno', TurnoLocalListadoFiltros::paraQueryString($filtros));
    }

    public function crear()
    {
        $this->assertFerli();
        can('crear-turno-local');

        return view('ventas.facturacion_local.turno_local.crear', [
            'data' => new TurnoLocal(['activo' => true, 'orden' => 0]),
            'empresa_query' => $this->empresaRepository->allFiltrado(),
        ]);
    }

    public function guardar(ValidacionTurnoLocal $request)
    {
        $this->assertFerli();
        can('crear-turno-local');
        $this->assertEmpresaPermitida((int) $request->input('empresa_id'));
        $this->repository->create($request->validated());

        return redirect()->route('facturacion_local_turno')->with('mensaje', 'Turno creado con éxito');
    }

    public function editar($id)
    {
        $this->assertFerli();
        can('editar-turno-local');
        $data = $this->repository->findOrFail($id);
        $this->assertEmpresaPermitida((int) $data->empresa_id);

        return view('ventas.facturacion_local.turno_local.editar', [
            'data' => $data,
            'empresa_query' => $this->empresaRepository->allFiltrado(),
        ]);
    }

    public function actualizar(ValidacionTurnoLocal $request, $id)
    {
        $this->assertFerli();
        can('actualizar-turno-local');
        $this->assertEmpresaPermitida((int) $request->input('empresa_id'));
        $this->repository->update($request->validated(), $id);

        return redirect()->route('facturacion_local_turno')->with('mensaje', 'Turno actualizado con éxito');
    }

    public function eliminar(Request $request, $id)
    {
        $this->assertFerli();
        can('borrar-turno-local');

        if ($request->ajax()) {
            $registro = $this->repository->findOrFail($id);
            $this->assertEmpresaPermitida((int) $registro->empresa_id);

            return response()->json([
                'mensaje' => $this->repository->delete($id) ? 'ok' : 'ng',
            ]);
        }

        abort(404);
    }

    private function assertPuedeListar(): void
    {
        if (can('listar-turno-local', false) || can('listar-turno-facturacion-local', false)) {
            return;
        }
        can('listar-turno-local');
    }

    private function assertEmpresaPermitida(int $empresaId): void
    {
        if (! $this->empresaRepository->empresaIdPermitida($empresaId)) {
            abort(403, 'Empresa no permitida para su usuario.');
        }
    }

    private function assertFerli(): void
    {
        if (! EntornoEmpresaSupport::esFerli()) {
            abort(404);
        }
    }
}

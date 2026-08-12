<?php

namespace App\Http\Controllers\Caja;

use App\Exports\Caja\TipotransaccionCajaListadoExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\ValidacionTipotransaccion_Caja;
use App\Models\Caja\Tipotransaccion_Caja;
use App\Repositories\Caja\Tipotransaccion_CajaRepositoryInterface;
use App\Support\Caja\TipotransaccionCajaListadoFiltros;
use App\Support\Listado\QueryRetornoListado;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class Tipotransaccion_CajaController extends Controller
{
    private $repository;

    public function __construct(Tipotransaccion_CajaRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function index(Request $request)
    {
        can('listar-tipo-transaccion-caja');

        $filtros = TipotransaccionCajaListadoFiltros::resolverDesdeRequest($request);
        $datas = $this->repository->leeTipotransaccionCaja($filtros, true);

        return view('caja.tipotransaccion_caja.index', [
            'datas' => $datas,
            'operacionEnum' => Tipotransaccion_Caja::$enumOperacion,
            'signoEnum' => Tipotransaccion_Caja::$enumSigno,
            'estadoEnum' => Tipotransaccion_Caja::$enumEstado,
            'filtros' => $filtros,
            'filtrosQuery' => TipotransaccionCajaListadoFiltros::paraQueryString($filtros),
            'camposFiltro' => TipotransaccionCajaListadoFiltros::CAMPOS,
        ]);
    }

    public function listar(Request $request, $formato = null, $busqueda = null)
    {
        can('listar-tipo-transaccion-caja');

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $filtros = TipotransaccionCajaListadoFiltros::resolverDesdeRequest($request, $busqueda);
        $operacionEnum = Tipotransaccion_Caja::$enumOperacion;
        $signoEnum = Tipotransaccion_Caja::$enumSigno;
        $estadoEnum = Tipotransaccion_Caja::$enumEstado;

        switch ($formato) {
            case 'PDF':
                $datas = $this->repository->leeTipotransaccionCaja($filtros, false);
                $view = \View::make('caja.tipotransaccion_caja.listado', compact(
                    'datas',
                    'operacionEnum',
                    'signoEnum',
                    'estadoEnum'
                ))->render();
                $path = storage_path('pdf/listados');
                $nombrePdf = 'listado_tipotransaccion_caja';
                $pdf = \App::make('dompdf.wrapper');
                $pdf->setPaper('legal', 'landscape');
                $pdf->loadHTML($view)->save($path.'/'.$nombrePdf.'.pdf');

                return response()->download($path.'/'.$nombrePdf.'.pdf');

            case 'EXCEL':
                return (new TipotransaccionCajaListadoExport($this->repository))
                    ->parametros($filtros)
                    ->download('tipotransaccion_caja.xlsx');

            case 'CSV':
                return (new TipotransaccionCajaListadoExport($this->repository))
                    ->parametros($filtros)
                    ->download('tipotransaccion_caja.csv', \Maatwebsite\Excel\Excel::CSV);
        }

        return redirect()->route('tipotransaccion_caja', TipotransaccionCajaListadoFiltros::paraQueryString($filtros));
    }

    public function crear(Request $request)
    {
        can('crear-tipo-transaccion-caja');
        $data = new Tipotransaccion_Caja;
        $operacionEnum = Tipotransaccion_Caja::$enumOperacion;
        $signoEnum = Tipotransaccion_Caja::$enumSigno;
        $estadoEnum = Tipotransaccion_Caja::$enumEstado;
        $filtrosQuery = QueryRetornoListado::desdeRequest($request, TipotransaccionCajaListadoFiltros::class);

        return view('caja.tipotransaccion_caja.crear', compact(
            'data',
            'operacionEnum',
            'signoEnum',
            'estadoEnum',
            'filtrosQuery'
        ));
    }

    public function guardar(ValidacionTipotransaccion_Caja $request)
    {
        can('crear-tipo-transaccion-caja');

        DB::beginTransaction();
        try {
            $this->repository->create($request->all());
            DB::commit();
        } catch (\Exception $e) {
            DB::rollback();

            return redirect()->back()->withInput()->withErrors(['error' => $e->getMessage()]);
        }

        return redirect()->route('tipotransaccion_caja', QueryRetornoListado::desdeRequest($request, TipotransaccionCajaListadoFiltros::class))
            ->with('mensaje', 'Tipo de transacción creada con éxito');
    }

    public function editar(Request $request, $id)
    {
        can('editar-tipo-transaccion-caja');
        $data = $this->repository->findOrFail($id);
        $operacionEnum = Tipotransaccion_Caja::$enumOperacion;
        $signoEnum = Tipotransaccion_Caja::$enumSigno;
        $estadoEnum = Tipotransaccion_Caja::$enumEstado;
        $filtrosQuery = QueryRetornoListado::desdeRequest($request, TipotransaccionCajaListadoFiltros::class);

        return view('caja.tipotransaccion_caja.editar', compact(
            'data',
            'operacionEnum',
            'signoEnum',
            'estadoEnum',
            'filtrosQuery'
        ));
    }

    public function actualizar(ValidacionTipotransaccion_Caja $request, $id)
    {
        can('actualizar-tipo-transaccion-caja');

        DB::beginTransaction();
        try {
            $this->repository->update($request->all(), $id);
            DB::commit();
        } catch (\Exception $e) {
            DB::rollback();

            return redirect()->back()->withInput()->withErrors(['error' => $e->getMessage()]);
        }

        return redirect()->route('tipotransaccion_caja', QueryRetornoListado::desdeRequest($request, TipotransaccionCajaListadoFiltros::class))
            ->with('mensaje', 'Tipo de transacción actualizada con éxito');
    }

    public function eliminar(Request $request, $id)
    {
        can('borrar-tipo-transaccion-caja');

        if ($request->ajax()) {
            if ($this->repository->delete($id)) {
                return response()->json(['mensaje' => 'ok']);
            }

            return response()->json(['mensaje' => 'ng']);
        }

        abort(404);
    }

    public function leeTipotransaccion_caja($id)
    {
        return $this->repository->find($id);
    }
}

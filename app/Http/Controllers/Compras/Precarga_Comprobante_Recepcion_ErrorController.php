<?php

namespace App\Http\Controllers\Compras;

use App\Exports\Compras\PrecargaRecepcionErrorListadoExport;
use App\Http\Controllers\Controller;
use App\Models\Compras\Precarga_Comprobante_Recepcion_Error;
use App\Support\Compras\PrecargaRecepcionErrorListadoFiltros;
use Illuminate\Http\Request;

class Precarga_Comprobante_Recepcion_ErrorController extends Controller
{
    public function index(Request $request)
    {
        can('listar-precarga-proveedores');

        $filtros = PrecargaRecepcionErrorListadoFiltros::resolverDesdeRequest($request);
        $datas = $this->leeErrores($filtros, true);

        return view('compras.precarga_comprobante_recepcion_error.index', [
            'datas' => $datas,
            'filtros' => $filtros,
            'filtrosQuery' => PrecargaRecepcionErrorListadoFiltros::paraQueryString($filtros),
            'camposFiltro' => PrecargaRecepcionErrorListadoFiltros::CAMPOS,
        ]);
    }

    public function listar(Request $request, $formato = null, $busqueda = null)
    {
        can('listar-precarga-proveedores');

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $filtros = PrecargaRecepcionErrorListadoFiltros::resolverDesdeRequest($request, $busqueda);

        switch ($formato) {
            case 'PDF':
                $datas = $this->leeErrores($filtros, false);
                $view = \View::make('compras.precarga_comprobante_recepcion_error.listado', compact('datas'))
                    ->render();
                $path = storage_path('pdf/listados');
                if (! is_dir($path)) {
                    @mkdir($path, 0755, true);
                }
                $nombrePdf = 'listado_precarga_recepcion_error';
                $pdf = \App::make('dompdf.wrapper');
                $pdf->setPaper('legal', 'landscape');
                $pdf->loadHTML($view)->save($path.'/'.$nombrePdf.'.pdf');

                return response()->download($path.'/'.$nombrePdf.'.pdf');

            case 'EXCEL':
                return (new PrecargaRecepcionErrorListadoExport)
                    ->parametros($filtros)
                    ->download('precarga_recepcion_error.xlsx');

            case 'CSV':
                return (new PrecargaRecepcionErrorListadoExport)
                    ->parametros($filtros)
                    ->download('precarga_recepcion_error.csv', \Maatwebsite\Excel\Excel::CSV);
        }

        return redirect()->route(
            'precarga_comprobante_recepcion_error',
            PrecargaRecepcionErrorListadoFiltros::paraQueryString($filtros)
        );
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator|\Illuminate\Support\Collection
     */
    private function leeErrores(array $filtros, bool $paginar)
    {
        $query = Precarga_Comprobante_Recepcion_Error::query()
            ->with(['usuario:id,nombre'])
            ->orderByDesc('id');

        if (PrecargaRecepcionErrorListadoFiltros::tieneCriteriosAplicados($filtros)) {
            PrecargaRecepcionErrorListadoFiltros::aplicar($query, $filtros);
        }

        return $paginar ? $query->paginate(10) : $query->get();
    }
}

<?php

namespace App\Http\Controllers\Sueldos;

use App\Exports\Sueldos\EntregaPrendaReporteExport;
use App\Http\Controllers\Controller;
use App\Models\Configuracion\Empresa;
use App\Models\Sueldos\Agrupamiento_Sueldos;
use App\Models\Sueldos\Entrega_Prenda_Sueldos;
use App\Support\Pdf\DompdfPaperSupport;
use App\Support\Sueldos\EntregaPrendaListadoFiltros;
use App\Support\Sueldos\EntregaPrendaReporteConsulta;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class Entrega_PrendaReporteController extends Controller
{
    public function index(Request $request)
    {
        can('listar-entrega-prenda');

        $filtros = EntregaPrendaListadoFiltros::resolverDesdeRequest($request);

        $datos = collect();
        $paginador = null;
        $totalCantidad = 0.0;
        if ($filtros['consultar']) {
            $datos = EntregaPrendaReporteConsulta::query($filtros)->get();
            $totalCantidad = (float) $datos->sum('cantidad');
            $paginador = $this->paginar($datos, $request);
        }

        return view('sueldos.entrega_prenda.index', [
            'datos' => $paginador ?? $datos,
            'filtros' => $filtros,
            'filtrosQuery' => EntregaPrendaListadoFiltros::paraQueryString($filtros),
            'camposFiltro' => EntregaPrendaListadoFiltros::CAMPOS,
            'totalCantidad' => $totalCantidad,
            'agrupamientos' => Agrupamiento_Sueldos::query()->orderBy('descripcion')->get(['id', 'descripcion']),
            'empresas' => Empresa::query()->orderBy('nombre')->get(['id', 'nombre']),
        ]);
    }

    public function exportar(Request $request, $formato = null)
    {
        can('listar-entrega-prenda');

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $filtros = EntregaPrendaListadoFiltros::resolverDesdeRequest($request);

        switch (strtoupper((string) $formato)) {
            case 'PDF':
                $datos = EntregaPrendaReporteConsulta::query($filtros)->get();
                $view = \View::make('sueldos.entrega_prenda.listado', [
                    'datos' => $datos,
                    'filtros' => $filtros,
                    'totalCantidad' => (float) $datos->sum('cantidad'),
                ])->render();
                $path = storage_path('pdf/listados');
                @mkdir($path, 0775, true);
                $pdf = \App::make('dompdf.wrapper');
                DompdfPaperSupport::aplicar($pdf, DompdfPaperSupport::CONTEXTO_LISTADO);
                $pdf->loadHTML($view)->save($path.'/listado_entrega_prenda.pdf');

                return response()->download($path.'/listado_entrega_prenda.pdf');

            case 'EXCEL':
                return (new EntregaPrendaReporteExport)->parametros($filtros)->download('entregas_indumentaria.xlsx');

            case 'CSV':
                return (new EntregaPrendaReporteExport)->parametros($filtros)
                    ->download('entregas_indumentaria.csv', \Maatwebsite\Excel\Excel::CSV);
        }

        return redirect()->route('entrega_prenda_reporte', EntregaPrendaListadoFiltros::paraQueryString($filtros));
    }

    public function comprobante($entregaId)
    {
        can('listar-entrega-prenda');

        $entrega = Entrega_Prenda_Sueldos::query()
            ->with([
                'empleado.empresa:id,nombre,domicilio,nroinscripcion,pais_id,provincia_id,localidad_id,codigopostal',
                'empleado.empresa.pais:id,nombre',
                'empleado.empresa.provincia:id,nombre',
                'empleado.empresa.localidad:id,nombre,codigopostal',
                'empleado.agrupamiento:id,descripcion',
                'empleado.categoria:id,descripcion',
                'empleado.lugartrabajo:id,nombre',
                'articulos.prenda:id,codigo,descripcion,marca,requiere_certificacion,norma',
                'articulos.color:id,nombre',
                'articulos.talle:id,nombre',
                'usuario:id,nombre',
                'deposito:id,nombre',
            ])
            ->findOrFail($entregaId);

        $view = \View::make('sueldos.entrega_prenda.comprobante', ['entrega' => $entrega])->render();
        $path = storage_path('pdf/comprobantes');
        @mkdir($path, 0775, true);
        $archivo = $path.'/entrega_prenda_'.$entrega->id.'.pdf';
        $pdf = \App::make('dompdf.wrapper');
        $pdf->setPaper('a4');
        $pdf->loadHTML($view)->save($archivo);

        return response()->file($archivo);
    }

    private function paginar($datos, Request $request): LengthAwarePaginator
    {
        $perPage = 15;
        $page = (int) $request->input('page', 1);
        $items = $datos->slice(($page - 1) * $perPage, $perPage)->values();

        return new LengthAwarePaginator($items, $datos->count(), $perPage, $page, [
            'path' => $request->url(),
            'query' => $request->query(),
        ]);
    }
}

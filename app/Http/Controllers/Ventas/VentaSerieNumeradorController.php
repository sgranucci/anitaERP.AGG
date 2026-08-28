<?php

namespace App\Http\Controllers\Ventas;

use App\Exports\Ventas\VentaSerieNumeradorListadoExport;
use App\Http\Controllers\Controller;
use App\Repositories\Ventas\VentaSerieNumeradorRepositoryInterface;
use App\Support\Ventas\VentaNumeradorFiscalSupport;
use App\Support\Ventas\VentaSerieNumeradorListadoFiltros;
use Illuminate\Http\Request;

class VentaSerieNumeradorController extends Controller
{
    public function __construct(
        private readonly VentaSerieNumeradorRepositoryInterface $repository,
    ) {}

    public function index(Request $request)
    {
        can('listar-venta-serie-numerador');

        $filtros = VentaSerieNumeradorListadoFiltros::resolverDesdeRequest($request);
        $datas = $this->repository->leeVentaSerieNumerador($filtros, true);

        return view('ventas.venta_serie_numerador.index', [
            'datas' => $datas,
            'filtros' => $filtros,
            'filtrosQuery' => VentaSerieNumeradorListadoFiltros::paraQueryString($filtros),
            'camposFiltro' => VentaSerieNumeradorListadoFiltros::CAMPOS,
            'enUso' => VentaNumeradorFiscalSupport::estaEnUso(),
        ]);
    }

    public function listar(Request $request, $formato = null, $busqueda = null)
    {
        can('listar-venta-serie-numerador');

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $filtros = VentaSerieNumeradorListadoFiltros::resolverDesdeRequest($request, $busqueda);

        switch ($formato) {
            case 'PDF':
                $datas = $this->repository->leeVentaSerieNumerador($filtros, false);
                $view = \View::make('ventas.venta_serie_numerador.listado', compact('datas'))->render();
                $path = storage_path('pdf/listados');
                if (! is_dir($path)) {
                    mkdir($path, 0755, true);
                }
                $nombrePdf = 'listado_venta_serie_numerador';

                $pdf = \App::make('dompdf.wrapper');
                $pdf->setPaper('legal', 'landscape');
                $pdf->loadHTML($view)->save($path.'/'.$nombrePdf.'.pdf');

                return response()->download($path.'/'.$nombrePdf.'.pdf');

            case 'EXCEL':
                return (new VentaSerieNumeradorListadoExport($this->repository))
                    ->parametros($filtros)
                    ->download('numerador_fiscal.xlsx');

            case 'CSV':
                return (new VentaSerieNumeradorListadoExport($this->repository))
                    ->parametros($filtros)
                    ->download('numerador_fiscal.csv', \Maatwebsite\Excel\Excel::CSV);
        }

        return redirect()->route('venta_serie_numerador', VentaSerieNumeradorListadoFiltros::paraQueryString($filtros));
    }

    public function sembrar(Request $request)
    {
        can('sembrar-venta-serie-numerador');

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $usarFallbackErp = $request->boolean('usar_fallback_erp');
        $query = VentaSerieNumeradorListadoFiltros::paraQueryString(
            VentaSerieNumeradorListadoFiltros::resolverDesdeRequest($request)
        );

        try {
            $r = VentaNumeradorFiscalSupport::sembrar($usarFallbackErp);
        } catch (\RuntimeException $e) {
            return redirect()
                ->route('venta_serie_numerador', $query)
                ->with('mensaje-error', $e->getMessage());
        }

        $mensaje = 'Series sembradas (Anita'
            .($usarFallbackErp ? ' + fallback ERP' : '')
            .'): '.$r['creadas'].' nuevas, '.$r['actualizadas'].' actualizadas, '
            .$r['sin_cambio'].' sin cambio'
            .' (Anita '.$r['desde_anita'].', ERP '.$r['desde_erp'].')';
        if ((int) $r['omitidas'] > 0) {
            $mensaje .= '. Omitidas '.$r['omitidas'].' (sin PV o tipo no fiscal).';
        }

        $redirect = redirect()
            ->route('venta_serie_numerador', $query)
            ->with('mensaje', $mensaje);

        if (! empty($r['aviso'])) {
            $redirect->with('mensaje-aviso', $r['aviso']);
        }

        return $redirect;
    }
}

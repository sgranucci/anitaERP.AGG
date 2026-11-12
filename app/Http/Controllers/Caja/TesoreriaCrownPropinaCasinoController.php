<?php

namespace App\Http\Controllers\Caja;

use App\Exports\Caja\TesoreriaCrownPropinaCasinoExport;
use App\Http\Controllers\Controller;
use App\Support\Caja\TesoreriaCrownAcceso;
use App\Support\Caja\TesoreriaCrownPropinaCasinoFiltros;
use App\Support\Caja\TesoreriaCrownPropinaCasinoSupport;
use App\Support\Reportes\DompdfListadoSupport;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class TesoreriaCrownPropinaCasinoController extends Controller
{
    public function index(Request $request)
    {
        TesoreriaCrownAcceso::abortSiNoEsCrown();
        can('listar-tesoreria-crown-propina-casino');

        $filtros = TesoreriaCrownPropinaCasinoFiltros::resolverDesdeRequest($request);
        $consultado = ! empty($filtros['consultar']);
        $resultado = null;
        $filasPaginadas = null;

        if ($consultado) {
            $resultado = TesoreriaCrownPropinaCasinoSupport::generar($filtros);
            $page = max(1, (int) $request->input('page', 1));
            $perPage = 50;
            $slice = array_slice($resultado['filas'], ($page - 1) * $perPage, $perPage);
            $filasPaginadas = new LengthAwarePaginator(
                $slice,
                count($resultado['filas']),
                $perPage,
                $page,
                ['path' => $request->url(), 'query' => $request->query()]
            );
        }

        return view('caja.tesoreria_crown.propina_casino.index', [
            'filtros' => $filtros,
            'filtrosQuery' => TesoreriaCrownPropinaCasinoFiltros::paraQueryString(
                array_merge($filtros, $consultado ? ['consultar' => true] : [])
            ),
            'consultado' => $consultado,
            'resultado' => $resultado,
            'filasPaginadas' => $filasPaginadas,
        ]);
    }

    public function exportar(Request $request, ?string $formato = null)
    {
        TesoreriaCrownAcceso::abortSiNoEsCrown();
        can('listar-tesoreria-crown-propina-casino');

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $filtros = TesoreriaCrownPropinaCasinoFiltros::resolverDesdeRequest($request);
        $resultado = TesoreriaCrownPropinaCasinoSupport::generar($filtros);
        $titulo = 'Propina de empleados';
        $subtitulo = $resultado['subtitulo'];
        $casinos = $resultado['casinos'];
        $filas = $resultado['filas'];
        $totales = $resultado['totales'];
        $totalDias = $resultado['total_dias'];

        switch (strtoupper((string) $formato)) {
            case 'PDF':
                $html = view('caja.tesoreria_crown.propina_casino.listado', compact(
                    'titulo',
                    'subtitulo',
                    'casinos',
                    'filas',
                    'totales',
                    'totalDias',
                ))->render();
                $ruta = storage_path('pdf/listados/reporte_propina_casino_'.date('Ymd_His').'.pdf');
                DompdfListadoSupport::guardarLegalLandscape($html, $ruta, [
                    'titulo_corto' => 'Propinas x casino',
                ]);

                return response()->download($ruta);

            case 'EXCEL':
                return (new TesoreriaCrownPropinaCasinoExport(
                    $casinos,
                    $filas,
                    $totales,
                    $titulo,
                    $subtitulo,
                    $totalDias
                ))->download('reporte_propina_casino.xlsx');

            case 'CSV':
                return (new TesoreriaCrownPropinaCasinoExport(
                    $casinos,
                    $filas,
                    $totales,
                    $titulo,
                    $subtitulo,
                    $totalDias
                ))->download('reporte_propina_casino.csv', \Maatwebsite\Excel\Excel::CSV);
        }

        return redirect()->route(
            'tesoreria_crown_propina_casino',
            TesoreriaCrownPropinaCasinoFiltros::paraQueryString(array_merge($filtros, ['consultar' => true]))
        );
    }
}

<?php

namespace App\Http\Controllers\Stock;

use App\Exports\Stock\KilosArticuloSurmarExport;
use App\Http\Controllers\Controller;
use App\Models\Stock\Articulo;
use App\Support\Reportes\DompdfListadoSupport;
use App\Support\Stock\KilosArticuloSurmarFiltros;
use App\Support\Stock\KilosArticuloSurmarSupport;
use App\Support\Stock\SurmarSupport;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class KilosArticuloSurmarController extends Controller
{
    public function index(Request $request)
    {
        $this->assertSurmar();
        can('listar-kilos-articulo-surmar');

        $filtros = KilosArticuloSurmarFiltros::resolverDesdeRequest($request);
        if (! $request->boolean('consultar')) {
            if ($filtros['fecha_desde'] === '' || $filtros['fecha_hasta'] === '') {
                $inicio = now()->subMonth()->startOfMonth();
                $filtros['fecha_desde'] = $inicio->toDateString();
                $filtros['fecha_hasta'] = $inicio->copy()->endOfMonth()->toDateString();
            }
        }

        $filtrosQuery = KilosArticuloSurmarFiltros::paraQueryString($filtros);
        $consultado = $request->boolean('consultar');
        $filas = null;
        $totales = null;
        $advertencias = [];
        $error = null;

        if ($consultado) {
            try {
                ini_set('memory_limit', '512M');
                $resultado = KilosArticuloSurmarSupport::consultar($filtros);
                $totales = $resultado['totales'];
                $advertencias = $resultado['advertencias'];
                $page = max(1, (int) $request->query('page', 1));
                $porPagina = 40;
                $slice = array_slice($resultado['filas'], ($page - 1) * $porPagina, $porPagina);
                $filas = new LengthAwarePaginator(
                    $slice,
                    count($resultado['filas']),
                    $porPagina,
                    $page,
                    ['path' => $request->url(), 'query' => $filtrosQuery]
                );
            } catch (\InvalidArgumentException $e) {
                $error = $e->getMessage();
                $consultado = false;
            }
        }

        return view('stock.kilos_articulo_surmar.index', [
            'filtros' => $filtros,
            'filtrosQuery' => $filtrosQuery,
            'consultado' => $consultado,
            'filas' => $filas,
            'totales' => $totales,
            'advertencias' => $advertencias,
            'error' => $error,
            'tipos' => KilosArticuloSurmarFiltros::tipos(),
            'subtitulo' => KilosArticuloSurmarFiltros::subtitulo($filtros),
            'descripcion_desde_sku' => $this->descripcionSku((string) ($filtros['desde_sku'] ?? '')),
            'descripcion_hasta_sku' => $this->descripcionSku((string) ($filtros['hasta_sku'] ?? '')),
            'puede_ver_articulo' => can('editar-articulos', false) || can('listar-articulos', false),
        ]);
    }

    public function exportar(Request $request, ?string $formato = null)
    {
        $this->assertSurmar();
        can('listar-kilos-articulo-surmar');

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $filtros = KilosArticuloSurmarFiltros::resolverDesdeRequest($request);
        try {
            $resultado = KilosArticuloSurmarSupport::consultar($filtros);
        } catch (\InvalidArgumentException $e) {
            return redirect()
                ->route('kilos_articulo_surmar', KilosArticuloSurmarFiltros::paraQueryString($filtros))
                ->with('mensaje', $e->getMessage());
        }

        $titulo = 'Kilos por artículo';
        $subtitulo = KilosArticuloSurmarFiltros::subtitulo($filtros);
        $formato = strtoupper((string) $formato);

        if ($formato === 'PDF') {
            $html = view('stock.kilos_articulo_surmar.listado', [
                'filas' => $resultado['filas'],
                'totales' => $resultado['totales'],
                'filtros' => $filtros,
                'titulo' => $titulo,
                'subtitulo' => $subtitulo,
                'advertencias' => $resultado['advertencias'],
                'puede_ver_articulo' => false,
            ])->render();
            $ruta = storage_path('pdf/listados/kilos_articulo_surmar.pdf');
            DompdfListadoSupport::guardarLegalLandscape($html, $ruta, [
                'titulo_corto' => 'Kilos por artículo',
            ]);

            return response()->download($ruta, 'kilos_articulo_surmar.pdf');
        }

        if ($formato === 'EXCEL' || $formato === 'CSV') {
            $export = new KilosArticuloSurmarExport(
                $resultado['filas'],
                $resultado['totales'],
                $filtros,
                $titulo,
                $subtitulo,
                $resultado['advertencias'],
            );
            if ($formato === 'CSV') {
                return $export->download('kilos_articulo_surmar.csv', \Maatwebsite\Excel\Excel::CSV);
            }

            return $export->download('kilos_articulo_surmar.xlsx');
        }

        return redirect()->route(
            'kilos_articulo_surmar',
            KilosArticuloSurmarFiltros::paraQueryString($filtros)
        );
    }

    private function assertSurmar(): void
    {
        if (! SurmarSupport::esEntornoBierzo() || ! SurmarSupport::esEmpresaSurmar(SurmarSupport::EMPRESA_ID)) {
            abort(404);
        }
    }

    private function descripcionSku(string $sku): string
    {
        $sku = strtoupper(trim($sku));
        if ($sku === '') {
            return '';
        }

        return trim((string) Articulo::query()->where('sku', $sku)->value('descripcion'));
    }
}

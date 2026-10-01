<?php

namespace App\Http\Controllers\Stock;

use App\Exports\Stock\PrecioListaFerliExport;
use App\Http\Controllers\Controller;
use App\Support\Configuracion\EmpresaLogoArchivo;
use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\Reportes\DompdfListadoSupport;
use App\Support\Stock\PrecioListaFerliConsulta;
use App\Support\Stock\PrecioListaFerliFiltros;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Excel as ExcelFormat;

class PrecioListaFerliController extends Controller
{
    public function index(Request $request)
    {
        $this->assertFerli();
        can('listar-precios');

        $filtros = PrecioListaFerliFiltros::resolverDesdeRequest($request);
        $listas = PrecioListaFerliConsulta::resolverListas($filtros['listaprecio_ids']);
        $filtros['listaprecio_ids'] = array_column($listas, 'id');

        $consultado = $request->boolean('consultar');
        $filas = null;
        $error = null;

        if ($consultado) {
            try {
                ini_set('memory_limit', '512M');
                $filas = PrecioListaFerliConsulta::paginar($filtros, $listas);
            } catch (\InvalidArgumentException $e) {
                $error = $e->getMessage();
                $consultado = false;
            }
        }

        return view('stock.precio.lista_ferli.index', [
            'filtros' => $filtros,
            'filtrosQuery' => PrecioListaFerliFiltros::paraQueryString($filtros),
            'marcas' => PrecioListaFerliFiltros::marcas(),
            'listas' => $listas,
            'consultado' => $consultado,
            'filas' => $filas,
            'error' => $error,
            'subtitulo' => PrecioListaFerliFiltros::subtitulo($filtros, $listas),
            'logosCabecera' => EmpresaLogoArchivo::logosCabeceraDesdeColeccion(collect()),
            'puede_ver_articulo' => can('editar-articulos', false) || can('listar-articulos', false),
        ]);
    }

    public function exportar(Request $request, ?string $formato = null)
    {
        $this->assertFerli();
        can('listar-precios');

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $filtros = PrecioListaFerliFiltros::resolverDesdeRequest($request);
        $listas = PrecioListaFerliConsulta::resolverListas($filtros['listaprecio_ids']);
        $filtros['listaprecio_ids'] = array_column($listas, 'id');
        $volver = PrecioListaFerliFiltros::paraQueryString($filtros);

        try {
            $filas = PrecioListaFerliConsulta::filas($filtros, $listas);
        } catch (\InvalidArgumentException $e) {
            return redirect()->route('precio_lista_ferli', $volver);
        }

        $titulo = 'Lista de precios';
        $subtitulo = PrecioListaFerliFiltros::subtitulo($filtros, $listas);
        $formato = strtoupper((string) $formato);

        if ($formato === 'PDF') {
            $html = view('stock.precio.lista_ferli.listado', [
                'filas' => $filas,
                'listas' => $listas,
                'titulo' => $titulo,
                'subtitulo' => $subtitulo,
                'logosCabecera' => EmpresaLogoArchivo::logosCabeceraDesdeColeccion(collect()),
            ])->render();
            $ruta = storage_path('pdf/listados/lista_precios_ferli.pdf');
            DompdfListadoSupport::guardarLegalLandscape($html, $ruta, [
                'titulo_corto' => 'Lista de precios',
            ]);

            return response()->download($ruta, 'lista_precios_ferli.pdf');
        }

        if ($formato === 'EXCEL' || $formato === 'CSV') {
            $export = new PrecioListaFerliExport(
                $filas,
                $listas,
                $titulo,
                $subtitulo,
                EmpresaLogoArchivo::rutasLogosCabeceraDesdeColeccion(collect()),
            );
            if ($formato === 'CSV') {
                return $export->download('lista_precios_ferli.csv', ExcelFormat::CSV);
            }

            return $export->download('lista_precios_ferli.xlsx');
        }

        return redirect()->route('precio_lista_ferli', $volver);
    }

    private function assertFerli(): void
    {
        if (! EntornoEmpresaSupport::esFerli()) {
            abort(404);
        }
    }
}

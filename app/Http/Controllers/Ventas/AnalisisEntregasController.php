<?php

namespace App\Http\Controllers\Ventas;

use App\Exports\Ventas\AnalisisEntregasExport;
use App\Http\Controllers\Controller;
use App\Support\Configuracion\EmpresaLogoArchivo;
use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\Reportes\DompdfListadoSupport;
use App\Support\Ventas\AnalisisEntregasFiltros;
use App\Support\Ventas\AnalisisEntregasReporte;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AnalisisEntregasController extends Controller
{
    public function index(Request $request)
    {
        $this->autorizar();

        $filtros = AnalisisEntregasFiltros::resolverDesdeRequest($request);
        $filtrosQuery = AnalisisEntregasFiltros::paraQueryString($filtros);
        $consultado = $request->boolean('consultar');
        $filas = null;
        $filasVista = [];
        $resumen = ['lineas' => 0, 'monedas' => []];

        if ($consultado) {
            ini_set('memory_limit', '-1');
            ini_set('max_execution_time', '0');
            $todas = AnalisisEntregasReporte::filas($filtros);
            $resumen = AnalisisEntregasReporte::resumen($todas);
            $filas = AnalisisEntregasReporte::paginar(
                $todas,
                (int) $request->input('per_page', 40),
                (int) $request->input('page', 1),
                $filtrosQuery
            );
            $filasVista = $filas->items();
        }

        return view('ventas.analisis_entregas.index', [
            'filtros' => $filtros,
            'filtrosQuery' => $filtrosQuery,
            'consultado' => $consultado,
            'filas' => $filas,
            'filasVista' => $filasVista,
            'resumen' => $resumen,
            'subtitulo' => AnalisisEntregasFiltros::subtitulo($filtros),
            'ordenes' => AnalisisEntregasFiltros::ordenes(),
            'estados' => AnalisisEntregasFiltros::estados(),
            'situaciones' => AnalisisEntregasFiltros::situaciones(),
            'modos' => AnalisisEntregasFiltros::modos(),
            'cliente' => $this->etiqueta('cliente', (int) $filtros['cliente_id'], 'codigo', 'nombre'),
            'articulo' => $this->etiqueta('articulo', (int) $filtros['articulo_id'], 'sku', 'descripcion'),
            'vendedor' => $this->etiqueta('vendedor', (int) $filtros['vendedor_id'], 'codigo', 'nombre'),
            'puede_ver_pedido' => can('editar-pedidos', false) || can('listar-pedidos', false),
            'puede_ver_cliente' => can('editar-clientes', false) || can('listar-clientes', false),
            'puede_ver_articulo' => can('editar-articulos', false) || can('listar-articulos', false),
            'logosCabecera' => $this->logos(),
        ]);
    }

    public function exportar(Request $request, string $formato)
    {
        $this->autorizar();

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $filtros = AnalisisEntregasFiltros::resolverDesdeRequest($request);
        if (! $request->boolean('consultar')) {
            return redirect()->route('analisis_entregas');
        }

        $filas = AnalisisEntregasReporte::filas($filtros);
        $resumen = AnalisisEntregasReporte::resumen($filas);
        $subtitulo = AnalisisEntregasFiltros::subtitulo($filtros);
        $logosCabecera = $this->logos();
        $formato = strtoupper($formato);

        if ($formato === 'PDF') {
            $html = view('ventas.analisis_entregas.listado', [
                'filas' => $filas,
                'filtros' => $filtros,
                'subtitulo' => $subtitulo,
                'resumen' => $resumen,
                'logosCabecera' => $logosCabecera,
                'puede_ver_pedido' => false,
                'puede_ver_cliente' => false,
                'puede_ver_articulo' => false,
                'para_excel' => false,
            ])->render();
            $ruta = storage_path('pdf/listados/analisis_entregas.pdf');
            DompdfListadoSupport::guardarLegalLandscape($html, $ruta, [
                'titulo_corto' => 'Análisis de entregas',
            ]);

            return response()->download($ruta, 'analisis_entregas.pdf');
        }

        if ($formato === 'EXCEL' || $formato === 'CSV') {
            $export = (new AnalisisEntregasExport)
                ->parametros($filas, $subtitulo, $resumen, $logosCabecera);
            $nombre = 'analisis_entregas.'.($formato === 'CSV' ? 'csv' : 'xlsx');

            return $export->download($nombre, $formato === 'CSV' ? \Maatwebsite\Excel\Excel::CSV : \Maatwebsite\Excel\Excel::XLSX);
        }

        return redirect()->route('analisis_entregas', AnalisisEntregasFiltros::paraQueryString($filtros));
    }

    private function autorizar(): void
    {
        if (! EntornoEmpresaSupport::esInterforming()) {
            abort(404);
        }
        if (! can('listar-analisis-entregas', false) && ! can('listar-pedidos', false)) {
            can('listar-analisis-entregas');
        }
    }

    /**
     * @return array{id: int, codigo: string, nombre: string}
     */
    private function etiqueta(string $tabla, int $id, string $codigo, string $nombre): array
    {
        if ($id <= 0) {
            return ['id' => 0, 'codigo' => '', 'nombre' => ''];
        }
        $row = DB::table($tabla)->where('id', $id)->first([$codigo, $nombre]);

        return [
            'id' => $id,
            'codigo' => trim((string) ($row->{$codigo} ?? '')),
            'nombre' => trim((string) ($row->{$nombre} ?? '')),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function logos(): array
    {
        $nombre = (string) (DB::table('empresa')->orderBy('id')->value('nombre') ?? '');
        if ($nombre === '') {
            return [];
        }

        return EmpresaLogoArchivo::logosCabeceraDesdeColeccion(collect([
            (object) ['nombreempresa' => $nombre],
        ]));
    }
}

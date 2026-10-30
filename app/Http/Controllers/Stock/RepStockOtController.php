<?php

namespace App\Http\Controllers\Stock;

use App\Exports\Stock\StockOtExport;
use App\Http\Controllers\Controller;
use App\Models\Stock\Articulo;
use App\Models\Stock\Categoria;
use App\Models\Stock\Depmae;
use App\Models\Stock\Linea;
use App\Models\Stock\Mventa;
use App\Queries\Stock\ArticuloQueryInterface;
use App\Services\Stock\Articulo_MovimientoService;
use App\Support\Listado\ReporteResultadoTextoSupport;
use App\Support\Reportes\DompdfListadoSupport;
use App\Support\Stock\ReporteStockOtDepositoAmbitoSupport;
use App\Support\Stock\ReporteStockOtListadoFiltros;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Maatwebsite\Excel\Excel;

class RepStockOtController extends Controller
{
    protected $articulo_movimientoService;

    protected $articuloQuery;

    public function __construct(
        Articulo_MovimientoService $articulo_movimientoservice,
        ArticuloQueryInterface $articuloquery
    ) {
        $this->middleware('auth');

        $this->articulo_movimientoService = $articulo_movimientoservice;
        $this->articuloQuery = $articuloquery;
    }

    public function index(Request $request)
    {
        $filtros = ReporteStockOtListadoFiltros::resolverDesdeRequest($request);
        $consultado = $request->boolean('consultar');
        $filas = null;
        $medidasColumnas = [];
        $totalPares = 0.0;
        $totalFilas = 0;

        if ($consultado) {
            ini_set('memory_limit', '1024M');
            set_time_limit(300);

            $todas = $this->filasConsulta($filtros);
            $visibles = ReporteResultadoTextoSupport::filtrarFilas(
                $todas,
                $filtros,
                ReporteStockOtListadoFiltros::camposFiltroFilas()
            );
            $totalFilas = count($visibles);
            foreach ($visibles as $fila) {
                $totalPares += (float) ($fila['total_pares'] ?? 0);
            }
            $medidasColumnas = $this->medidasColumnas($visibles);

            $page = max(1, (int) $request->input('page', 1));
            $perPage = 30;
            $filas = new LengthAwarePaginator(
                array_slice($visibles, ($page - 1) * $perPage, $perPage),
                $totalFilas,
                $perPage,
                $page,
                ['path' => $request->url(), 'query' => $request->query()]
            );
        }

        $filtrosQuery = ReporteStockOtListadoFiltros::paraQueryString($filtros);
        if ($consultado) {
            $filtrosQuery['consultar'] = 1;
        }
        $limpiarTexto = ReporteStockOtListadoFiltros::paraQueryString(
            ReporteStockOtListadoFiltros::sinTexto($filtros)
        );
        $limpiarTexto['consultar'] = 1;

        return view('stock.repstockot.create', [
            'filtros' => $filtros,
            'filtrosQuery' => $filtrosQuery,
            'limpiar_filtros_texto_url' => route('rep_stockot', $limpiarTexto),
            'tiene_filtros_texto' => ReporteResultadoTextoSupport::tieneAplicados($filtros),
            'consultado' => $consultado,
            'filas' => $filas,
            'medidas_columnas' => $medidasColumnas,
            'total_pares' => $totalPares,
            'total_filas' => $totalFilas,
            'subtitulo' => $this->subtitulo($filtros),
            'etiquetas' => $this->etiquetas($filtros),
            'estado_enum' => [
                'ACTIVAS' => 'Combinaciones activas',
                'INACTIVAS' => 'Combinaciones inactivas',
                'TODAS' => 'Todas las combinaciones',
            ],
            'foto_enum' => [
                'SIN_FOTO' => 'Sin fotos',
                'CON_FOTO' => 'Con fotos',
            ],
            'apertura_enum' => [
                'TOTALIZADO' => 'Totalizado',
                'MOVIMIENTOS' => 'Movimientos',
            ],
            'estadoOt_enum' => [
                'TODAS' => 'Todo',
                'ENTREGA' => 'Para entregar (sin lo rojo)',
                'PRODUCCION' => 'Solo lo rojo (en producción)',
            ],
            'ambito_deposito_enum' => ReporteStockOtDepositoAmbitoSupport::opciones(),
            'depositos_locales_etiqueta' => ReporteStockOtDepositoAmbitoSupport::etiquetaDepositosLocales(),
            'marcas' => Mventa::query()->orderBy('nombre')->get(['id', 'codigo', 'nombre']),
        ]);
    }

    public function exportar(Request $request, $formato = null)
    {
        ini_set('memory_limit', '1024M');
        set_time_limit(300);

        $filtros = ReporteStockOtListadoFiltros::resolverDesdeRequest($request);
        $formato = strtoupper((string) $formato);
        if (! in_array($formato, ['PDF', 'EXCEL', 'CSV'], true)) {
            return redirect()->route('rep_stockot', ReporteStockOtListadoFiltros::paraQueryString($filtros));
        }

        $visibles = ReporteResultadoTextoSupport::filtrarFilas(
            $this->filasConsulta($filtros),
            $filtros,
            ReporteStockOtListadoFiltros::camposFiltroFilas()
        );
        $titulo = $this->titulo($filtros);
        $subtitulo = $this->subtitulo($filtros);
        $medidas = $this->medidasColumnas($visibles);

        if ($formato === 'PDF') {
            $html = view('stock.repstockot.listado', [
                'filas' => $visibles,
                'titulo' => $titulo,
                'subtitulo' => $subtitulo,
                'medidas_columnas' => $medidas,
                'es_movimientos' => ($filtros['apertura'] ?? '') === 'MOVIMIENTOS',
                'total_filas' => count($visibles),
            ])->render();
            $ruta = storage_path('pdf/listados/stockot.pdf');
            DompdfListadoSupport::guardarLegalLandscape($html, $ruta, [
                'titulo_corto' => 'Stock por OT',
            ]);

            return response()->download($ruta, 'stockot.pdf');
        }

        $export = (new StockOtExport($this->articuloQuery, $this->articulo_movimientoService))
            ->parametros(
                $filtros['estado'],
                (int) $filtros['mventa_id'],
                $this->idRango($filtros['desdearticulo_id'] ?: '', 0),
                $this->idRango($filtros['hastaarticulo_id'] ?: '', 99999999),
                $this->idRango($filtros['desdelinea_id'] ?: '', 0),
                $this->idRango($filtros['hastalinea_id'] ?: '', 99999999),
                $this->idRango($filtros['desdecategoria_id'] ?: '', 0),
                $this->idRango($filtros['hastacategoria_id'] ?: '', 99999999),
                $filtros['desdelote'],
                $filtros['hastalote'],
                $filtros['imprimefoto'],
                $filtros['estadoOt'],
                $filtros['apertura'],
                (int) $filtros['deposito_id'],
                $filtros['ambito_deposito']
            )
            ->conFilas($visibles);

        if ($formato === 'CSV') {
            return $export->download('stockot.csv', Excel::CSV);
        }

        return $export->download('stockot.xlsx');
    }

    public function crearReporteStockOt(Request $request)
    {
        ini_set('memory_limit', '1024M');
        set_time_limit(300);

        switch ($request->extension) {
            case 'Genera Reporte en Excel':
                $extension = 'xlsx';
                break;
            case 'Genera Reporte en PDF':
                $extension = 'pdf';
                break;
            case 'Genera Reporte en CSV':
                $extension = 'csv';
                break;
            default:
                $extension = 'xlsx';
        }

        return (new StockOtExport($this->articuloQuery, $this->articulo_movimientoService))
            ->parametros(
                $request->estado,
                $this->idOCero($request->mventa_id),
                $this->idRango($request->desdearticulo_id, 0),
                $this->idRango($request->hastaarticulo_id, 99999999),
                $this->idRango($request->desdelinea_id, 0),
                $this->idRango($request->hastalinea_id, 99999999),
                $this->idRango($request->desdecategoria_id, 0),
                $this->idRango($request->hastacategoria_id, 99999999),
                $request->desdelote,
                $request->hastalote,
                $request->imprimefoto,
                $request->estadoOt,
                $request->apertura,
                $this->idOCero($request->deposito_id),
                ReporteStockOtDepositoAmbitoSupport::normalizar($request->ambito_deposito)
            )
            ->download('stockot.'.$extension);
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return list<array<string, mixed>>
     */
    private function filasConsulta(array $filtros): array
    {
        $ret = generaRangoArticulo(
            $this->idRango($filtros['desdearticulo_id'] ?: '', 0),
            $this->idRango($filtros['hastaarticulo_id'] ?: '', 99999999),
            $this->articuloQuery
        );
        $filas = $this->articulo_movimientoService->generaDatosRepStockOt(
            $filtros['estado'],
            (int) $filtros['mventa_id'],
            $ret['desdearticulorango'],
            $ret['hastaarticulorango'],
            $this->idRango($filtros['desdelinea_id'] ?: '', 0),
            $this->idRango($filtros['hastalinea_id'] ?: '', 99999999),
            $this->idRango($filtros['desdecategoria_id'] ?: '', 0),
            $this->idRango($filtros['hastacategoria_id'] ?: '', 99999999),
            $filtros['desdelote'],
            $filtros['hastalote'],
            $filtros['estadoOt'],
            $filtros['apertura'],
            (int) $filtros['deposito_id'],
            $filtros['ambito_deposito']
        );
        return is_array($filas) ? $filas : [];
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     * @return list<int>
     */
    private function medidasColumnas(array $filas): array
    {
        $min = null;
        $max = null;
        foreach ($filas as $fila) {
            foreach ([$fila['medidas'] ?? [], $fila['modulo'] ?? []] as $lista) {
                foreach ($lista as $m) {
                    if (abs((float) ($m['cantidad'] ?? 0)) < 0.0001) {
                        continue;
                    }
                    $medida = (int) ($m['medida'] ?? 0);
                    if ($medida <= 0) {
                        continue;
                    }
                    $min = $min === null ? $medida : min($min, $medida);
                    $max = $max === null ? $medida : max($max, $medida);
                }
            }
        }
        if ($min === null || $max === null) {
            $min = (int) config('consprod.DESDE_MEDIDA', 35);
            $max = (int) config('consprod.HASTA_MEDIDA', 40);
        }

        $out = [];
        for ($i = $min; $i <= $max; $i++) {
            $out[] = $i;
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array<string, array{codigo: string, descripcion: string}>
     */
    private function etiquetas(array $filtros): array
    {
        return [
            'desde_articulo' => $this->etiquetaArticulo((int) $filtros['desdearticulo_id']),
            'hasta_articulo' => $this->etiquetaArticulo((int) $filtros['hastaarticulo_id']),
            'desde_linea' => $this->etiquetaLinea((int) $filtros['desdelinea_id']),
            'hasta_linea' => $this->etiquetaLinea((int) $filtros['hastalinea_id']),
            'desde_categoria' => $this->etiquetaCategoria((int) $filtros['desdecategoria_id']),
            'hasta_categoria' => $this->etiquetaCategoria((int) $filtros['hastacategoria_id']),
            'deposito' => $this->etiquetaDeposito((int) $filtros['deposito_id']),
        ];
    }

    /**
     * @return array{codigo: string, descripcion: string}
     */
    private function etiquetaArticulo(int $id): array
    {
        if ($id <= 0) {
            return ['codigo' => '', 'descripcion' => ''];
        }
        $row = Articulo::query()->find($id, ['sku', 'descripcion']);

        return [
            'codigo' => (string) ($row->sku ?? ''),
            'descripcion' => (string) ($row->descripcion ?? ''),
        ];
    }

    /**
     * @return array{codigo: string, descripcion: string}
     */
    private function etiquetaLinea(int $id): array
    {
        if ($id <= 0) {
            return ['codigo' => '', 'descripcion' => ''];
        }
        $row = Linea::query()->find($id, ['codigo', 'nombre']);

        return [
            'codigo' => (string) ($row->codigo ?? ''),
            'descripcion' => (string) ($row->nombre ?? ''),
        ];
    }

    /**
     * @return array{codigo: string, descripcion: string}
     */
    private function etiquetaCategoria(int $id): array
    {
        if ($id <= 0) {
            return ['codigo' => '', 'descripcion' => ''];
        }
        $row = Categoria::query()->find($id, ['codigo', 'nombre']);

        return [
            'codigo' => (string) ($row->codigo ?? ''),
            'descripcion' => (string) ($row->nombre ?? ''),
        ];
    }

    /**
     * @return array{codigo: string, descripcion: string}
     */
    private function etiquetaDeposito(int $id): array
    {
        if ($id <= 0) {
            return ['codigo' => '', 'descripcion' => ''];
        }
        $row = Depmae::query()->find($id, ['codigo', 'nombre']);

        return [
            'codigo' => (string) ($row->codigo ?? ''),
            'descripcion' => (string) ($row->nombre ?? ''),
        ];
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    private function titulo(array $filtros): string
    {
        $nombre = 'Todas las marcas';
        $marcaId = (int) ($filtros['mventa_id'] ?? 0);
        if ($marcaId > 0) {
            $nombre = (string) (Mventa::query()->whereKey($marcaId)->value('nombre') ?: '--');
        }
        $titulo = 'Stock por OT — '.$nombre;
        if ((int) ($filtros['deposito_id'] ?? 0) <= 0
            && ReporteStockOtDepositoAmbitoSupport::normalizar((string) ($filtros['ambito_deposito'] ?? ''))
                !== ReporteStockOtDepositoAmbitoSupport::TODOS) {
            $titulo .= ' — '.ReporteStockOtDepositoAmbitoSupport::etiqueta((string) $filtros['ambito_deposito']);
        }

        return $titulo;
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    private function subtitulo(array $filtros): string
    {
        $partes = [$this->titulo($filtros)];
        $partes[] = 'Stock: '.($filtros['estadoOt'] ?? 'TODAS');
        $partes[] = 'Apertura: '.($filtros['apertura'] ?? 'TOTALIZADO');
        if (trim((string) ($filtros['desdelote'] ?? '')) !== '' || trim((string) ($filtros['hastalote'] ?? '')) !== '') {
            $partes[] = 'Lote '.$filtros['desdelote'].' a '.$filtros['hastalote'];
        }
        if (ReporteResultadoTextoSupport::tieneAplicados($filtros)) {
            $partes[] = 'Filtro de resultado: '.trim((string) ($filtros['filtro_valor'] ?? ''));
        }

        return implode(' · ', $partes);
    }

    private function idRango($valor, int $siVacio): int
    {
        if ($valor === null || $valor === '') {
            return $siVacio;
        }

        return (int) $valor;
    }

    private function idOCero($valor): int
    {
        return (int) ($valor ?: 0);
    }
}

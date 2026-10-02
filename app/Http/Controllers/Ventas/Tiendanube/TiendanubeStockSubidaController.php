<?php

namespace App\Http\Controllers\Ventas\Tiendanube;

use App\Exports\Ventas\TiendanubeStockSubidaLineaExport;
use App\Http\Controllers\Controller;
use App\Models\Ventas\TiendanubeStockSubida;
use App\Models\Ventas\TiendanubeStockSubidaLinea;
use App\Services\Ventas\Tiendanube\TiendanubeStockSubidaService;
use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\Reportes\DompdfListadoSupport;
use App\Support\Ventas\Tiendanube\TiendanubeStockCatalogoSupport;
use App\Support\Ventas\Tiendanube\TiendanubeTiendasSupport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class TiendanubeStockSubidaController extends Controller
{
    public function index(Request $request)
    {
        $this->autorizar();

        $filtros = [
            'estado' => trim((string) $request->query('estado', '')),
            'store_id' => trim((string) $request->query('store_id', '')),
            'desde' => trim((string) $request->query('desde', '')),
            'hasta' => trim((string) $request->query('hasta', '')),
        ];

        $query = TiendanubeStockSubida::query()->with('usuario:id,nombre')->orderByDesc('inicio_at')->orderByDesc('id');
        if (in_array($filtros['estado'], [
            TiendanubeStockSubida::ESTADO_PROCESO,
            TiendanubeStockSubida::ESTADO_OK,
            TiendanubeStockSubida::ESTADO_PARCIAL,
            TiendanubeStockSubida::ESTADO_ERROR,
        ], true)) {
            $query->where('estado', $filtros['estado']);
        }
        if ($filtros['store_id'] !== '') {
            $query->where('store_id', $filtros['store_id']);
        }
        if ($filtros['desde'] !== '') {
            $query->where('inicio_at', '>=', $filtros['desde'].' 00:00:00');
        }
        if ($filtros['hasta'] !== '') {
            $query->where('inicio_at', '<=', $filtros['hasta'].' 23:59:59');
        }

        $subidas = $query->paginate(15)->appends($filtros);
        $nombres = [];
        foreach (TiendanubeTiendasSupport::paraVista() as $tienda) {
            $nombres[$tienda['store_id']] = $tienda['nombre'];
        }

        return view('ventas.tiendanube_stock.index', [
            'subidas' => $subidas,
            'filtros' => $filtros,
            'tiendas' => TiendanubeTiendasSupport::paraVista(),
            'nombresTienda' => $nombres,
            'enCurso' => TiendanubeStockCatalogoSupport::haySubidaEnCurso(),
        ]);
    }

    public function ver(Request $request, int $id)
    {
        $this->autorizar();

        $subida = TiendanubeStockSubida::query()->with('usuario:id,nombre')->findOrFail($id);
        $estado = $this->estadoLineaFiltro($request);
        $conteos = TiendanubeStockSubidaLinea::query()
            ->where('subida_id', $subida->id)
            ->selectRaw('estado, COUNT(*) as c')
            ->groupBy('estado')
            ->pluck('c', 'estado');
        $lineas = TiendanubeStockSubidaLinea::query()
            ->where('subida_id', $subida->id)
            ->when($estado !== '', fn ($q) => $q->where('estado', $estado))
            ->orderBy('id')
            ->paginate(50)
            ->appends($estado !== '' ? ['estado' => $estado] : []);

        return view('ventas.tiendanube_stock.ver', [
            'subida' => $subida,
            'lineas' => $lineas,
            'estado' => $estado,
            'fichasEstado' => $this->fichasEstado($subida, $conteos),
            'tiendaNombre' => TiendanubeTiendasSupport::nombre($subida->store_id),
        ]);
    }

    public function exportar(Request $request, int $id, string $formato)
    {
        $this->autorizar();

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $subida = TiendanubeStockSubida::query()->findOrFail($id);
        $estado = $this->estadoLineaFiltro($request);
        $formato = strtoupper($formato);
        $nombre = 'previsualizacion_tiendanube_'.$subida->id;

        if ($formato === 'PDF') {
            $pack = TiendanubeStockSubidaLineaExport::armar($subida, $estado);
            $html = view('ventas.tiendanube_stock.listado', [
                'lineas' => $pack['lineas'],
                'titulo' => $pack['titulo'],
                'subtitulo' => $pack['subtitulo'],
                'logosCabecera' => $pack['logosCabecera'],
            ])->render();
            $rutaPdf = storage_path('pdf/listados/'.$nombre.'.pdf');
            DompdfListadoSupport::guardarLegalLandscape($html, $rutaPdf, [
                'titulo_corto' => 'Stock y precios Tiendanube',
                'dompdf' => [
                    'isFontSubsettingEnabled' => false,
                    'isJavascriptEnabled' => false,
                ],
            ]);

            return response()->download($rutaPdf, $nombre.'.pdf');
        }

        if ($formato === 'EXCEL') {
            return (new TiendanubeStockSubidaLineaExport)
                ->deSubida($subida, $estado)
                ->download($nombre.'.xlsx');
        }

        if ($formato === 'CSV') {
            return (new TiendanubeStockSubidaLineaExport)
                ->deSubida($subida, $estado)
                ->download($nombre.'.csv', \Maatwebsite\Excel\Excel::CSV);
        }

        return redirect()->route('ver_tiendanube_stock_subida', $subida->id);
    }

    public function eliminar(int $id)
    {
        $this->autorizar();

        $subida = TiendanubeStockSubida::query()->findOrFail($id);
        if ($subida->estado === TiendanubeStockSubida::ESTADO_PROCESO) {
            return redirect()
                ->route('tiendanube_stock_subidas')
                ->with('mensaje', 'No se puede borrar una corrida que está en curso.');
        }

        $subida->delete();

        return redirect()
            ->route('tiendanube_stock_subidas')
            ->with('mensaje', 'Corrida borrada del historial.');
    }

    public function subir(Request $request)
    {
        return $this->lanzar(TiendanubeStockSubida::ORIGEN_MANUAL, 'La subida arrancó. La grilla muestra el avance al actualizar.');
    }

    public function previsualizar(TiendanubeStockSubidaService $servicio)
    {
        $this->autorizar();

        if (TiendanubeStockCatalogoSupport::haySubidaEnCurso()) {
            return redirect()
                ->route('tiendanube_stock_subidas')
                ->with('mensaje', 'Ya hay una subida o una previsualización en curso.');
        }

        if (TiendanubeStockCatalogoSupport::tiendasParaSubir() === []) {
            return redirect()
                ->route('editar_configuracion_tiendanube')
                ->with('mensaje', 'Ninguna tienda tiene activa la subida de stock. Activala en Configuración Tiendanube.');
        }

        set_time_limit(120);
        $resultado = $servicio->ejecutar(
            TiendanubeStockSubida::ORIGEN_SIMULACION,
            (int) Auth::id()
        );
        $id = (int) ($resultado['subidas'][0] ?? 0);
        if ($id <= 0) {
            return redirect()
                ->route('tiendanube_stock_subidas')
                ->with('mensaje', $resultado['mensaje'] ?? 'No se pudo armar la previsualización.');
        }

        return redirect()->route('ver_tiendanube_stock_subida', $id);
    }

    private function lanzar(string $origen, string $mensaje)
    {
        $this->autorizar();

        if (TiendanubeStockCatalogoSupport::haySubidaEnCurso()) {
            return redirect()
                ->route('tiendanube_stock_subidas')
                ->with('mensaje', 'Ya hay una subida o una previsualización en curso.');
        }

        if (TiendanubeStockCatalogoSupport::tiendasParaSubir() === []) {
            return redirect()
                ->route('editar_configuracion_tiendanube')
                ->with('mensaje', 'Ninguna tienda tiene activa la subida de stock. Activala en Configuración Tiendanube.');
        }

        $php = PHP_BINARY;
        if ($php === '' || str_contains($php, 'fpm') || str_contains($php, 'cgi')) {
            $php = 'php';
        }
        $log = storage_path('logs/tiendanube-stock-subida.log');
        $cmd = sprintf(
            'nohup %s %s tiendanube:subir-stock --origen=%s --usuario=%d >> %s 2>&1 &',
            escapeshellarg($php),
            escapeshellarg(base_path('artisan')),
            escapeshellarg($origen),
            (int) Auth::id(),
            escapeshellarg($log)
        );
        exec($cmd);

        return redirect()
            ->route('tiendanube_stock_subidas')
            ->with('mensaje', $mensaje);
    }

    private function estadoLineaFiltro(Request $request): string
    {
        $estado = trim((string) $request->query('estado', ''));

        return in_array($estado, [
            TiendanubeStockSubidaLinea::ESTADO_OK,
            TiendanubeStockSubidaLinea::ESTADO_ERROR,
            TiendanubeStockSubidaLinea::ESTADO_OMITIDA,
            TiendanubeStockSubidaLinea::ESTADO_PREVISTA,
        ], true) ? $estado : '';
    }

    /**
     * @param  \Illuminate\Support\Collection<string, int|string>  $conteos
     * @return list<array{estado:string, label:string, clase:string, cantidad:int}>
     */
    private function fichasEstado(TiendanubeStockSubida $subida, $conteos): array
    {
        $cantidad = static fn (string $estado): int => (int) ($conteos[$estado] ?? 0);
        $simulacion = $subida->origen === TiendanubeStockSubida::ORIGEN_SIMULACION;
        $fichas = [[
            'estado' => '',
            'label' => 'Todas',
            'clase' => 'primary',
            'cantidad' => $cantidad(TiendanubeStockSubidaLinea::ESTADO_OK)
                + $cantidad(TiendanubeStockSubidaLinea::ESTADO_ERROR)
                + $cantidad(TiendanubeStockSubidaLinea::ESTADO_OMITIDA)
                + $cantidad(TiendanubeStockSubidaLinea::ESTADO_PREVISTA),
        ]];
        if ($simulacion || $cantidad(TiendanubeStockSubidaLinea::ESTADO_PREVISTA) > 0) {
            $fichas[] = [
                'estado' => TiendanubeStockSubidaLinea::ESTADO_PREVISTA,
                'label' => 'A enviar',
                'clase' => 'info',
                'cantidad' => $cantidad(TiendanubeStockSubidaLinea::ESTADO_PREVISTA),
            ];
        }
        if (! $simulacion || $cantidad(TiendanubeStockSubidaLinea::ESTADO_OK) > 0) {
            $fichas[] = [
                'estado' => TiendanubeStockSubidaLinea::ESTADO_OK,
                'label' => 'OK',
                'clase' => 'success',
                'cantidad' => $cantidad(TiendanubeStockSubidaLinea::ESTADO_OK),
            ];
        }
        $fichas[] = [
            'estado' => TiendanubeStockSubidaLinea::ESTADO_ERROR,
            'label' => 'Error',
            'clase' => 'danger',
            'cantidad' => $cantidad(TiendanubeStockSubidaLinea::ESTADO_ERROR),
        ];
        $fichas[] = [
            'estado' => TiendanubeStockSubidaLinea::ESTADO_OMITIDA,
            'label' => 'Omitidas',
            'clase' => 'secondary',
            'cantidad' => $cantidad(TiendanubeStockSubidaLinea::ESTADO_OMITIDA),
        ];

        return $fichas;
    }

    private function autorizar(): void
    {
        if (! EntornoEmpresaSupport::esFerli()) {
            abort(Response::HTTP_NOT_FOUND);
        }
        can('importar-tiendanube');
    }
}

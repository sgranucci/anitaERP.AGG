<?php

declare(strict_types=1);

namespace App\Http\Controllers\Ventas;

use App\Exports\Ventas\FacturacionJuegoConciliacionExport;
use App\Http\Controllers\Controller;
use App\Models\Caja\Cuentacaja;
use App\Models\Configuracion\Sala;
use App\Models\Contable\Centrocosto;
use App\Models\Contable\Cuentacontable;
use App\Models\Ventas\FacturacionJuegoSala;
use App\Services\Ventas\FacturacionJuegoService;
use App\Support\Reportes\DompdfListadoSupport;
use App\Support\Ventas\FacturacionJuegoConciliacionSupport;
use App\Support\Ventas\FacturacionJuegoConfigSupport;
use App\Support\Ventas\FacturacionJuegoSemillaAnitaSupport;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FacturacionJuegoController extends Controller
{
    public function __construct(
        private readonly FacturacionJuegoService $service,
        private readonly FacturacionJuegoSemillaAnitaSupport $semillaAnita,
        private readonly FacturacionJuegoConciliacionSupport $conciliacionSupport,
    ) {
    }

    public function index(Request $request): View
    {
        can('usar-facturacion-juego');

        $fechaDesde = $this->fecha($request->input('fecha_desde'), Carbon::now()->startOfMonth()->toDateString());
        $fechaHasta = $this->fecha($request->input('fecha_hasta'), Carbon::now()->toDateString());
        $salaId = (int) $request->input('sala_id', 0);
        $consultar = (string) $request->input('consultar', '') === '1';

        $preview = [];
        if ($consultar) {
            $preview = $this->service->preview($fechaDesde, $fechaHasta, $salaId > 0 ? $salaId : null);
        }

        $helpSalas = $this->conciliacionSupport->armarHelpSalas(
            FacturacionJuegoConfigSupport::salasActivas()
        );

        return view('ventas.facturacion_juego.index', [
            'fechaDesde' => $fechaDesde,
            'fechaHasta' => $fechaHasta,
            'salaId' => $salaId,
            'consultar' => $consultar,
            'preview' => $preview,
            'salas' => Sala::query()->orderBy('codigo')->get(['id', 'codigo', 'nombre']),
            'listos' => collect($preview)->where('listo', true)->count(),
            'pendientesConfig' => collect($preview)->where('listo', false)->count(),
            'totalMonto' => collect($preview)->sum('monto'),
            'helpSalas' => $helpSalas,
        ]);
    }

    public function conciliacion(Request $request): View
    {
        can('usar-facturacion-juego');

        $fechaDesde = $this->fecha($request->input('fecha_desde'), Carbon::now()->startOfMonth()->toDateString());
        $fechaHasta = $this->fecha($request->input('fecha_hasta'), Carbon::now()->toDateString());
        $salaId = (int) $request->input('sala_id', 0);
        $consultar = (string) $request->input('consultar', '') === '1';

        $resultado = null;
        $filasPagina = null;
        $filtrosQuery = [
            'fecha_desde' => $fechaDesde,
            'fecha_hasta' => $fechaHasta,
            'sala_id' => $salaId > 0 ? $salaId : null,
            'consultar' => 1,
        ];

        if ($consultar) {
            ini_set('memory_limit', '512M');
            ini_set('max_execution_time', '180');
            $resultado = $this->conciliacionSupport->conciliar(
                $fechaDesde,
                $fechaHasta,
                $salaId > 0 ? $salaId : null,
            );
            $filas = $resultado['filas'] ?? [];
            $page = max(1, (int) $request->input('page', 1));
            $perPage = 50;
            $filasPagina = new LengthAwarePaginator(
                array_slice($filas, ($page - 1) * $perPage, $perPage),
                count($filas),
                $perPage,
                $page,
                ['path' => $request->url(), 'query' => array_filter($filtrosQuery, static fn ($v) => $v !== null && $v !== '')]
            );
        }

        return view('ventas.facturacion_juego.conciliacion', [
            'fechaDesde' => $fechaDesde,
            'fechaHasta' => $fechaHasta,
            'salaId' => $salaId,
            'consultar' => $consultar,
            'resultado' => $resultado,
            'filasPagina' => $filasPagina,
            'filtrosQuery' => array_filter($filtrosQuery, static fn ($v) => $v !== null && $v !== ''),
            'salas' => Sala::query()->orderBy('codigo')->get(['id', 'codigo', 'nombre']),
            'helpSalas' => $resultado['help_salas']
                ?? $this->conciliacionSupport->armarHelpSalas(FacturacionJuegoConfigSupport::salasActivas()),
        ]);
    }

    public function exportarConciliacion(Request $request, ?string $formato = null): BinaryFileResponse|StreamedResponse|RedirectResponse
    {
        can('usar-facturacion-juego');
        ini_set('memory_limit', '512M');
        ini_set('max_execution_time', '180');

        $fechaDesde = $this->fecha($request->input('fecha_desde'), '');
        $fechaHasta = $this->fecha($request->input('fecha_hasta'), '');
        $salaId = (int) $request->input('sala_id', 0);
        $redirectQuery = array_filter([
            'fecha_desde' => $fechaDesde !== '' ? $fechaDesde : null,
            'fecha_hasta' => $fechaHasta !== '' ? $fechaHasta : null,
            'sala_id' => $salaId > 0 ? $salaId : null,
            'consultar' => 1,
        ], static fn ($v) => $v !== null && $v !== '');

        $formato = strtoupper(trim((string) $formato));
        if ($fechaDesde === '' || $fechaHasta === '' || ! in_array($formato, ['PDF', 'EXCEL', 'CSV'], true)) {
            return redirect()
                ->route('facturacion_juego_conciliacion', $redirectQuery)
                ->with('mensaje_error', 'Indique fechas y formato de exportación.');
        }

        $resultado = $this->conciliacionSupport->conciliar(
            $fechaDesde,
            $fechaHasta,
            $salaId > 0 ? $salaId : null,
        );

        if ($formato === 'PDF') {
            $html = view('ventas.facturacion_juego.conciliacion_listado', [
                'resultado' => $resultado,
                'filas' => $resultado['filas'] ?? [],
                'titulo' => 'Conciliación facturación juego',
            ])->render();
            $ruta = storage_path('pdf/listados/conciliacion_facturacion_juego.pdf');
            DompdfListadoSupport::guardarLegalLandscape($html, $ruta, [
                'titulo_corto' => 'Conciliación facturación juego',
            ]);

            return response()->download($ruta)->deleteFileAfterSend(true);
        }

        $export = new FacturacionJuegoConciliacionExport($resultado, $formato === 'CSV');
        $nombre = 'conciliacion_facturacion_juego_'.date('Ymd_His');

        if ($formato === 'CSV') {
            return Excel::download($export, $nombre.'.csv', \Maatwebsite\Excel\Excel::CSV);
        }

        return Excel::download($export, $nombre.'.xlsx');
    }

    public function ejecutar(Request $request): RedirectResponse
    {
        can('ejecutar-facturacion-juego');

        $fechaDesde = $this->fecha($request->input('fecha_desde'), '');
        $fechaHasta = $this->fecha($request->input('fecha_hasta'), '');
        if ($fechaDesde === '' || $fechaHasta === '') {
            return redirect()
                ->route('facturacion_juego', $request->only(['fecha_desde', 'fecha_hasta', 'sala_id']) + ['consultar' => 1])
                ->with('mensaje_error', 'Indique fecha desde y hasta.');
        }

        $salaId = (int) $request->input('sala_id', 0);
        $resultado = $this->service->ejecutar($fechaDesde, $fechaHasta, $salaId > 0 ? $salaId : null);

        $ok = count($resultado['generados']);
        $omit = count($resultado['omitidos']);
        $err = count($resultado['errores']);
        $msg = "Generados: {$ok}. Omitidos: {$omit}.";
        if ($err > 0) {
            $msg .= ' Errores: '.$err.'. '.implode(' | ', array_slice($resultado['errores'], 0, 3));
        }

        $redirect = redirect()
            ->route('facturacion_juego', [
                'fecha_desde' => $fechaDesde,
                'fecha_hasta' => $fechaHasta,
                'sala_id' => $salaId > 0 ? $salaId : null,
                'consultar' => 1,
            ]);

        if ($err > 0 && $ok === 0) {
            return $redirect->with('mensaje_error', $msg);
        }

        return $redirect->with('mensaje', $msg);
    }

    public function configuracion(): View
    {
        can('configurar-facturacion-juego');

        $parametro = FacturacionJuegoConfigSupport::parametro();
        $salasCfg = FacturacionJuegoSala::query()
            ->with(['sala', 'cuentacaja', 'cuentacontableMesa', 'cuentacontableSlot', 'cuentacontableRuletaEl', 'centrocosto'])
            ->orderBy('sala_id')
            ->get();

        return view('ventas.facturacion_juego.configuracion', [
            'parametro' => $parametro,
            'salasCfg' => $salasCfg,
        ]);
    }

    public function guardarConfiguracion(Request $request): RedirectResponse
    {
        can('configurar-facturacion-juego');

        $parametro = FacturacionJuegoConfigSupport::parametro();
        $parametro->fill([
            'tipo_gan_mesa' => strtoupper(trim((string) $request->input('tipo_gan_mesa', 'FME'))),
            'tipo_per_mesa' => strtoupper(trim((string) $request->input('tipo_per_mesa', 'CME'))),
            'tipo_gan_slot' => strtoupper(trim((string) $request->input('tipo_gan_slot', 'FSL'))),
            'tipo_per_slot' => strtoupper(trim((string) $request->input('tipo_per_slot', 'CSL'))),
            'tipo_gan_ruleta_el' => strtoupper(trim((string) $request->input('tipo_gan_ruleta_el', 'FRE'))),
            'tipo_per_ruleta_el' => strtoupper(trim((string) $request->input('tipo_per_ruleta_el', 'CRE'))),
            'letra' => strtoupper(substr(trim((string) $request->input('letra', 'B')), 0, 1)) ?: 'B',
            'tipoasiento_abreviatura' => strtoupper(trim((string) $request->input('tipoasiento_abreviatura', 'VTA'))),
        ]);
        $parametro->save();
        FacturacionJuegoConfigSupport::forgetCache();

        $filas = (array) $request->input('salas', []);
        foreach ($filas as $id => $datos) {
            $cfg = FacturacionJuegoSala::query()->find((int) $id);
            if ($cfg === null) {
                continue;
            }
            $cfg->fill([
                'sucursal_anita' => (int) ($datos['sucursal_anita'] ?? 0),
                'cuentacaja_id' => $this->resolverIdPorCodigo(Cuentacaja::class, (string) ($datos['cuentacaja_codigo'] ?? '')),
                'cuentacontable_mesa_id' => $this->resolverIdPorCodigo(Cuentacontable::class, (string) ($datos['cuentacontable_mesa_codigo'] ?? '')),
                'cuentacontable_slot_id' => $this->resolverIdPorCodigo(Cuentacontable::class, (string) ($datos['cuentacontable_slot_codigo'] ?? '')),
                'cuentacontable_ruleta_el_id' => $this->resolverIdPorCodigo(Cuentacontable::class, (string) ($datos['cuentacontable_ruleta_el_codigo'] ?? '')),
                'centrocosto_id' => $this->resolverIdPorCodigo(Centrocosto::class, (string) ($datos['centrocosto_codigo'] ?? '')),
                'activo' => ! empty($datos['activo']),
            ]);
            $cfg->save();
        }

        return redirect()
            ->route('facturacion_juego_configuracion')
            ->with('mensaje', 'Configuración de facturación juego actualizada.');
    }

    public function sembrarDesdeAnita(): RedirectResponse
    {
        can('configurar-facturacion-juego');

        try {
            $stats = $this->semillaAnita->sembrar();
            $msg = 'Sembradas '.$stats['actualizadas'].' sala(s).';
            if ($stats['sin_cuenta'] > 0) {
                $msg .= ' Sin cuenta: '.$stats['sin_cuenta'].'.';
            }
            if ($stats['detalle'] !== []) {
                $msg .= ' '.implode(' | ', array_slice($stats['detalle'], 0, 5));
            }

            return redirect()
                ->route('facturacion_juego_configuracion')
                ->with('mensaje', $msg);
        } catch (\Throwable $e) {
            return redirect()
                ->route('facturacion_juego_configuracion')
                ->with('mensaje_error', 'No se pudo sembrar desde Anita: '.$e->getMessage());
        }
    }

    public function apiPreview(Request $request): JsonResponse
    {
        can('usar-facturacion-juego');

        $fechaDesde = $this->fecha($request->input('fecha_desde'), '');
        $fechaHasta = $this->fecha($request->input('fecha_hasta'), '');
        if ($fechaDesde === '' || $fechaHasta === '') {
            return response()->json(['ok' => false, 'mensaje' => 'Indique fechas.'], 422);
        }
        $salaId = (int) $request->input('sala_id', 0);
        $preview = $this->service->preview($fechaDesde, $fechaHasta, $salaId > 0 ? $salaId : null);

        return response()->json([
            'ok' => true,
            'filas' => $preview,
            'listos' => collect($preview)->where('listo', true)->count(),
            'pendientes_config' => collect($preview)->where('listo', false)->count(),
            'total_monto' => collect($preview)->sum('monto'),
        ]);
    }

    private function fecha(mixed $valor, string $default): string
    {
        $raw = trim((string) ($valor ?? ''));
        if ($raw === '') {
            return $default;
        }
        try {
            return Carbon::parse($raw)->toDateString();
        } catch (\Throwable) {
            return $default;
        }
    }

    /**
     * @param  class-string  $modelo
     */
    private function resolverIdPorCodigo(string $modelo, string $codigo): ?int
    {
        $codigo = trim($codigo);
        if ($codigo === '') {
            return null;
        }
        $id = (int) ($modelo::query()->where('codigo', $codigo)->value('id') ?? 0);
        if ($id <= 0) {
            $id = (int) ($modelo::query()
                ->where('codigo', ltrim($codigo, '0'))
                ->value('id') ?? 0);
        }

        return $id > 0 ? $id : null;
    }
}

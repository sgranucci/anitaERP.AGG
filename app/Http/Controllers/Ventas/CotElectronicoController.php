<?php

namespace App\Http\Controllers\Ventas;

use App\Exports\Ventas\CotGuiaSuburbanoExport;
use App\Exports\Ventas\CotSesionEnvioExport;
use App\Http\Controllers\Controller;
use App\Models\Ventas\CotGuia;
use App\Models\Ventas\Transporte;
use App\Repositories\Ventas\CotGuiaRepository;
use App\Repositories\Ventas\CotSesionEnvioRepository;
use App\Services\Ventas\ComprobanteImpresionSesionService;
use App\Services\Ventas\CotElectronico\ArbaCotPresentacionService;
use App\Services\Ventas\CotElectronico\CotElectronicoService;
use App\Services\Ventas\CotElectronico\CotGuiaService;
use App\Services\Ventas\CotElectronico\CotGuiaSuburbanoExcelService;
use App\Support\Ventas\ComprobanteImpresionFormulario;
use App\Support\Ventas\ComprobanteImpresionSalidaUsuarioSupport;
use App\Support\Ventas\CotConfiguracionSupport;
use App\Support\Ventas\CotElectronicoPreferenciasUsuario;
use App\Support\Ventas\CotGuiaSuburbanoSupport;
use App\Support\Ventas\CotRemitoTotalesSupport;
use App\Support\Ventas\CuitFormatoValidacionSupport;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class CotElectronicoController extends Controller
{
    public function __construct(
        private CotElectronicoService $service,
        private ArbaCotPresentacionService $presentacionService,
        private CotSesionEnvioRepository $sesionRepository,
        private ComprobanteImpresionSesionService $impresionSesionService,
        private CotGuiaService $guiaService,
        private CotGuiaRepository $guiaRepository,
        private CotGuiaSuburbanoExcelService $guiaSuburbanoExcelService,
    ) {}

    public function index(Request $request)
    {
        can('procesar-cot-electronico');

        if (CotConfiguracionSupport::esPorGuia()) {
            return $this->indexGuia($request);
        }

        return $this->indexReparto($request);
    }

    private function indexReparto(Request $request)
    {
        $fecha = $request->input('fecha', now()->format('Y-m-d'));
        $consultado = $request->boolean('consultar');
        $procesado = $request->boolean('procesar');

        $repartos = $this->normalizarRepartosRequest($request);
        $remitos = [];
        $resultadoProceso = null;
        $errorCuit = null;

        if ($consultado || $procesado) {
            CotElectronicoPreferenciasUsuario::persistirImprimirAlProcesar(
                $request->boolean('imprimir_al_procesar')
            );
        }

        $ctxImpresion = $this->contextoImpresion();

        if ($consultado || $procesado) {
            $errorCuit = CuitFormatoValidacionSupport::primerErrorEnRepartos($repartos);

            if ($errorCuit === null) {
                $preview = $this->service->preview(Carbon::parse($fecha), $repartos);
                $repartos = $preview['repartos'];
                $remitos = $preview['remitos'];
            }
        }

        if ($procesado && $errorCuit === null) {
            $claves = array_values(array_filter((array) $request->input('remitos_seleccionados', [])));
            $resultadoProceso = $this->service->procesar(Carbon::parse($fecha), $repartos, $claves);

            if ($resultadoProceso['ok'] ?? false) {
                $preview = $this->service->preview(Carbon::parse($fecha), $repartos);
                $remitos = $preview['remitos'];

                if ($this->debeImprimirAutomaticamente(
                    $resultadoProceso,
                    $ctxImpresion['imprimirAlProcesar'],
                    $ctxImpresion['tieneImpresoraAsignada']
                )) {
                    $this->imprimirCotTrasProcesar((int) $resultadoProceso['sesion_id']);
                }
            }
        }

        return view('ventas.cot_electronico.index', array_merge(
            $this->datosHistorico($request, $fecha),
            $ctxImpresion,
            [
                'modoCot' => CotConfiguracionSupport::MODO_POR_REPARTO,
                'fecha' => $fecha,
                'repartos' => $repartos,
                'remitos' => $remitos,
                'cantidadRemitosPendientes' => collect($remitos)->filter(
                    fn ($r) => empty($r['ya_enviado']) && ! empty($r['importe_ok'])
                )->count(),
                'cantidadRemitosBloqueados' => collect($remitos)->filter(
                    fn ($r) => empty($r['ya_enviado']) && empty($r['importe_ok'])
                )->count(),
                'cantidadRemitosEmitidos' => collect($remitos)->filter(fn ($r) => ! empty($r['ya_enviado']))->count(),
                'totalesCot' => CotRemitoTotalesSupport::resumir($remitos),
                'consultado' => $consultado || $procesado,
                'resultadoProceso' => $resultadoProceso,
                'resultadoPruebaConexion' => session('resultadoPruebaConexion'),
                'errorCuit' => $errorCuit,
                'ambiente' => (string) config('arba_cot.ambiente', 'test'),
            ]
        ));
    }

    private function indexGuia(Request $request)
    {
        $ctxImpresion = $this->contextoImpresion();
        $guiaId = $request->integer('guia_id') ?: null;
        $guia = null;
        $resultadoProceso = session('resultadoProcesoGuia');

        if ($guiaId > 0) {
            $guia = $this->guiaService->cargar($guiaId);
        } elseif ($request->filled('numero_guia')) {
            $guia = $this->guiaService->cargarPorNumero((int) $request->input('numero_guia'));
        }

        $fecha = $guia?->fecha?->format('Y-m-d')
            ?? $request->input('fecha', now()->format('Y-m-d'));

        return view('ventas.cot_electronico.index_guia', array_merge(
            $this->datosHistorico($request, $fecha),
            $ctxImpresion,
            [
                'modoCot' => CotConfiguracionSupport::MODO_POR_GUIA,
                'guia' => $guia,
                'fecha' => $fecha,
                'resultadoProceso' => $resultadoProceso,
                'resultadoPruebaConexion' => session('resultadoPruebaConexion'),
                'ambiente' => (string) config('arba_cot.ambiente', 'test'),
                'siguienteNumeroGuia' => $this->guiaRepository->siguienteNumero(),
                'guiaSuburbanoHabilitado' => CotGuiaSuburbanoSupport::habilitadoEnEntorno(),
                'esGuiaSuburbano' => CotGuiaSuburbanoSupport::esSuburbano($guia?->transportes),
                'urlsGuia' => [
                    'guardar' => route('cot_electronico_guia_guardar'),
                    'resolver' => route('cot_electronico_guia_resolver_factura'),
                    'pendientes' => route('cot_electronico_guia_pendientes'),
                    'enviar' => route('cot_electronico_guia_enviar'),
                    'consultar' => route('cot_electronico_guia_consultar'),
                    'leer' => route('cot_electronico'),
                    'suburbanoExcel' => $guia
                        ? route('cot_electronico_guia_suburbano_excel', ['id' => $guia->id])
                        : '',
                ],
            ]
        ));
    }

    public function exportarGuiaSuburbano(int $id)
    {
        can('procesar-cot-electronico');
        if (! CotGuiaSuburbanoSupport::habilitadoEnEntorno()) {
            abort(404);
        }

        $guia = $this->guiaService->cargar($id);
        if ($guia === null) {
            abort(404);
        }

        try {
            $payload = $this->guiaSuburbanoExcelService->armar($guia);
        } catch (\InvalidArgumentException $e) {
            return redirect()
                ->route('cot_electronico', ['guia_id' => $guia->id])
                ->withErrors([$e->getMessage()]);
        }

        $nombre = 'guia_suburbano_'.$guia->numero.'_'.date('Ymd_His').'.xlsx';

        return (new CotGuiaSuburbanoExport($payload))->download($nombre);
    }

    public function guardarGuia(Request $request)
    {
        can('procesar-cot-electronico');
        if (! CotConfiguracionSupport::esPorGuia()) {
            abort(404);
        }

        try {
            $cabecera = $this->cabeceraGuiaDesdeRequest($request);
            $lineas = $this->lineasGuiaDesdeRequest($request);
            $guiaId = $request->integer('guia_id') ?: null;
            $guia = $this->guiaService->guardar($cabecera, $lineas, $guiaId);

            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'ok' => true,
                    'mensaje' => 'Guía '.$guia->numero.' guardada.',
                    'guia' => $this->serializarGuia($guia),
                ]);
            }

            return redirect()
                ->route('cot_electronico', ['guia_id' => $guia->id])
                ->with('mensaje', 'Guía '.$guia->numero.' guardada.');
        } catch (\Throwable $e) {
            Log::error('ventas.cot_guia.guardar', ['error' => $e->getMessage()]);
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json(['ok' => false, 'mensaje' => $e->getMessage()], 422);
            }

            return redirect()->route('cot_electronico')->withErrors([$e->getMessage()]);
        }
    }

    public function enviarGuia(Request $request)
    {
        can('procesar-cot-electronico');
        if (! CotConfiguracionSupport::esPorGuia()) {
            abort(404);
        }

        CotElectronicoPreferenciasUsuario::persistirImprimirAlProcesar(
            $request->boolean('imprimir_al_procesar')
        );

        try {
            $cabecera = $this->cabeceraGuiaDesdeRequest($request);
            $lineas = $this->lineasGuiaDesdeRequest($request);
            $guiaId = $request->integer('guia_id') ?: null;
            $guia = $this->guiaService->guardar($cabecera, $lineas, $guiaId);
            $resultado = $this->guiaService->enviarAArba($guia->fresh(['lineas', 'transportes']) ?? $guia);

            $ctx = $this->contextoImpresion();
            if (($resultado['ok'] ?? false) && $this->debeImprimirAutomaticamente(
                $resultado,
                $ctx['imprimirAlProcesar'],
                $ctx['tieneImpresoraAsignada']
            )) {
                $this->imprimirCotTrasProcesar((int) $resultado['sesion_id']);
            }

            return redirect()
                ->route('cot_electronico', array_filter([
                    'guia_id' => $guia->id,
                    'sesion_id' => $resultado['sesion_id'] ?? null,
                ]))
                ->with('resultadoProcesoGuia', $resultado)
                ->with(($resultado['ok'] ?? false) ? 'mensaje' : 'errores', ($resultado['ok'] ?? false)
                    ? ($resultado['mensaje'] ?? 'Envío OK')
                    : [$resultado['mensaje'] ?? 'Error al enviar']);
        } catch (\Throwable $e) {
            Log::error('ventas.cot_guia.enviar', ['error' => $e->getMessage()]);

            return redirect()->route('cot_electronico')->withErrors([$e->getMessage()]);
        }
    }

    public function resolverFacturaGuia(Request $request)
    {
        can('procesar-cot-electronico');

        $tipo = (string) $request->input('tipo', '');
        $letra = (string) $request->input('letra', '');
        $sucursal = (int) $request->input('sucursal', 0);
        $numero = (int) $request->input('numero', 0);

        $codigo = trim((string) $request->input('codigo', ''));
        if ($codigo !== '' && ($tipo === '' || $numero < 1)) {
            $parsed = $this->parsearCodigoFactura($codigo);
            $tipo = $parsed['tipo'];
            $letra = $parsed['letra'];
            $sucursal = $parsed['sucursal'];
            $numero = $parsed['numero'];
        }

        return response()->json($this->guiaService->resolverFactura($tipo, $letra, $sucursal, $numero));
    }

    public function pendientesGuia(Request $request)
    {
        can('procesar-cot-electronico');

        $fecha = Carbon::parse($request->input('fecha', now()->format('Y-m-d')));
        $transporteId = $request->integer('transporte_id') ?: null;
        $guiaId = $request->integer('guia_id') ?: null;
        $resultado = $this->guiaService->facturasPendientesDelDia($fecha, $transporteId, $guiaId);
        $filas = $resultado['filas'];

        return response()->json([
            'ok' => true,
            'cantidad' => count($filas),
            'cantidad_total_dia' => (int) ($resultado['cantidad_total_dia'] ?? 0),
            'cantidad_emitidas' => (int) ($resultado['cantidad_emitidas'] ?? 0),
            'cantidad_sin_importe' => (int) ($resultado['cantidad_sin_importe'] ?? 0),
            'cantidad_en_guia' => (int) ($resultado['cantidad_en_guia'] ?? 0),
            'filas' => $filas,
        ]);
    }

    public function consultarGuias(Request $request)
    {
        can('procesar-cot-electronico');

        $coleccion = $this->guiaRepository->consultar([
            'fecha' => $request->input('fecha'),
            'texto' => $request->input('texto', $request->input('q')),
        ], false);

        $html = '';
        foreach ($coleccion as $g) {
            $html .= '<tr class="elige-cot-guia" data-id="'.$g->id.'" data-numero="'.$g->numero.'">'
                .'<td>'.$g->numero.'</td>'
                .'<td>'.e(optional($g->fecha)->format('d/m/Y')).'</td>'
                .'<td>'.e(optional($g->transportes)->codigo.' '.optional($g->transportes)->nombre).'</td>'
                .'<td>'.(int) ($g->lineas_count ?? $g->lineas()->count()).'</td>'
                .'<td>'.e($g->estado).'</td>'
                .'<td><button type="button" class="btn btn-warning btn-sm elige-cot-guia">Elegir</button></td>'
                .'</tr>';
        }
        if ($html === '') {
            $html = '<tr><td colspan="6" class="text-center text-muted">Sin guías</td></tr>';
        }

        return response()->json(['data' => $html]);
    }

    public function probarConexion()
    {
        can('procesar-cot-electronico');

        $resultado = $this->presentacionService->probarConexion();

        return redirect()
            ->route('cot_electronico')
            ->with('resultadoPruebaConexion', $resultado);
    }

    public function exportar(Request $request, ?string $formato = null)
    {
        can('procesar-cot-electronico');

        return $this->generarExport($request, $formato, null);
    }

    public function exportarSesion(Request $request, int $id, ?string $formato = null)
    {
        can('procesar-cot-electronico');

        return $this->generarExport($request, $formato, $id);
    }

    /**
     * @return array{imprimirAlProcesar: bool, impresoraUsuario: array<string, mixed>, tieneImpresoraAsignada: bool}
     */
    private function contextoImpresion(): array
    {
        return [
            'imprimirAlProcesar' => CotElectronicoPreferenciasUsuario::resolverImprimirAlProcesar(),
            'impresoraUsuario' => ComprobanteImpresionSalidaUsuarioSupport::resumenImpresora(
                null,
                ComprobanteImpresionFormulario::COT
            ),
            'tieneImpresoraAsignada' => ComprobanteImpresionSalidaUsuarioSupport::tieneImpresoraAsignada(
                null,
                ComprobanteImpresionFormulario::COT
            ),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function datosHistorico(Request $request, string $fecha): array
    {
        $filtrosHistorico = $this->sesionRepository->filtrosDesdeRequest(
            $request->input('fecha_desde'),
            $request->input('fecha_hasta'),
            $request->input('ambiente'),
            $request->input('ok'),
        );
        $filtrosHistoricoQuery = $this->sesionRepository->paraQueryString($filtrosHistorico);

        $sesionId = $request->integer('sesion_id') ?: null;
        $sesionDetalle = null;
        $remitosSesion = collect();

        if ($sesionId > 0) {
            $sesionDetalle = $this->sesionRepository->leeSesion($sesionId);
            if ($sesionDetalle === null) {
                abort(404);
            }
            $remitosSesion = $sesionDetalle->remitos()->orderBy('numero_remito')->get();
        }

        $sesiones = $this->sesionRepository->leeSesiones($filtrosHistorico, true)
            ->appends(array_merge($filtrosHistoricoQuery, array_filter([
                'fecha' => $fecha,
                'sesion_id' => $sesionId,
                'guia_id' => $request->integer('guia_id') ?: null,
            ])));

        return [
            'sesiones' => $sesiones,
            'filtrosHistorico' => $filtrosHistorico,
            'filtrosHistoricoQuery' => $filtrosHistoricoQuery,
            'sesionDetalle' => $sesionDetalle,
            'remitosSesion' => $remitosSesion,
            'sesionId' => $sesionId,
            'guiaSuburbanoHabilitado' => CotGuiaSuburbanoSupport::habilitadoEnEntorno(),
        ];
    }

    /**
     * @param  array<string, mixed>  $resultadoProceso
     */
    private function debeImprimirAutomaticamente(
        array $resultadoProceso,
        bool $imprimirAlProcesar,
        bool $tieneImpresoraAsignada
    ): bool {
        if (! $imprimirAlProcesar || ! $tieneImpresoraAsignada) {
            return false;
        }
        if ((int) ($resultadoProceso['sesion_id'] ?? 0) < 1) {
            return false;
        }

        foreach ((array) ($resultadoProceso['resultados'] ?? []) as $resultado) {
            if (! empty($resultado['cot'])) {
                return true;
            }
        }

        return false;
    }

    private function imprimirCotTrasProcesar(int $sesionId): void
    {
        try {
            $resultado = $this->impresionSesionService->imprimirCotDirecto($sesionId);
            session()->flash('mensaje', $resultado['mensaje']);
        } catch (\InvalidArgumentException $e) {
            session()->flash('errores', [$e->getMessage()]);
        } catch (\Throwable $e) {
            Log::error('ventas.cot_electronico.impresion', [
                'sesion_id' => $sesionId,
                'error' => $e->getMessage(),
            ]);
            session()->flash('errores', [
                'El envío se procesó, pero no se pudo imprimir el COT: '.$e->getMessage(),
            ]);
        }
    }

    private function generarExport(Request $request, ?string $formato, ?int $id)
    {
        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $sesion = null;

        if ($id !== null && $id > 0) {
            $sesion = $this->sesionRepository->leeSesion($id);
            if ($sesion === null) {
                abort(404);
            }
            $filas = $sesion->remitos()->orderBy('numero_remito')->get();
            $titulo = 'Detalle sesión COT #'.$sesion->id;
            $repartoTxt = $sesion->etiquetaRepartos();
            $subtitulo = 'Fecha facturas: '.$sesion->fecha_facturas?->format('d/m/Y')
                .' — Envío: '.$sesion->fecha_envio?->format('d/m/Y H:i');
            if ($repartoTxt !== '') {
                $subtitulo .= ' — Reparto: '.$repartoTxt;
            }
        } else {
            $filtros = $this->sesionRepository->filtrosDesdeRequest(
                $request->input('fecha_desde'),
                $request->input('fecha_hasta'),
                $request->input('ambiente'),
                $request->input('ok'),
            );
            $filas = $this->sesionRepository->leeRemitosDetalle($filtros, false);
            $titulo = 'Histórico envíos COT ARBA';
            $subtitulo = 'Desde '.$filtros['fecha_desde'].' hasta '.$filtros['fecha_hasta'];
        }

        switch ($formato) {
            case 'PDF':
                $view = \View::make('ventas.cot_electronico.historico.listado', compact(
                    'filas',
                    'titulo',
                    'subtitulo',
                    'sesion',
                ))->render();
                $path = storage_path('pdf/listados');
                if (! is_dir($path)) {
                    mkdir($path, 0775, true);
                }
                $nombrePdf = 'cot_historico_'.date('Ymd_His');

                $pdf = \App::make('dompdf.wrapper');
                $pdf->setPaper('legal', 'landscape');
                $pdf->loadHTML($view, 'UTF-8')->save($path.'/'.$nombrePdf.'.pdf');

                return response()->download($path.'/'.$nombrePdf.'.pdf');

            case 'EXCEL':
                return (new CotSesionEnvioExport($filas, $titulo, $subtitulo))
                    ->download('cot_historico.xlsx');

            case 'CSV':
                return (new CotSesionEnvioExport($filas, $titulo, $subtitulo))
                    ->download('cot_historico.csv', \Maatwebsite\Excel\Excel::CSV);
        }

        return redirect()->route('cot_electronico', $this->sesionRepository->paraQueryString(
            $this->sesionRepository->filtrosDesdeRequest(
                $request->input('fecha_desde'),
                $request->input('fecha_hasta'),
                $request->input('ambiente'),
                $request->input('ok'),
            )
        ));
    }

    /** @return array<string, mixed> */
    private function cabeceraGuiaDesdeRequest(Request $request): array
    {
        $transporteId = (int) $request->input('transporte_id', 0);
        if ($transporteId < 1) {
            $codigo = trim((string) $request->input('transporte_codigo', ''));
            if ($codigo !== '') {
                $t = Transporte::query()->where('codigo', $codigo)->first();
                $transporteId = (int) ($t->id ?? 0);
            }
        }

        return [
            'numero' => (int) $request->input('numero', 0),
            'fecha' => $request->input('fecha', now()->format('Y-m-d')),
            'transporte_id' => $transporteId ?: null,
            'cuit_chofer' => CuitFormatoValidacionSupport::formatear(
                trim((string) $request->input('cuit_chofer', ''))
            ),
            'dominio' => strtoupper(trim((string) $request->input('dominio', ''))),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function lineasGuiaDesdeRequest(Request $request): array
    {
        $tipos = (array) $request->input('linea_tipo', []);
        $letras = (array) $request->input('linea_letra', []);
        $sucursales = (array) $request->input('linea_sucursal', []);
        $numeros = (array) $request->input('linea_numero', []);
        $clientesCod = (array) $request->input('linea_cliente_codigo', []);
        $clientesNom = (array) $request->input('linea_cliente_nombre', []);
        $bultos = (array) $request->input('linea_bultos', []);
        $cantidades = (array) $request->input('linea_cantidad', []);
        $valores = (array) $request->input('linea_valor', []);
        $transIds = (array) $request->input('linea_transporte_id', []);
        $transCod = (array) $request->input('linea_transporte_codigo', []);
        $entregas = (array) $request->input('linea_entrega', []);
        $ventaIds = (array) $request->input('linea_venta_id', []);
        $ids = (array) $request->input('linea_id', []);

        $total = max(count($tipos), count($numeros));
        $lineas = [];
        for ($i = 0; $i < $total; $i++) {
            $lineas[] = [
                'id' => (int) ($ids[$i] ?? 0),
                'tipo' => (string) ($tipos[$i] ?? ''),
                'letra' => (string) ($letras[$i] ?? ''),
                'sucursal' => (int) ($sucursales[$i] ?? 0),
                'numero' => (int) ($numeros[$i] ?? 0),
                'cliente_codigo' => (string) ($clientesCod[$i] ?? ''),
                'cliente_nombre' => (string) ($clientesNom[$i] ?? ''),
                'bultos' => (float) str_replace(',', '.', (string) ($bultos[$i] ?? 0)),
                'cantidad' => (float) str_replace(',', '.', (string) ($cantidades[$i] ?? 0)),
                'valor_declarado' => (float) str_replace(',', '.', (string) ($valores[$i] ?? 0)),
                'transporte_id' => (int) ($transIds[$i] ?? 0) ?: null,
                'transporte_codigo' => (string) ($transCod[$i] ?? ''),
                'entrega' => (string) ($entregas[$i] ?? ''),
                'venta_id' => (int) ($ventaIds[$i] ?? 0) ?: null,
            ];
        }

        return $lineas;
    }

    /**
     * Acepta formatos flexibles (sin ceros a la izquierda obligatorios):
     * - FAC A-12-83027 / FAC A 12 83027 / FACA-12-83027
     * - A-12-83027 / A 12 83027 (asume FAC)
     * - 12-83027 (asume FAC A)
     * - 83027 (solo número)
     *
     * @return array{tipo: string, letra: string, sucursal: int, numero: int}
     */
    private function parsearCodigoFactura(string $codigo): array
    {
        $codigo = strtoupper(trim($codigo));
        $codigo = preg_replace('/\s+/', ' ', $codigo) ?? $codigo;
        $codigo = str_replace(['/', '\\', '.'], '-', $codigo);

        // FAC A-12-83027 | FAC A 12 83027 | FACA-00012-00083027
        if (preg_match('/^([A-Z]{1,3})\s*([A-Z])\s*[- ]?\s*(\d+)\s*[- ]\s*(\d+)$/', $codigo, $m)) {
            return [
                'tipo' => $m[1],
                'letra' => $m[2],
                'sucursal' => (int) $m[3],
                'numero' => (int) $m[4],
            ];
        }

        // FAC A 12 83027 (espacios)
        if (preg_match('/^([A-Z]{1,3})\s+([A-Z])\s+(\d+)\s+(\d+)$/', $codigo, $m)) {
            return [
                'tipo' => $m[1],
                'letra' => $m[2],
                'sucursal' => (int) $m[3],
                'numero' => (int) $m[4],
            ];
        }

        // A-12-83027 | A 12 83027 (sin tipo → FAC)
        if (preg_match('/^([A-Z])\s*[- ]\s*(\d+)\s*[- ]\s*(\d+)$/', $codigo, $m)
            || preg_match('/^([A-Z])\s+(\d+)\s+(\d+)$/', $codigo, $m)) {
            return [
                'tipo' => 'FAC',
                'letra' => $m[1],
                'sucursal' => (int) $m[2],
                'numero' => (int) $m[3],
            ];
        }

        // 12-83027 | 00012-00083027 (PV-número → FAC A)
        if (preg_match('/^(\d+)\s*[- ]\s*(\d+)$/', $codigo, $m)) {
            return [
                'tipo' => 'FAC',
                'letra' => 'A',
                'sucursal' => (int) $m[1],
                'numero' => (int) $m[2],
            ];
        }

        // Solo número
        if (preg_match('/^(\d+)$/', $codigo, $m)) {
            return [
                'tipo' => '',
                'letra' => '',
                'sucursal' => 0,
                'numero' => (int) $m[1],
            ];
        }

        return ['tipo' => '', 'letra' => '', 'sucursal' => 0, 'numero' => 0];
    }

    /** @return array<string, mixed> */
    private function serializarGuia(CotGuia $guia): array
    {
        $guia->loadMissing(['lineas', 'transportes']);

        return [
            'id' => $guia->id,
            'numero' => $guia->numero,
            'fecha' => $guia->fecha?->format('Y-m-d'),
            'estado' => $guia->estado,
            'transporte_id' => $guia->transporte_id,
            'transporte_codigo' => optional($guia->transportes)->codigo,
            'transporte_nombre' => optional($guia->transportes)->nombre,
            'cuit_chofer' => $guia->cuit_chofer,
            'dominio' => $guia->dominio,
            'cot_sesion_envio_id' => $guia->cot_sesion_envio_id,
            'lineas' => $guia->lineas->map(fn ($l) => [
                'id' => $l->id,
                'orden' => $l->orden,
                'tipo' => $l->tipo,
                'letra' => $l->letra,
                'sucursal' => $l->sucursal,
                'numero' => $l->numero,
                'cliente_codigo' => $l->cliente_codigo,
                'cliente_nombre' => $l->cliente_nombre,
                'bultos' => $l->bultos,
                'cantidad' => $l->cantidad,
                'valor_declarado' => $l->valor_declarado,
                'transporte_id' => $l->transporte_id,
                'transporte_codigo' => $l->transporte_codigo,
                'entrega' => $l->entrega,
                'venta_id' => $l->venta_id,
                'etiqueta' => $l->etiquetaFactura(),
            ])->values()->all(),
        ];
    }

    /** @return list<array<string, mixed>> */
    private function normalizarRepartosRequest(Request $request): array
    {
        $codigos = (array) $request->input('reparto_codigo', []);
        $nombres = (array) $request->input('reparto_nombre', []);
        $ids = (array) $request->input('reparto_transporte_id', []);
        $patentes = (array) $request->input('reparto_patente', []);
        $cuits = (array) $request->input('reparto_cuit_chofer', []);

        /** @var list<array<string, mixed>> $repartos */
        $repartos = [];
        $transporteIdsVistos = [];
        $codigosVistos = [];
        $total = max(count($codigos), count($ids));

        for ($i = 0; $i < $total; $i++) {
            $codigo = trim((string) ($codigos[$i] ?? ''));
            $transporteId = (int) ($ids[$i] ?? 0);
            if ($codigo === '' && $transporteId < 1) {
                continue;
            }

            if ($transporteId > 0) {
                if (isset($transporteIdsVistos[$transporteId])) {
                    continue;
                }
                $transporteIdsVistos[$transporteId] = true;
            } elseif ($codigo !== '') {
                $codigoClave = strtoupper($codigo);
                if (isset($codigosVistos[$codigoClave])) {
                    continue;
                }
                $codigosVistos[$codigoClave] = true;
            }

            $repartos[] = [
                'transporte_id' => $transporteId,
                'codigo' => $codigo,
                'nombre' => trim((string) ($nombres[$i] ?? '')),
                'patente' => trim((string) ($patentes[$i] ?? '')),
                'cuit_chofer' => CuitFormatoValidacionSupport::formatear(trim((string) ($cuits[$i] ?? ''))),
            ];
        }

        return $repartos;
    }
}

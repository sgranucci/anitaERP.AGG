<?php

namespace App\Http\Controllers\Ventas;

use App\Exports\Ventas\CotSesionEnvioExport;
use App\Http\Controllers\Controller;
use App\Repositories\Ventas\CotSesionEnvioRepository;
use App\Services\Ventas\CotElectronico\ArbaCotPresentacionService;
use App\Services\Ventas\CotElectronico\CotElectronicoService;
use App\Support\Ventas\CuitFormatoValidacionSupport;
use Carbon\Carbon;
use Illuminate\Http\Request;

class CotElectronicoController extends Controller
{
    public function __construct(
        private CotElectronicoService $service,
        private ArbaCotPresentacionService $presentacionService,
        private CotSesionEnvioRepository $sesionRepository,
    ) {}

    public function index(Request $request)
    {
        can('procesar-cot-electronico');

        $fecha = $request->input('fecha', now()->format('Y-m-d'));
        $consultado = $request->boolean('consultar');
        $procesado = $request->boolean('procesar');

        $repartos = $this->normalizarRepartosRequest($request);
        $remitos = [];
        $resultadoProceso = null;
        $errorCuit = null;

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
            }
        }

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
            ])));

        return view('ventas.cot_electronico.index', [
            'fecha' => $fecha,
            'repartos' => $repartos,
            'remitos' => $remitos,
            'cantidadRemitosPendientes' => collect($remitos)->filter(fn ($r) => empty($r['ya_enviado']))->count(),
            'cantidadRemitosEmitidos' => collect($remitos)->filter(fn ($r) => ! empty($r['ya_enviado']))->count(),
            'consultado' => $consultado || $procesado,
            'resultadoProceso' => $resultadoProceso,
            'resultadoPruebaConexion' => session('resultadoPruebaConexion'),
            'errorCuit' => $errorCuit,
            'ambiente' => (string) config('arba_cot.ambiente', 'test'),
            'sesiones' => $sesiones,
            'filtrosHistorico' => $filtrosHistorico,
            'filtrosHistoricoQuery' => $filtrosHistoricoQuery,
            'sesionDetalle' => $sesionDetalle,
            'remitosSesion' => $remitosSesion,
            'sesionId' => $sesionId,
        ]);
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

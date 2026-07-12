<?php

namespace App\Http\Controllers\Contable;

use App\Exports\Contable\CierreRendicionMaquinavendingListadoExport;
use App\Http\Controllers\Controller;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Services\Contable\CierreRendicionMaquinavendingService;
use App\Support\Contable\CierreRendicionMaquinavendingListadoFiltros;
use App\Support\Listado\FiltrosListadoRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Maatwebsite\Excel\Excel;

class CierreRendicionMaquinavendingController extends Controller
{
    public function __construct(
        private readonly CierreRendicionMaquinavendingService $service,
        private readonly EmpresaRepositoryInterface $empresaRepository,
    ) {
        $this->middleware('auth');
    }

    public function index(Request $request)
    {
        can('listar-cierre-rendicion-maquinavending-contable');

        $filtros = $this->resolverFiltrosListado($request);
        $grupos = $this->service->listarAgrupado($filtros, true);

        return view('contable.cierre_rendicion_maquinavending.index', [
            'grupos' => $grupos,
            'filtros' => $filtros,
            'filtrosQuery' => CierreRendicionMaquinavendingListadoFiltros::paraQueryString($filtros),
            'camposFiltro' => CierreRendicionMaquinavendingListadoFiltros::CAMPOS,
            'empresa_query' => $this->empresaRepository->allFiltrado(),
        ]);
    }

    public function listar(Request $request, ?string $formato = null, ?string $busqueda = null)
    {
        can('exportar-cierre-rendicion-maquinavending-contable');

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $filtros = $this->resolverFiltrosListado($request, $busqueda);
        $rendiciones = $this->service->listar($filtros, false);

        switch ($formato) {
            case 'PDF':
                $view = \View::make('contable.cierre_rendicion_maquinavending.listado', compact('rendiciones'))->render();
                $path = storage_path('pdf/listados');
                $nombrePdf = 'listado_cierre_rendicion_maquinavending';

                $pdf = \App::make('dompdf.wrapper');
                $pdf->setPaper('legal', 'landscape');
                $pdf->loadHTML($view)->save($path.'/'.$nombrePdf.'.pdf');

                return response()->download($path.'/'.$nombrePdf.'.pdf');

            case 'EXCEL':
            case 'CSV':
                $mime = $formato === 'CSV' ? Excel::CSV : Excel::XLSX;
                $ext = $formato === 'CSV' ? 'csv' : 'xlsx';

                return \Maatwebsite\Excel\Facades\Excel::download(
                    new CierreRendicionMaquinavendingListadoExport($rendiciones),
                    'cierre_rendicion_maquinavending.'.$ext,
                    $mime,
                );
        }

        return redirect()->route(
            'cierre_rendicion_maquinavending_contable',
            CierreRendicionMaquinavendingListadoFiltros::paraQueryString($filtros),
        );
    }


    public function apiPreviewAsiento(Request $request): JsonResponse
    {
        can('ejecutar-cierre-rendicion-maquinavending-contable');

        $empresaId = (int) $request->input('empresa_id', 0);
        $fechaDia = trim((string) $request->input('fecha_dia', ''));
        $puntoventaCaeId = (int) $request->input('puntoventa_cae_id', 0);

        if ($empresaId <= 0 || $fechaDia === '' || $puntoventaCaeId <= 0) {
            return response()->json(['ok' => false, 'mensaje' => 'Indique empresa, fecha jornada y punto de venta.'], 422);
        }

        try {
            $preview = $this->service->previewGrupo($empresaId, $fechaDia, $puntoventaCaeId);

            return response()->json(['ok' => true, 'preview' => $preview]);
        } catch (InvalidArgumentException $e) {
            return response()->json(['ok' => false, 'mensaje' => $e->getMessage()], 422);
        }
    }

    public function apiEjecutarCierre(Request $request): JsonResponse
    {
        can('ejecutar-cierre-rendicion-maquinavending-contable');

        $empresaId = (int) $request->input('empresa_id', 0);
        $fechaDia = trim((string) $request->input('fecha_dia', ''));
        $puntoventaCaeId = (int) $request->input('puntoventa_cae_id', 0);

        if ($empresaId <= 0 || $fechaDia === '' || $puntoventaCaeId <= 0) {
            return response()->json(['ok' => false, 'mensaje' => 'Indique empresa, fecha jornada y punto de venta.'], 422);
        }

        try {
            $resultado = $this->service->ejecutarCierreGrupo($empresaId, $fechaDia, $puntoventaCaeId);

            return response()->json([
                'ok' => true,
                'mensaje' => 'Cierre contable registrado. Asiento '.$resultado['numeroasiento']
                    .' ('.count($resultado['rendicion_ids']).' rendición/es).',
                'asiento_id' => $resultado['asiento_id'],
                'numeroasiento' => $resultado['numeroasiento'],
                'rendicion_ids' => $resultado['rendicion_ids'],
            ]);
        } catch (InvalidArgumentException $e) {
            return response()->json(['ok' => false, 'mensaje' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'mensaje' => $e->getMessage()], 422);
        }
    }

    public function apiPreviewCierreRango(Request $request): JsonResponse
    {
        can('ejecutar-cierre-rendicion-maquinavending-contable');

        $empresaId = (int) $request->input('empresa_id', 0);
        $fechaDesde = trim((string) $request->input('fecha_desde', ''));
        $fechaHasta = trim((string) $request->input('fecha_hasta', ''));

        if ($empresaId <= 0 || $fechaDesde === '' || $fechaHasta === '') {
            return response()->json(['ok' => false, 'mensaje' => 'Indique empresa y rango de fechas.'], 422);
        }

        try {
            $preview = $this->service->previewCierreRango($empresaId, $fechaDesde, $fechaHasta);

            return response()->json(['ok' => true, 'preview' => $preview]);
        } catch (InvalidArgumentException $e) {
            return response()->json(['ok' => false, 'mensaje' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'mensaje' => $e->getMessage()], 422);
        }
    }

    public function apiEjecutarCierreRango(Request $request): JsonResponse
    {
        can('ejecutar-cierre-rendicion-maquinavending-contable');

        $empresaId = (int) $request->input('empresa_id', 0);
        $fechaDesde = trim((string) $request->input('fecha_desde', ''));
        $fechaHasta = trim((string) $request->input('fecha_hasta', ''));

        if ($empresaId <= 0 || $fechaDesde === '' || $fechaHasta === '') {
            return response()->json(['ok' => false, 'mensaje' => 'Indique empresa y rango de fechas.'], 422);
        }

        if (! $request->boolean('confirmar')) {
            return response()->json(['ok' => false, 'mensaje' => 'Debe confirmar el cierre masivo.'], 422);
        }

        try {
            $resultado = $this->service->ejecutarCierreRango($empresaId, $fechaDesde, $fechaHasta);

            return response()->json([
                'ok' => true,
                'mensaje' => $this->mensajeResultadoCierreMasivo($resultado),
                'resultado' => $resultado,
            ]);
        } catch (InvalidArgumentException $e) {
            return response()->json(['ok' => false, 'mensaje' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'mensaje' => $e->getMessage()], 422);
        }
    }

    public function apiEjecutarCierreJornada(Request $request): JsonResponse
    {
        can('ejecutar-cierre-rendicion-maquinavending-contable');

        $empresaId = (int) $request->input('empresa_id', 0);
        $fechaJornada = trim((string) $request->input('fecha_jornada', ''));

        if ($empresaId <= 0 || $fechaJornada === '') {
            return response()->json(['ok' => false, 'mensaje' => 'Indique empresa y fecha de jornada.'], 422);
        }

        if (! $request->boolean('confirmar')) {
            return response()->json(['ok' => false, 'mensaje' => 'Debe confirmar el cierre de la jornada.'], 422);
        }

        try {
            $resultado = $this->service->ejecutarCierreJornada($empresaId, $fechaJornada);

            return response()->json([
                'ok' => true,
                'mensaje' => $this->mensajeResultadoCierreMasivo($resultado),
                'resultado' => $resultado,
            ]);
        } catch (InvalidArgumentException $e) {
            return response()->json(['ok' => false, 'mensaje' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'mensaje' => $e->getMessage()], 422);
        }
    }

    /**
     * @param  array{ok: list<mixed>, errores: list<mixed>}  $resultado
     */
    private function mensajeResultadoCierreMasivo(array $resultado): string
    {
        $cantOk = count($resultado['ok']);
        $cantErr = count($resultado['errores']);
        $mensaje = 'Cierre contable: '.$cantOk.' grupo(s) cerrado(s).';
        if ($cantErr > 0) {
            $mensaje .= ' '.$cantErr.' con error.';
        }

        return $mensaje;
    }

    public function apiAnularCierre(Request $request): JsonResponse
    {
        can('anular-cierre-rendicion-maquinavending-contable');

        $empresaId = (int) $request->input('empresa_id', 0);
        $fechaDia = trim((string) $request->input('fecha_dia', ''));
        $puntoventaCaeId = (int) $request->input('puntoventa_cae_id', 0);

        if ($empresaId <= 0 || $fechaDia === '' || $puntoventaCaeId <= 0) {
            return response()->json(['ok' => false, 'mensaje' => 'Indique empresa, fecha jornada y punto de venta.'], 422);
        }

        if (! $request->boolean('confirmar')) {
            return response()->json([
                'ok' => false,
                'mensaje' => 'Debe confirmar la anulación del cierre contable.',
            ], 422);
        }

        try {
            $this->service->anularCierreGrupo($empresaId, $fechaDia, $puntoventaCaeId);

            return response()->json([
                'ok' => true,
                'mensaje' => 'Cierre contable anulado. Se eliminó el asiento en ERP y ctamov.',
            ]);
        } catch (InvalidArgumentException $e) {
            return response()->json(['ok' => false, 'mensaje' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'mensaje' => $e->getMessage()], 422);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function resolverFiltrosListado(Request $request, ?string $busquedaRuta = null): array
    {
        $filtros = CierreRendicionMaquinavendingListadoFiltros::resolverDesdeRequest($request, $busquedaRuta);
        $asignadas = $this->empresaRepository->traeEmpresasAsignadas();
        $filtros['empresas_asignadas'] = $asignadas;

        if (FiltrosListadoRequest::solicitudLimpiaFiltros($request)) {
            return $filtros;
        }

        $empresaQuery = $this->empresaRepository->allFiltrado();
        $empresaId = (int) ($filtros['empresa_id'] ?? 0);

        if ($empresaId <= 0 && count($asignadas) === 1 && ! $request->has('empresa_id')) {
            $primera = $empresaQuery->first();
            if ($primera !== null) {
                $filtros['empresa_id'] = (int) $primera->id;
            }
        } elseif ($empresaId > 0 && count($asignadas) >= 1 && ! in_array($empresaId, $asignadas, true)) {
            $primera = $empresaQuery->first();
            if ($primera !== null) {
                $filtros['empresa_id'] = (int) $primera->id;
            }
        }

        return $filtros;
    }
}

<?php

namespace App\Http\Controllers\Contable;

use App\Exports\Contable\CierreRendicionEstacionamientoListadoExport;
use App\Http\Controllers\Controller;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Services\Contable\CierreRendicionEstacionamientoService;
use App\Support\Contable\CierreRendicionEstacionamientoListadoFiltros;
use App\Support\Listado\FiltrosListadoRequest;
use App\Support\Listado\QueryRetornoListado;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Maatwebsite\Excel\Excel;

class CierreRendicionEstacionamientoController extends Controller
{
    public function __construct(
        private readonly CierreRendicionEstacionamientoService $service,
        private readonly EmpresaRepositoryInterface $empresaRepository,
    ) {
        $this->middleware('auth');
    }

    public function index(Request $request)
    {
        can('listar-cierre-rendicion-estacionamiento-contable');

        $filtros = $this->resolverFiltrosListado($request);
        $grupos = $this->service->listarAgrupado($filtros, true);

        return view('contable.cierre_rendicion_estacionamiento.index', [
            'grupos' => $grupos,
            'filtros' => $filtros,
            'filtrosQuery' => CierreRendicionEstacionamientoListadoFiltros::paraQueryString($filtros),
            'camposFiltro' => CierreRendicionEstacionamientoListadoFiltros::CAMPOS,
            'empresa_query' => $this->empresaRepository->allFiltrado(),
        ]);
    }

    public function listar(Request $request, ?string $formato = null, ?string $busqueda = null)
    {
        can('exportar-cierre-rendicion-estacionamiento-contable');

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $filtros = $this->resolverFiltrosListado($request, $busqueda);
        $rendiciones = $this->service->listar($filtros, false);

        switch ($formato) {
            case 'PDF':
                $view = \View::make('contable.cierre_rendicion_estacionamiento.listado', compact('rendiciones'))->render();
                $path = storage_path('pdf/listados');
                $nombrePdf = 'listado_cierre_rendicion_estacionamiento';

                $pdf = \App::make('dompdf.wrapper');
                $pdf->setPaper('legal', 'landscape');
                $pdf->loadHTML($view)->save($path.'/'.$nombrePdf.'.pdf');

                return response()->download($path.'/'.$nombrePdf.'.pdf');

            case 'EXCEL':
            case 'CSV':
                $mime = $formato === 'CSV' ? Excel::CSV : Excel::XLSX;
                $ext = $formato === 'CSV' ? 'csv' : 'xlsx';

                return \Maatwebsite\Excel\Facades\Excel::download(
                    new CierreRendicionEstacionamientoListadoExport($rendiciones),
                    'cierre_rendicion_estacionamiento.'.$ext,
                    $mime,
                );
        }

        return redirect()->route(
            'cierre_rendicion_estacionamiento_contable',
            CierreRendicionEstacionamientoListadoFiltros::paraQueryString($filtros),
        );
    }

    public function conciliacionFlash(Request $request)
    {
        can('listar-cierre-rendicion-estacionamiento-contable');

        $empresaQuery = $this->empresaRepository->allFiltrado();
        $asignadas = $this->empresaRepository->traeEmpresasAsignadas();
        $empresaId = (int) $request->input('empresa_id', 0);

        if ($empresaId <= 0 && count($asignadas) === 1) {
            $primera = $empresaQuery->first();
            if ($primera !== null) {
                $empresaId = (int) $primera->id;
            }
        } elseif ($empresaId > 0 && count($asignadas) >= 1 && ! in_array($empresaId, $asignadas, true)) {
            $primera = $empresaQuery->first();
            $empresaId = $primera !== null ? (int) $primera->id : 0;
        }

        $fechaDesde = trim((string) $request->input('fecha_desde', ''));
        $fechaHasta = trim((string) $request->input('fecha_hasta', ''));

        if ($fechaDesde === '' && $fechaHasta === '') {
            $defaults = $this->service->resolverRangoConciliacionDefault($empresaId);
            $fechaDesde = $defaults['desde'];
            $fechaHasta = $defaults['hasta'];
        } elseif ($fechaHasta === '' && $fechaDesde !== '') {
            $fechaHasta = now()->toDateString();
        }

        $consultar = $request->boolean('consultar');
        $resultado = null;
        $errorFlash = null;

        if ($consultar && $empresaId > 0 && $fechaDesde !== '' && $fechaHasta !== '') {
            try {
                $resultado = $this->service->conciliarFlash($empresaId, $fechaDesde, $fechaHasta);
            } catch (\Throwable $e) {
                $errorFlash = $e->getMessage();
            }
        }

        return view('contable.cierre_rendicion_estacionamiento.conciliacion_flash', [
            'empresa_query' => $empresaQuery,
            'empresa_id' => $empresaId,
            'fecha_desde' => $fechaDesde,
            'fecha_hasta' => $fechaHasta,
            'consultar' => $consultar,
            'resultado' => $resultado,
            'error_flash' => $errorFlash,
            'retornoListadoQuery' => $this->resolverRetornoListadoQuery($request),
        ]);
    }

    public function apiPreviewAsiento(Request $request): JsonResponse
    {
        can('ejecutar-cierre-rendicion-estacionamiento-contable');

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
        can('ejecutar-cierre-rendicion-estacionamiento-contable');

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
        can('ejecutar-cierre-rendicion-estacionamiento-contable');

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
        can('ejecutar-cierre-rendicion-estacionamiento-contable');

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
        can('ejecutar-cierre-rendicion-estacionamiento-contable');

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
        can('anular-cierre-rendicion-estacionamiento-contable');

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
        $filtros = CierreRendicionEstacionamientoListadoFiltros::resolverDesdeRequest($request, $busquedaRuta);
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

    /**
     * Query del index para volver conservando filtros (desde conciliación flash u otras pantallas).
     *
     * @return array<string, string|int>
     */
    private function resolverRetornoListadoQuery(Request $request): array
    {
        $retorno = $request->input('retorno');
        if (is_array($retorno) && $retorno !== []) {
            $query = [];
            foreach ($retorno as $key => $value) {
                if (! is_string($key) || $key === '' || ! is_scalar($value)) {
                    continue;
                }
                $trimmed = is_string($value) ? trim($value) : $value;
                if ($trimmed === '' || $trimmed === null) {
                    continue;
                }
                $query[$key] = $trimmed;
            }

            return $query;
        }

        return QueryRetornoListado::desdeRequest($request, CierreRendicionEstacionamientoListadoFiltros::class);
    }
}

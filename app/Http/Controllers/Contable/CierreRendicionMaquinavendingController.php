<?php

namespace App\Http\Controllers\Contable;

use App\Exports\Contable\CierreRendicionMaquinavendingConciliacionFlashExport;
use App\Exports\Contable\CierreRendicionMaquinavendingListadoExport;
use App\Exports\Contable\MaquinavendingDiarioPuntoventaExport;
use App\Http\Controllers\Controller;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Services\Contable\CierreRendicionMaquinavendingService;
use App\Support\Contable\CierreRendicionMaquinavendingListadoFiltros;
use App\Support\Listado\QueryRetornoListado;
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
                    new CierreRendicionMaquinavendingListadoExport($rendiciones, $formato === 'CSV'),
                    'cierre_rendicion_maquinavending.'.$ext,
                    $mime,
                );
        }

        return redirect()->route(
            'cierre_rendicion_maquinavending_contable',
            CierreRendicionMaquinavendingListadoFiltros::paraQueryString($filtros),
        );
    }

    public function conciliacionFlash(Request $request)
    {
        can('listar-cierre-rendicion-maquinavending-contable');

        [$empresaQuery, $empresaId] = $this->resolverEmpresaConciliacion($request);

        $fechaDesde = trim((string) $request->input('fecha_desde', ''));
        $fechaHasta = trim((string) $request->input('fecha_hasta', ''));
        $consultar = $request->boolean('consultar');

        if (! $consultar || ($fechaDesde === '' && $fechaHasta === '')) {
            $defaults = $this->service->resolverRangoConciliacionDefault($empresaId);
            $fechaDesde = $defaults['desde'];
            $fechaHasta = $defaults['hasta'];
        } elseif ($fechaHasta === '' && $fechaDesde !== '') {
            $fechaHasta = now()->toDateString();
        }

        $resultado = null;
        $errorFlash = null;

        if ($consultar && $empresaId > 0 && $fechaDesde !== '' && $fechaHasta !== '') {
            try {
                ini_set('memory_limit', '-1');
                ini_set('max_execution_time', '0');

                $resultado = $this->service->conciliarFlash($empresaId, $fechaDesde, $fechaHasta);
            } catch (\Throwable $e) {
                $errorFlash = $e->getMessage();
            }
        }

        $filtrosQueryConciliacion = array_filter([
            'empresa_id' => $empresaId > 0 ? $empresaId : null,
            'fecha_desde' => $fechaDesde !== '' ? $fechaDesde : null,
            'fecha_hasta' => $fechaHasta !== '' ? $fechaHasta : null,
            'consultar' => $consultar ? 1 : null,
        ], static fn ($v) => $v !== null && $v !== '');

        return view('contable.cierre_rendicion_maquinavending.conciliacion_flash', [
            'empresa_query' => $empresaQuery,
            'empresa_id' => $empresaId,
            'fecha_desde' => $fechaDesde,
            'fecha_hasta' => $fechaHasta,
            'consultar' => $consultar,
            'resultado' => $resultado,
            'error_flash' => $errorFlash,
            'filtrosQueryConciliacion' => $filtrosQueryConciliacion,
            'retornoListadoQuery' => $this->resolverRetornoListadoQuery($request),
        ]);
    }

    public function listarConciliacionFlash(Request $request, ?string $formato = null)
    {
        can('exportar-cierre-rendicion-maquinavending-contable');

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $empresaId = (int) $request->input('empresa_id', 0);
        $fechaDesde = trim((string) $request->input('fecha_desde', ''));
        $fechaHasta = trim((string) $request->input('fecha_hasta', ''));

        $redirectQuery = array_filter([
            'empresa_id' => $empresaId > 0 ? $empresaId : null,
            'fecha_desde' => $fechaDesde !== '' ? $fechaDesde : null,
            'fecha_hasta' => $fechaHasta !== '' ? $fechaHasta : null,
            'consultar' => 1,
        ], static fn ($v) => $v !== null && $v !== '');

        if ($empresaId <= 0 || $fechaDesde === '' || $fechaHasta === '') {
            return redirect()
                ->route('cierre_rendicion_maquinavending_conciliacion_flash', $redirectQuery)
                ->with('mensaje_error', 'Indique empresa y rango de jornadas para exportar.');
        }

        try {
            $resultado = $this->service->conciliarFlash($empresaId, $fechaDesde, $fechaHasta);
        } catch (\Throwable $e) {
            return redirect()
                ->route('cierre_rendicion_maquinavending_conciliacion_flash', $redirectQuery)
                ->with('mensaje_error', $e->getMessage());
        }

        switch ($formato) {
            case 'PDF':
                $view = \View::make('contable.cierre_rendicion_maquinavending.conciliacion_flash_listado', [
                    'resultado' => $resultado,
                    'esExcel' => false,
                    'filas' => CierreRendicionMaquinavendingConciliacionFlashExport::aplanarFilas($resultado),
                ])->render();
                $path = storage_path('pdf/listados');
                if (! is_dir($path)) {
                    mkdir($path, 0755, true);
                }
                $nombrePdf = 'listado_conciliacion_flash_vending';

                $pdf = \App::make('dompdf.wrapper');
                $pdf->setPaper('legal', 'landscape');
                $pdf->loadHTML($view)->save($path.'/'.$nombrePdf.'.pdf');

                return response()->download($path.'/'.$nombrePdf.'.pdf');

            case 'EXCEL':
            case 'CSV':
                $mime = $formato === 'CSV' ? Excel::CSV : Excel::XLSX;
                $ext = $formato === 'CSV' ? 'csv' : 'xlsx';

                return \Maatwebsite\Excel\Facades\Excel::download(
                    new CierreRendicionMaquinavendingConciliacionFlashExport($resultado, $formato === 'CSV'),
                    'conciliacion_flash_vending.'.$ext,
                    $mime,
                );
        }

        return redirect()->route('cierre_rendicion_maquinavending_conciliacion_flash', $redirectQuery);
    }

    public function diarioPuntoventa(Request $request)
    {
        can('listar-cierre-rendicion-maquinavending-contable');

        [$empresaQuery, $empresaId] = $this->resolverEmpresaConciliacion($request);

        $fechaDesde = trim((string) $request->input('fecha_desde', ''));
        $fechaHasta = trim((string) $request->input('fecha_hasta', ''));
        $consultar = $request->boolean('consultar');

        if (! $consultar || ($fechaDesde === '' && $fechaHasta === '')) {
            $defaults = $this->service->resolverRangoConciliacionDefault($empresaId);
            $fechaDesde = $defaults['desde'];
            $fechaHasta = $defaults['hasta'];
        } elseif ($fechaHasta === '' && $fechaDesde !== '') {
            $fechaHasta = now()->toDateString();
        }

        $resultado = null;
        $errorReporte = null;

        if ($consultar && $empresaId > 0 && $fechaDesde !== '' && $fechaHasta !== '') {
            try {
                ini_set('memory_limit', '-1');
                ini_set('max_execution_time', '0');

                $resultado = $this->service->reporteDiarioPuntoventa($empresaId, $fechaDesde, $fechaHasta);
            } catch (\Throwable $e) {
                $errorReporte = $e->getMessage();
            }
        }

        $filtrosQuery = array_filter([
            'empresa_id' => $empresaId > 0 ? $empresaId : null,
            'fecha_desde' => $fechaDesde !== '' ? $fechaDesde : null,
            'fecha_hasta' => $fechaHasta !== '' ? $fechaHasta : null,
            'consultar' => $consultar ? 1 : null,
        ], static fn ($v) => $v !== null && $v !== '');

        return view('contable.cierre_rendicion_maquinavending.diario_puntoventa', [
            'empresa_query' => $empresaQuery,
            'empresa_id' => $empresaId,
            'fecha_desde' => $fechaDesde,
            'fecha_hasta' => $fechaHasta,
            'consultar' => $consultar,
            'resultado' => $resultado,
            'error_reporte' => $errorReporte,
            'filtrosQuery' => $filtrosQuery,
            'retornoListadoQuery' => $this->resolverRetornoListadoQuery($request),
        ]);
    }

    public function listarDiarioPuntoventa(Request $request, ?string $formato = null)
    {
        can('exportar-cierre-rendicion-maquinavending-contable');

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $empresaId = (int) $request->input('empresa_id', 0);
        $fechaDesde = trim((string) $request->input('fecha_desde', ''));
        $fechaHasta = trim((string) $request->input('fecha_hasta', ''));

        $redirectQuery = array_filter([
            'empresa_id' => $empresaId > 0 ? $empresaId : null,
            'fecha_desde' => $fechaDesde !== '' ? $fechaDesde : null,
            'fecha_hasta' => $fechaHasta !== '' ? $fechaHasta : null,
            'consultar' => 1,
        ], static fn ($v) => $v !== null && $v !== '');

        if ($empresaId <= 0 || $fechaDesde === '' || $fechaHasta === '') {
            return redirect()
                ->route('cierre_rendicion_maquinavending_diario_puntoventa', $redirectQuery)
                ->with('mensaje_error', 'Indique empresa y rango de jornadas para exportar.');
        }

        try {
            $resultado = $this->service->reporteDiarioPuntoventa($empresaId, $fechaDesde, $fechaHasta);
        } catch (\Throwable $e) {
            return redirect()
                ->route('cierre_rendicion_maquinavending_diario_puntoventa', $redirectQuery)
                ->with('mensaje_error', $e->getMessage());
        }

        switch ($formato) {
            case 'PDF':
                $view = \View::make('contable.cierre_rendicion_maquinavending.diario_puntoventa_listado', [
                    'resultado' => $resultado,
                    'esExcel' => false,
                    'matriz' => MaquinavendingDiarioPuntoventaExport::matrizAncha($resultado),
                ])->render();
                $path = storage_path('pdf/listados');
                if (! is_dir($path)) {
                    mkdir($path, 0755, true);
                }
                $nombrePdf = 'vending_diario_puntoventa_contable';

                $pdf = \App::make('dompdf.wrapper');
                $pdf->setPaper('legal', 'landscape');
                $pdf->loadHTML($view)->save($path.'/'.$nombrePdf.'.pdf');

                return response()->download($path.'/'.$nombrePdf.'.pdf');

            case 'EXCEL':
            case 'CSV':
                $mime = $formato === 'CSV' ? Excel::CSV : Excel::XLSX;
                $ext = $formato === 'CSV' ? 'csv' : 'xlsx';

                return \Maatwebsite\Excel\Facades\Excel::download(
                    new MaquinavendingDiarioPuntoventaExport($resultado, $formato === 'CSV'),
                    'vending_diario_puntoventa_contable.'.$ext,
                    $mime,
                );
        }

        return redirect()->route('cierre_rendicion_maquinavending_diario_puntoventa', $redirectQuery);
    }

    /**
     * @return array{0: \Illuminate\Support\Collection, 1: int}
     */
    private function resolverEmpresaConciliacion(Request $request): array
    {
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

        return [$empresaQuery, $empresaId];
    }

    /**
     * @return array<string, mixed>
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

        return QueryRetornoListado::desdeRequest($request, CierreRendicionMaquinavendingListadoFiltros::class);
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

            $msgRmv = ! empty($resultado['venta_codigo'])
                ? ' RMV '.$resultado['venta_codigo'].'.'
                : '';

            return response()->json([
                'ok' => true,
                'mensaje' => 'Cierre contable registrado. Asiento '.$resultado['numeroasiento']
                    .' ('.count($resultado['rendicion_ids']).' rendición/es).'.$msgRmv,
                'asiento_id' => $resultado['asiento_id'],
                'numeroasiento' => $resultado['numeroasiento'],
                'venta_id' => $resultado['venta_id'] ?? null,
                'venta_codigo' => $resultado['venta_codigo'] ?? null,
                'rendicion_ids' => $resultado['rendicion_ids'],
            ]);
        } catch (InvalidArgumentException $e) {
            return response()->json(['ok' => false, 'mensaje' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'mensaje' => $e->getMessage()], 422);
        }
    }

    public function apiPendientesCierre(Request $request): JsonResponse
    {
        can('listar-cierre-rendicion-maquinavending-contable');

        $empresaId = (int) $request->input('empresa_id', 0);
        if ($empresaId <= 0) {
            return response()->json(['ok' => false, 'mensaje' => 'Indique empresa.'], 422);
        }

        $permitidas = $this->empresaRepository->allFiltrado()
            ->pluck('id')
            ->map(static fn ($id) => (int) $id)
            ->all();
        if (! in_array($empresaId, $permitidas, true)) {
            return response()->json(['ok' => false, 'mensaje' => 'Empresa no autorizada.'], 403);
        }

        try {
            $resumen = $this->service->resumenPendientesCierre($empresaId);

            return response()->json(['ok' => true, 'resumen' => $resumen]);
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
        $empresaQuery = $this->empresaRepository->allFiltrado();
        $empresaDefault = $this->resolverEmpresaDefaultId($empresaQuery);
        $asignadas = $this->empresaRepository->traeEmpresasAsignadas();

        $filtros = CierreRendicionMaquinavendingListadoFiltros::resolverDesdeRequest(
            $request,
            $busquedaRuta,
            $empresaDefault,
        );
        $filtros['empresas_asignadas'] = $asignadas;

        $empresaId = (int) ($filtros['empresa_id'] ?? 0);
        if (
            ($filtros['empresa_scope'] ?? 'una') === 'una'
            && $empresaId > 0
            && count($asignadas) >= 1
            && ! in_array($empresaId, $asignadas, true)
        ) {
            $filtros['empresa_id'] = $empresaDefault > 0 ? $empresaDefault : '';
            if ((int) $filtros['empresa_id'] <= 0) {
                $filtros['empresa_scope'] = 'todas';
            }
        }

        return $filtros;
    }

    private function resolverEmpresaDefaultId($empresaQuery): int
    {
        $first = $empresaQuery->first();

        return $first !== null ? (int) $first->id : 0;
    }
}

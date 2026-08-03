<?php

namespace App\Http\Controllers\Contable;

use App\Exports\Contable\CierreRendicionBingoListadoExport;
use App\Http\Controllers\Controller;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Services\Contable\CierreRendicionBingoService;
use App\Support\Contable\CierreRendicionBingoListadoFiltros;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Maatwebsite\Excel\Excel;

class CierreRendicionBingoController extends Controller
{
    public function __construct(
        private readonly CierreRendicionBingoService $service,
        private readonly EmpresaRepositoryInterface $empresaRepository,
    ) {
        $this->middleware('auth');
    }

    public function index(Request $request)
    {
        can('listar-cierre-rendicion-bingo-contable');

        $filtros = $this->resolverFiltrosListado($request);
        $grupos = $this->service->listarAgrupado($filtros, true);

        return view('contable.cierre_rendicion_bingo.index', [
            'grupos' => $grupos,
            'filtros' => $filtros,
            'filtrosQuery' => CierreRendicionBingoListadoFiltros::paraQueryString($filtros),
            'camposFiltro' => CierreRendicionBingoListadoFiltros::CAMPOS,
            'empresa_query' => $this->empresaRepository->allFiltrado(),
        ]);
    }

    public function listar(Request $request, ?string $formato = null, ?string $busqueda = null)
    {
        can('exportar-cierre-rendicion-bingo-contable');

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $filtros = $this->resolverFiltrosListado($request, $busqueda);
        $rendiciones = $this->service->listar($filtros, false);

        switch ($formato) {
            case 'PDF':
                $view = \View::make('contable.cierre_rendicion_bingo.listado', compact('rendiciones'))->render();
                $path = storage_path('pdf/listados');
                $nombrePdf = 'listado_cierre_rendicion_bingo';

                $pdf = \App::make('dompdf.wrapper');
                $pdf->setPaper('legal', 'landscape');
                $pdf->loadHTML($view)->save($path.'/'.$nombrePdf.'.pdf');

                return response()->download($path.'/'.$nombrePdf.'.pdf');

            case 'EXCEL':
            case 'CSV':
                $mime = $formato === 'CSV' ? Excel::CSV : Excel::XLSX;
                $ext = $formato === 'CSV' ? 'csv' : 'xlsx';

                return \Maatwebsite\Excel\Facades\Excel::download(
                    new CierreRendicionBingoListadoExport($rendiciones, $formato === 'CSV'),
                    'cierre_rendicion_bingo.'.$ext,
                    $mime,
                );
        }

        return redirect()->route(
            'cierre_rendicion_bingo_contable',
            CierreRendicionBingoListadoFiltros::paraQueryString($filtros),
        );
    }

    public function apiPendientesCierre(Request $request): JsonResponse
    {
        can('listar-cierre-rendicion-bingo-contable');

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
        can('ejecutar-cierre-rendicion-bingo-contable');

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
        can('ejecutar-cierre-rendicion-bingo-contable');

        $empresaId = (int) $request->input('empresa_id', 0);
        $fechaDesde = trim((string) $request->input('fecha_desde', ''));
        $fechaHasta = trim((string) $request->input('fecha_hasta', ''));

        if ($empresaId <= 0 || $fechaDesde === '' || $fechaHasta === '') {
            return response()->json(['ok' => false, 'mensaje' => 'Indique empresa y rango de fechas.'], 422);
        }

        if (! $request->boolean('confirmar')) {
            return response()->json(['ok' => false, 'mensaje' => 'Debe confirmar el cierre del rango.'], 422);
        }

        try {
            $resultado = $this->service->ejecutarCierreRango($empresaId, $fechaDesde, $fechaHasta);
            $cantOk = count($resultado['ok']);
            $cantErr = count($resultado['errores']);
            $mensaje = 'Cierre contable: '.$cantOk.' jornada(s) cerrada(s).';
            if ($cantErr > 0) {
                $mensaje .= ' '.$cantErr.' con error.';
            }

            return response()->json([
                'ok' => true,
                'mensaje' => $mensaje,
                'resultado' => $resultado,
            ]);
        } catch (InvalidArgumentException $e) {
            return response()->json(['ok' => false, 'mensaje' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'mensaje' => $e->getMessage()], 422);
        }
    }

    public function apiPreviewAsiento(Request $request): JsonResponse
    {
        can('ejecutar-cierre-rendicion-bingo-contable');

        $empresaId = (int) $request->input('empresa_id', 0);
        $fechaDia = trim((string) $request->input('fecha_dia', ''));

        if ($empresaId <= 0 || $fechaDia === '') {
            return response()->json(['ok' => false, 'mensaje' => 'Indique empresa y fecha jornada.'], 422);
        }

        try {
            $preview = $this->service->previewGrupo($empresaId, $fechaDia);

            return response()->json(['ok' => true, 'preview' => $preview]);
        } catch (InvalidArgumentException $e) {
            return response()->json(['ok' => false, 'mensaje' => $e->getMessage()], 422);
        }
    }

    public function apiEjecutarCierre(Request $request): JsonResponse
    {
        can('ejecutar-cierre-rendicion-bingo-contable');

        $empresaId = (int) $request->input('empresa_id', 0);
        $fechaDia = trim((string) $request->input('fecha_dia', ''));

        if ($empresaId <= 0 || $fechaDia === '') {
            return response()->json(['ok' => false, 'mensaje' => 'Indique empresa y fecha jornada.'], 422);
        }

        try {
            $resultado = $this->service->ejecutarCierreGrupo($empresaId, $fechaDia);
            $fbi = $resultado['fbi'];
            $fbiLabel = ($fbi['letra'] ?? 'B')
                .str_pad((string) ($fbi['sucursal'] ?? 0), 4, '0', STR_PAD_LEFT)
                .'-'
                .str_pad((string) ($fbi['nro'] ?? 0), 8, '0', STR_PAD_LEFT);

            return response()->json([
                'ok' => true,
                'mensaje' => 'Cierre contable registrado. Asiento '.$resultado['numeroasiento']
                    .' + FBI '.$fbiLabel.' ('.count($resultado['rendicion_ids']).' rendición/es).',
                'asiento_id' => $resultado['asiento_id'],
                'numeroasiento' => $resultado['numeroasiento'],
                'rendicion_ids' => $resultado['rendicion_ids'],
                'fbi' => $fbi,
            ]);
        } catch (InvalidArgumentException $e) {
            return response()->json(['ok' => false, 'mensaje' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'mensaje' => $e->getMessage()], 422);
        }
    }

    public function apiAnularCierre(Request $request): JsonResponse
    {
        can('anular-cierre-rendicion-bingo-contable');

        $empresaId = (int) $request->input('empresa_id', 0);
        $fechaDia = trim((string) $request->input('fecha_dia', ''));

        if ($empresaId <= 0 || $fechaDia === '') {
            return response()->json(['ok' => false, 'mensaje' => 'Indique empresa y fecha jornada.'], 422);
        }

        if (! $request->boolean('confirmar')) {
            return response()->json([
                'ok' => false,
                'mensaje' => 'Debe confirmar la anulación del cierre contable.',
            ], 422);
        }

        try {
            $this->service->anularCierreGrupo($empresaId, $fechaDia);

            return response()->json([
                'ok' => true,
                'mensaje' => 'Cierre contable anulado. Se eliminaron los asientos en ERP y ctamov.',
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

        $filtros = CierreRendicionBingoListadoFiltros::resolverDesdeRequest(
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
            $filtros['empresa_id'] = $empresaDefault > 0 ? $empresaDefault : 0;
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

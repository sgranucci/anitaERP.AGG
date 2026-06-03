<?php

namespace App\Http\Controllers\Caja;

use App\Http\Controllers\Controller;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Services\Caja\WaitryCierreJornadaService;
use App\Services\Ventas\Gastronomia\GastronomiaCierreJornadaProcesoService;
use App\Services\Ventas\Gastronomia\GastronomiaJornadaService;
use App\Support\Caja\RendicionGastronomiaPdfPermiso;
use App\Support\Ventas\Gastronomia\CierreJornadaProcesoConfigSupport;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Throwable;

class WaitryCierreJornadaController extends Controller
{
    public function __construct(
        private readonly WaitryCierreJornadaService $cierreJornadaService,
        private readonly GastronomiaCierreJornadaProcesoService $procesoService,
        private readonly EmpresaRepositoryInterface $empresaRepository,
    ) {
    }

    public function index(Request $request)
    {
        can('listar-waitry-cierre-jornada-caja');

        $empresas = $this->empresaRepository->allFiltrado();
        $empresaId = (int) $request->input('empresa_id', $empresas->first()->id ?? 0);
        $fechaJornada = (string) $request->input('fecha_jornada', now()->format('Y-m-d'));

        $payload = null;
        $error = null;

        if ($request->has('empresa_id') && $request->has('fecha_jornada') && $empresaId > 0) {
            try {
                $payload = $this->cierreJornadaService->conciliar($empresaId, $fechaJornada);
                if (! ($payload['ok'] ?? false)) {
                    $error = $payload['error'] ?? 'No se pudo conciliar la jornada.';
                }
            } catch (InvalidArgumentException $e) {
                $error = $e->getMessage();
            }
        }

        $empresaNombre = $empresas->firstWhere('id', $empresaId)?->nombre ?? '';

        return view('caja.waitry_cierre_jornada.index', [
            'empresas' => $empresas,
            'empresa_id' => $empresaId,
            'empresa_nombre' => $empresaNombre,
            'fecha_jornada' => $fechaJornada,
            'payload' => $payload,
            'error' => $error,
            'consultado' => $request->has('empresa_id') && $request->has('fecha_jornada'),
            'proceso_habilitado' => $this->procesoService->habilitado(),
            'puede_proceso_cierre' => can('proceso-cierre-jornada-waitry-caja', false),
            'config_contable' => $empresaId > 0
                ? CierreJornadaProcesoConfigSupport::paraEmpresaConDetalle($empresaId)
                : [],
            'url_movimientos_proceso_base' => str_replace(
                '__GRUPO__',
                '',
                route('waitry_cierre_jornada_api_proceso_movimientos', ['grupo' => '__GRUPO__']),
            ),
        ]);
    }

    public function apiProcesoAnalizar(Request $request)
    {
        $this->canProcesoCierre();

        $empresaId = (int) $request->input('empresa_id', 0);
        $fechaJornada = (string) $request->input('fecha_jornada', '');

        try {
            @set_time_limit(180);

            return response()->json(
                $this->procesoService->analizarPorEmpresaYFecha($empresaId, $fechaJornada),
            );
        } catch (InvalidArgumentException $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            return response()->json([
                'ok' => false,
                'error' => GastronomiaJornadaService::mensajeDesdeExcepcion($e),
            ], 422);
        }
    }

    public function apiProcesoRecalcular(Request $request)
    {
        $this->canProcesoCierre();

        $request->validate([
            'empresa_id' => 'required|integer|min:1',
            'fecha_jornada' => 'required|date',
            'porcentaje' => 'required|numeric|min:0|max:100',
        ]);

        try {
            @set_time_limit(180);

            return response()->json($this->procesoService->recalcularPorEmpresaYFecha(
                (int) $request->input('empresa_id'),
                (string) $request->input('fecha_jornada'),
                (float) $request->input('porcentaje'),
            ));
        } catch (InvalidArgumentException $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            return response()->json([
                'ok' => false,
                'error' => GastronomiaJornadaService::mensajeDesdeExcepcion($e),
            ], 422);
        }
    }

    public function apiProcesoMovimientosGrupo(Request $request, string $grupo)
    {
        $this->canProcesoCierre();

        $empresaId = (int) $request->input('empresa_id', 0);
        $fechaJornada = (string) $request->input('fecha_jornada', '');
        $pagina = max(1, (int) $request->input('pagina', 1));
        $porPagina = max(10, min(200, (int) $request->input('por_pagina', 50)));

        try {
            return response()->json($this->procesoService->movimientosGrupoPorEmpresaYFecha(
                $empresaId,
                $fechaJornada,
                $grupo,
                $pagina,
                $porPagina,
            ));
        } catch (InvalidArgumentException $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 422);
        }
    }

    public function apiProcesoConfig(int $empresaId)
    {
        $this->canProcesoCierre();

        if ($empresaId <= 0) {
            return response()->json(['ok' => false, 'error' => 'Empresa inválida.'], 422);
        }

        $cfg = CierreJornadaProcesoConfigSupport::paraEmpresaConDetalle($empresaId);

        return response()->json([
            'ok' => true,
            'config' => $cfg,
            'faltantes' => CierreJornadaProcesoConfigSupport::faltantes($cfg),
        ]);
    }

    public function apiProcesoGuardarConfig(Request $request, int $empresaId)
    {
        $this->canProcesoCierre();

        if ($empresaId <= 0) {
            return response()->json(['ok' => false, 'error' => 'Empresa inválida.'], 422);
        }

        try {
            return response()->json(
                $this->procesoService->guardarConfig($empresaId, $request->all()),
            );
        } catch (InvalidArgumentException|\RuntimeException $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 422);
        }
    }

    private function canProcesoCierre(): void
    {
        can('proceso-cierre-jornada-waitry-caja');
    }

    public function listar(Request $request, ?string $formato = null)
    {
        can('listar-waitry-cierre-jornada-caja');

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $empresaId = (int) $request->input('empresa_id', 0);
        $fechaJornada = (string) $request->input('fecha_jornada', now()->format('Y-m-d'));
        $empresas = $this->empresaRepository->allFiltrado();
        $empresaNombre = $empresas->firstWhere('id', $empresaId)?->nombre ?? '';

        try {
            $payload = $this->cierreJornadaService->conciliar($empresaId, $fechaJornada);
        } catch (InvalidArgumentException $e) {
            return redirect()
                ->route('waitry_cierre_jornada', [
                    'empresa_id' => $empresaId,
                    'fecha_jornada' => $fechaJornada,
                ])
                ->with('mensaje', $e->getMessage());
        }

        if (! ($payload['ok'] ?? false)) {
            return redirect()
                ->route('waitry_cierre_jornada', [
                    'empresa_id' => $empresaId,
                    'fecha_jornada' => $fechaJornada,
                ])
                ->with('mensaje', $payload['error'] ?? 'No se pudo exportar el cierre.');
        }

        $filas = $payload['filas'] ?? [];
        $resumen = $payload['resumen'] ?? [];
        $titulo = 'Cierre jornada Waitry — '.$empresaNombre.' — '.($payload['fecha_jornada_fmt'] ?? $fechaJornada);

        switch ($formato) {
            case 'PDF':
                if (! RendicionGastronomiaPdfPermiso::puedeVerPdfWaitry()) {
                    abort(403, 'No tiene permiso para exportar el PDF de cierre Waitry.');
                }
                $view = \View::make('caja.waitry_cierre_jornada.listado', compact(
                    'filas',
                    'resumen',
                    'titulo',
                    'empresaNombre',
                    'payload',
                ))->render();
                $path = storage_path('pdf/listados');
                $nombrePdf = 'waitry_cierre_jornada_'.$empresaId.'_'.$fechaJornada;

                $pdf = \App::make('dompdf.wrapper');
                $pdf->setPaper('legal', 'landscape');
                $pdf->loadHTML($view)->save($path.'/'.$nombrePdf.'.pdf');

                return response()->download($path.'/'.$nombrePdf.'.pdf');

            case 'EXCEL':
            case 'CSV':
                $mime = $formato === 'CSV'
                    ? \Maatwebsite\Excel\Excel::CSV
                    : \Maatwebsite\Excel\Excel::XLSX;
                $ext = $formato === 'CSV' ? 'csv' : 'xlsx';

                return \Maatwebsite\Excel\Facades\Excel::download(
                    new \App\Exports\Caja\WaitryCierreJornadaExport($filas, $resumen, $titulo),
                    'waitry_cierre_jornada_'.$empresaId.'_'.$fechaJornada.'.'.$ext,
                    $mime,
                );
        }

        return redirect()->route('waitry_cierre_jornada', [
            'empresa_id' => $empresaId,
            'fecha_jornada' => $fechaJornada,
        ]);
    }
}

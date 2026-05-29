<?php

namespace App\Http\Controllers\Caja;

use App\Http\Controllers\Controller;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Services\Caja\WaitryCierreJornadaService;
use Illuminate\Http\Request;
use InvalidArgumentException;

class WaitryCierreJornadaController extends Controller
{
    public function __construct(
        private readonly WaitryCierreJornadaService $cierreJornadaService,
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
        ]);
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

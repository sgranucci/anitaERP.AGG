<?php

namespace App\Http\Controllers\Contable;

use App\Exports\Contable\ConciliacionBancariaAgingExport;
use App\Http\Controllers\Controller;
use App\Models\Caja\Cuentacaja;
use App\Models\Configuracion\Empresa;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Services\Contable\ConciliacionBancariaService;
use App\Support\Contable\ConciliacionBancaria\ConciliacionBancariaForecastSupport;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class ConciliacionBancariaAgingController extends Controller
{
    public function __construct(
        private readonly ConciliacionBancariaService $service,
        private readonly EmpresaRepositoryInterface $empresaRepository,
    ) {
        $this->middleware('auth');
    }

    public function index(Request $request)
    {
        can('listar-conciliacion-bancaria-aging');

        $ctx = $this->resolverContexto($request);
        $resultado = null;
        $forecast = null;
        $errores = [];

        if ($ctx['consultar']) {
            if ($ctx['empresa_id'] <= 0 || $ctx['cuentacaja_id'] <= 0) {
                $errores[] = 'Elegí empresa y cuenta de caja.';
            } elseif (! $this->empresaRepository->empresaIdPermitida($ctx['empresa_id'])) {
                abort(403);
            } else {
                try {
                    $resultado = $this->service->agingPendientes(
                        $ctx['empresa_id'],
                        $ctx['cuentacaja_id'],
                        $ctx['mes'],
                        $ctx['anio'],
                    );
                    $forecast = ConciliacionBancariaForecastSupport::desdeAging(
                        $this->service,
                        $ctx['empresa_id'],
                        $ctx['cuentacaja_id'],
                        $ctx['mes'],
                        $ctx['anio'],
                    );
                } catch (\Throwable $e) {
                    $errores[] = $e->getMessage();
                }
            }
        }

        return view('contable.conciliacion_bancaria.aging', [
            'empresa_query' => $ctx['empresa_query'],
            'empresa_id' => $ctx['empresa_id'],
            'cuentacaja_id' => $ctx['cuentacaja_id'],
            'cuentacaja' => $ctx['cuentacaja'],
            'mes' => $ctx['mes'],
            'anio' => $ctx['anio'],
            'resultado' => $resultado,
            'forecast' => $forecast,
            'errores' => $errores,
            'filtrosQuery' => $ctx['filtrosQuery'],
        ]);
    }

    public function exportar(Request $request, string $formato)
    {
        can('listar-conciliacion-bancaria-aging');
        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $ctx = $this->resolverContexto($request);
        if ($ctx['empresa_id'] <= 0 || $ctx['cuentacaja_id'] <= 0) {
            return redirect()->route('conciliacion_bancaria_aging', $ctx['filtrosQuery']);
        }
        if (! $this->empresaRepository->empresaIdPermitida($ctx['empresa_id'])) {
            abort(403);
        }

        try {
            $resultado = $this->service->agingPendientes(
                $ctx['empresa_id'],
                $ctx['cuentacaja_id'],
                $ctx['mes'],
                $ctx['anio'],
            );
        } catch (\Throwable $e) {
            return redirect()
                ->route('conciliacion_bancaria_aging', $ctx['filtrosQuery'])
                ->with('mensaje_error', $e->getMessage());
        }

        $meta = [
            'mes' => $ctx['mes'],
            'anio' => $ctx['anio'],
            'empresa_nombre' => (string) (Empresa::query()->where('id', $ctx['empresa_id'])->value('nombre') ?? ''),
            'cuentacaja_label' => trim(($ctx['cuentacaja']->codigo ?? '').' '.($ctx['cuentacaja']->nombre ?? '')),
        ];
        $formato = strtoupper(trim($formato));
        $base = 'aging_conciliacion_'.sprintf('%02d_%d', $ctx['mes'], $ctx['anio']);

        return match ($formato) {
            'PDF' => $this->exportarPdf($resultado, $meta, $base),
            'EXCEL' => (new ConciliacionBancariaAgingExport)
                ->parametros($resultado, $meta)
                ->download($base.'.xlsx'),
            'CSV' => (new ConciliacionBancariaAgingExport)
                ->parametros($resultado, $meta)
                ->download($base.'.csv', Excel::CSV),
            default => redirect()->route('conciliacion_bancaria_aging', $ctx['filtrosQuery']),
        };
    }

    /**
     * @param  array<string, mixed>  $resultado
     * @param  array<string, mixed>  $meta
     */
    private function exportarPdf(array $resultado, array $meta, string $base)
    {
        $pdf = Pdf::loadView('contable.conciliacion_bancaria.aging_listado', [
            'resultado' => $resultado,
            'meta' => $meta,
        ])->setPaper('legal', 'landscape');

        $dir = storage_path('pdf/listados');
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $path = $dir.'/'.$base.'.pdf';
        $pdf->save($path);

        return response()->file($path);
    }

    /**
     * @return array{
     *   empresa_query: mixed,
     *   empresa_id: int,
     *   cuentacaja_id: int,
     *   cuentacaja: ?Cuentacaja,
     *   mes: int,
     *   anio: int,
     *   consultar: bool,
     *   filtrosQuery: array<string, mixed>
     * }
     */
    private function resolverContexto(Request $request): array
    {
        $empresaQuery = $this->empresaRepository->allFiltrado();
        $empresaId = (int) $request->input('empresa_id', $empresaQuery->count() === 1 ? (int) $empresaQuery->first()->id : 0);
        $cuentacajaId = (int) $request->input('cuentacaja_id', 0);
        $mes = max(1, min(12, (int) $request->input('mes', (int) date('n'))));
        $anio = max(2000, (int) $request->input('anio', (int) date('Y')));

        return [
            'empresa_query' => $empresaQuery,
            'empresa_id' => $empresaId,
            'cuentacaja_id' => $cuentacajaId,
            'cuentacaja' => $cuentacajaId > 0 ? Cuentacaja::query()->find($cuentacajaId) : null,
            'mes' => $mes,
            'anio' => $anio,
            'consultar' => $request->boolean('consultar'),
            'filtrosQuery' => [
                'empresa_id' => $empresaId,
                'cuentacaja_id' => $cuentacajaId,
                'mes' => $mes,
                'anio' => $anio,
                'consultar' => 1,
            ],
        ];
    }
}

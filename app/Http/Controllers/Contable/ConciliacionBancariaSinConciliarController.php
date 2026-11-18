<?php

namespace App\Http\Controllers\Contable;

use App\Exports\Contable\ConciliacionBancariaSinConciliarExport;
use App\Http\Controllers\Controller;
use App\Models\Caja\Cuentacaja;
use App\Models\Configuracion\Empresa;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Services\Contable\ConciliacionBancariaService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Facades\Excel;

class ConciliacionBancariaSinConciliarController extends Controller
{
    public function __construct(
        private readonly ConciliacionBancariaService $service,
        private readonly EmpresaRepositoryInterface $empresaRepository,
    ) {
        $this->middleware('auth');
    }

    public function index(Request $request)
    {
        $this->autorizar();

        $ctx = $this->resolverContexto($request);
        $reporte = null;
        $errores = [];

        if ($ctx['consultar']) {
            if ($ctx['empresa_id'] <= 0 || $ctx['cuentacaja_id'] <= 0) {
                $errores[] = 'Elegí empresa y cuenta de caja.';
            } elseif (! $this->empresaRepository->empresaIdPermitida($ctx['empresa_id'])) {
                abort(403);
            } else {
                try {
                    $reporte = $this->service->movimientosSinConciliar(
                        $ctx['empresa_id'],
                        $ctx['cuentacaja_id'],
                        $ctx['mes'],
                        $ctx['anio'],
                        (int) Auth::id(),
                        $request->boolean('recalcular'),
                    );
                } catch (\Throwable $e) {
                    $errores[] = $e->getMessage();
                }
            }
        }

        return view('contable.conciliacion_bancaria.sin_conciliar', [
            'empresa_query' => $ctx['empresa_query'],
            'empresa_id' => $ctx['empresa_id'],
            'cuentacaja_id' => $ctx['cuentacaja_id'],
            'cuentacaja' => $ctx['cuentacaja'],
            'mes' => $ctx['mes'],
            'anio' => $ctx['anio'],
            'reporte' => $reporte,
            'errores' => $errores,
            'filtrosQuery' => $ctx['filtrosQuery'],
        ]);
    }

    public function exportar(Request $request, string $formato)
    {
        $this->autorizar();
        ini_set('memory_limit', (string) config('conciliacion_bancaria.memory_limit', '2048M'));
        ini_set('max_execution_time', '0');

        $ctx = $this->resolverContexto($request);
        if ($ctx['empresa_id'] <= 0 || $ctx['cuentacaja_id'] <= 0) {
            return redirect()->route('conciliacion_bancaria_sin_conciliar', $ctx['filtrosQuery']);
        }
        if (! $this->empresaRepository->empresaIdPermitida($ctx['empresa_id'])) {
            abort(403);
        }

        try {
            $reporte = $this->service->movimientosSinConciliar(
                $ctx['empresa_id'],
                $ctx['cuentacaja_id'],
                $ctx['mes'],
                $ctx['anio'],
                (int) Auth::id(),
                $request->boolean('recalcular'),
            );
        } catch (\Throwable $e) {
            return redirect()
                ->route('conciliacion_bancaria_sin_conciliar', $ctx['filtrosQuery'])
                ->with('mensaje_error', $e->getMessage());
        }

        $meta = array_merge($reporte['meta'] ?? [], [
            'empresa_nombre' => (string) (Empresa::query()->where('id', $ctx['empresa_id'])->value('nombre') ?? ''),
            'cuentacaja_label' => trim(($ctx['cuentacaja']->codigo ?? '').' '.($ctx['cuentacaja']->nombre ?? '')),
            'mes' => $ctx['mes'],
            'anio' => $ctx['anio'],
        ]);
        $formato = strtoupper(trim($formato));
        $codigo = preg_replace('/\W+/', '_', (string) ($ctx['cuentacaja']->codigo ?? 'cuenta')) ?: 'cuenta';
        $base = 'sin_conciliar_'.$codigo.'_'.sprintf('%02d_%d', $ctx['mes'], $ctx['anio']);

        return match ($formato) {
            'PDF' => $this->exportarPdf($reporte, $meta, $base),
            'EXCEL' => (new ConciliacionBancariaSinConciliarExport)
                ->parametros($reporte, $meta)
                ->download($base.'.xlsx'),
            'CSV' => (new ConciliacionBancariaSinConciliarExport)
                ->parametros($reporte, $meta)
                ->download($base.'.csv', Excel::CSV),
            default => redirect()->route('conciliacion_bancaria_sin_conciliar', $ctx['filtrosQuery']),
        };
    }

    /**
     * @param  array<string, mixed>  $reporte
     * @param  array<string, mixed>  $meta
     */
    private function exportarPdf(array $reporte, array $meta, string $base)
    {
        $pdf = Pdf::loadView('contable.conciliacion_bancaria.sin_conciliar_listado', [
            'reporte' => $reporte,
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

    private function autorizar(): void
    {
        if (can('listar-conciliacion-bancaria-sin-conciliar', false)
            || can('listar-conciliacion-bancaria', false)
            || can('ejecutar-conciliacion-bancaria', false)
            || can('listar-conciliacion-bancaria-aging', false)
        ) {
            return;
        }
        can('listar-conciliacion-bancaria-sin-conciliar');
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

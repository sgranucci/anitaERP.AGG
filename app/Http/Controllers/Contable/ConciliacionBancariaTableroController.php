<?php

namespace App\Http\Controllers\Contable;

use App\Exports\Contable\ConciliacionBancariaTableroExport;
use App\Http\Controllers\Controller;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Services\Contable\ConciliacionBancariaService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class ConciliacionBancariaTableroController extends Controller
{
    public function __construct(
        private readonly ConciliacionBancariaService $service,
        private readonly EmpresaRepositoryInterface $empresaRepository,
    ) {
        $this->middleware('auth');
    }

    public function index(Request $request)
    {
        can('listar-conciliacion-bancaria-tablero');

        $ctx = $this->resolverContexto($request);

        return view('contable.conciliacion_bancaria.tablero', [
            'empresa_query' => $ctx['empresa_query'],
            'empresa_ids' => $ctx['empresa_ids'],
            'mes' => $ctx['mes'],
            'anio' => $ctx['anio'],
            'filas' => $ctx['filas'],
            'resumen' => $ctx['resumen'],
            'filtrosQuery' => $ctx['filtrosQuery'],
        ]);
    }

    public function exportar(Request $request, string $formato)
    {
        can('listar-conciliacion-bancaria-tablero');
        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $ctx = $this->resolverContexto($request);
        $formato = strtoupper(trim($formato));
        $meta = ['mes' => $ctx['mes'], 'anio' => $ctx['anio']];
        $base = 'tablero_conciliacion_'.sprintf('%02d_%d', $ctx['mes'], $ctx['anio']);

        return match ($formato) {
            'PDF' => $this->exportarPdf($ctx['filas'], $ctx['resumen'], $meta, $base),
            'EXCEL' => (new ConciliacionBancariaTableroExport)
                ->parametros($ctx['filas'], $ctx['resumen'], $meta)
                ->download($base.'.xlsx'),
            'CSV' => (new ConciliacionBancariaTableroExport)
                ->parametros($ctx['filas'], $ctx['resumen'], $meta)
                ->download($base.'.csv', Excel::CSV),
            default => redirect()->route('conciliacion_bancaria_tablero', $ctx['filtrosQuery']),
        };
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     * @param  array<string, mixed>  $resumen
     * @param  array<string, mixed>  $meta
     */
    private function exportarPdf(array $filas, array $resumen, array $meta, string $base)
    {
        $pdf = Pdf::loadView('contable.conciliacion_bancaria.tablero_listado', [
            'filas' => $filas,
            'resumen' => $resumen,
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
     *   empresa_ids: list<int>,
     *   mes: int,
     *   anio: int,
     *   filas: list<array<string, mixed>>,
     *   resumen: array<string, int>,
     *   filtrosQuery: array<string, mixed>
     * }
     */
    private function resolverContexto(Request $request): array
    {
        $empresaQuery = $this->empresaRepository->allFiltrado();
        $mes = max(1, min(12, (int) $request->input('mes', (int) date('n'))));
        $anio = max(2000, (int) $request->input('anio', (int) date('Y')));
        $empresaIds = $request->input('empresa_ids');
        if (! is_array($empresaIds) || $empresaIds === []) {
            $empresaIds = $empresaQuery->pluck('id')->map(fn ($id) => (int) $id)->all();
        } else {
            $empresaIds = array_values(array_filter(array_map('intval', $empresaIds), fn ($id) => $id > 0));
            $empresaIds = array_values(array_filter(
                $empresaIds,
                fn (int $id) => $this->empresaRepository->empresaIdPermitida($id)
            ));
        }

        $filas = $empresaIds !== []
            ? $this->service->tableroAvance($empresaIds, $mes, $anio)
            : [];

        $pcts = array_values(array_filter(
            array_map(fn ($f) => $f['pct_conciliado'] ?? null, $filas),
            fn ($v) => $v !== null
        ));
        $resumen = [
            'cuentas' => count($filas),
            'cerradas' => count(array_filter($filas, fn ($f) => ($f['estado'] ?? '') === 'cerrado')),
            'aprobadas' => count(array_filter($filas, fn ($f) => ($f['estado'] ?? '') === 'aprobado')),
            'pares' => array_sum(array_column($filas, 'pares_mes')),
            'grupos' => array_sum(array_column($filas, 'grupos_mes')),
            'pct_promedio' => $pcts !== []
                ? number_format(array_sum($pcts) / count($pcts), 1, ',', '.').' %'
                : '—',
        ];

        return [
            'empresa_query' => $empresaQuery,
            'empresa_ids' => $empresaIds,
            'mes' => $mes,
            'anio' => $anio,
            'filas' => $filas,
            'resumen' => $resumen,
            'filtrosQuery' => [
                'mes' => $mes,
                'anio' => $anio,
                'empresa_ids' => $empresaIds,
                'consultar' => 1,
            ],
        ];
    }
}

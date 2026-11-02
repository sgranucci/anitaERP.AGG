<?php

declare(strict_types=1);

namespace App\Http\Controllers\Ventas;

use App\Exports\Ventas\TransferenciaCrownControlExport;
use App\Http\Controllers\Controller;
use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\Reportes\DompdfListadoSupport;
use App\Support\Ventas\TransferenciaCrown\TransferenciaCrownControlSupport;
use App\Support\Ventas\TransferenciaCrown\TransferenciaCrownResumenImportSupport;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Control de incongruencias y resumen de lo transferido (Crown).
 */
class TransferenciaCrownControlController extends Controller
{
    private const PERMISO = 'controlar-transferencia-ventas-crown';

    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index(Request $request): View
    {
        $this->asegurar();
        $filtros = $this->filtrosDesdeRequest($request);
        $resultado = null;
        $filasPagina = null;
        $resumenCarga = null;

        if ((int) $request->input('consultar', 0) === 1) {
            ini_set('memory_limit', '512M');
            ini_set('max_execution_time', '180');
            $resultado = TransferenciaCrownControlSupport::analizar($filtros);
            $page = max(1, (int) $request->input('page', 1));
            $perPage = 50;
            $slice = array_slice($resultado['filas'], ($page - 1) * $perPage, $perPage);
            $filasPagina = new LengthAwarePaginator(
                $slice,
                count($resultado['filas']),
                $perPage,
                $page,
                ['path' => $request->url(), 'query' => $request->query()]
            );

            if (in_array((string) ($filtros['alcance'] ?? 'todo'), ['todo', 'importacion'], true)) {
                $resumenCarga = TransferenciaCrownResumenImportSupport::conUrlsMayor(
                    TransferenciaCrownResumenImportSupport::armarDesdePeriodo(
                        (int) ($filtros['empresa_id'] ?? 1),
                        (string) ($filtros['desde'] ?? ''),
                        (string) ($filtros['hasta'] ?? ''),
                        (string) ($filtros['origen'] ?? 'todos'),
                    )
                );
            }
        }

        return view('ventas.transferencia_control.index', [
            'filtros' => $filtros,
            'filtrosQuery' => $this->paraQueryString($filtros),
            'resultado' => $resultado,
            'filasPagina' => $filasPagina,
            'resumenCarga' => $resumenCarga,
            'tipos' => TransferenciaCrownControlSupport::TIPOS,
        ]);
    }

    public function exportar(Request $request, string $formato): BinaryFileResponse|StreamedResponse|\Illuminate\Http\RedirectResponse
    {
        $this->asegurar();
        ini_set('memory_limit', '512M');
        ini_set('max_execution_time', '180');

        $filtros = $this->filtrosDesdeRequest($request);
        $resultado = TransferenciaCrownControlSupport::analizar($filtros);
        $formato = strtoupper(trim($formato));

        if (! in_array($formato, ['PDF', 'EXCEL', 'CSV'], true)) {
            return redirect()->route('transferencia_control_crown', $this->paraQueryString($filtros) + ['consultar' => 1]);
        }

        if ($formato === 'PDF') {
            $html = view('ventas.transferencia_control.listado', [
                'resultado' => $resultado,
                'filtros' => $filtros,
                'tipos' => TransferenciaCrownControlSupport::TIPOS,
                'titulo' => 'Control transferencias Crown',
            ])->render();
            $ruta = storage_path('pdf/listados/control_transferencia_crown.pdf');
            DompdfListadoSupport::guardarLegalLandscape($html, $ruta);

            return response()->download($ruta)->deleteFileAfterSend(true);
        }

        $export = (new TransferenciaCrownControlExport())->parametros($filtros, $resultado);
        $nombre = 'control_transferencia_crown_'.date('Ymd_His');

        if ($formato === 'CSV') {
            return Excel::download($export, $nombre.'.csv', \Maatwebsite\Excel\Excel::CSV);
        }

        return Excel::download($export, $nombre.'.xlsx');
    }

    /**
     * @return array<string, mixed>
     */
    private function filtrosDesdeRequest(Request $request): array
    {
        $hoy = now()->format('Y-m-d');
        $desde = trim((string) $request->input('desde', now()->startOfMonth()->format('Y-m-d')));
        $hasta = trim((string) $request->input('hasta', $hoy));
        $alcance = (string) $request->input('alcance', 'todo');
        if (! in_array($alcance, ['todo', 'config', 'importacion'], true)) {
            $alcance = 'todo';
        }
        $origen = (string) $request->input('origen', 'todos');
        if (! in_array($origen, ['todos', 'gastro', 'arca'], true)) {
            $origen = 'todos';
        }

        return [
            'empresa_id' => 1,
            'desde' => $desde !== '' ? $desde : now()->startOfMonth()->format('Y-m-d'),
            'hasta' => $hasta !== '' ? $hasta : $hoy,
            'alcance' => $alcance,
            'origen' => $origen,
            'consultar' => (int) $request->input('consultar', 0),
        ];
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>
     */
    private function paraQueryString(array $filtros): array
    {
        return [
            'desde' => $filtros['desde'] ?? null,
            'hasta' => $filtros['hasta'] ?? null,
            'alcance' => $filtros['alcance'] ?? 'todo',
            'origen' => $filtros['origen'] ?? 'todos',
            'consultar' => 1,
        ];
    }

    private function asegurar(): void
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            abort(404);
        }
        can(self::PERMISO);
    }
}

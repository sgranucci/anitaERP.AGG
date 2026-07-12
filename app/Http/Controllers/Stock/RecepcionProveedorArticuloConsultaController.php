<?php

namespace App\Http\Controllers\Stock;

use App\Exports\Stock\RecepcionProveedorArticuloConsultaExport;
use App\Http\Controllers\Controller;
use App\Support\Stock\RecepcionProveedorArticuloConsultaSupport;
use Illuminate\Http\Request;

class RecepcionProveedorArticuloConsultaController extends Controller
{
    public function index(Request $request)
    {
        $this->assertPermisoConsulta();

        $articuloId = (int) $request->query('articulo_id', 0);

        try {
            $contexto = RecepcionProveedorArticuloConsultaSupport::validarContexto($articuloId);
        } catch (\Throwable $e) {
            return redirect($this->resolverUrlVolver($request))->with('mensaje', $e->getMessage());
        }

        $queryParams = $this->queryParamsDesdeRequest($request, $articuloId);

        $filas = RecepcionProveedorArticuloConsultaSupport::query($contexto['ids_filtro'])
            ->paginate(50)
            ->appends($queryParams);

        $filas->getCollection()->transform(
            fn ($row) => RecepcionProveedorArticuloConsultaSupport::enriquecerFila($row)
        );

        return view('stock.recepcion_proveedor.articulo_consulta.index', [
            'filas' => $filas,
            'contexto' => $contexto,
            'queryParams' => $queryParams,
            'volverUrl' => $this->resolverUrlVolver($request),
        ]);
    }

    public function listar(Request $request, ?string $formato = null)
    {
        $this->assertPermisoConsulta();

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $articuloId = (int) $request->query('articulo_id', 0);

        try {
            $contexto = RecepcionProveedorArticuloConsultaSupport::validarContexto($articuloId);
        } catch (\Throwable $e) {
            return redirect($this->resolverUrlVolver($request))->with('mensaje', $e->getMessage());
        }

        $rows = RecepcionProveedorArticuloConsultaSupport::query($contexto['ids_filtro'])
            ->get()
            ->map(fn ($row) => RecepcionProveedorArticuloConsultaSupport::enriquecerFila($row));

        $sku = preg_replace('/[^\w\-]+/', '_', (string) ($contexto['articulo']['sku'] ?? 'articulo'));
        $baseNombre = 'recepciones_'.$sku;

        switch (strtoupper((string) $formato)) {
            case 'PDF':
                $view = \View::make('stock.recepcion_proveedor.articulo_consulta.listado', [
                    'contexto' => $contexto,
                    'filas' => $rows,
                ])->render();
                $path = storage_path('pdf/listados');
                $nombrePdf = $baseNombre;

                $pdf = \App::make('dompdf.wrapper');
                $pdf->setPaper('legal', 'landscape');
                $pdf->loadHTML($view)->save($path.'/'.$nombrePdf.'.pdf');

                return response()->download($path.'/'.$nombrePdf.'.pdf');

            case 'EXCEL':
                return (new RecepcionProveedorArticuloConsultaExport)
                    ->parametros($contexto, $rows)
                    ->download($baseNombre.'.xlsx');

            case 'CSV':
                return (new RecepcionProveedorArticuloConsultaExport)
                    ->parametros($contexto, $rows)
                    ->download($baseNombre.'.csv', \Maatwebsite\Excel\Excel::CSV);
        }

        return redirect()->route(
            'recepcion_proveedor_consulta_articulo',
            $this->queryParamsDesdeRequest($request, $articuloId)
        );
    }

    private function assertPermisoConsulta(): void
    {
        if (! RecepcionProveedorArticuloConsultaSupport::puedeConsultar()) {
            abort(403, 'No tiene permisos para consultar recepciones de proveedor.');
        }
    }

    private function resolverUrlVolver(Request $request): string
    {
        $volver = trim((string) $request->query('volver', ''));
        if ($volver !== '' && str_starts_with($volver, '/')) {
            return $volver;
        }

        $articuloId = (int) $request->query('articulo_id', 0);
        if ($articuloId > 0 && can('editar-articulos', false)) {
            return route('editar_articulo', ['id' => $articuloId]);
        }

        return route('articulo');
    }

    /**
     * @return array<string, int|string>
     */
    private function queryParamsDesdeRequest(Request $request, int $articuloId): array
    {
        $params = [
            'articulo_id' => $articuloId,
        ];

        if ($request->filled('volver')) {
            $params['volver'] = (string) $request->query('volver');
        }
        if ($request->input('vista') === 'consulta') {
            $params['vista'] = 'consulta';
        }

        return $params;
    }
}

<?php

namespace App\Http\Controllers\Stock;

use App\Exports\Stock\RecuentoMovimientosArticuloExport;
use App\Http\Controllers\Controller;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Support\Stock\MovimientosArticuloDepositoSupport;
use App\Support\Stock\RecuentoMovimientosArticuloSupport;
use Illuminate\Http\Request;

class RecuentoMovimientosArticuloController extends Controller
{
    public function index(Request $request)
    {
        $this->assertPermisoConsulta();

        $articuloId = (int) $request->query('articulo_id', 0);
        $depositoId = RecuentoMovimientosArticuloSupport::resolverDepositoIdDesdeRequest(
            $request->query('deposito_id')
        );
        $empresaId = $this->resolverEmpresaIdFiltrada($request);

        try {
            $contexto = RecuentoMovimientosArticuloSupport::validarContexto($articuloId, $depositoId, $empresaId);
        } catch (\Throwable $e) {
            return redirect($this->resolverUrlVolver($request))->with('mensaje', $e->getMessage());
        }

        $queryParams = $this->queryParamsDesdeRequest($request, $articuloId, $depositoId, $empresaId);
        $modoTodosDepositos = (bool) ($contexto['modo_todos_depositos'] ?? false);

        $movimientos = RecuentoMovimientosArticuloSupport::query($articuloId, $depositoId, $empresaId)
            ->paginate(50)
            ->appends($queryParams);

        $movimientos->getCollection()->transform(
            fn ($row) => RecuentoMovimientosArticuloSupport::enriquecerFila($row, $modoTodosDepositos)
        );

        return view('stock.recuento.movimientos_articulo.index', [
            'movimientos' => $movimientos,
            'contexto' => $contexto,
            'queryParams' => $queryParams,
            'modoTodosDepositos' => $modoTodosDepositos,
            'mostrarEmpresa' => MovimientosArticuloDepositoSupport::mostrarEmpresaEnListados(),
            'volverUrl' => $this->resolverUrlVolver($request),
            'empresaIdFiltrada' => $empresaId,
        ]);
    }

    public function listar(Request $request, ?string $formato = null)
    {
        $this->assertPermisoConsulta();

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $articuloId = (int) $request->query('articulo_id', 0);
        $depositoId = RecuentoMovimientosArticuloSupport::resolverDepositoIdDesdeRequest(
            $request->query('deposito_id')
        );
        $empresaId = $this->resolverEmpresaIdFiltrada($request);

        try {
            $contexto = RecuentoMovimientosArticuloSupport::validarContexto($articuloId, $depositoId, $empresaId);
        } catch (\Throwable $e) {
            return redirect($this->resolverUrlVolver($request))->with('mensaje', $e->getMessage());
        }

        $modoTodosDepositos = (bool) ($contexto['modo_todos_depositos'] ?? false);

        $rows = RecuentoMovimientosArticuloSupport::query($articuloId, $depositoId, $empresaId)
            ->get()
            ->map(fn ($row) => RecuentoMovimientosArticuloSupport::enriquecerFila($row, $modoTodosDepositos));

        $sku = preg_replace('/[^\w\-]+/', '_', (string) ($contexto['articulo']['sku'] ?? 'articulo'));
        $baseNombre = $modoTodosDepositos
            ? 'movimientos_'.$sku.'_todos_depositos'
            : 'movimientos_'.$sku.'_dep_'.$depositoId;

        switch (strtoupper((string) $formato)) {
            case 'PDF':
                $view = \View::make('stock.recuento.movimientos_articulo.listado', [
                    'contexto' => $contexto,
                    'movimientos' => $rows,
                ])->render();
                $path = storage_path('pdf/listados');
                $nombrePdf = $baseNombre;

                $pdf = \App::make('dompdf.wrapper');
                $pdf->setPaper('legal', 'landscape');
                $pdf->loadHTML($view)->save($path.'/'.$nombrePdf.'.pdf');

                return response()->download($path.'/'.$nombrePdf.'.pdf');

            case 'EXCEL':
                return (new RecuentoMovimientosArticuloExport)
                    ->parametros($contexto, $rows)
                    ->download($baseNombre.'.xlsx');

            case 'CSV':
                return (new RecuentoMovimientosArticuloExport)
                    ->parametros($contexto, $rows)
                    ->download($baseNombre.'.csv', \Maatwebsite\Excel\Excel::CSV);
        }

        return redirect()->route('recuento_movimientos_articulo', $this->queryParamsDesdeRequest($request, $articuloId, $depositoId, $empresaId));
    }

    private function resolverEmpresaIdFiltrada(Request $request): ?int
    {
        $empresaId = (int) $request->query('empresa_id', 0);
        if ($empresaId <= 0) {
            return null;
        }

        if (! app(EmpresaRepositoryInterface::class)->empresaIdPermitida($empresaId)) {
            abort(403, 'Empresa no autorizada.');
        }

        return $empresaId;
    }

    private function assertPermisoConsulta(): void
    {
        if (! MovimientosArticuloDepositoSupport::puedeConsultar()) {
            abort(403, 'No tiene permisos para consultar movimientos de stock.');
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

        return route('recuento');
    }

    /**
     * @return array<string, int|string>
     */
    private function queryParamsDesdeRequest(Request $request, int $articuloId, int $depositoId, ?int $empresaId = null): array
    {
        $params = [
            'articulo_id' => $articuloId,
            'deposito_id' => $depositoId,
        ];

        if ($empresaId !== null && $empresaId > 0) {
            $params['empresa_id'] = $empresaId;
        }

        if ($request->filled('volver')) {
            $params['volver'] = (string) $request->query('volver');
        }
        if ($request->input('vista') === 'consulta') {
            $params['vista'] = 'consulta';
        }

        return $params;
    }
}

<?php

namespace App\Http\Controllers\Direccion;

use App\Http\Controllers\Controller;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Services\Direccion\TableroDireccionService;
use App\Support\Direccion\TableroDireccionPeriodo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TableroController extends Controller
{
    public function __construct(
        private EmpresaRepositoryInterface $empresaRepository,
        private TableroDireccionService $tableroService,
    ) {
    }

    public function index(Request $request)
    {
        can('listar-tablero-direccion');

        $empresas = $this->empresaRepository->allFiltrado();
        $idsPermitidos = $empresas->pluck('id')->map(fn ($id) => (int) $id)->all();
        $empresaId = (int) $request->query('empresa_id', 0);
        if ($empresaId > 0 && ! in_array($empresaId, $idsPermitidos, true)) {
            $empresaId = 0;
        }
        $ids = $empresaId > 0 ? [$empresaId] : $idsPermitidos;
        $periodo = TableroDireccionPeriodo::resolver($request);
        $tablero = $this->tableroService->armar($ids, $periodo);

        return view('direccion.tablero.index', [
            'empresas' => $empresas,
            'empresaId' => $empresaId,
            'periodo' => $periodo,
            'tablero' => $tablero,
            'atajos' => $this->atajos(),
        ]);
    }

    public function detalle(Request $request): JsonResponse
    {
        can('listar-tablero-direccion');

        $bloque = (string) $request->query('bloque', '');
        $empresas = $this->empresaRepository->allFiltrado();
        $idsPermitidos = $empresas->pluck('id')->map(fn ($id) => (int) $id)->all();
        $empresaId = (int) $request->query('empresa_id', 0);
        if ($empresaId > 0 && ! in_array($empresaId, $idsPermitidos, true)) {
            $empresaId = 0;
        }
        $ids = $empresaId > 0 ? [$empresaId] : $idsPermitidos;
        $periodo = TableroDireccionPeriodo::resolver($request);

        return response()->json($this->tableroService->detalle($bloque, $ids, $periodo));
    }

    /**
     * @return list<array{titulo: string, url: string, icono: string}>
     */
    private function atajos(): array
    {
        return [
            ['titulo' => 'Depositar cheques', 'url' => route('cheque', ['para_depositar' => 1]), 'icono' => 'fa-university'],
            ['titulo' => 'Cartera', 'url' => route('cheque', ['cartera' => 1]), 'icono' => 'fa-folder-open'],
            ['titulo' => 'Cheques emitidos', 'url' => route('cheque', ['origen' => 'E']), 'icono' => 'fa-money-check'],
            ['titulo' => 'Programa de pagos', 'url' => route('programa_pago'), 'icono' => 'fa-calendar-check'],
            ['titulo' => 'Aging cartera', 'url' => route('aging_cheque_cartera'), 'icono' => 'fa-hourglass-half'],
            ['titulo' => 'Cashflow', 'url' => route('cashflow_cheque'), 'icono' => 'fa-stream'],
            ['titulo' => 'Deuda clientes', 'url' => route('cliente_cuentacorriente_reporte'), 'icono' => 'fa-user-friends'],
            ['titulo' => 'Deuda proveedores', 'url' => route('proveedor_cuentacorriente_reporte'), 'icono' => 'fa-truck'],
            ['titulo' => 'IVA ventas', 'url' => route('iva_ventas'), 'icono' => 'fa-file-invoice-dollar'],
            ['titulo' => 'IVA compras', 'url' => route('iva_compras'), 'icono' => 'fa-file-invoice'],
        ];
    }
}

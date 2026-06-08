<?php

namespace App\Http\Controllers\Stock;

use App\Http\Controllers\Controller;
use App\Http\Requests\ValidacionTransferenciaMercaderia;
use App\Models\Stock\Depmae;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Repositories\Stock\DepmaeRepositoryInterface;
use App\Repositories\Stock\Tipotransaccion_StockRepository;
use App\Services\Stock\TransferenciaMercaderiaService;
use Illuminate\Http\JsonResponse;
use App\Support\Stock\UsuarioDepositoAutorizado;

class TransferenciaMercaderiaController extends Controller
{
    public function __construct(
        private TransferenciaMercaderiaService $transferenciaService,
        private Tipotransaccion_StockRepository $tipotransaccionStockRepository,
        private DepmaeRepositoryInterface $depmaeRepository,
        private EmpresaRepositoryInterface $empresaRepository,
    ) {}

    public function index()
    {
        can('crear-transferencia-mercaderia');

        $tipotransacciones = $this->tipotransaccionStockRepository->all(['T'], ['A']);
        $defaults = $this->transferenciaService->defaultsUsuario();
        $empresa_query = $this->empresaRepository->allFiltrado();
        $empresa_id = old('empresa_id', $empresa_query->first()->id ?? null);

        $depSalida = $this->resolverDepositoDefault($defaults['deposito_salida_id'] ?? null, $empresa_id);
        $depEntrada = $this->resolverDepositoDefault($defaults['deposito_entrada_id'] ?? null, $empresa_id);

        return view('stock.transferencia_mercaderia.index', compact(
            'tipotransacciones',
            'defaults',
            'depSalida',
            'depEntrada',
            'empresa_query',
            'empresa_id',
        ));
    }

    private function resolverDepositoDefault($id, $empresaId = null): ?Depmae
    {
        $id = (int) $id;
        if ($id <= 0) {
            return null;
        }

        $query = Depmae::query()->whereKey($id);
        $empresaId = (int) $empresaId;
        if ($empresaId > 0) {
            $query->paraEmpresa($empresaId);
        }

        UsuarioDepositoAutorizado::aplicarFiltroQuery($query);

        return $query->first();
    }

    public function preferencias(Request $request): JsonResponse
    {
        can('crear-transferencia-mercaderia');

        $this->transferenciaService->persistirPreferencias($request->only([
            'deposito_salida_id',
            'deposito_entrada_id',
            'tipotransaccion_id',
            'tipotransaccion_stock_id',
        ]));

        return response()->json(['ok' => true]);
    }

    public function inventario(Request $request): JsonResponse
    {
        can('crear-transferencia-mercaderia');

        $depositoId = (int) $request->input('deposito_salida_id', 0);
        if ($depositoId <= 0) {
            return response()->json(['ok' => false, 'mensaje' => 'Seleccione depósito de salida.'], 422);
        }

        try {
            $filas = $this->transferenciaService->inventarioDepositoSalida($depositoId);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'mensaje' => $e->getMessage()], 422);
        }

        return response()->json(['ok' => true, 'filas' => $filas]);
    }

    public function guardar(ValidacionTransferenciaMercaderia $request): JsonResponse
    {
        can('crear-transferencia-mercaderia');

        $lineas = [];
        foreach ($request->input('lineas', []) as $linea) {
            $cantidad = (float) ($linea['cantidad'] ?? 0);
            if ($cantidad > 0) {
                $lineas[] = [
                    'articulo_id' => (int) $linea['articulo_id'],
                    'cantidad' => $cantidad,
                ];
            }
        }

        $resultado = $this->transferenciaService->grabarTransferencia(
            $request->only(['deposito_salida_id', 'deposito_entrada_id', 'tipotransaccion_id', 'tipotransaccion_stock_id']),
            $lineas
        );

        $status = $resultado['ok'] ? 200 : 422;

        return response()->json($resultado, $status);
    }
}

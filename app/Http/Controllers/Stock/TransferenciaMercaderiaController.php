<?php

namespace App\Http\Controllers\Stock;

use App\Http\Controllers\Controller;
use App\Http\Requests\ValidacionTransferenciaMercaderia;
use App\Models\Stock\Depmae;
use App\Repositories\Ventas\TipotransaccionRepository;
use App\Services\Stock\TransferenciaMercaderiaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TransferenciaMercaderiaController extends Controller
{
    public function __construct(
        private TransferenciaMercaderiaService $transferenciaService,
        private TipotransaccionRepository $tipotransaccionRepository,
    ) {}

    public function index()
    {
        can('crear-transferencia-mercaderia');

        $tipotransacciones = $this->tipotransaccionRepository->all(['T'], ['A']);
        $defaults = $this->transferenciaService->defaultsUsuario();

        $depSalida = $this->resolverDepositoDefault($defaults['deposito_salida_id'] ?? null);
        $depEntrada = $this->resolverDepositoDefault($defaults['deposito_entrada_id'] ?? null);

        return view('stock.transferencia_mercaderia.index', compact(
            'tipotransacciones',
            'defaults',
            'depSalida',
            'depEntrada'
        ));
    }

    private function resolverDepositoDefault($id): ?Depmae
    {
        $id = (int) $id;
        if ($id <= 0) {
            return null;
        }

        return Depmae::query()->find($id);
    }

    public function preferencias(Request $request): JsonResponse
    {
        can('crear-transferencia-mercaderia');

        $this->transferenciaService->persistirPreferencias($request->only([
            'deposito_salida_id',
            'deposito_entrada_id',
            'tipotransaccion_id',
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
            $request->only(['deposito_salida_id', 'deposito_entrada_id', 'tipotransaccion_id']),
            $lineas
        );

        $status = $resultado['ok'] ? 200 : 422;

        return response()->json($resultado, $status);
    }
}

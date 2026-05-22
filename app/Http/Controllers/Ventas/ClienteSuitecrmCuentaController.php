<?php

namespace App\Http\Controllers\Ventas;

use App\Http\Controllers\Controller;
use App\Models\Ventas\Cliente;
use App\Services\Crm\SuitecrmAccountService;
use App\Support\SuitecrmPermiso;
use Illuminate\Http\JsonResponse;

class ClienteSuitecrmCuentaController extends Controller
{
    public function __construct(
        private readonly SuitecrmAccountService $cuentaService
    ) {}

    public function show(int $cliente_id): JsonResponse
    {
        $this->assertHabilitado();
        SuitecrmPermiso::puedeListarNotas();

        $cliente = Cliente::findOrFail($cliente_id);

        return response()->json($this->cuentaService->estadoParaCliente($cliente));
    }

    public function sincronizar(int $cliente_id): JsonResponse
    {
        $this->assertHabilitado();
        SuitecrmPermiso::assertSincronizarCuenta();

        $cliente = Cliente::with(['localidades', 'provincias', 'paises'])->findOrFail($cliente_id);
        $resultado = $this->cuentaService->sincronizar($cliente);

        if (! $resultado['ok']) {
            return response()->json($resultado, 422);
        }

        return response()->json($resultado);
    }

    private function assertHabilitado(): void
    {
        if (! $this->cuentaService->isHabilitado()) {
            abort(404);
        }
    }
}

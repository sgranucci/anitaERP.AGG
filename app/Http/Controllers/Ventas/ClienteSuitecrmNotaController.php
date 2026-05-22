<?php

namespace App\Http\Controllers\Ventas;

use App\Http\Controllers\Controller;
use App\Models\Ventas\Cliente;
use App\Services\Crm\SuitecrmNotaService;
use App\Support\SuitecrmPermiso;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClienteSuitecrmNotaController extends Controller
{
    public function __construct(
        private readonly SuitecrmNotaService $notaService
    ) {}

    public function index(int $cliente_id): JsonResponse
    {
        $this->assertHabilitado();
        SuitecrmPermiso::puedeListarNotas();

        $cliente = Cliente::findOrFail($cliente_id);
        $resultado = $this->notaService->listarParaCliente($cliente);

        return response()->json($resultado);
    }

    public function store(Request $request, int $cliente_id): JsonResponse
    {
        $this->assertHabilitado();
        SuitecrmPermiso::puedeGestionarNotas();

        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:65000',
        ]);

        $cliente = Cliente::findOrFail($cliente_id);
        $resultado = $this->notaService->crear(
            $cliente,
            trim((string) $request->input('name')),
            trim((string) $request->input('description', ''))
        );

        if (! $resultado['ok']) {
            return response()->json($resultado, 422);
        }

        return response()->json($resultado, 201);
    }

    public function update(Request $request, int $cliente_id, string $nota_id): JsonResponse
    {
        $this->assertHabilitado();
        SuitecrmPermiso::puedeGestionarNotas();

        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:65000',
        ]);

        $cliente = Cliente::findOrFail($cliente_id);
        $resultado = $this->notaService->actualizar(
            $cliente,
            $nota_id,
            trim((string) $request->input('name')),
            trim((string) $request->input('description', ''))
        );

        if (! $resultado['ok']) {
            return response()->json($resultado, 422);
        }

        return response()->json($resultado);
    }

    public function destroy(int $cliente_id, string $nota_id): JsonResponse
    {
        $this->assertHabilitado();
        SuitecrmPermiso::puedeGestionarNotas();

        $cliente = Cliente::findOrFail($cliente_id);
        $resultado = $this->notaService->eliminar($cliente, $nota_id);

        if (! $resultado['ok']) {
            return response()->json($resultado, 422);
        }

        return response()->json($resultado);
    }

    private function assertHabilitado(): void
    {
        if (! $this->notaService->isHabilitado()) {
            abort(404);
        }
    }
}

<?php

namespace App\Http\Controllers\Arca;

use App\Http\Controllers\Controller;
use App\Services\Arca\ConstanciaInscripcionService;
use App\Support\Ventas\ArcaPadronImpuestosClienteValidacion;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConstanciaInscripcionController extends Controller
{
    public function __construct(private ConstanciaInscripcionService $service) {}

    public function consultar(Request $request): JsonResponse
    {
        $request->validate([
            'cuit' => ['required', 'string'],
            'condicioniva_id' => ['nullable', 'integer'],
        ]);

        try {
            $data = $this->service->getPersonaV2((string) $request->input('cuit'));
            $validacion = $this->validacionImpuestosCliente($request, $data);

            if (! empty($data['error'])) {
                return response()->json([
                    'ok' => false,
                    'message' => (string) $data['error'],
                    'data' => $data,
                    'validacion' => $validacion,
                    'soap' => $data['soap'] ?? null,
                ], 422);
            }

            return response()->json([
                'ok' => true,
                'data' => $data,
                'validacion' => $validacion,
                'soap' => $data['soap'] ?? null,
            ]);
        } catch (Exception $e) {
            $soap = $this->service->getLastSoapTrace();

            return response()->json(array_filter([
                'ok' => false,
                'message' => $e->getMessage(),
                'soap' => $soap,
            ]), 500);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>|null
     */
    private function validacionImpuestosCliente(Request $request, array $data): ?array
    {
        if (! filter_var(config('arca.padron_validacion_cliente.habilitado', true), FILTER_VALIDATE_BOOLEAN)) {
            return null;
        }

        if (! $request->filled('condicioniva_id')) {
            return null;
        }

        return ArcaPadronImpuestosClienteValidacion::validar(
            (int) $request->input('condicioniva_id'),
            $data
        );
    }
}

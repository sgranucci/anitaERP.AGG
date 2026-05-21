<?php

namespace App\Http\Controllers\Arca;

use App\Http\Controllers\Controller;
use App\Services\Arca\ConstanciaInscripcionService;
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
        ]);

        try {
            $data = $this->service->getPersonaV2((string) $request->input('cuit'));

            if (! empty($data['error'])) {
                return response()->json([
                    'ok' => false,
                    'message' => (string) $data['error'],
                    'data' => $data,
                    'soap' => $data['soap'] ?? null,
                ], 422);
            }

            return response()->json([
                'ok' => true,
                'data' => $data,
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
}

<?php

namespace App\Http\Controllers\Arca;

use App\Http\Controllers\Controller;
use App\Services\Arca\ConstanciaInscripcionService;
use App\Support\Ventas\ArcaPadronImpuestosClienteValidacion;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

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
            $mensaje = $e->getMessage();
            $esTransporte = (bool) preg_match(
                '/could not connect to host|failed to load external entity|ssl|timed out|connection refused/i',
                $mensaje
            );

            Log::warning('arca.padron.constancia_fallo', [
                'cuit' => preg_replace('/\D+/', '', (string) $request->input('cuit')),
                'error' => $mensaje,
                'transporte' => $esTransporte,
            ]);

            // El titular CUIT en COT es informativo: devolvemos 503 (no 500) y un texto claro.
            if ($esTransporte) {
                $mensaje = 'Padrón ARCA no disponible (no bloquea el COT)';
            }

            return response()->json(array_filter([
                'ok' => false,
                'message' => $mensaje,
                'soap' => $soap,
            ]), $esTransporte ? 503 : 500);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>|null
     */
    private function validacionImpuestosCliente(Request $request, array $data): ?array
    {
        $habilitadoCliente = filter_var(config('arca.padron_validacion_cliente.habilitado', true), FILTER_VALIDATE_BOOLEAN);
        $habilitadoProveedor = filter_var(config('arca.padron_validacion_proveedor.habilitado', true), FILTER_VALIDATE_BOOLEAN);

        if (! $habilitadoCliente && ! $habilitadoProveedor) {
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

<?php

namespace App\Http\Controllers\Configuracion;

use App\Http\Controllers\Controller;
use App\Services\Configuracion\WebPushEnvioService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WebPushController extends Controller
{
    public function __construct(
        private WebPushEnvioService $webPush,
    ) {}

    public function publicKey(): JsonResponse
    {
        if (! auth()->check()) {
            return response()->json(['ok' => false], 401);
        }

        return response()->json([
            'ok' => $this->webPush->habilitado(),
            'publicKey' => $this->webPush->publicKey(),
        ]);
    }

    public function suscribir(Request $request): JsonResponse
    {
        $usuarioId = (int) (auth()->id() ?? 0);
        if ($usuarioId <= 0) {
            return response()->json(['ok' => false], 401);
        }

        $sub = $this->webPush->suscribir(
            $usuarioId,
            $request->all(),
            $request->userAgent()
        );

        return response()->json(['ok' => $sub !== null, 'id' => $sub?->id]);
    }

    public function desuscribir(Request $request): JsonResponse
    {
        $usuarioId = (int) (auth()->id() ?? 0);
        if ($usuarioId <= 0) {
            return response()->json(['ok' => false], 401);
        }
        $endpoint = (string) $request->input('endpoint', '');
        $n = $this->webPush->desuscribirPorEndpoint($usuarioId, $endpoint);

        return response()->json(['ok' => true, 'borrados' => $n]);
    }
}

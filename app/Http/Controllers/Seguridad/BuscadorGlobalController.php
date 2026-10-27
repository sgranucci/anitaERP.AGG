<?php

namespace App\Http\Controllers\Seguridad;

use App\Http\Controllers\Controller;
use App\Support\Seguridad\BuscadorGlobalSupport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BuscadorGlobalController extends Controller
{
    public function __construct(
        private BuscadorGlobalSupport $buscador
    ) {}

    public function index(Request $request): JsonResponse
    {
        $consulta = trim((string) $request->query('q', ''));
        if (mb_strlen($consulta) > 80) {
            $consulta = mb_substr($consulta, 0, 80);
        }

        return response()->json([
            'ok' => true,
            'q' => $consulta,
        ] + $this->buscador->buscar($consulta));
    }

    public function eleccion(Request $request): JsonResponse
    {
        $consulta = trim((string) $request->input('q', ''));
        if (mb_strlen($consulta) > 80) {
            $consulta = mb_substr($consulta, 0, 80);
        }

        $this->buscador->registrarEleccion(
            $consulta,
            (string) $request->input('tipo', ''),
            (string) $request->input('clave', '')
        );

        return response()->json(['ok' => true]);
    }
}

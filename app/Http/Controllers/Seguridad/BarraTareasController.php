<?php

namespace App\Http\Controllers\Seguridad;

use App\Http\Controllers\Controller;
use App\Support\Seguridad\BarraTareasSupport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BarraTareasController extends Controller
{
    public function __construct(
        private BarraTareasSupport $barraTareasSupport
    ) {}

    public function index(): JsonResponse
    {
        return response()->json([
            'ok' => true,
            'anclados' => $this->barraTareasSupport->ancladosResueltos(),
        ]);
    }

    public function menusDisponibles(): JsonResponse
    {
        return response()->json([
            'ok' => true,
            'menus' => $this->barraTareasSupport->menusDisponibles(),
        ]);
    }

    public function anclar(Request $request): JsonResponse
    {
        $menuId = (int) $request->input('menu_id');

        try {
            $anclados = $this->barraTareasSupport->anclar($menuId);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['ok' => false, 'mensaje' => $e->getMessage()], 422);
        }

        return response()->json(['ok' => true, 'anclados' => $anclados]);
    }

    public function desanclar(Request $request): JsonResponse
    {
        $menuId = (int) $request->input('menu_id');
        $anclados = $this->barraTareasSupport->desanclar($menuId);

        return response()->json(['ok' => true, 'anclados' => $anclados]);
    }

    public function reordenar(Request $request): JsonResponse
    {
        $menuIds = $request->input('menu_ids', []);
        if (! is_array($menuIds)) {
            return response()->json(['ok' => false, 'mensaje' => 'Formato de orden inválido.'], 422);
        }

        $anclados = $this->barraTareasSupport->reordenar($menuIds);

        return response()->json(['ok' => true, 'anclados' => $anclados]);
    }
}

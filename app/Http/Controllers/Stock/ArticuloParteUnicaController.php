<?php

namespace App\Http\Controllers\Stock;

use App\Http\Controllers\Controller;
use App\Models\Stock\Articulo_ParteUnica;
use App\Services\Stock\ArticuloParteUnicaService;
use App\Support\Stock\RecepcionProveedorParteUnicaSupport;

class ArticuloParteUnicaController extends Controller
{
    public function __construct(
        private readonly ArticuloParteUnicaService $service,
    ) {
    }

    public function index(int $articuloId, \Illuminate\Http\Request $request)
    {
        can('editar-articulos');

        $estado = (string) $request->query('estado', 'A');

        return response()->json(
            $this->service->listarPorArticulo($articuloId, 20, $estado)
        );
    }

    public function guardar(int $articuloId)
    {
        can('actualizar-articulos');

        $producto = \App\Models\Stock\Articulo::findOrFail($articuloId);
        if (! RecepcionProveedorParteUnicaSupport::articuloManejaParteUnica($producto)) {
            return response()->json(['mensaje' => 'El artículo no lleva número de parte única.'], 422);
        }

        try {
            $parte = $this->service->crear($articuloId);
        } catch (\Throwable $e) {
            return response()->json(['mensaje' => $e->getMessage()], 422);
        }

        return response()->json(['mensaje' => 'Número de parte creado.', 'parte' => $parte], 201);
    }

    public function eliminar(int $id)
    {
        can('actualizar-articulos');

        $parte = Articulo_ParteUnica::findOrFail($id);

        try {
            $this->service->eliminar($parte);
        } catch (\Throwable $e) {
            return response()->json(['mensaje' => $e->getMessage()], 422);
        }

        return response()->json(['mensaje' => 'Número de parte eliminado.']);
    }
}

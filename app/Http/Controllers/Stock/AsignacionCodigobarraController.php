<?php

namespace App\Http\Controllers\Stock;

use App\Http\Controllers\Controller;
use App\Services\Stock\AsignacionCodigobarraService;
use App\Support\Stock\CodigoBarrasImagenSupport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AsignacionCodigobarraController extends Controller
{
    public function __construct(
        private AsignacionCodigobarraService $service,
    ) {}

    public function index()
    {
        can('asignar-codigobarra-articulo');

        return view('stock.asignacion_codigobarra.index');
    }

    public function pendientes(Request $request): JsonResponse
    {
        can('asignar-codigobarra-articulo');

        $depositoId = (int) $request->input('deposito_id', 0);
        if ($depositoId <= 0) {
            return response()->json(['ok' => false, 'mensaje' => 'Seleccione un depósito.'], 422);
        }

        try {
            $filas = $this->service->pendientesDeposito($depositoId);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'mensaje' => $e->getMessage()], 422);
        }

        return response()->json([
            'ok' => true,
            'total' => count($filas),
            'filas' => $filas,
        ]);
    }

    public function proveedores(Request $request): JsonResponse
    {
        can('asignar-codigobarra-articulo');

        $busqueda = trim((string) $request->input('q', $request->input('busqueda', '')));

        return response()->json([
            'ok' => true,
            'opciones' => $this->service->opcionesProveedor($busqueda !== '' ? $busqueda : null),
        ]);
    }

    public function guardar(Request $request): JsonResponse
    {
        can('asignar-codigobarra-articulo');

        $articuloId = (int) $request->input('articulo_id', 0);
        $codigobarra = (string) $request->input('codigobarra', '');
        $proveedorId = $request->filled('proveedor_id') ? (int) $request->input('proveedor_id') : null;

        try {
            $resultado = $this->service->guardar($articuloId, $codigobarra, $proveedorId);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'mensaje' => $e->getMessage()], 422);
        }

        $status = ! empty($resultado['ok']) ? 200 : 422;

        return response()->json($resultado, $status);
    }

    public function decodificarFoto(Request $request): JsonResponse
    {
        can('asignar-codigobarra-articulo');

        $foto = $request->file('foto');
        if (! $foto || ! $foto->isValid()) {
            return response()->json([
                'ok' => false,
                'codigos' => [],
                'mensaje' => 'No llegó la foto.',
            ], 422);
        }

        $mime = (string) $foto->getMimeType();
        if (! str_starts_with($mime, 'image/')) {
            return response()->json([
                'ok' => false,
                'codigos' => [],
                'mensaje' => 'El archivo no es una imagen.',
            ], 422);
        }

        try {
            $resultado = CodigoBarrasImagenSupport::decodificarDesdePath((string) $foto->getRealPath());
        } catch (\Throwable $e) {
            return response()->json([
                'ok' => false,
                'codigos' => [],
                'mensaje' => $e->getMessage(),
            ], 422);
        }

        return response()->json($resultado, ! empty($resultado['ok']) ? 200 : 422);
    }
}

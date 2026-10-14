<?php

namespace App\Http\Controllers\Stock;

use App\Http\Controllers\Controller;
use App\Models\Stock\Articulo;
use App\Models\Stock\ArticuloWaitryEnvio;
use App\Services\Stock\ArticuloWaitrySyncService;
use App\Support\Stock\ArticuloWaitrySupport;
use App\Support\Ventas\GastronomiaSkuCatalogoSupport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ArticuloWaitryController extends Controller
{
    public function __construct(
        private readonly ArticuloWaitrySyncService $syncService,
    ) {}

    public function guardarMarcas(Request $request, int $id): JsonResponse
    {
        can('enviar-articulo-waitry');

        $articulo = $this->articuloEnviable($id);
        if ($articulo instanceof JsonResponse) {
            return $articulo;
        }

        ArticuloWaitrySupport::guardarMarcas(
            $id,
            $request->boolean('waitry_dispara_comanda'),
            $request->boolean('waitry_va_cocina')
        );

        $articulo->refresh();

        return response()->json([
            'ok' => true,
            'mensaje' => 'Marcas guardadas.',
            'panel' => $this->syncService->panel($articulo, true),
        ]);
    }

    public function alta(Request $request, int $id): JsonResponse
    {
        return $this->ejecutar($request, $id, ArticuloWaitryEnvio::ALTA);
    }

    public function baja(Request $request, int $id): JsonResponse
    {
        return $this->ejecutar($request, $id, ArticuloWaitryEnvio::BAJA);
    }

    private function ejecutar(Request $request, int $id, string $operacion): JsonResponse
    {
        can('enviar-articulo-waitry');

        $articulo = $this->articuloEnviable($id);
        if ($articulo instanceof JsonResponse) {
            return $articulo;
        }

        if ($request->exists('waitry_dispara_comanda') || $request->exists('waitry_va_cocina')) {
            ArticuloWaitrySupport::aplicarDesdeRequest($request, $id);
            $articulo->refresh();
        }

        $resultado = $this->syncService->ejecutar($articulo, $operacion);
        $resultado['panel'] = $this->syncService->panel($articulo->fresh(), true);

        return response()->json($resultado, ($resultado['ok'] ?? false) ? 200 : 422);
    }

    private function articuloEnviable(int $id): Articulo|JsonResponse
    {
        if (! ArticuloWaitrySupport::uiActiva()) {
            return response()->json(['ok' => false, 'mensaje' => 'Waitry de artículos no está disponible.'], 404);
        }

        $articulo = Articulo::query()->find($id);
        if ($articulo === null) {
            return response()->json(['ok' => false, 'mensaje' => 'Artículo inexistente.'], 404);
        }

        if (! GastronomiaSkuCatalogoSupport::skuPermitido((string) $articulo->sku)) {
            return response()->json(['ok' => false, 'mensaje' => 'Este SKU no se envía a Waitry.'], 422);
        }

        return $articulo;
    }
}

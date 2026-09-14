<?php

namespace App\Http\Controllers\Ventas\FacturacionLocal;

use App\Http\Controllers\Controller;
use App\Models\Stock\Articulo;
use App\Models\Ventas\LocalVenta;
use App\Services\Ventas\FacturacionLocal\StockLocalConsultaService;
use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\Ventas\FacturacionLocal\ArticuloCanalSupport;
use App\Support\Ventas\FacturacionLocal\FacturacionLocalVarianteArticuloSupport;
use App\Support\Ventas\FacturacionLocal\StockLocalInformeListadoFiltros;
use Illuminate\Http\Request;

/**
 * Consultas operativas de locales (c-stocklocal / c-articulo).
 */
class FacturacionLocalStockController extends Controller
{
    public function __construct(
        private readonly StockLocalConsultaService $consultaService,
    ) {
    }

    public function stockIndex(Request $request)
    {
        $this->assertFerli();
        can('consultar-stock-local');

        $locales = $this->localesActivos();
        $localId = (int) $request->input('local_id', $locales->first()?->id ?? 0);

        return view('ventas.facturacion_local.stock.index', [
            'locales' => $locales,
            'localId' => $localId,
            'modo' => 'stock',
        ]);
    }

    public function preciosIndex(Request $request)
    {
        $this->assertFerli();
        can('consultar-precios-local');

        $locales = $this->localesActivos();
        $localId = (int) $request->input('local_id', $locales->first()?->id ?? 0);

        return view('ventas.facturacion_local.consulta_precios.index', [
            'locales' => $locales,
            'localId' => $localId,
            'modo' => 'precios',
        ]);
    }

    public function apiStock(Request $request)
    {
        $this->assertFerli();
        can('consultar-stock-local', false);

        $local = $this->resolverLocal((int) $request->input('local_id', 0));
        if (! $local) {
            return response()->json(['ok' => false, 'error' => 'Seleccione un local válido.'], 422);
        }

        $busqueda = $this->resolverBusquedaArticulo($request);
        if ($busqueda === '') {
            return response()->json(['ok' => false, 'error' => 'Ingrese un artículo.'], 422);
        }

        $resultado = $this->consultaService->consultarStockLocal(
            $local,
            $busqueda,
            $this->resolverOrigen($request)
        );
        $status = ($resultado['ok'] ?? false) ? 200 : 422;

        return response()->json($resultado, $status);
    }

    public function apiPrecios(Request $request)
    {
        $this->assertFerli();
        can('consultar-precios-local', false);

        $local = $this->resolverLocal((int) $request->input('local_id', 0));
        if (! $local) {
            return response()->json(['ok' => false, 'error' => 'Seleccione un local válido.'], 422);
        }

        $busqueda = $this->resolverBusquedaArticulo($request);
        if ($busqueda === '') {
            return response()->json(['ok' => false, 'error' => 'Ingrese un artículo.'], 422);
        }

        $resultado = $this->consultaService->consultarPreciosYStock(
            $local,
            $busqueda,
            $this->resolverOrigen($request)
        );
        $status = ($resultado['ok'] ?? false) ? 200 : 422;

        return response()->json($resultado, $status);
    }

    public function apiBuscarArticulo(Request $request)
    {
        $this->assertFerli();
        if (! can('consultar-stock-local', false) && ! can('consultar-precios-local', false)) {
            return response()->json(['message' => 'Sin permiso.'], 403);
        }

        $q = trim((string) $request->input('q', ''));
        if (strlen($q) < 2) {
            return response()->json(['data' => []]);
        }

        $query = Articulo::query()
            ->select(['id', 'sku', 'descripcion', 'maneja_stock_color_talle', 'nofactura'])
            ->where(function ($w) use ($q) {
                $w->where('sku', 'like', '%'.$q.'%')
                    ->orWhere('descripcion', 'like', '%'.$q.'%');
            });
        ArticuloCanalSupport::scopeArticulosCanalLocal($query);
        $rows = $query->orderBy('sku')->limit(30)->get()->map(static function (Articulo $a) {
            return [
                'id' => (int) $a->id,
                'sku' => (string) $a->sku,
                'descripcion' => (string) $a->descripcion,
                'modo_variante' => FacturacionLocalVarianteArticuloSupport::modo($a),
                'nofactura' => (bool) $a->nofactura,
            ];
        });

        return response()->json(['data' => $rows]);
    }

    private function localesActivos()
    {
        return LocalVenta::query()
            ->with(['listaprecio:id,codigo,nombre', 'deposito:id,codigo,nombre'])
            ->where('activo', true)
            ->orderBy('codigo')
            ->get();
    }

    /**
     * Mismo criterio que el informe: tilde origen_anita=1 → Anita; sin tilde → ERP.
     * Default Anita mientras se prueba el bridge (antes de importar stock al ERP).
     */
    private function resolverOrigen(Request $request): string
    {
        if ($request->input('origen') === StockLocalInformeListadoFiltros::ORIGEN_ERP) {
            return StockLocalInformeListadoFiltros::ORIGEN_ERP;
        }
        if ($request->input('origen') === StockLocalInformeListadoFiltros::ORIGEN_ANITA) {
            return StockLocalInformeListadoFiltros::ORIGEN_ANITA;
        }

        // Checkbox: ausente o 1 = Anita (default prueba); 0 = ERP
        $flag = $request->input('origen_anita', '1');
        if (is_array($flag)) {
            $flag = end($flag);
        }

        return (string) $flag === '0'
            ? StockLocalInformeListadoFiltros::ORIGEN_ERP
            : StockLocalInformeListadoFiltros::ORIGEN_ANITA;
    }

    private function resolverBusquedaArticulo(Request $request): string
    {
        $articuloId = (int) $request->input('articulo_id', 0);
        if ($articuloId > 0) {
            return (string) $articuloId;
        }

        return trim((string) $request->input('q', $request->input('articulo', '')));
    }

    private function resolverLocal(int $localId): ?LocalVenta
    {
        $query = LocalVenta::query()
            ->with(['listaprecio:id,codigo,nombre', 'deposito:id,codigo,nombre'])
            ->where('activo', true);
        if ($localId <= 0) {
            return $query->orderBy('codigo')->first();
        }

        return $query->find($localId);
    }

    private function assertFerli(): void
    {
        if (! EntornoEmpresaSupport::esFerli()) {
            abort(404);
        }
    }
}

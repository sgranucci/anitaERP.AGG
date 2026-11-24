<?php

namespace App\Http\Controllers\Ventas\Mercadolibre;

use App\Http\Controllers\Controller;
use App\Models\Ventas\MercadolibrePedido;
use App\Services\Ventas\Mercadolibre\MercadolibrePedidoEmisionService;
use App\Services\Ventas\Mercadolibre\MercadolibrePedidoSyncService;
use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\Ventas\Mercadolibre\MercadolibrePedidoEstadoSupport;
use App\Support\Ventas\Mercadolibre\MercadolibrePedidoListadoFiltros;
use App\Support\Ventas\Mercadolibre\MercadolibrePedidoListoSupport;
use App\Support\Ventas\Mercadolibre\MercadolibreTokenSupport;
use Carbon\Carbon;
use Illuminate\Http\Request;

class MercadolibrePedidoController extends Controller
{
    public function __construct(
        private readonly MercadolibrePedidoSyncService $syncService,
        private readonly MercadolibrePedidoEmisionService $emisionService,
    ) {
    }

    public function index(Request $request)
    {
        $this->assertFerli();
        can('listar-mercadolibre-pedidos');

        $filtros = MercadolibrePedidoListadoFiltros::resolverDesdeRequest($request);
        if (empty($filtros['desde']) && empty($filtros['hasta']) && ! $request->has('consultar')) {
            $filtros['desde'] = Carbon::now()->subDays(7)->format('Y-m-d');
            $filtros['hasta'] = Carbon::now()->format('Y-m-d');
            $filtros['consultar'] = true;
        }
        $filtrosQuery = MercadolibrePedidoListadoFiltros::paraQueryString($filtros);

        $query = MercadolibrePedido::query()->with(['venta:id,codigo,cae', 'lineas']);
        MercadolibrePedidoListadoFiltros::aplicar($query, $filtros);
        $coleccion = $query->orderByDesc('paid_at')->orderByDesc('id')->paginate(20);

        $puedeFacturar = can('facturar-mercadolibre-pedidos', false);
        $listosPorId = [];
        foreach ($coleccion as $p) {
            $listosPorId[(int) $p->id] = $puedeFacturar && MercadolibrePedidoListoSupport::estaListo($p);
        }

        return view('ventas.mercadolibre_pedido.index', [
            'coleccion' => $coleccion,
            'filtros' => $filtros,
            'filtrosQuery' => $filtrosQuery,
            'estados' => MercadolibrePedidoEstadoSupport::etiquetas(),
            'oauth' => MercadolibreTokenSupport::estadoParaVista(),
            'appOk' => MercadolibreTokenSupport::appConfigurada(),
            'apiOk' => MercadolibreTokenSupport::configurado(),
            'puedeFacturar' => $puedeFacturar,
            'listosPorId' => $listosPorId,
            'puedeSync' => can('sincronizar-mercadolibre-pedidos', false),
            'puedeAuth' => can('autorizar-mercadolibre', false),
        ]);
    }

    public function sincronizar(Request $request)
    {
        $this->assertFerli();
        can('sincronizar-mercadolibre-pedidos');

        $desde = $request->input('desde') ?: Carbon::now()->subDays(7)->format('Y-m-d');
        $hasta = $request->input('hasta') ?: Carbon::now()->format('Y-m-d');
        $resultado = $this->syncService->sincronizarRango((string) $desde, (string) $hasta);

        if (! ($resultado['ok'] ?? false)) {
            return redirect()
                ->route('mercadolibre_pedidos', MercadolibrePedidoListadoFiltros::paraQueryString([
                    'desde' => $desde,
                    'hasta' => $hasta,
                    'consultar' => true,
                ]))
                ->with('error', $resultado['error'] ?? 'Error al sincronizar');
        }

        $msg = sprintf(
            'Sync OK: %d nuevos, %d actualizados (%d páginas).',
            (int) $resultado['creados'],
            (int) $resultado['actualizados'],
            (int) $resultado['paginas']
        );
        if (! empty($resultado['advertencias'])) {
            $msg .= ' '.$resultado['advertencias'];
        }

        return redirect()
            ->route('mercadolibre_pedidos', [
                'desde' => $desde,
                'hasta' => $hasta,
                'consultar' => 1,
            ])
            ->with('mensaje', $msg);
    }

    public function show(int $id)
    {
        $this->assertFerli();
        can('listar-mercadolibre-pedidos');

        $pedido = MercadolibrePedido::query()->with(['lineas.articulo', 'venta', 'puntoventaSugerido', 'depositoSugerido'])->findOrFail($id);

        return view('ventas.mercadolibre_pedido.show', [
            'pedido' => $pedido,
            'estados' => MercadolibrePedidoEstadoSupport::etiquetas(),
            'puedeFacturar' => can('facturar-mercadolibre-pedidos', false),
            'puedeSync' => can('sincronizar-mercadolibre-pedidos', false),
        ]);
    }

    public function refrescar(int $id)
    {
        $this->assertFerli();
        can('sincronizar-mercadolibre-pedidos');

        $pedido = MercadolibrePedido::query()->findOrFail($id);
        $r = $this->syncService->refrescarOrden((int) $pedido->order_id);
        if (! ($r['ok'] ?? false)) {
            return redirect()->route('mercadolibre_pedido_show', $id)->with('error', $r['error'] ?? 'Error');
        }

        return redirect()->route('mercadolibre_pedido_show', $id)->with('mensaje', 'Orden actualizada desde Mercado Libre.');
    }

    public function facturar(Request $request, int $id)
    {
        $this->assertFerli();
        can('facturar-mercadolibre-pedidos');

        $pedido = MercadolibrePedido::query()->with('lineas')->findOrFail($id);
        $resultado = $this->emisionService->emitir($pedido, $request->only([
            'puntoventa_id', 'deposito_id', 'cliente_id', 'letra', 'medios_pago',
        ]));

        if (! ($resultado['ok'] ?? false)) {
            return redirect()
                ->route('mercadolibre_pedido_show', $id)
                ->with('error', $resultado['error'] ?? 'No se pudo facturar');
        }

        return redirect()
            ->route('mercadolibre_pedido_show', $id)
            ->with('mensaje', 'Factura emitida. Venta #'.$resultado['venta_id'].' CAE '.$resultado['cae']);
    }

    public function facturarMasivo(Request $request)
    {
        $this->assertFerli();
        can('facturar-mercadolibre-pedidos');

        $ids = $request->input('pedido_ids', []);
        if (! is_array($ids)) {
            $ids = [];
        }

        $resultado = $this->emisionService->emitirMasivo($ids);
        $msg = sprintf(
            'Masivo: %d facturados, %d omitidos (no listos), %d con error.',
            (int) $resultado['facturados'],
            (int) $resultado['omitidos'],
            (int) $resultado['errores']
        );

        $detalleFallos = collect($resultado['detalle'] ?? [])
            ->filter(fn ($d) => empty($d['ok']))
            ->take(8)
            ->map(function ($d) {
                $nro = $d['order_id'] ?? ('#'.$d['id']);

                return $nro.': '.($d['motivo'] ?? '');
            })
            ->implode(' | ');
        if ($detalleFallos !== '') {
            $msg .= ' Detalle: '.$detalleFallos;
        }

        $query = array_filter([
            'desde' => $request->input('desde'),
            'hasta' => $request->input('hasta'),
            'estado_erp' => $request->input('estado_erp'),
            'texto' => $request->input('texto'),
            'consultar' => 1,
        ], fn ($v) => $v !== null && $v !== '');

        return redirect()
            ->route('mercadolibre_pedidos', $query)
            ->with('mensaje', $msg);
    }

    private function assertFerli(): void
    {
        if (! EntornoEmpresaSupport::esFerli()) {
            abort(404);
        }
    }
}

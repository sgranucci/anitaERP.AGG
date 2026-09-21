<?php

namespace App\Http\Controllers\Ventas\Tiendanube;

use App\Http\Controllers\Controller;
use App\Models\Stock\Depmae;
use App\Models\Ventas\TiendanubePedido;
use App\Services\Ventas\Tiendanube\TiendanubeApiClient;
use App\Services\Ventas\Tiendanube\TiendanubePedidoEmisionService;
use App\Services\Ventas\Tiendanube\TiendanubePedidoSyncService;
use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\Ventas\Tiendanube\TiendanubeApiHealthSupport;
use App\Support\Ventas\Tiendanube\TiendanubePedidoEstadoSupport;
use App\Support\Ventas\Tiendanube\TiendanubePedidoListadoFiltros;
use App\Support\Ventas\Tiendanube\TiendanubePedidoListoSupport;
use App\Support\Ventas\Tiendanube\TiendanubePedidoMaestrosSupport;
use App\Support\Ventas\Tiendanube\TiendanubePedidoStatusExternoSupport;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TiendanubePedidoController extends Controller
{
    public function __construct(
        private readonly TiendanubePedidoSyncService $syncService,
        private readonly TiendanubePedidoEmisionService $emisionService,
        private readonly TiendanubeApiClient $api,
    ) {
    }

    public function index(Request $request)
    {
        $this->assertFerli();
        can('listar-tiendanube-pedidos');

        $filtros = TiendanubePedidoListadoFiltros::resolverDesdeRequest($request);
        if (empty($filtros['desde']) && empty($filtros['hasta']) && ! $request->has('consultar')) {
            $filtros['desde'] = Carbon::now()->subDays(7)->format('Y-m-d');
            $filtros['hasta'] = Carbon::now()->format('Y-m-d');
            $filtros['payment_status'] = $filtros['payment_status'] ?: 'paid';
            $filtros['consultar'] = true;
        }

        $filtrosQuery = TiendanubePedidoListadoFiltros::paraQueryString($filtros);
        $query = TiendanubePedido::query()->with(['venta:id,codigo,cae', 'lineas']);
        TiendanubePedidoListadoFiltros::aplicar($query, $filtros);
        $coleccion = $query->orderByDesc('paid_at')->orderByDesc('id')->paginate(20);

        $apiOk = $this->api->configurado();
        $estados = TiendanubePedidoEstadoSupport::etiquetas();
        $estadosExternos = TiendanubePedidoStatusExternoSupport::etiquetas();
        $puedeFacturar = can('facturar-tiendanube-pedidos', false);

        $listosPorId = [];
        foreach ($coleccion as $p) {
            $listosPorId[(int) $p->id] = $puedeFacturar && TiendanubePedidoListoSupport::estaListo($p);
        }

        $apiHealth = TiendanubeApiHealthSupport::estado();
        if ($apiOk) {
            $needsCheck = ($apiHealth['checked_at'] ?? null) === null
                || ! ($apiHealth['auth_ok'] ?? false);
            if ($needsCheck) {
                app(TiendanubeApiHealthSupport::class)->verificar(false);
                $apiHealth = TiendanubeApiHealthSupport::estado();
            }
        }

        return view('ventas.tiendanube_pedido.index', compact(
            'coleccion',
            'filtros',
            'filtrosQuery',
            'apiOk',
            'apiHealth',
            'estados',
            'estadosExternos',
            'puedeFacturar',
            'listosPorId'
        ));
    }

    public function sincronizar(Request $request)
    {
        $this->assertFerli();
        can('sincronizar-tiendanube-pedidos');

        $desde = trim((string) $request->input('desde', Carbon::now()->subDays(7)->format('Y-m-d')));
        $hasta = trim((string) $request->input('hasta', Carbon::now()->format('Y-m-d')));

        try {
            $resultado = $this->syncService->sincronizarRango($desde, $hasta);
        } catch (\Throwable $e) {
            Log::error('tiendanube.sync.controller', ['error' => $e->getMessage()]);

            return redirect()
                ->route('tiendanube_pedidos', ['desde' => $desde, 'hasta' => $hasta, 'consultar' => 1])
                ->with('mensaje', 'Error al sincronizar: '.$e->getMessage());
        }

        if (! ($resultado['ok'] ?? false)) {
            return redirect()
                ->route('tiendanube_pedidos', ['desde' => $desde, 'hasta' => $hasta, 'consultar' => 1])
                ->with('mensaje', 'Error API: '.($resultado['error'] ?? 'desconocido'));
        }

        $msg = sprintf(
            'Sincronizado: %d nuevos, %d actualizados (%d páginas). SKUs resueltos: %d.',
            (int) $resultado['creados'],
            (int) $resultado['actualizados'],
            (int) $resultado['paginas'],
            (int) ($resultado['skus_rematch'] ?? 0)
        );

        return redirect()
            ->route('tiendanube_pedidos', [
                'desde' => $desde,
                'hasta' => $hasta,
                'payment_status' => 'paid',
                'consultar' => 1,
            ])
            ->with('mensaje', $msg);
    }

    public function show(int $id)
    {
        $this->assertFerli();
        can('listar-tiendanube-pedidos');

        $pedido = TiendanubePedido::query()
            ->with(['lineas.articulo', 'lineas.combinacion', 'lineas.talle', 'venta', 'cliente'])
            ->findOrFail($id);

        // Reintento match SKU compuesto al abrir (pedidos stageados antes del fix)
        if (! $pedido->estaFacturado()) {
            $this->syncService->rematchearSkusPendientes((int) $pedido->id);
            $pedido->load(['lineas.articulo', 'lineas.combinacion', 'lineas.talle']);
        }

        $puntoventas = TiendanubePedidoMaestrosSupport::puntoventasOnline();
        $depositos = Depmae::query()->orderBy('codigo')->get(['id', 'codigo', 'nombre']);
        $cuentacajas = TiendanubePedidoMaestrosSupport::cuentacajasOperativas();
        $cuentacajaSugeridaId = TiendanubePedidoMaestrosSupport::sugerirCuentacajaId(
            $pedido->gateway,
            $pedido->gateway_name,
            is_array($pedido->payment_json) ? $pedido->payment_json : null
        );
        $pvDefaultId = (int) ($pedido->puntoventa_id_sugerido
            ?: TiendanubePedidoMaestrosSupport::puntoventaDefault()?->id
            ?: 0);
        $depDefaultId = (int) ($pedido->deposito_id_sugerido
            ?: TiendanubePedidoMaestrosSupport::depositoDefault()?->id
            ?: 0);
        $listaprecioId = TiendanubePedidoMaestrosSupport::listaprecioIdDefault();
        $puedeFacturar = can('facturar-tiendanube-pedidos', false)
            && ! $pedido->estaFacturado()
            && $pedido->estaPagado();
        $domicilioDefault = \App\Support\Ventas\Tiendanube\TiendanubePedidoReceptorSupport::domicilioDesdePedido($pedido);
        $letraDefault = 'B';
        $pagoDetalle = [
            'gateway' => $pedido->gateway,
            'gateway_name' => $pedido->gateway_name,
            'method' => is_array($pedido->payment_json) ? ($pedido->payment_json['method'] ?? null) : null,
            'card' => is_array($pedido->payment_json) ? ($pedido->payment_json['credit_card_company'] ?? null) : null,
            'installments' => is_array($pedido->payment_json) ? ($pedido->payment_json['installments'] ?? null) : null,
        ];

        return view('ventas.tiendanube_pedido.show', compact(
            'pedido',
            'puntoventas',
            'depositos',
            'cuentacajas',
            'cuentacajaSugeridaId',
            'pvDefaultId',
            'depDefaultId',
            'listaprecioId',
            'puedeFacturar',
            'domicilioDefault',
            'letraDefault',
            'pagoDetalle'
        ));
    }

    public function refrescar(int $id)
    {
        $this->assertFerli();
        can('sincronizar-tiendanube-pedidos');

        $pedido = TiendanubePedido::query()->findOrFail($id);
        $r = $this->syncService->refrescarPedido($pedido);
        if (! ($r['ok'] ?? false)) {
            return redirect()
                ->route('tiendanube_pedido_show', $id)
                ->with('mensaje', 'No se pudo refrescar: '.($r['error'] ?? ''));
        }

        return redirect()
            ->route('tiendanube_pedido_show', $id)
            ->with('mensaje', 'Pedido actualizado desde Tiendanube.');
    }

    public function facturar(Request $request, int $id)
    {
        $this->assertFerli();
        can('facturar-tiendanube-pedidos');

        $pedido = TiendanubePedido::query()->with('lineas')->findOrFail($id);

        $medios = [];
        $cuentas = $request->input('cuentacaja_ids', []);
        $montos = $request->input('montos', []);
        if (is_array($cuentas)) {
            foreach ($cuentas as $i => $cid) {
                $cid = (int) $cid;
                $monto = (float) ($montos[$i] ?? 0);
                if ($cid > 0 && $monto > 0) {
                    $medios[] = [
                        'cuentacaja_id' => $cid,
                        'moneda_id' => 1,
                        'monto' => $monto,
                    ];
                }
            }
        }

        $receptor = [
            'nombre' => trim((string) $request->input('receptor_nombre', $pedido->customer_name)),
            'numerodocumento' => preg_replace('/\D+/', '', (string) $request->input('receptor_doc', $pedido->customer_doc)) ?: null,
            'email' => trim((string) $request->input('receptor_email', $pedido->customer_email)) ?: null,
            'domicilio' => trim((string) $request->input('receptor_domicilio', '')) ?: null,
        ];
        $letra = strtoupper(trim((string) $request->input('letra', 'B')));
        if (! in_array($letra, ['A', 'B'], true)) {
            $letra = 'B';
        }

        $input = [
            'puntoventa_id' => (int) $request->input('puntoventa_id'),
            'deposito_id' => (int) $request->input('deposito_id'),
            'cliente_id' => (int) $request->input('cliente_id') ?: null,
            'listaprecio_id' => (int) $request->input('listaprecio_id') ?: null,
            'medios_pago' => $medios,
            'receptor' => $receptor,
            'letra' => $letra,
            'forzar_cf' => (bool) $request->boolean('forzar_cf'),
            'descuentoimportepie' => (float) $request->input('descuentoimportepie', 0),
        ];

        // Persistir sugerencias elegidas
        $pedido->puntoventa_id_sugerido = $input['puntoventa_id'] ?: $pedido->puntoventa_id_sugerido;
        $pedido->deposito_id_sugerido = $input['deposito_id'] ?: $pedido->deposito_id_sugerido;
        if ($receptor['numerodocumento']) {
            $pedido->customer_doc = $receptor['numerodocumento'];
        }
        if ($receptor['nombre']) {
            $pedido->customer_name = $receptor['nombre'];
        }
        if ($receptor['email']) {
            $pedido->customer_email = $receptor['email'];
        }
        $pedido->save();

        $resultado = $this->emisionService->emitir($pedido->fresh(['lineas']), $input);
        if (! ($resultado['ok'] ?? false)) {
            $msg = $resultado['error'] ?? 'No se pudo facturar';
            if (! empty($resultado['errores'])) {
                $msg = implode(' ', $resultado['errores']);
            }

            return redirect()
                ->route('tiendanube_pedido_show', $id)
                ->with('mensaje', $msg);
        }

        return redirect()
            ->route('tiendanube_pedido_show', $id)
            ->with('mensaje', 'Factura emitida OK. Venta #'.$resultado['venta_id']
                .(! empty($resultado['cae']) ? ' CAE '.$resultado['cae'] : ''));
    }

    public function facturarMasivo(Request $request)
    {
        $this->assertFerli();
        can('facturar-tiendanube-pedidos');

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
                $nro = $d['order_number'] ?? ('#'.$d['id']);

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
            'payment_status' => $request->input('payment_status', 'paid'),
            'buscar' => $request->input('buscar'),
            'consultar' => 1,
        ], fn ($v) => $v !== null && $v !== '');

        return redirect()
            ->route('tiendanube_pedidos', $query)
            ->with('mensaje', $msg);
    }

    private function assertFerli(): void
    {
        if (! EntornoEmpresaSupport::esFerli()) {
            abort(404);
        }
    }
}

<?php

namespace App\Services\Ventas\Gastronomia\Waitry;

use App\Models\Stock\Articulo;
use App\Models\Ventas\ConfiguracionPuntoventaGastronomia;
use App\Models\Ventas\CuentaGastronomia;
use App\Services\Ventas\Gastronomia\GastronomiaCuentaService;
use App\Support\Ventas\GastronomiaSkuCatalogoSupport;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Cuentas externas Waitry — GET getOrdersPOS (doc. pág. 21).
 *
 * Lista órdenes sin pago en Waitry e importa ítems al POS gastronomía (sin correlación con facturas Anita).
 */
final class WaitryOrdenesExternasService
{
    public function __construct(
        private readonly WaitryHttpClient $httpClient,
        private readonly WaitryAuthService $authService,
        private readonly GastronomiaCuentaService $cuentaService,
    ) {
    }

    /**
     * @return array{
     *     ok:bool,
     *     ordenes?:list<array<string,mixed>>,
     *     filtro?:array{minutos_atras:int,from:?string,to:?string},
     *     error?:string
     * }
     */
    public function listarOrdenesPendientes(int $empresaId, ?string $desde = null, ?string $hasta = null): array
    {
        if (! config('waitry.habilitado', false)) {
            return ['ok' => false, 'error' => 'Integración Waitry deshabilitada.'];
        }

        if (! $this->authService->credencialesCompletas()) {
            return ['ok' => false, 'error' => 'Waitry: credenciales incompletas.'];
        }

        $placeId = $this->resolverPlaceId($empresaId);
        if ($placeId === null) {
            return ['ok' => false, 'error' => 'Waitry: no hay placeId para la empresa '.$empresaId.'.'];
        }

        $rango = $this->resolverRangoFechasConsulta($desde, $hasta);
        $desdeLimite = $rango['desde_limite'];

        $query = ['placeId' => (string) $placeId];
        if ($rango['from'] !== null && $rango['from'] !== '') {
            $query['from'] = $rango['from'];
        }
        if ($rango['to'] !== null && $rango['to'] !== '') {
            $query['to'] = $rango['to'];
        }

        $url = (string) config('waitry.get_orders_url');
        $resultado = $this->httpClient->getJson($url, $query, 'get_orders_pos');

        if (! $resultado['ok']) {
            return ['ok' => false, 'error' => $resultado['error'] ?? 'Error al consultar órdenes Waitry.'];
        }

        $data = is_array($resultado['data'] ?? null) ? $resultado['data'] : [];
        $ordenesRaw = $data['orders'] ?? $data['response']['orders'] ?? [];
        if (! is_array($ordenesRaw)) {
            $ordenesRaw = [];
        }

        $importadasAbiertas = CuentaGastronomia::query()
            ->where('empresa_id', $empresaId)
            ->where('estado', CuentaGastronomia::ESTADO_ABIERTA)
            ->whereNotNull('waitry_order_id')
            ->pluck('waitry_order_id')
            ->map(static fn ($id) => (int) $id)
            ->all();

        $lista = [];
        foreach ($ordenesRaw as $orden) {
            if (! is_array($orden)) {
                continue;
            }
            if (! $this->ordenPendienteDePago($orden)) {
                continue;
            }

            if (! $this->ordenDentroDeVentanaHoraria($orden, $desdeLimite)) {
                continue;
            }

            $orderId = (int) ($orden['id'] ?? $orden['orderId'] ?? 0);
            if ($orderId <= 0) {
                continue;
            }

            if (in_array($orderId, $importadasAbiertas, true)) {
                continue;
            }

            $lineas = $this->extraerLineasDesdeOrden($orden);
            if ($lineas === []) {
                continue;
            }

            $lista[] = [
                'waitry_order_id' => $orderId,
                'display_id' => trim((string) ($orden['display_id'] ?? $orden['external_reference_id'] ?? '')),
                'external_reference_id' => trim((string) ($orden['external_reference_id'] ?? '')),
                'current_state' => trim((string) ($orden['current_state'] ?? '')),
                'type' => trim((string) ($orden['type'] ?? '')),
                'placed_at' => $orden['placed_at'] ?? null,
                'total_estimado' => round(array_sum(array_map(
                    static fn (array $ln): float => (float) $ln['precio_unitario'] * (float) $ln['cantidad'],
                    $lineas
                )), 2),
                'cantidad_items' => count($lineas),
                'lineas_preview' => array_slice($lineas, 0, 8),
            ];
        }

        usort($lista, static function (array $a, array $b): int {
            return ($b['waitry_order_id'] ?? 0) <=> ($a['waitry_order_id'] ?? 0);
        });

        return [
            'ok' => true,
            'ordenes' => $lista,
            'filtro' => [
                'minutos_atras' => $rango['minutos_atras'],
                'from' => $rango['from'],
                'to' => $rango['to'],
            ],
        ];
    }

    /**
     * Rango from/to para getOrdersPOS; por defecto últimos N minutos (WAITRY_GET_ORDERS_MINUTOS_ATRAS).
     *
     * @return array{
     *     from:?string,
     *     to:?string,
     *     minutos_atras:int,
     *     desde_limite:?Carbon
     * }
     */
    private function resolverRangoFechasConsulta(?string $desde, ?string $hasta): array
    {
        $desde = $desde !== null ? trim($desde) : '';
        $hasta = $hasta !== null ? trim($hasta) : '';

        if ($desde !== '') {
            return [
                'from' => $desde,
                'to' => $hasta !== '' ? $hasta : null,
                'minutos_atras' => 0,
                'desde_limite' => null,
            ];
        }

        $minutos = max(0, (int) config('waitry.get_orders_minutos_atras', 20));
        if ($minutos <= 0) {
            return [
                'from' => null,
                'to' => null,
                'minutos_atras' => 0,
                'desde_limite' => null,
            ];
        }

        $tz = (string) config('app.timezone', 'UTC');
        $hastaDt = Carbon::now($tz);
        $desdeDt = $hastaDt->copy()->subMinutes($minutos);

        return [
            'from' => $desdeDt->toIso8601String(),
            'to' => $hastaDt->toIso8601String(),
            'minutos_atras' => $minutos,
            'desde_limite' => $desdeDt,
        ];
    }

    /**
     * Filtro defensivo por placed_at si Waitry devuelve órdenes fuera del rango pedido.
     *
     * @param  array<string, mixed>  $orden
     */
    private function ordenDentroDeVentanaHoraria(array $orden, ?Carbon $desdeLimite): bool
    {
        if ($desdeLimite === null) {
            return true;
        }

        $placed = $orden['placed_at'] ?? $orden['created_at'] ?? null;
        if ($placed === null || $placed === '') {
            return true;
        }

        try {
            return Carbon::parse((string) $placed)->gte($desdeLimite);
        } catch (\Throwable) {
            return true;
        }
    }

    /**
     * @return array{ok:bool,cuenta?:CuentaGastronomia,errores?:list<string>,error?:string}
     */
    public function importarOrdenEnCuenta(
        ConfiguracionPuntoventaGastronomia $cfg,
        int $waitryOrderId,
        array $datosApertura = [],
        ?int $cuentaId = null,
    ): array {
        if (! config('waitry.habilitado', false)) {
            return ['ok' => false, 'error' => 'Integración Waitry deshabilitada.'];
        }

        $empresaId = (int) $cfg->empresa_id;
        $orden = $this->obtenerOrdenPorId($empresaId, $waitryOrderId);
        if ($orden === null) {
            return ['ok' => false, 'error' => 'Orden Waitry #'.$waitryOrderId.' no encontrada o ya pagada.'];
        }

        $lineasWaitry = $this->extraerLineasDesdeOrden($orden);
        if ($lineasWaitry === []) {
            return ['ok' => false, 'error' => 'La orden Waitry no tiene ítems para importar.'];
        }

        $cuenta = null;
        if ($cuentaId !== null && $cuentaId > 0) {
            $cuenta = CuentaGastronomia::query()
                ->where('id', $cuentaId)
                ->where('empresa_id', $empresaId)
                ->where('estado', CuentaGastronomia::ESTADO_ABIERTA)
                ->first();
            if ($cuenta === null) {
                return ['ok' => false, 'error' => 'Cuenta no encontrada o no está abierta.'];
            }
            if ($cuenta->lineas()->exists()) {
                return ['ok' => false, 'error' => 'La cuenta ya tiene consumos; abra una cuenta vacía o cree una nueva.'];
            }
        } else {
            try {
                $cuenta = $this->cuentaService->abrirCuentaLibre($empresaId, $cfg, $datosApertura);
            } catch (\Throwable $e) {
                return ['ok' => false, 'error' => $e->getMessage()];
            }
        }

        $errores = [];
        foreach ($lineasWaitry as $ln) {
            $sku = (string) $ln['sku'];
            $articulo = $this->resolverArticuloPorCodigoWaitry($cfg, $sku);
            if ($articulo === null) {
                $errores[] = 'SKU «'.$sku.'» ('.($ln['titulo'] ?? '').') no está en catálogo gastronomía.';

                continue;
            }

            try {
                $this->cuentaService->agregarLinea(
                    $cuenta,
                    (int) $articulo->id,
                    (float) $ln['cantidad'],
                    (float) $ln['precio_unitario'],
                    [],
                    0.0,
                );
            } catch (\Throwable $e) {
                $errores[] = $sku.': '.$e->getMessage();
            }
        }

        $cuenta->waitry_order_id = $waitryOrderId;
        $cuenta->save();

        $cuenta = $this->cuentaService->cuentaConLineas($cuenta->id);

        if ($cuenta->lineas->isEmpty()) {
            $cuenta->delete();

            return [
                'ok' => false,
                'error' => 'No se pudo importar ningún ítem.',
                'errores' => $errores,
            ];
        }

        if ($errores !== []) {
            Log::warning('waitry.importacion_parcial', [
                'waitry_order_id' => $waitryOrderId,
                'cuenta_id' => $cuenta->id,
                'errores' => $errores,
            ]);
        }

        return [
            'ok' => true,
            'cuenta' => $cuenta,
            'errores' => $errores,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function obtenerOrdenPorId(int $empresaId, int $orderId): ?array
    {
        $placeId = $this->resolverPlaceId($empresaId);
        if ($placeId === null) {
            return null;
        }

        $url = (string) config('waitry.get_orders_url');
        $resultado = $this->httpClient->getJson($url, [
            'placeId' => (string) $placeId,
            'orderId' => $orderId,
        ], 'get_orders_pos_detalle');

        if (! ($resultado['ok'] ?? false)) {
            return null;
        }

        $data = is_array($resultado['data'] ?? null) ? $resultado['data'] : [];
        $ordenes = $data['orders'] ?? $data['response']['orders'] ?? [];
        if (! is_array($ordenes)) {
            return null;
        }

        foreach ($ordenes as $orden) {
            if (! is_array($orden)) {
                continue;
            }
            $id = (int) ($orden['id'] ?? $orden['orderId'] ?? 0);
            if ($id === $orderId && $this->ordenPendienteDePago($orden)) {
                return $orden;
            }
        }

        return null;
    }

    /**
     * Sin pago en Waitry: campo paid explícito (push) o sin cobro en payment (getOrders pág. 21).
     */
    private function ordenPendienteDePago(array $orden): bool
    {
        if (array_key_exists('paid', $orden)) {
            $paid = $orden['paid'];

            return in_array($paid, [0, '0', false], true);
        }

        $estado = mb_strtolower(trim((string) ($orden['current_state'] ?? '')));
        if (in_array($estado, ['closed', 'cancelled', 'rejected'], true)) {
            return false;
        }

        $payment = $orden['payment'] ?? null;
        if (! is_array($payment)) {
            return true;
        }

        $totalFee = $payment['total_fee'] ?? null;
        $monto = 0.0;
        if (is_array($totalFee) && isset($totalFee['amount'])) {
            $monto = (float) $totalFee['amount'];
        } elseif (is_numeric($totalFee)) {
            $monto = (float) $totalFee;
        }

        $tipo = mb_strtolower(trim((string) ($payment['type'] ?? '')));

        return $monto <= 0.0001 && $tipo === '';
    }

    /**
     * Ítems del carrito getOrdersPOS (cart.items: external_id, quantity, price).
     *
     * @return list<array{sku:string,titulo:string,cantidad:float,precio_unitario:float}>
     */
    public function extraerLineasDesdeOrden(array $orden): array
    {
        $items = $orden['cart']['items'] ?? $orden['items'] ?? null;
        if (! is_array($items) || $items === []) {
            if (! empty($orden['orderItems']) && is_array($orden['orderItems'])) {
                return $this->extraerLineasFormatoPush($orden['orderItems']);
            }

            return [];
        }

        $lineas = [];
        foreach ($items as $row) {
            if (! is_array($row)) {
                continue;
            }

            $sku = trim((string) ($row['external_id'] ?? $row['item']['externalId'] ?? $row['item']['external_id'] ?? ''));
            if ($sku === '') {
                continue;
            }

            $cantidad = (float) ($row['quantity'] ?? $row['count'] ?? 1);
            if ($cantidad <= 0.) {
                continue;
            }

            $precioUnitario = $this->precioUnitarioDesdeItemWaitry($row, $cantidad);
            if ($precioUnitario < 0.) {
                continue;
            }

            $lineas[] = [
                'sku' => $sku,
                'titulo' => trim((string) ($row['title'] ?? $row['item']['name'] ?? $sku)),
                'cantidad' => $cantidad,
                'precio_unitario' => $precioUnitario,
            ];
        }

        return $lineas;
    }

    /**
     * @param  list<array<string, mixed>>  $orderItems
     * @return list<array{sku:string,titulo:string,cantidad:float,precio_unitario:float}>
     */
    private function extraerLineasFormatoPush(array $orderItems): array
    {
        $lineas = [];
        foreach ($orderItems as $oi) {
            if (! is_array($oi)) {
                continue;
            }
            if (array_key_exists('paid', $oi) && ! in_array($oi['paid'], [0, '0', false], true)) {
                continue;
            }

            $item = is_array($oi['item'] ?? null) ? $oi['item'] : [];
            $sku = trim((string) ($item['externalId'] ?? $item['external_id'] ?? ''));
            if ($sku === '') {
                continue;
            }

            $cantidad = (float) ($oi['count'] ?? 1);
            if ($cantidad <= 0.) {
                continue;
            }

            $precio = isset($oi['price']) && is_numeric($oi['price'])
                ? (float) $oi['price']
                : (float) ($item['price'] ?? 0);

            $lineas[] = [
                'sku' => $sku,
                'titulo' => trim((string) ($item['name'] ?? $sku)),
                'cantidad' => $cantidad,
                'precio_unitario' => round($precio, 4),
            ];
        }

        return $lineas;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function precioUnitarioDesdeItemWaitry(array $row, float $cantidad): float
    {
        if (isset($row['price']) && is_numeric($row['price'])) {
            return round((float) $row['price'], 4);
        }

        $price = $row['price'] ?? null;
        if (! is_array($price)) {
            return 0.0;
        }

        if (isset($price['total_price']['amount'])) {
            $total = (float) $price['total_price']['amount'];
            $qty = max(1.0, $cantidad);

            return round($total / $qty, 4);
        }

        if (isset($price['unit_price']['amount'])) {
            return round((float) $price['unit_price']['amount'], 4);
        }

        return 0.0;
    }

    private function resolverArticuloPorCodigoWaitry(ConfiguracionPuntoventaGastronomia $cfg, string $codigo): ?Articulo
    {
        $codigo = trim($codigo);
        if ($codigo === '') {
            return null;
        }

        $candidatos = array_values(array_unique(array_filter([
            $codigo,
            mb_strtoupper($codigo, 'UTF-8'),
            GastronomiaSkuCatalogoSupport::skuDesdeSufijoDigitos($codigo),
            GastronomiaSkuCatalogoSupport::prefijo().$codigo,
            mb_strtoupper(GastronomiaSkuCatalogoSupport::prefijo().$codigo, 'UTF-8'),
        ])));

        foreach ($candidatos as $sku) {
            $articulo = $this->cuentaService->buscarArticuloCatalogoPorSku($cfg, $sku);
            if ($articulo !== null) {
                return $articulo;
            }
        }

        return null;
    }

    private function resolverPlaceId(int $empresaId): ?int
    {
        $map = config('waitry.place_id_por_empresa', []);
        if (! is_array($map)) {
            return null;
        }

        $placeId = (int) ($map[$empresaId] ?? 0);

        return $placeId > 0 ? $placeId : null;
    }
}

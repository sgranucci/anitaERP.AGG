<?php

namespace App\Services\Ventas\Gastronomia\Waitry;

use App\Models\Ventas\CuentaGastronomia;
use App\Models\Ventas\Venta;
use App\Models\Ventas\WaitryComandaEnvio;
use App\Support\Ventas\Waitry\WaitryImpuestoLineaSupport;
use App\Support\Ventas\Waitry\WaitryMediosPagoFromVentaSupport;
use App\Support\Ventas\Waitry\WaitryPaymentPayloadSupport;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

/**
 * Envía comandas a cocina Waitry (Push Orders / pushExternalOrder) tras facturar en gastronomía.
 *
 * external_id = venta.id. La respuesta debe devolver el mismo externalId y orderId para cuenta_gastronomia.
 *
 * @see docs/waitry/README.md
 */
final class WaitryComandaService
{
    public function __construct(
        private readonly WaitryHttpClient $httpClient,
        private readonly WaitryAuthService $authService,
        private readonly WaitryComandaEnvioService $envioService,
        private readonly WaitryPaymentPayloadSupport $paymentPayloadSupport,
        private readonly WaitryMediosPagoFromVentaSupport $mediosPagoFromVentaSupport,
    ) {
    }

    /**
     * @param  list<array{cuentacaja_id:int,moneda_id:int,monto:float,cotizacion?:float|null,observacion?:string|null}>  $mediosPago
     * @return array{ok:bool,omitida?:bool,mensaje?:string,waitry_order_id?:int|string|null}
     */
    public function enviarComandaTrasFactura(
        int $ventaId,
        CuentaGastronomia $cuenta,
        string $facturaTxt,
        bool $pagada,
        array $mediosPago = [],
    ): array {
        if (! config('waitry.habilitado', false)) {
            return ['ok' => true, 'omitida' => true, 'mensaje' => 'Integración Waitry deshabilitada.'];
        }

        if (! $this->authService->credencialesCompletas()) {
            Log::warning('waitry.comanda.credenciales_incompletas', ['venta_id' => $ventaId]);

            return [
                'ok' => false,
                'mensaje' => 'Waitry: faltan credenciales de API en configuración.',
            ];
        }

        $empresaId = (int) $cuenta->empresa_id;
        $table = $this->resolverTable($empresaId);
        if ($table === null) {
            Log::warning('waitry.comanda.table_vacio', ['venta_id' => $ventaId, 'empresa_id' => $empresaId]);

            return [
                'ok' => false,
                'mensaje' => 'Waitry: configure WAITRY_TABLE_POR_EMPRESA para la empresa '.$empresaId.'.',
            ];
        }

        $placeId = $this->resolverPlaceId($empresaId);
        if ($placeId === null) {
            return [
                'ok' => false,
                'mensaje' => 'Waitry: no hay placeId configurado para la empresa '.$empresaId.'.',
            ];
        }

        $envioExistente = $this->envioService->buscarPorVenta($ventaId);
        if ($envioExistente !== null && $envioExistente->estado === WaitryComandaEnvio::ESTADO_ENVIADO) {
            return [
                'ok' => true,
                'waitry_order_id' => $envioExistente->waitry_order_id,
            ];
        }

        $cuenta->refresh();
        if ($cuenta->waitry_order_id !== null && (int) $cuenta->waitry_order_id > 0) {
            return [
                'ok' => true,
                'waitry_order_id' => $cuenta->waitry_order_id,
            ];
        }

        $externalId = $this->externalIdDesdeVenta($ventaId);
        $envio = $envioExistente ?? $this->envioService->crearRegistro(
            $ventaId,
            $cuenta->id,
            $empresaId,
            $placeId,
            $externalId,
            $pagada,
        );

        return $this->ejecutarEnvio($envio, $cuenta, $facturaTxt, $pagada, $table, $placeId, $mediosPago);
    }

    public function procesarEnvioRegistro(WaitryComandaEnvio $envio): void
    {
        if (! config('waitry.habilitado', false)) {
            return;
        }

        if ($envio->estado === WaitryComandaEnvio::ESTADO_ENVIADO) {
            return;
        }

        $cuenta = $envio->cuentaGastronomia;
        if ($cuenta === null) {
            $this->envioService->marcarOmitido($envio, 'Sin cuenta_gastronomia asociada');

            return;
        }

        $empresaId = (int) $envio->empresa_id;
        $table = $this->resolverTable($empresaId);
        $placeId = (int) $envio->place_id;
        if ($table === null || $placeId <= 0) {
            $this->envioService->registrarFallo($envio, 'Configuración table/placeId inválida');

            return;
        }

        $ventaId = (int) $envio->venta_id;
        $mediosPago = (bool) $envio->pagada
            ? $this->mediosPagoFromVentaSupport->desdeVentaId($ventaId)
            : [];

        $this->ejecutarEnvio(
            $envio,
            $cuenta,
            '',
            (bool) $envio->pagada,
            $table,
            $placeId,
            $mediosPago,
        );
    }

    /**
     * @param  list<array{cuentacaja_id:int,moneda_id:int,monto:float,cotizacion?:float|null,observacion?:string|null}>  $mediosPago
     * @return array{ok:bool,omitida?:bool,mensaje?:string,waitry_order_id?:int|string|null}
     */
    private function ejecutarEnvio(
        WaitryComandaEnvio $envio,
        CuentaGastronomia $cuenta,
        string $facturaTxt,
        bool $pagada,
        array $table,
        int $placeId,
        array $mediosPago = [],
    ): array {
        $ventaId = (int) $envio->venta_id;
        $externalId = $this->externalIdDesdeVenta($ventaId);

        $venta = Venta::query()
            ->with(['venta_emisiones.articulos'])
            ->find($ventaId);

        if (! $venta) {
            $this->envioService->registrarFallo($envio, 'Venta '.$ventaId.' no encontrada');

            return ['ok' => false, 'mensaje' => 'Waitry: venta '.$ventaId.' no encontrada.'];
        }

        try {
            $payload = $this->armarPayloadOrden(
                $venta,
                $cuenta,
                $placeId,
                $table,
                $externalId,
                $pagada,
                $facturaTxt,
                $mediosPago,
            );
        } catch (InvalidArgumentException $e) {
            $this->envioService->registrarFallo($envio, $e->getMessage());
            Log::warning('waitry.comanda.payload_invalido', [
                'venta_id' => $ventaId,
                'msg' => $e->getMessage(),
            ]);

            return ['ok' => false, 'mensaje' => $e->getMessage()];
        }

        $this->envioService->marcarEnviando($envio);

        $url = (string) config('waitry.push_order_url');
        $resultado = $this->httpClient->postJson($url, $payload, 'push_external_order');

        if (! $resultado['ok']) {
            $error = 'Waitry: '.($resultado['error'] ?? 'error al enviar comanda.');
            $this->envioService->registrarFallo(
                $envio,
                $error,
                isset($resultado['http_code']) ? (int) $resultado['http_code'] : null,
                is_array($resultado['data'] ?? null) ? $resultado['data'] : null,
                $payload,
            );
            $this->envioService->encolarReintento($envio->fresh());

            Log::error('waitry.comanda.fallo', [
                'venta_id' => $ventaId,
                'external_id' => $externalId,
                'place_id' => $placeId,
                'http' => $resultado['http_code'] ?? null,
                'error' => $resultado['error'] ?? null,
            ]);

            return ['ok' => false, 'mensaje' => $error];
        }

        $data = is_array($resultado['data'] ?? null) ? $resultado['data'] : [];
        $interpretacion = $this->interpretarRespuestaPush($data, $ventaId);

        if (! $interpretacion['ok']) {
            $mensaje = 'Waitry: '.$interpretacion['mensaje'];
            $this->envioService->registrarFallo(
                $envio,
                $mensaje,
                isset($resultado['http_code']) ? (int) $resultado['http_code'] : null,
                $data,
                $payload,
            );
            $this->envioService->encolarReintento($envio->fresh());

            Log::error('waitry.comanda.rechazada', [
                'venta_id' => $ventaId,
                'external_id' => $externalId,
                'respuesta' => $data,
                'detalle' => $interpretacion['mensaje'],
            ]);

            return ['ok' => false, 'mensaje' => $mensaje];
        }

        $orderId = $interpretacion['order_id'] ?? null;
        $this->envioService->marcarExito($envio, $orderId, $data);

        Log::info('waitry.comanda.ok', [
            'venta_id' => $ventaId,
            'external_id' => $externalId,
            'waitry_order_id' => $orderId,
            'place_id' => $placeId,
            'payment_type' => $payload['payment']['type'] ?? null,
        ]);

        return [
            'ok' => true,
            'waitry_order_id' => $orderId,
        ];
    }

    private function externalIdDesdeVenta(int $ventaId): string
    {
        return (string) $ventaId;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function resolverTable(int $empresaId): ?array
    {
        $map = config('waitry.table_por_empresa', []);
        if (is_array($map)) {
            $table = $map[$empresaId] ?? null;
            if (is_array($table) && $table !== []) {
                return $table;
            }
        }

        $legacy = config('waitry.table', []);
        if (is_array($legacy) && $legacy !== []) {
            return $legacy;
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $table
     * @param  list<array{cuentacaja_id:int,moneda_id:int,monto:float,cotizacion?:float|null,observacion?:string|null}>  $mediosPago
     * @return array<string, mixed>
     */
    private function armarPayloadOrden(
        Venta $venta,
        CuentaGastronomia $cuenta,
        int $placeId,
        array $table,
        string $externalId,
        bool $pagada,
        string $facturaTxt,
        array $mediosPago = [],
    ): array {
        $orderItems = $this->construirOrderItems($venta);
        if ($orderItems === []) {
            throw new InvalidArgumentException('Waitry: la venta no tiene ítems para enviar a cocina.');
        }

        $totalAmount = round(array_sum(array_map(
            static fn (array $item): float => (float) $item['price'] * (int) $item['count'],
            $orderItems
        )), 2);

        $payload = [
            'timestamp' => [
                'date' => Carbon::now('UTC')->format('Y-m-d H:i:s.u'),
                'timezone_type' => null,
                'timezone' => null,
            ],
            'placeId' => $placeId,
            'table' => $table,
            'paid' => $pagada,
            'external_id' => $externalId,
            'totalAmount' => $totalAmount,
            'orderItems' => $orderItems,
        ];

        if ($pagada && $mediosPago !== []) {
            try {
                $payload['payment'] = $this->paymentPayloadSupport->armarBloquePayment(
                    $mediosPago,
                    (int) $cuenta->empresa_id,
                );
            } catch (InvalidArgumentException $e) {
                Log::warning('waitry.comanda.payment_omitido', [
                    'venta_id' => (int) $venta->id,
                    'cuenta_id' => $cuenta->id,
                    'msg' => $e->getMessage(),
                ]);
            }
        }

        $notas = trim($facturaTxt);
        if ($notas !== '') {
            $payload['notes'] = mb_substr($notas, 0, 255);
        }

        $cuenta->loadMissing('cliente');
        $cliente = $cuenta->cliente;
        if ($cliente !== null) {
            $nombre = trim((string) ($cliente->nombre ?? $cliente->fantasia ?? ''));
            if ($nombre !== '') {
                $payload['client_name'] = mb_substr($nombre, 0, 120);
            }
            $doc = trim((string) ($cliente->numerodocumento ?? ''));
            if ($doc !== '') {
                $payload['external_client_id'] = mb_substr($doc, 0, 32);
            }
        }

        return $payload;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function construirOrderItems(Venta $venta): array
    {
        $items = [];
        $tsItem = [
            'date' => Carbon::now('UTC')->format('Y-m-d\TH:i:sP'),
            'timezone_type' => 0,
            'timezone' => '+00:00',
        ];

        foreach ($venta->venta_emisiones as $emision) {
            $cantidad = (float) $emision->cantidad;
            if ($cantidad <= 0.) {
                continue;
            }

            $precio = round((float) $emision->precio, 4);
            if ($precio < 0.) {
                continue;
            }

            $articulo = $emision->articulos;
            $sku = trim((string) ($articulo->sku ?? ''));
            if ($sku === '') {
                $sku = 'ART-'.(int) $emision->articulo_id;
            }

            $tax = WaitryImpuestoLineaSupport::impuestoSobrePrecioFinal(
                $precio,
                (int) $emision->impuesto_id,
                (string) ($emision->incluyeimpuesto ?? 'N'),
            );

            $nombre = trim((string) ($articulo->descripcion ?? $emision->detalle ?? $sku));
            $count = (int) max(1, round($cantidad));
            $subtotal = round($precio * $count, 2);

            $items[] = [
                'timestamp' => $tsItem,
                'count' => $count,
                'notes' => null,
                'price' => $precio,
                'tax' => $tax,
                'discount' => 0.0,
                'discountPrice' => null,
                'subtotal' => $subtotal,
                'paid' => false,
                'item' => [
                    'name' => $nombre,
                    'price' => $precio,
                    'externalId' => $sku,
                    'externalCode' => $sku,
                ],
                'orderItemVariations' => [],
            ];
        }

        return $items;
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

    /**
     * @param  array<string, mixed>  $data
     * @return array{ok:bool,mensaje:string,order_id?:int|string|null}
     */
    private function interpretarRespuestaPush(array $data, int $ventaIdEsperado): array
    {
        $okFlag = $data['ok'] ?? null;
        if ($okFlag === false) {
            $msg = trim((string) ($data['msg'] ?? $data['message'] ?? 'pedido rechazado'));

            return ['ok' => false, 'mensaje' => $msg];
        }

        $response = $data['response'] ?? null;
        if (is_string($response) && stripos($response, 'error') !== false) {
            return ['ok' => false, 'mensaje' => $response];
        }

        if (is_array($response)) {
            $error = trim((string) ($response['error'] ?? ''));
            if ($error !== '') {
                return ['ok' => false, 'mensaje' => $error];
            }

            $validacion = $this->validarExternalIdEnRespuesta($response, $ventaIdEsperado);
            if (! $validacion['ok']) {
                return $validacion;
            }

            $orderId = $response['orderId'] ?? $response['order_id'] ?? null;
            if ($orderId === null || ! is_numeric($orderId)) {
                return ['ok' => false, 'mensaje' => 'respuesta sin orderId'];
            }

            return [
                'ok' => true,
                'mensaje' => '',
                'order_id' => $orderId,
            ];
        }

        if (is_scalar($response) && trim((string) $response) !== '' && stripos((string) $response, 'finish') === false) {
            return ['ok' => false, 'mensaje' => (string) $response];
        }

        $orderId = $data['orderId'] ?? $data['order_id'] ?? null;
        $externalEnRaiz = $data['externalId'] ?? $data['external_id'] ?? null;
        if ($externalEnRaiz !== null) {
            $validacion = $this->validarExternalIdEnRespuesta(
                ['externalId' => $externalEnRaiz],
                $ventaIdEsperado,
            );
            if (! $validacion['ok']) {
                return $validacion;
            }
        }

        if ($orderId !== null && is_numeric($orderId)) {
            return ['ok' => true, 'mensaje' => '', 'order_id' => $orderId];
        }

        if ($okFlag === true) {
            return ['ok' => false, 'mensaje' => 'respuesta sin orderId'];
        }

        return ['ok' => false, 'mensaje' => 'respuesta inesperada de Waitry'];
    }

    /**
     * @param  array<string, mixed>  $response
     * @return array{ok:bool,mensaje:string}
     */
    private function validarExternalIdEnRespuesta(array $response, int $ventaIdEsperado): array
    {
        $externalId = $response['externalId'] ?? $response['external_id'] ?? null;
        if ($externalId === null || $externalId === '') {
            return ['ok' => true, 'mensaje' => ''];
        }

        $esperado = (string) $ventaIdEsperado;
        $recibido = trim((string) $externalId);
        if ($recibido === $esperado || (is_numeric($recibido) && (int) $recibido === $ventaIdEsperado)) {
            return ['ok' => true, 'mensaje' => ''];
        }

        return [
            'ok' => false,
            'mensaje' => 'externalId de respuesta ('.$recibido.') no coincide con venta '.$esperado,
        ];
    }
}

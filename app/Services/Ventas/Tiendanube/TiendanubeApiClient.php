<?php

namespace App\Services\Ventas\Tiendanube;

use App\Support\Ventas\Tiendanube\TiendanubeApiHealthSupport;
use App\Support\Ventas\Tiendanube\TiendanubeTiendasSupport;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Cliente HTTP API Tiendanube / Nuvemshop v1.
 * Sin argumentos usa la tienda Ferli. paraStoreId() ata otra tienda configurada.
 */
final class TiendanubeApiClient
{
    private bool $registrarSalud = true;

    public function __construct(
        private readonly ?string $storeIdForzado = null,
        private readonly ?string $tokenForzado = null,
    ) {
    }

    public static function paraStoreId(string $storeId): self
    {
        $tienda = TiendanubeTiendasSupport::porStoreId($storeId);
        if ($tienda === null) {
            throw new RuntimeException(
                'La tienda Tiendanube '.trim($storeId).' no tiene token en .env.'
            );
        }

        return new self($tienda['store_id'], $tienda['access_token']);
    }

    /** Ping de health: no pisa el estado global de la otra tienda en cada request. */
    public function sinRegistrarSalud(): self
    {
        $this->registrarSalud = false;

        return $this;
    }

    public function storeId(): string
    {
        if ($this->storeIdForzado !== null && $this->storeIdForzado !== '') {
            return $this->storeIdForzado;
        }

        return trim((string) config('tiendanube.store_id', ''));
    }

    public function token(): string
    {
        if ($this->tokenForzado !== null && $this->tokenForzado !== '') {
            return $this->tokenForzado;
        }

        return trim((string) config('tiendanube.access_token', ''));
    }

    public function configurado(): bool
    {
        return $this->storeId() !== '' && $this->token() !== '';
    }

    /**
     * @param  array<string,scalar|null>  $query
     * @return array{ok:bool,status:int,data?:mixed,error?:string,headers?:array<string,list<string>>}
     */
    public function get(string $path, array $query = []): array
    {
        return $this->request('GET', $path, $query);
    }

    /**
     * @param  array<string,mixed>  $body
     * @return array{ok:bool,status:int,data?:mixed,error?:string}
     */
    public function post(string $path, array $body = []): array
    {
        return $this->request('POST', $path, [], $body);
    }

    /**
     * @param  array<string,mixed>  $body
     * @return array{ok:bool,status:int,data?:mixed,error?:string}
     */
    public function put(string $path, array $body = []): array
    {
        return $this->request('PUT', $path, [], $body);
    }

    /**
     * @return array{ok:bool,status:int,data?:mixed,error?:string}
     */
    public function delete(string $path): array
    {
        return $this->request('DELETE', $path);
    }

    /**
     * Lista pedidos paginados. page empieza en 1.
     *
     * @param  array<string,scalar|null>  $filtros
     * @return array{ok:bool,status:int,data?:list<array<string,mixed>>,error?:string,total_pages?:int}
     */
    public function listarPedidos(array $filtros = [], int $page = 1, ?int $perPage = null): array
    {
        $perPage = $perPage ?? (int) config('tiendanube.sync_page_size', 50);
        $query = array_merge($filtros, [
            'page' => $page,
            'per_page' => $perPage,
        ]);

        return $this->get('orders', $query);
    }

    /**
     * @return array{ok:bool,status:int,data?:array<string,mixed>,error?:string}
     */
    public function obtenerPedido(int $orderId): array
    {
        return $this->get('orders/'.$orderId);
    }

    /**
     * Publica factura en el pedido vía metafield oficial `nfe/list` (requiere write_orders).
     * Docs: GET/POST/PUT metafields — no existe /orders/{id}/invoices.
     *
     * @param  array{key:string,link?:string|null,fulfillment_order_id?:string|null}  $invoice
     * @return array{ok:bool,status:int,data?:mixed,error?:string}
     */
    public function crearInvoice(int $orderId, array $invoice): array
    {
        $key = trim((string) ($invoice['key'] ?? ''));
        if ($key === '') {
            return ['ok' => false, 'status' => 0, 'error' => 'Invoice sin key (CAE/número)'];
        }

        $entry = array_filter([
            'key' => $key,
            'link' => isset($invoice['link']) && $invoice['link'] !== '' ? (string) $invoice['link'] : null,
            'fulfillment_order_id' => isset($invoice['fulfillment_order_id']) && $invoice['fulfillment_order_id'] !== ''
                ? (string) $invoice['fulfillment_order_id']
                : null,
        ], static fn ($v) => $v !== null);

        $existente = $this->get('metafields/orders', [
            'per_page' => 1,
            'owner_id' => $orderId,
            'namespace' => 'nfe',
            'key' => 'list',
            'fields' => 'id,value',
        ]);
        if (! ($existente['ok'] ?? false)) {
            return $existente;
        }

        $rows = is_array($existente['data'] ?? null) ? $existente['data'] : [];
        $meta = $rows[0] ?? null;
        $lista = [];
        if (is_array($meta) && isset($meta['value']) && is_string($meta['value']) && $meta['value'] !== '') {
            $decoded = json_decode($meta['value'], true);
            if (is_array($decoded)) {
                $lista = $decoded;
            }
        }

        foreach ($lista as $item) {
            if (is_array($item) && (string) ($item['key'] ?? '') === $key) {
                return ['ok' => true, 'status' => 200, 'data' => $meta, 'error' => null];
            }
        }
        $lista[] = $entry;
        $valueJson = json_encode($lista, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($valueJson === false) {
            return ['ok' => false, 'status' => 0, 'error' => 'No se pudo serializar lista de invoices'];
        }

        if (is_array($meta) && (int) ($meta['id'] ?? 0) > 0) {
            return $this->put('metafields/'.(int) $meta['id'], [
                'value' => $valueJson,
            ]);
        }

        return $this->post('metafields', [
            'namespace' => 'nfe',
            'key' => 'list',
            'value' => $valueJson,
            'description' => 'Lista de facturas',
            'owner_resource' => 'Order',
            'owner_id' => $orderId,
        ]);
    }

    /**
     * @param  array<string,scalar|null>  $query
     * @param  array<string,mixed>|null  $body
     * @return array{ok:bool,status:int,data?:mixed,error?:string,headers?:array<string,list<string>>}
     */
    private function request(string $method, string $path, array $query = [], ?array $body = null): array
    {
        if (! $this->configurado()) {
            return [
                'ok' => false,
                'status' => 0,
                'error' => 'Faltan store_id / access_token de Tiendanube en .env',
            ];
        }

        $base = rtrim((string) config('tiendanube.api_base'), '/');
        $url = $base.'/'.$this->storeId().'/'.ltrim($path, '/');

        try {
            $pending = Http::withHeaders([
                'Authentication' => 'bearer '.$this->token(),
                'User-Agent' => (string) config('tiendanube.user_agent'),
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ])->timeout(60);

            $response = match (strtoupper($method)) {
                'POST' => $pending->post($url, $body ?? []),
                'PUT' => $pending->put($url, $body ?? []),
                'DELETE' => $pending->delete($url),
                default => $pending->get($url, $query),
            };
        } catch (\Throwable $e) {
            $this->logSafe('tiendanube.api.exception', [
                'method' => $method,
                'path' => $path,
                'error' => $e->getMessage(),
            ]);

            return ['ok' => false, 'status' => 0, 'error' => $e->getMessage()];
        }

        $status = $response->status();
        $json = $response->json();
        if ($response->successful()) {
            return [
                'ok' => true,
                'status' => $status,
                'data' => $json,
                'headers' => $response->headers(),
            ];
        }

        $msg = is_array($json)
            ? (string) ($json['description'] ?? $json['message'] ?? $json['error'] ?? json_encode($json))
            : (string) $response->body();

        $this->logSafe('tiendanube.api.error', [
            'method' => $method,
            'path' => $path,
            'status' => $status,
            'error' => mb_substr($msg, 0, 500),
        ]);

        if ($this->registrarSalud && TiendanubeApiHealthSupport::esErrorAuth($status, $msg)) {
            TiendanubeApiHealthSupport::marcarAuthInvalida($status, $msg, $this->storeId());
            $msg = TiendanubeApiHealthSupport::MENSAJE_TOKEN_INVALIDO;
        }

        return [
            'ok' => false,
            'status' => $status,
            'error' => $msg !== '' ? $msg : 'Error HTTP '.$status,
            'data' => $json,
        ];
    }

    /** @param  array<string,mixed>  $context */
    private function logSafe(string $message, array $context = []): void
    {
        try {
            Log::warning($message, $context);
        } catch (\Throwable) {
            // storage/logs puede no ser escribible desde CLI; no tumbar la request
        }
    }

    public function assertConfigurado(): void
    {
        if (! $this->configurado()) {
            throw new RuntimeException('Configure el store_id y el access_token de Tiendanube en .env');
        }
    }
}

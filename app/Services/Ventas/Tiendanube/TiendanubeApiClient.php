<?php

namespace App\Services\Ventas\Tiendanube;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Cliente HTTP API Tiendanube / Nuvemshop v1.
 */
final class TiendanubeApiClient
{
    public function storeId(): string
    {
        return trim((string) config('tiendanube.store_id', ''));
    }

    public function token(): string
    {
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
     * Publica factura asociada al pedido (requiere write_orders).
     *
     * @param  array<string,mixed>  $invoice
     * @return array{ok:bool,status:int,data?:mixed,error?:string}
     */
    public function crearInvoice(int $orderId, array $invoice): array
    {
        return $this->post('orders/'.$orderId.'/invoices', $invoice);
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
                'error' => 'Faltan TIENDANUBE_STORE_ID / TIENDANUBE_ACCESS_TOKEN en .env',
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
            throw new RuntimeException('Configure TIENDANUBE_STORE_ID y TIENDANUBE_ACCESS_TOKEN en .env');
        }
    }
}

<?php

namespace App\Services\Ventas\Mercadolibre;

use App\Support\Ventas\Mercadolibre\MercadolibreTokenSupport;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Cliente HTTP API Mercado Libre.
 * Renueva access_token con refresh_token ante 401.
 */
final class MercadolibreApiClient
{
    public function __construct(
        private readonly ?MercadolibreOauthService $oauth = null,
    ) {
    }

    public function configurado(): bool
    {
        return MercadolibreTokenSupport::configurado();
    }

    /**
     * @param  array<string,scalar|null>  $query
     * @return array{ok:bool,status:int,data?:mixed,error?:string}
     */
    public function get(string $path, array $query = []): array
    {
        return $this->request('GET', $path, $query);
    }

    /**
     * @param  array<string,mixed>  $body
     * @return array{ok:bool,status:int,data?:mixed,error?:string}
     */
    public function post(string $path, array $body = [], bool $asMultipart = false): array
    {
        return $this->request('POST', $path, [], $body, $asMultipart);
    }

    /**
     * @return array{ok:bool,status:int,data?:mixed,error?:string}
     */
    public function obtenerOrden(int|string $orderId): array
    {
        return $this->get('/orders/'.$orderId);
    }

    /**
     * @param  array<string,scalar|null>  $query
     * @return array{ok:bool,status:int,data?:mixed,error?:string}
     */
    public function buscarOrdenes(array $query): array
    {
        $sellerId = MercadolibreTokenSupport::sellerId();
        if ($sellerId === '') {
            return ['ok' => false, 'status' => 0, 'error' => 'Falta seller_id (autorizá la app).'];
        }
        $query['seller'] = $sellerId;

        return $this->get('/orders/search', $query);
    }

    /**
     * @return array{ok:bool,status:int,data?:mixed,error?:string}
     */
    public function billingInfo(string $billingInfoId): array
    {
        $site = trim((string) config('mercadolibre.site_id', 'MLA'));

        return $this->get('/orders/billing-info/'.$site.'/'.$billingInfoId);
    }

    /**
     * Sube PDF fiscal al pack (o order si no hay pack).
     * Si ya hay uno, lo borra y vuelve a subir (MeLi solo permite un PDF por pack).
     *
     * @return array{ok:bool,status:int,data?:mixed,error?:string}
     */
    public function subirDocumentoFiscal(string $packOrOrderId, string $pdfPath, string $fileName): array
    {
        if (! is_readable($pdfPath)) {
            return ['ok' => false, 'status' => 0, 'error' => 'PDF no legible: '.$pdfPath];
        }

        $resp = $this->request(
            'POST',
            '/packs/'.$packOrOrderId.'/fiscal_documents',
            [],
            [
                'fiscal_document' => fopen($pdfPath, 'r'),
                'file_name' => $fileName,
            ],
            true
        );

        $err = strtolower((string) ($resp['error'] ?? ''));
        if (($resp['ok'] ?? false) || ! str_contains($err, 'already exists')) {
            return $resp;
        }

        $this->request('DELETE', '/packs/'.$packOrOrderId.'/fiscal_documents');

        return $this->request(
            'POST',
            '/packs/'.$packOrOrderId.'/fiscal_documents',
            [],
            [
                'fiscal_document' => fopen($pdfPath, 'r'),
                'file_name' => $fileName,
            ],
            true
        );
    }

    /**
     * @param  array<string,scalar|null>  $query
     * @param  array<string,mixed>  $body
     * @return array{ok:bool,status:int,data?:mixed,error?:string}
     */
    private function request(
        string $method,
        string $path,
        array $query = [],
        array $body = [],
        bool $multipart = false,
        bool $retried = false,
    ): array {
        $token = MercadolibreTokenSupport::accessToken();
        if ($token === '') {
            return ['ok' => false, 'status' => 0, 'error' => 'Sin access_token Mercado Libre.'];
        }

        $base = rtrim((string) config('mercadolibre.api_base', 'https://api.mercadolibre.com'), '/');
        $url = $base.'/'.ltrim($path, '/');
        $ua = (string) config('mercadolibre.user_agent', 'anitaERP');

        try {
            $pending = Http::withToken($token)
                ->withHeaders(['User-Agent' => $ua, 'Accept' => 'application/json'])
                ->timeout(60);

            if ($multipart) {
                $response = $pending->asMultipart()->send($method, $url, [
                    'query' => $query,
                    'multipart' => $this->multipartParts($body),
                ]);
            } elseif ($method === 'GET') {
                $response = $pending->get($url, $query);
            } else {
                $response = $pending->asJson()->send($method, $url, [
                    'query' => $query,
                    'json' => $body,
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('mercadolibre.api.exception', ['path' => $path, 'error' => $e->getMessage()]);

            return ['ok' => false, 'status' => 0, 'error' => $e->getMessage()];
        }

        $status = $response->status();
        $data = $response->json();
        if (! is_array($data) && ! is_null($data)) {
            $data = ['raw' => $response->body()];
        }

        if ($status === 401 && ! $retried) {
            $oauth = $this->oauth ?? app(MercadolibreOauthService::class);
            $refresco = $oauth->refrescarSiPuede();
            if ($refresco['ok'] ?? false) {
                return $this->request($method, $path, $query, $body, $multipart, true);
            }
        }

        if ($status >= 200 && $status < 300) {
            return ['ok' => true, 'status' => $status, 'data' => $data];
        }

        $error = is_array($data)
            ? (string) ($data['message'] ?? $data['error'] ?? $response->body())
            : $response->body();

        return ['ok' => false, 'status' => $status, 'error' => $error, 'data' => $data];
    }

    /**
     * @param  array<string,mixed>  $body
     * @return list<array{name:string,contents:mixed,filename?:string}>
     */
    private function multipartParts(array $body): array
    {
        $parts = [];
        foreach ($body as $name => $value) {
            if ($name === 'fiscal_document' && is_resource($value)) {
                $parts[] = [
                    'name' => $name,
                    'contents' => $value,
                    'filename' => (string) ($body['file_name'] ?? 'factura.pdf'),
                ];
                continue;
            }
            if ($name === 'file_name') {
                continue;
            }
            $parts[] = ['name' => (string) $name, 'contents' => is_scalar($value) ? (string) $value : json_encode($value)];
        }

        return $parts;
    }
}

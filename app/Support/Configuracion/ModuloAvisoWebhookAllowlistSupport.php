<?php

namespace App\Support\Configuracion;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Disparo HTTP solo a URLs allowlist (config/modulo_aviso.webhooks_allowlist).
 * Sin URL libre desde la UI ni desde reglas.
 */
final class ModuloAvisoWebhookAllowlistSupport
{
    /**
     * @return array<string, array{url: string, metodo: string, timeout?: int}>
     */
    public static function catalogo(): array
    {
        $raw = config('modulo_aviso.webhooks_allowlist', []);
        if (! is_array($raw)) {
            return [];
        }
        $out = [];
        foreach ($raw as $clave => $cfg) {
            if (! is_array($cfg)) {
                continue;
            }
            $url = trim((string) ($cfg['url'] ?? ''));
            if ($url === '' || ! preg_match('#^https?://#i', $url)) {
                continue;
            }
            $clave = trim((string) $clave);
            if ($clave === '') {
                continue;
            }
            $metodo = strtoupper(trim((string) ($cfg['metodo'] ?? 'POST')));
            if (! in_array($metodo, ['POST', 'PUT', 'GET'], true)) {
                $metodo = 'POST';
            }
            $out[$clave] = [
                'url' => $url,
                'metodo' => $metodo,
                'timeout' => max(3, min(30, (int) ($cfg['timeout'] ?? 10))),
            ];
        }

        return $out;
    }

    /** @return list<string> */
    public static function claves(): array
    {
        return array_keys(self::catalogo());
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{ok: bool, http_status: int|null, detalle: string}
     */
    public static function disparar(
        string $clave,
        array $payload = [],
        ?string $origen = null,
        ?int $avisoReglaId = null
    ): array {
        $clave = trim($clave);
        $catalogo = self::catalogo();
        if ($clave === '' || ! isset($catalogo[$clave])) {
            $detalle = 'Clave no allowlist o sin URL configurada: '.$clave;
            self::auditar($clave, $origen, $avisoReglaId, '', 'POST', null, false, $detalle);

            return ['ok' => false, 'http_status' => null, 'detalle' => $detalle];
        }

        $cfg = $catalogo[$clave];
        $url = $cfg['url'];
        $metodo = $cfg['metodo'];
        $timeout = $cfg['timeout'];
        $body = array_merge([
            'clave' => $clave,
            'origen' => $origen,
            'enviado_en' => now()->toIso8601String(),
        ], $payload);

        try {
            $pending = Http::timeout($timeout)
                ->acceptJson()
                ->asJson();
            $response = match ($metodo) {
                'GET' => $pending->get($url, $body),
                'PUT' => $pending->put($url, $body),
                default => $pending->post($url, $body),
            };
            $status = $response->status();
            $ok = $response->successful();
            $detalle = $ok ? 'ok' : mb_substr($response->body(), 0, 400);
            self::auditar($clave, $origen, $avisoReglaId, $url, $metodo, $status, $ok, $detalle);

            return ['ok' => $ok, 'http_status' => $status, 'detalle' => $detalle];
        } catch (\Throwable $e) {
            $detalle = mb_substr($e->getMessage(), 0, 400);
            Log::warning('ModuloAvisoWebhook: fallo', [
                'clave' => $clave,
                'error' => $detalle,
            ]);
            self::auditar($clave, $origen, $avisoReglaId, $url, $metodo, null, false, $detalle);

            return ['ok' => false, 'http_status' => null, 'detalle' => $detalle];
        }
    }

    private static function auditar(
        string $clave,
        ?string $origen,
        ?int $avisoReglaId,
        string $url,
        string $metodo,
        ?int $httpStatus,
        bool $ok,
        string $detalle
    ): void {
        if (! Schema::hasTable('modulo_aviso_webhook_log')) {
            return;
        }
        try {
            \DB::table('modulo_aviso_webhook_log')->insert([
                'clave' => mb_substr($clave !== '' ? $clave : '(vacia)', 0, 80),
                'origen' => $origen ? mb_substr($origen, 0, 80) : null,
                'aviso_regla_id' => $avisoReglaId,
                'url' => mb_substr($url !== '' ? $url : '(sin-url)', 0, 500),
                'metodo' => mb_substr($metodo, 0, 10),
                'http_status' => $httpStatus,
                'ok' => $ok,
                'detalle' => mb_substr($detalle, 0, 500),
                'ejecutado_en' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('ModuloAvisoWebhook: no se pudo auditar', ['error' => $e->getMessage()]);
        }
    }
}

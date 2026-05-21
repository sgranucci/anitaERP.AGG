<?php

namespace App\Services\Ventas\Gastronomia\Waitry;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Cliente HTTP Waitry con Bearer, timeouts, reintentos y renovación de token ante 401.
 */
final class WaitryHttpClient
{
    public function __construct(
        private readonly WaitryAuthService $authService,
    ) {
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{ok:bool,http_code:int,data:array|null,error:string|null}
     */
    public function postJson(string $url, array $payload, string $operacion): array
    {
        $reintentos = (int) config('waitry.http_reintentos', 2);
        $timeout = (int) config('waitry.http_timeout_segundos', 30);

        for ($intento = 1; $intento <= $reintentos; $intento++) {
            if ($intento > 1) {
                usleep(250_000 * ($intento - 1));
            }

            $ctx = $this->authService->contextoAutenticado();
            if (! $ctx['ok']) {
                return [
                    'ok' => false,
                    'http_code' => 0,
                    'data' => null,
                    'error' => $ctx['error'],
                ];
            }

            try {
                $response = Http::timeout($timeout)
                    ->acceptJson()
                    ->withToken($ctx['access_token'])
                    ->post($url, $payload);
            } catch (Throwable $e) {
                Log::warning("waitry.{$operacion}.conexion", [
                    'intento' => $intento,
                    'msg' => $e->getMessage(),
                ]);

                if ($intento >= $reintentos) {
                    return [
                        'ok' => false,
                        'http_code' => 0,
                        'data' => null,
                        'error' => 'No se pudo conectar con Waitry: '.$e->getMessage(),
                    ];
                }

                continue;
            }

            $httpCode = $response->status();
            $data = $response->json();
            if (! is_array($data)) {
                if ($intento < $reintentos) {
                    continue;
                }

                return [
                    'ok' => false,
                    'http_code' => $httpCode,
                    'data' => null,
                    'error' => 'Respuesta inválida de Waitry (HTTP '.$httpCode.').',
                ];
            }

            if ($httpCode === 401 && $intento < $reintentos) {
                Log::info("waitry.{$operacion}.401_renovar_token");
                $this->authService->invalidarToken();
                $this->authService->renovarTokenForzado();

                continue;
            }

            if ($httpCode < 200 || $httpCode >= 300) {
                return [
                    'ok' => false,
                    'http_code' => $httpCode,
                    'data' => $data,
                    'error' => $this->mensajeErrorHttp($data, $httpCode),
                ];
            }

            return [
                'ok' => true,
                'http_code' => $httpCode,
                'data' => $data,
                'error' => null,
            ];
        }

        return [
            'ok' => false,
            'http_code' => 0,
            'data' => null,
            'error' => 'Waitry: agotados los reintentos de comunicación.',
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function mensajeErrorHttp(array $data, int $httpCode): string
    {
        $msg = $data['msg']
            ?? $data['message']
            ?? $data['response']
            ?? $data['error']
            ?? ('HTTP '.$httpCode);

        if (is_array($msg)) {
            $msg = json_encode($msg, JSON_UNESCAPED_UNICODE);
        }

        return trim((string) $msg);
    }
}

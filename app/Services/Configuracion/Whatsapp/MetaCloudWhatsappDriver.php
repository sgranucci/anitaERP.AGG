<?php

namespace App\Services\Configuracion\Whatsapp;

use App\Contracts\Configuracion\WhatsappDriverInterface;
use App\Models\Configuracion\EmpresaWhatsappCuenta;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MetaCloudWhatsappDriver implements WhatsappDriverInterface
{
    public function enviarTexto(EmpresaWhatsappCuenta $cuenta, string $celularE164, string $texto): array
    {
        $token = $cuenta->accessTokenPlano();
        $phoneId = trim((string) $cuenta->phone_number_id);
        if ($token === null || $token === '' || $phoneId === '') {
            return ['ok' => false, 'error' => 'cuenta_incompleta'];
        }

        $version = trim((string) ($cuenta->api_version ?: config('whatsapp.meta.api_version_default', 'v21.0')));
        $base = rtrim((string) config('whatsapp.meta.api_base', 'https://graph.facebook.com'), '/');
        $url = $base.'/'.$version.'/'.$phoneId.'/messages';

        $payload = [
            'messaging_product' => 'whatsapp',
            'to' => $celularE164,
            'type' => 'text',
            'text' => [
                'preview_url' => false,
                'body' => mb_substr($texto, 0, 4000),
            ],
        ];

        try {
            $resp = Http::timeout((int) config('whatsapp.meta.timeout', 20))
                ->withToken($token)
                ->acceptJson()
                ->post($url, $payload);

            if (! $resp->successful()) {
                Log::warning('WhatsApp Meta: HTTP error', [
                    'empresa_id' => $cuenta->empresa_id,
                    'status' => $resp->status(),
                    'body' => mb_substr($resp->body(), 0, 500),
                ]);

                return ['ok' => false, 'error' => 'http_'.$resp->status()];
            }

            $id = data_get($resp->json(), 'messages.0.id');

            return ['ok' => true, 'provider_id' => is_string($id) ? $id : null];
        } catch (\Throwable $e) {
            Log::error('WhatsApp Meta: excepción', [
                'empresa_id' => $cuenta->empresa_id,
                'error' => $e->getMessage(),
            ]);

            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    public function enviarPlantilla(
        EmpresaWhatsappCuenta $cuenta,
        string $celularE164,
        string $nombrePlantilla,
        string $idioma,
        array $parametrosCuerpo = []
    ): array {
        $token = $cuenta->accessTokenPlano();
        $phoneId = trim((string) $cuenta->phone_number_id);
        if ($token === null || $token === '' || $phoneId === '') {
            return ['ok' => false, 'error' => 'cuenta_incompleta'];
        }

        $version = trim((string) ($cuenta->api_version ?: config('whatsapp.meta.api_version_default', 'v21.0')));
        $base = rtrim((string) config('whatsapp.meta.api_base', 'https://graph.facebook.com'), '/');
        $url = $base.'/'.$version.'/'.$phoneId.'/messages';

        $components = [];
        if ($parametrosCuerpo !== []) {
            $parameters = [];
            foreach ($parametrosCuerpo as $valor) {
                $parameters[] = [
                    'type' => 'text',
                    'text' => mb_substr((string) $valor, 0, 1024),
                ];
            }
            $components[] = [
                'type' => 'body',
                'parameters' => $parameters,
            ];
        }

        $payload = [
            'messaging_product' => 'whatsapp',
            'to' => $celularE164,
            'type' => 'template',
            'template' => [
                'name' => $nombrePlantilla,
                'language' => ['code' => $idioma],
            ],
        ];
        if ($components !== []) {
            $payload['template']['components'] = $components;
        }

        try {
            $resp = Http::timeout((int) config('whatsapp.meta.timeout', 20))
                ->withToken($token)
                ->acceptJson()
                ->post($url, $payload);

            if (! $resp->successful()) {
                Log::warning('WhatsApp Meta plantilla: HTTP error', [
                    'empresa_id' => $cuenta->empresa_id,
                    'status' => $resp->status(),
                    'body' => mb_substr($resp->body(), 0, 500),
                ]);

                return ['ok' => false, 'error' => 'http_'.$resp->status()];
            }

            $id = data_get($resp->json(), 'messages.0.id');

            return ['ok' => true, 'provider_id' => is_string($id) ? $id : null];
        } catch (\Throwable $e) {
            Log::error('WhatsApp Meta plantilla: excepción', [
                'empresa_id' => $cuenta->empresa_id,
                'error' => $e->getMessage(),
            ]);

            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }
}

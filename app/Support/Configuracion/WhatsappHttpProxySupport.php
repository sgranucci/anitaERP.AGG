<?php

declare(strict_types=1);

namespace App\Support\Configuracion;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * Salida HTTPS a graph.facebook.com (WhatsApp Cloud API).
 * Solo Crown: Squid (WHATSAPP_PROXY_* o ARCA_PADRON_PROXY_*).
 */
final class WhatsappHttpProxySupport
{
    public static function cliente(string $token, int $timeout): PendingRequest
    {
        $req = Http::withToken($token)
            ->timeout(max(5, $timeout))
            ->acceptJson();

        return CrownHttpsProxySupport::aplicar($req, self::proxy());
    }

    public static function activo(): bool
    {
        return self::proxy() !== null;
    }

    public static function hostPuertoResumen(): ?string
    {
        return CrownHttpsProxySupport::hostPuertoResumen(self::proxy());
    }

    /**
     * @return array{host: string, port: int, user: string, password: string}|null
     */
    private static function proxy(): ?array
    {
        return CrownHttpsProxySupport::resolver(
            filter_var(config('whatsapp.proxy_habilitado', true), FILTER_VALIDATE_BOOLEAN),
            (string) config('whatsapp.proxy_host', ''),
            (int) config('whatsapp.proxy_port', 3128),
            (string) config('whatsapp.proxy_user', ''),
            (string) config('whatsapp.proxy_password', ''),
        );
    }
}

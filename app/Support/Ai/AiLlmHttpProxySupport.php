<?php

declare(strict_types=1);

namespace App\Support\Ai;

use App\Support\Configuracion\CrownHttpsProxySupport;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * Salida HTTPS de Groq/Gemini/primary (CI_LLM_* y failover copiloto).
 *
 * Solo Crown: Squid (CI_LLM_PROXY_* o, vacío, ARCA_PADRON_PROXY_*).
 */
final class AiLlmHttpProxySupport
{
    public static function cliente(string $apiKey, int $timeout): PendingRequest
    {
        $req = Http::withToken($apiKey)
            ->timeout(max(3, $timeout))
            ->acceptJson();

        return CrownHttpsProxySupport::aplicar($req, self::proxy());
    }

    /**
     * @return array<string, mixed>
     */
    public static function opcionesGuzzle(): array
    {
        return CrownHttpsProxySupport::opcionesGuzzle(self::proxy());
    }

    public static function urlProxy(): ?string
    {
        return CrownHttpsProxySupport::url(self::proxy());
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
            filter_var(config('consulta_inteligente.llm_proxy_habilitado', true), FILTER_VALIDATE_BOOLEAN),
            (string) config('consulta_inteligente.llm_proxy_host', ''),
            (int) config('consulta_inteligente.llm_proxy_port', 3128),
            (string) config('consulta_inteligente.llm_proxy_user', ''),
            (string) config('consulta_inteligente.llm_proxy_password', ''),
        );
    }
}

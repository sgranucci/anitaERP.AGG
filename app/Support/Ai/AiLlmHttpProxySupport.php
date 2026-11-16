<?php

declare(strict_types=1);

namespace App\Support\Ai;

use App\Support\Configuracion\EntornoEmpresaSupport;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * Salida HTTPS de Groq/Gemini/primary (CI_LLM_* y failover copiloto).
 *
 * Solo Crown: el servidor no tiene Internet directo; usa Squid
 * (CI_LLM_PROXY_* o, vacío, ARCA_PADRON_PROXY_*). En AGG/resto no aplica.
 */
final class AiLlmHttpProxySupport
{
    /**
     * Cliente OpenAI-compatible con proxy Crown si corresponde.
     */
    public static function cliente(string $apiKey, int $timeout): PendingRequest
    {
        $req = Http::withToken($apiKey)
            ->timeout(max(3, $timeout))
            ->acceptJson();

        $opts = self::opcionesGuzzle();
        if ($opts === []) {
            return $req;
        }

        return $req->withOptions($opts);
    }

    /**
     * @return array<string, mixed>
     */
    public static function opcionesGuzzle(): array
    {
        $url = self::urlProxy();
        if ($url === null) {
            return [];
        }

        return ['proxy' => $url];
    }

    /**
     * URL http://[user:pass@]host:port o null si no aplica.
     */
    public static function urlProxy(): ?string
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            return null;
        }

        if (! filter_var(config('consulta_inteligente.llm_proxy_habilitado', true), FILTER_VALIDATE_BOOLEAN)) {
            return null;
        }

        $host = trim((string) config('consulta_inteligente.llm_proxy_host', ''));
        if ($host === '') {
            return null;
        }

        $port = max(1, (int) config('consulta_inteligente.llm_proxy_port', 3128));
        $user = trim((string) config('consulta_inteligente.llm_proxy_user', ''));
        $password = (string) config('consulta_inteligente.llm_proxy_password', '');

        if ($user !== '') {
            return 'http://'.rawurlencode($user).':'.rawurlencode($password).'@'.$host.':'.$port;
        }

        return 'http://'.$host.':'.$port;
    }

    public static function activo(): bool
    {
        return self::urlProxy() !== null;
    }

    /** Host sin credenciales (logs / UI). */
    public static function hostPuertoResumen(): ?string
    {
        if (! self::activo()) {
            return null;
        }

        $host = trim((string) config('consulta_inteligente.llm_proxy_host', ''));
        $port = max(1, (int) config('consulta_inteligente.llm_proxy_port', 3128));

        return $host.':'.$port;
    }
}

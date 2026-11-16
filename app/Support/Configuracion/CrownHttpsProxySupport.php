<?php

declare(strict_types=1);

namespace App\Support\Configuracion;

use Illuminate\Http\Client\PendingRequest;

/**
 * Salida HTTPS vía Squid solo en Crown (sin Internet directo).
 * Host vacío = no aplica. Credenciales opcionales (Basic).
 */
final class CrownHttpsProxySupport
{
    /**
     * @return array{host: string, port: int, user: string, password: string}|null
     */
    public static function resolver(
        bool $habilitado,
        string $host,
        int $port = 3128,
        string $user = '',
        string $password = ''
    ): ?array {
        if (! EntornoEmpresaSupport::esCrown() || ! $habilitado) {
            return null;
        }

        $host = trim($host);
        if ($host === '') {
            return null;
        }

        return [
            'host' => $host,
            'port' => max(1, $port),
            'user' => trim($user),
            'password' => $password,
        ];
    }

    /**
     * @param  array{host: string, port: int, user: string, password: string}|null  $proxy
     */
    public static function url(?array $proxy): ?string
    {
        if ($proxy === null) {
            return null;
        }

        $host = $proxy['host'];
        $port = $proxy['port'];
        $user = $proxy['user'];
        $password = $proxy['password'];

        if ($user !== '') {
            return 'http://'.rawurlencode($user).':'.rawurlencode($password).'@'.$host.':'.$port;
        }

        return 'http://'.$host.':'.$port;
    }

    /**
     * @param  array{host: string, port: int, user: string, password: string}|null  $proxy
     * @return array<string, mixed>
     */
    public static function opcionesGuzzle(?array $proxy): array
    {
        $url = self::url($proxy);
        if ($url === null) {
            return [];
        }

        return ['proxy' => $url];
    }

    /**
     * @param  array{host: string, port: int, user: string, password: string}|null  $proxy
     */
    public static function aplicar(PendingRequest $req, ?array $proxy): PendingRequest
    {
        $opts = self::opcionesGuzzle($proxy);
        if ($opts === []) {
            return $req;
        }

        return $req->withOptions($opts);
    }

    /**
     * @param  array{host: string, port: int, user: string, password: string}|null  $proxy
     */
    public static function hostPuertoResumen(?array $proxy): ?string
    {
        if ($proxy === null) {
            return null;
        }

        return $proxy['host'].':'.$proxy['port'];
    }
}

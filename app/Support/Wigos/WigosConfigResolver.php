<?php

namespace App\Support\Wigos;

/**
 * Resuelve servidores Wigos (SQL premio + HTTP AccountInfo fidelidad) según empresa del PV.
 */
final class WigosConfigResolver
{
    public static function currWigos(int $empresaId = 0): string
    {
        $curr = strtoupper(trim((string) (self::perEmpresa($empresaId)['curr_wigos'] ?? config('wigos.curr_wigos', 'A'))));

        return in_array($curr, ['A', 'B'], true) ? $curr : 'A';
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function connections(int $empresaId = 0): array
    {
        $global = (array) config('wigos.connections', []);
        $override = (array) (self::perEmpresa($empresaId)['connections'] ?? []);

        $result = [];
        foreach (['A', 'B'] as $alias) {
            $merged = array_merge(
                (array) ($global[$alias] ?? []),
                (array) ($override[$alias] ?? []),
            );
            if (trim((string) ($merged['host'] ?? '')) !== '') {
                $result[$alias] = $merged;
            }
        }

        return $result;
    }

    /**
     * URLs AccountInfoJSON a probar en orden (primario A/B y fallback).
     *
     * @return list<string>
     */
    public static function accountInfoUrls(int $empresaId = 0): array
    {
        $per = self::perEmpresa($empresaId);
        $curr = self::currWigos($empresaId);
        $secundario = $curr === 'A' ? 'B' : 'A';

        $urlA = trim((string) ($per['account_info_url'] ?? ''));
        $urlB = trim((string) ($per['account_info_url_b'] ?? ''));
        if ($urlA !== '' || $urlB !== '') {
            $map = ['A' => $urlA, 'B' => $urlB];

            return self::urlsUnicas([
                $map[$curr] ?? '',
                $map[$secundario] ?? '',
            ]);
        }

        $conns = self::connections($empresaId);
        $globalConns = (array) config('wigos.connections', []);
        $urls = [];
        foreach ([$curr, $secundario] as $alias) {
            $host = trim((string) ($conns[$alias]['host'] ?? ''));
            $globalHost = trim((string) ($globalConns[$alias]['host'] ?? ''));
            if ($host !== '' && ($empresaId <= 0 || $host !== $globalHost || count($conns) !== count($globalConns))) {
                $urls[] = self::accountInfoUrlDesdeHost($host);
            }
        }
        if ($urls !== []) {
            return self::urlsUnicas($urls);
        }

        $global = trim((string) config('wigos.account_info_url', ''));

        return $global !== '' ? [$global] : [];
    }

    public static function conexionConfigurada(string $alias, int $empresaId = 0): bool
    {
        $conns = self::connections($empresaId);

        return trim((string) ($conns[$alias]['host'] ?? '')) !== '';
    }

    /**
     * @return array<string, mixed>
     */
    public static function conexion(string $alias, int $empresaId = 0): array
    {
        $conns = self::connections($empresaId);

        return (array) ($conns[$alias] ?? []);
    }

    private static function accountInfoUrlDesdeHost(string $host): string
    {
        return 'http://'.$host.':7788/WIGOS/AccountInfoJSON?trackdata=%s';
    }

    /**
     * @param  list<string>  $urls
     * @return list<string>
     */
    private static function urlsUnicas(array $urls): array
    {
        $out = [];
        foreach ($urls as $url) {
            $url = trim($url);
            if ($url !== '' && ! in_array($url, $out, true)) {
                $out[] = $url;
            }
        }

        return $out;
    }

    /**
     * @return array<string, mixed>
     */
    private static function perEmpresa(int $empresaId): array
    {
        if ($empresaId <= 0) {
            return [];
        }

        $map = (array) config('wigos.por_empresa', []);

        return (array) ($map[$empresaId] ?? $map[(string) $empresaId] ?? []);
    }
}

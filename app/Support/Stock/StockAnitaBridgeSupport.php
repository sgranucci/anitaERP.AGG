<?php

namespace App\Support\Stock;

/**
 * Bridge Anita por empresa para stock descentralizado (stkmov, stkmae, stkdep, stkmgastro).
 * Administración central (recepmae, recepmov, compras) sigue en el bridge global Biyemas.
 */
final class StockAnitaBridgeSupport
{
    /**
     * @return array{servidor?: string, path_sistema?: string, sistema: string, ifx_server?: string}
     */
    public static function parametrosBridge(int $empresaId): array
    {
        $sistemaStock = (string) config('stock.anita_stkmov.sistema_ventas', 'ventas');
        $override = self::perEmpresa($empresaId);

        $sistemaOverride = trim((string) ($override['sistema'] ?? ''));
        $params = [
            'sistema' => $sistemaOverride !== '' ? $sistemaOverride : $sistemaStock,
        ];

        $servidor = trim((string) ($override['servidor'] ?? ''));
        if ($servidor !== '') {
            $params['servidor'] = $servidor;
        } else {
            $globalServidor = trim((string) config('anita.ip', ''));
            if ($globalServidor !== '') {
                $params['servidor'] = $globalServidor;
            }
        }

        $path = rtrim(trim((string) ($override['path_sistema'] ?? '')), '/');
        if ($path !== '') {
            $params['path_sistema'] = $path;
        } else {
            $globalPath = rtrim((string) config('anita.bdd_path', ''), '/');
            if ($globalPath !== '') {
                $params['path_sistema'] = $globalPath;
            }
        }

        $ifxServer = trim((string) ($override['ifx_server'] ?? ''));
        if ($ifxServer !== '') {
            $params['ifx_server'] = $ifxServer;
        }

        return $params;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public static function mergePayload(array $payload, int $empresaId): array
    {
        return array_merge(self::parametrosBridge(max(1, $empresaId)), $payload);
    }

    /**
     * stkv_empresa / stkv_cant_unidad solo existen en Informix central (Biyemas).
     * Kandiko y Rebisco usan bridge propio sin esas columnas.
     */
    public static function stkmovIncluyeColumnasAggMultiempresa(int $empresaId): bool
    {
        if (strtoupper((string) config('app.empresa')) !== 'AGG') {
            return false;
        }

        return self::perEmpresa(max(1, $empresaId)) === [];
    }

    /**
     * @return array<string, mixed>
     */
    private static function perEmpresa(int $empresaId): array
    {
        if ($empresaId <= 0) {
            return [];
        }

        // Preferir override stock (top-level o anita_stkmov); si vacío, gastronomía AGG.
        $map = (array) config('stock.anita_por_empresa', []);
        if ($map === []) {
            $map = (array) config('stock.anita_stkmov.anita_por_empresa', []);
        }
        if ($map === []) {
            $map = (array) config('gastronomia.ticket_tarjeta_anita_por_empresa', []);
        }

        return (array) ($map[$empresaId] ?? $map[(string) $empresaId] ?? []);
    }
}

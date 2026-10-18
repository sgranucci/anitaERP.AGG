<?php

namespace App\Support\Stock;

use App\Support\Configuracion\EntornoEmpresaSupport;

/**
 * Fuentes Anita para sync de depmae.
 * Crown: varias (cncadmin→CIP, acncadmin→AC). AGG: vacío → sync por empresa_id.
 */
final class DepmaeAnitaFuentesSupport
{
    /**
     * @return list<array{
     *     ifx_server: string,
     *     sala_codigo: string,
     *     empresa_id: int,
     *     path_sistema: string,
     *     sistema: string
     * }>
     */
    public static function fuentes(): array
    {
        $raw = trim((string) config('stock.depmae_anita_fuentes', ''));
        if ($raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded) && $decoded !== []) {
                return self::normalizarLista($decoded);
            }
        }

        if (! EntornoEmpresaSupport::esCrown()) {
            return [];
        }

        $path = rtrim((string) config('anita.bdd_path', '/usr2/casino'), '/') ?: '/usr2/casino';
        $sistema = (string) config('stock.anita_stkmov.sistema_ventas', 'ventas');
        $empresaId = (int) (config('cliente.EMPRESA_DEFAULT_ID') ?? 1);

        return self::normalizarLista([
            [
                'ifx_server' => 'cncadmin',
                'sala_codigo' => 'CIP',
                'empresa_id' => $empresaId,
                'path_sistema' => $path,
                'sistema' => $sistema,
            ],
            [
                'ifx_server' => 'acncadmin',
                'sala_codigo' => 'AC',
                'empresa_id' => $empresaId,
                'path_sistema' => $path,
                'sistema' => $sistema,
            ],
        ]);
    }

    public static function usaFuentesPorSala(): bool
    {
        return EntornoEmpresaSupport::esCrown() && self::fuentes() !== [];
    }

    /**
     * @param  list<mixed>  $lista
     * @return list<array{
     *     ifx_server: string,
     *     sala_codigo: string,
     *     empresa_id: int,
     *     path_sistema: string,
     *     sistema: string
     * }>
     */
    private static function normalizarLista(array $lista): array
    {
        $out = [];
        $pathDefault = rtrim((string) config('anita.bdd_path', '/usr2/casino'), '/') ?: '/usr2/casino';
        $sistemaDefault = (string) config('stock.anita_stkmov.sistema_ventas', 'ventas');
        $empresaDefault = (int) (config('cliente.EMPRESA_DEFAULT_ID') ?? 1);

        foreach ($lista as $item) {
            if (! is_array($item)) {
                continue;
            }
            $ifx = trim((string) ($item['ifx_server'] ?? ''));
            $sala = trim((string) ($item['sala_codigo'] ?? ''));
            if ($ifx === '' || $sala === '') {
                continue;
            }
            $out[] = [
                'ifx_server' => $ifx,
                'sala_codigo' => $sala,
                'empresa_id' => max(1, (int) ($item['empresa_id'] ?? $empresaDefault)),
                'path_sistema' => rtrim(trim((string) ($item['path_sistema'] ?? $pathDefault)), '/') ?: $pathDefault,
                'sistema' => trim((string) ($item['sistema'] ?? $sistemaDefault)) ?: $sistemaDefault,
            ];
        }

        return $out;
    }
}

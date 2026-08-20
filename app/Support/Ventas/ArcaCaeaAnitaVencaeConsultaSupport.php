<?php

declare(strict_types=1);

namespace App\Support\Ventas;

use App\ApiAnita;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Comprobantes CAEA en Informix (vencae) para presentar junto a las ventas del ERP.
 */
final class ArcaCaeaAnitaVencaeConsultaSupport
{
    /** @var array<string, list<array<string, mixed>>> */
    private static array $cachePorCaea = [];

    public static function resetCache(): void
    {
        self::$cachePorCaea = [];
    }

    /**
     * @param  list<int>  $sucursales  Códigos de PV CAEA de la empresa (enteros)
     * @return list<array{
     *   tipo_anita: string,
     *   letra: string,
     *   sucursal: int,
     *   numero: int,
     *   tipo_afip: int,
     *   nro_caea: string
     * }>
     */
    public static function listarPorCaeaIvaVentas(string $nroCaea, array $sucursales): array
    {
        $nroCaea = trim($nroCaea);
        $sucursales = array_values(array_unique(array_filter(
            array_map(static fn ($s): int => (int) $s, $sucursales),
            static fn (int $s): bool => $s > 0,
        )));
        if ($nroCaea === '' || $sucursales === []) {
            return [];
        }

        $cacheKey = $nroCaea.'|'.implode(',', $sucursales);
        if (isset(self::$cachePorCaea[$cacheKey])) {
            return self::$cachePorCaea[$cacheKey];
        }

        $in = implode(',', $sucursales);
        try {
            $parsed = ApiAnita::parsearRespuestaLista((new ApiAnita)->apiCall([
                'acc' => 'list',
                'sistema' => 'ventas',
                'tabla' => 'vencae',
                'campos' => 'venc_tipo,venc_letra,venc_sucursal,venc_nro,venc_nro_cae',
                'whereArmado' => " WHERE venc_nro_cae = '".addslashes($nroCaea)."'"
                    .' AND venc_sucursal IN ('.$in.')',
            ]));
        } catch (Throwable $e) {
            Log::warning('arca.caea.anita.vencae_fallo', ['caea' => $nroCaea, 'msg' => $e->getMessage()]);

            return [];
        }

        if ($parsed['error_lectura'] !== null) {
            Log::warning('arca.caea.anita.vencae_fallo', ['caea' => $nroCaea, 'msg' => $parsed['error_lectura']]);

            return [];
        }

        $out = [];
        $seen = [];
        foreach ($parsed['filas'] as $fila) {
            $item = self::normalizarFila((array) $fila);
            if ($item === null) {
                continue;
            }
            $clave = $item['sucursal'].'|'.$item['tipo_afip'].'|'.$item['numero'];
            if (isset($seen[$clave])) {
                continue;
            }
            $seen[$clave] = true;
            $out[] = $item;
        }

        usort(
            $out,
            static fn (array $a, array $b): int => [$a['sucursal'], $a['tipo_afip'], $a['numero']]
                <=> [$b['sucursal'], $b['tipo_afip'], $b['numero']],
        );

        self::$cachePorCaea[$cacheKey] = $out;

        return $out;
    }

    /**
     * Busca un número concreto (próximo ARCA) en vencae, solo IVA-ventas.
     *
     * @return array{
     *   tipo_anita: string,
     *   letra: string,
     *   sucursal: int,
     *   numero: int,
     *   tipo_afip: int,
     *   nro_caea: string
     * }|null
     */
    public static function buscarIvaVentasPorAfip(int $sucursal, int $numero, int $tipoAfip, string $nroCaea = ''): ?array
    {
        if ($sucursal < 1 || $numero < 1 || $tipoAfip < 1) {
            return null;
        }

        $where = ' WHERE venc_sucursal = '.$sucursal.' AND venc_nro = '.$numero;
        $nroCaea = trim($nroCaea);
        if ($nroCaea !== '') {
            $where .= " AND venc_nro_cae = '".addslashes($nroCaea)."'";
        }

        try {
            $parsed = ApiAnita::parsearRespuestaLista((new ApiAnita)->apiCall([
                'acc' => 'list',
                'sistema' => 'ventas',
                'tabla' => 'vencae',
                'campos' => 'venc_tipo,venc_letra,venc_sucursal,venc_nro,venc_nro_cae',
                'whereArmado' => $where,
            ]));
        } catch (Throwable $e) {
            Log::warning('arca.caea.anita.vencae_buscar_fallo', [
                'sucursal' => $sucursal,
                'numero' => $numero,
                'msg' => $e->getMessage(),
            ]);

            return null;
        }

        if ($parsed['error_lectura'] !== null) {
            return null;
        }

        foreach ($parsed['filas'] as $fila) {
            $item = self::normalizarFila((array) $fila);
            if ($item !== null && $item['tipo_afip'] === $tipoAfip) {
                return $item;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $fila
     * @return array{
     *   tipo_anita: string,
     *   letra: string,
     *   sucursal: int,
     *   numero: int,
     *   tipo_afip: int,
     *   nro_caea: string
     * }|null
     */
    private static function normalizarFila(array $fila): ?array
    {
        $tipoAnita = strtoupper(trim((string) ($fila['venc_tipo'] ?? '')));
        $letra = strtoupper(trim((string) ($fila['venc_letra'] ?? '')));
        $sucursal = (int) ($fila['venc_sucursal'] ?? 0);
        $numero = (int) ($fila['venc_nro'] ?? 0);
        $nroCaea = trim((string) ($fila['venc_nro_cae'] ?? ''));
        if ($tipoAnita === '' || $letra === '' || $sucursal < 1 || $numero < 1) {
            return null;
        }

        if (! ArcaCaeaAnitaIvaVentasSupport::vaAlSubdiarioIvaVentas($tipoAnita)) {
            return null;
        }

        $tipoAfip = ArcaCaeaAnitaTipoAfipSupport::tipoAfipDesdeAnita($tipoAnita, $letra);
        if ($tipoAfip <= 0) {
            return null;
        }

        return [
            'tipo_anita' => $tipoAnita,
            'letra' => $letra,
            'sucursal' => $sucursal,
            'numero' => $numero,
            'tipo_afip' => $tipoAfip,
            'nro_caea' => $nroCaea,
        ];
    }
}

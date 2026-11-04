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

    private static ?float $requestMark = null;

    private static float $deadlinePantalla = 0.0;

    private static bool $omitidaEnPantalla = false;

    /** El bridge no contestó en este proceso: no volver a esperar otro timeout. */
    private static bool $bridgeNoResponde = false;

    /** Tope de una lectura Anita en el job de informe. null = timeout del bridge. */
    private static ?int $timeoutProceso = null;

    public static function resetCache(): void
    {
        self::$cachePorCaea = [];
        self::$requestMark = null;
        self::$deadlinePantalla = 0.0;
        self::$omitidaEnPantalla = false;
        self::$bridgeNoResponde = false;
        self::$timeoutProceso = null;
    }

    public static function fijarTimeoutProceso(?int $segundos): void
    {
        self::$timeoutProceso = $segundos !== null && $segundos > 0 ? $segundos : null;
    }

    public static function bridgeNoResponde(): bool
    {
        return self::$bridgeNoResponde;
    }

    /**
     * Tope de espera a Anita para armar el listado. Si el bridge no contesta,
     * el resto de quincenas de la misma petición no vuelve a bloquear la pantalla.
     */
    public static function reservarPresupuestoPantalla(int $segundos): ?int
    {
        $segundos = max(5, $segundos);
        if (! isset($_SERVER['REQUEST_TIME_FLOAT'])) {
            return $segundos;
        }

        self::alinearRequest();
        if (self::$deadlinePantalla <= 0) {
            self::$deadlinePantalla = microtime(true) + $segundos;
        }

        $rest = (int) floor(self::$deadlinePantalla - microtime(true));
        if ($rest < 5) {
            self::$omitidaEnPantalla = true;

            return null;
        }

        return min($segundos, $rest);
    }

    public static function marcarAnitaOmitidaEnPantalla(): void
    {
        self::alinearRequest();
        self::$omitidaEnPantalla = true;
    }

    public static function anitaOmitidaEnPantalla(): bool
    {
        self::alinearRequest();

        return self::$omitidaEnPantalla;
    }

    private static function registrarFalloBridge(string $mensaje, bool $presupuestoPantalla): void
    {
        if ($presupuestoPantalla) {
            self::marcarAnitaOmitidaEnPantalla();
        }

        if (self::esCorteTransporte($mensaje)) {
            self::$bridgeNoResponde = true;
        }
    }

    private static function esCorteTransporte(string $mensaje): bool
    {
        $m = strtolower($mensaje);

        return str_contains($m, 'timed out')
            || str_contains($m, 'timeout')
            || str_contains($m, 'failed to connect')
            || str_contains($m, 'could not resolve')
            || str_contains($m, 'connection refused')
            || str_contains($m, 'empty reply')
            || str_contains($m, 'operation timed');
    }

    private static function alinearRequest(): void
    {
        if (! isset($_SERVER['REQUEST_TIME_FLOAT'])) {
            return;
        }

        $mark = (float) $_SERVER['REQUEST_TIME_FLOAT'];
        if (self::$requestMark !== $mark) {
            self::$requestMark = $mark;
            self::$deadlinePantalla = 0.0;
            self::$omitidaEnPantalla = false;
        }
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
    public static function listarPorCaeaIvaVentas(string $nroCaea, array $sucursales, ?int $curlTimeout = null): array
    {
        $nroCaea = trim($nroCaea);
        $sucursales = array_values(array_unique(array_filter(
            array_map(static fn ($s): int => (int) $s, $sucursales),
            static fn (int $s): bool => ArcaCaeaSucursalesInformeSupport::esSucursalPermitida($s),
        )));
        if ($nroCaea === '' || $sucursales === []) {
            return [];
        }

        if (self::$bridgeNoResponde) {
            return [];
        }

        $cacheKey = $nroCaea.'|'.implode(',', $sucursales);
        if (isset(self::$cachePorCaea[$cacheKey])) {
            return self::$cachePorCaea[$cacheKey];
        }

        $in = implode(',', $sucursales);
        $payload = ArcaCaeaSucursalesInformeSupport::mergePathAnita([
            'acc' => 'list',
            'sistema' => 'ventas',
            'tabla' => 'vencae',
            'campos' => 'venc_tipo,venc_letra,venc_sucursal,venc_nro,venc_nro_cae',
            'whereArmado' => " WHERE venc_nro_cae = '".addslashes($nroCaea)."'"
                .' AND venc_sucursal IN ('.$in.')',
        ]);
        if (($curlTimeout === null || $curlTimeout <= 0) && self::$timeoutProceso !== null) {
            $curlTimeout = self::$timeoutProceso;
        }
        if ($curlTimeout !== null && $curlTimeout > 0) {
            $payload['curl_timeout'] = $curlTimeout;
        }
        try {
            $parsed = ApiAnita::parsearRespuestaLista((new ApiAnita)->apiCall($payload));
        } catch (Throwable $e) {
            Log::warning('arca.caea.anita.vencae_fallo', ['caea' => $nroCaea, 'msg' => $e->getMessage()]);
            self::registrarFalloBridge($e->getMessage(), $curlTimeout !== null);

            return [];
        }

        if ($parsed['error_lectura'] !== null) {
            Log::warning('arca.caea.anita.vencae_fallo', ['caea' => $nroCaea, 'msg' => $parsed['error_lectura']]);
            self::registrarFalloBridge((string) $parsed['error_lectura'], $curlTimeout !== null);

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
        if (self::$bridgeNoResponde) {
            return null;
        }

        if ($sucursal < 1 || $numero < 1 || $tipoAfip < 1
            || ! ArcaCaeaSucursalesInformeSupport::esSucursalPermitida($sucursal)
        ) {
            return null;
        }

        $where = ' WHERE venc_sucursal = '.$sucursal.' AND venc_nro = '.$numero;
        $nroCaea = trim($nroCaea);
        if ($nroCaea !== '') {
            $where .= " AND venc_nro_cae = '".addslashes($nroCaea)."'";
        }

        $payload = ArcaCaeaSucursalesInformeSupport::mergePathAnita([
            'acc' => 'list',
            'sistema' => 'ventas',
            'tabla' => 'vencae',
            'campos' => 'venc_tipo,venc_letra,venc_sucursal,venc_nro,venc_nro_cae',
            'whereArmado' => $where,
        ]);
        if (self::$timeoutProceso !== null) {
            $payload['curl_timeout'] = self::$timeoutProceso;
        }

        try {
            $parsed = ApiAnita::parsearRespuestaLista((new ApiAnita)->apiCall($payload));
        } catch (Throwable $e) {
            Log::warning('arca.caea.anita.vencae_buscar_fallo', [
                'sucursal' => $sucursal,
                'numero' => $numero,
                'msg' => $e->getMessage(),
            ]);
            self::registrarFalloBridge($e->getMessage(), false);

            return null;
        }

        if ($parsed['error_lectura'] !== null) {
            self::registrarFalloBridge((string) $parsed['error_lectura'], false);

            return null;
        }

        foreach ($parsed['filas'] as $fila) {
            $item = self::normalizarFila((array) $fila);
            if ($item !== null && $item['tipo_afip'] === $tipoAfip) {
                return $item;
            }
        }

        return self::buscarEnVentaPorAfip($sucursal, $numero, $tipoAfip);
    }

    /**
     * Hueco en vencae: el comprobante puede existir solo en venta (ej. NDP A Bierzo).
     *
     * @return array{
     *   tipo_anita: string,
     *   letra: string,
     *   sucursal: int,
     *   numero: int,
     *   tipo_afip: int,
     *   nro_caea: string,
     *   fecha?: string
     * }|null
     */
    private static function buscarEnVentaPorAfip(int $sucursal, int $numero, int $tipoAfip): ?array
    {
        $letra = ArcaCaeaAnitaTipoAfipSupport::letraDesdeTipoAfip($tipoAfip);
        if ($letra === '') {
            return null;
        }

        foreach (ArcaCaeaAnitaTipoAfipSupport::tiposAnitaParaTipoAfip($tipoAfip) as $tipoAnita) {
            $cab = ArcaCaeaInformeDatosDesdeAnitaSupport::leerCabecera($tipoAnita, $letra, $sucursal, $numero);
            if ($cab === null) {
                continue;
            }

            $fecha = preg_replace('/\D+/', '', (string) ($cab['ven_fecha'] ?? '')) ?? '';

            return [
                'tipo_anita' => $tipoAnita,
                'letra' => $letra,
                'sucursal' => $sucursal,
                'numero' => $numero,
                'tipo_afip' => $tipoAfip,
                'nro_caea' => '',
                'fecha' => strlen($fecha) >= 8 ? substr($fecha, 0, 8) : '',
            ];
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

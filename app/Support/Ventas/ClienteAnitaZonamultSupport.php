<?php

namespace App\Support\Ventas;

use App\ApiAnita;
use App\Models\Configuracion\Provincia;
use Illuminate\Support\Facades\Log;

/**
 * clim_zonamult en Anita (EL BIERZO) no coincide con provincia.codigo del ERP.
 * La tabla ventas.zonamult tiene zonm_codjur (jurisdicción AFIP) → zonm_codigo.
 */
final class ClienteAnitaZonamultSupport
{
    /** @var array<int, int>|null jurisdicción => zonm_codigo */
    private static ?array $mapaPorJurisdiccion = null;

    public static function resetCache(): void
    {
        self::$mapaPorJurisdiccion = null;
    }

    /**
     * ERP → Anita: provincia_iibb_id (sede) → clim_zonamult / ven_zonamult.
     * Si no hay sede, el caller puede caer al domicilio.
     */
    public static function codigoDesdeProvinciaIibbId(?int $provinciaIibbId): int
    {
        return self::codigoDesdeProvinciaId($provinciaIibbId);
    }

    /**
     * Anita veni_provincia / clim_zonamult → jurisdicción AFIP (901, 902, 915…).
     * Acepta ya un código 900+ (histórico). 0 si no hay mapa.
     */
    public static function jurisdiccionDesdeCodigoZonamult(?int $codigoZonamult): int
    {
        if ($codigoZonamult === null || $codigoZonamult <= 0) {
            return 0;
        }
        if ($codigoZonamult >= 900) {
            return $codigoZonamult;
        }
        foreach (self::mapaPorJurisdiccion() as $jur => $codigo) {
            if ((int) $codigo === $codigoZonamult) {
                return (int) $jur;
            }
        }
        foreach (self::mapaFallbackConfig() as $jur => $codigo) {
            if ((int) $codigo === $codigoZonamult) {
                return (int) $jur;
            }
        }

        return 0;
    }

    /**
     * Anita → ERP: clim_zonamult → provincia.id de esa jurisdicción (901, 902, …).
     * No usa el domicilio. Null si la zona es 0 o no hay match.
     */
    public static function provinciaIdDesdeCodigoZonamult(?int $codigoZonamult): ?int
    {
        $jurisdiccion = self::jurisdiccionDesdeCodigoZonamult($codigoZonamult);
        if ($jurisdiccion < 900) {
            return null;
        }

        $porJurisdiccion = Provincia::query()
            ->where('jurisdiccion', $jurisdiccion)
            ->value('id');
        if ($porJurisdiccion) {
            return (int) $porJurisdiccion;
        }

        $porCodigo = Provincia::query()
            ->where('codigo', $jurisdiccion)
            ->value('id');

        return $porCodigo ? (int) $porCodigo : null;
    }

    /**
     * Código Anita para clim_zonamult.
     * 1) Busca zonamult por jurisdicción de la provincia.
     * 2) Si no hay match, usa provincia.codigo (comportamiento legacy).
     */
    public static function codigoDesdeProvinciaId(?int $provinciaId): int
    {
        if ($provinciaId === null || $provinciaId <= 0) {
            return 0;
        }

        $provincia = Provincia::query()
            ->select(['id', 'codigo', 'jurisdiccion', 'nombre'])
            ->whereKey($provinciaId)
            ->first();

        return self::codigoDesdeProvincia($provincia);
    }

    public static function codigoDesdeProvincia(?Provincia $provincia): int
    {
        if ($provincia === null) {
            return 0;
        }

        $fallback = max(0, (int) ($provincia->codigo ?? 0));
        $jurisdiccion = self::resolverJurisdiccion($provincia);
        if ($jurisdiccion <= 0) {
            return $fallback;
        }

        $mapa = self::mapaPorJurisdiccion();
        if ($mapa === [] || ! isset($mapa[$jurisdiccion])) {
            return $fallback;
        }

        return $mapa[$jurisdiccion];
    }

    /**
     * Jurisdicción AFIP (900+). Usa provincia.jurisdiccion o inferencia por nombre.
     */
    public static function resolverJurisdiccion(?Provincia $provincia): int
    {
        if ($provincia === null) {
            return 0;
        }

        $jurisdiccion = (int) ($provincia->jurisdiccion ?? 0);
        if ($jurisdiccion >= 900) {
            return $jurisdiccion;
        }

        return self::jurisdiccionDesdeNombre((string) ($provincia->nombre ?? ''));
    }

    public static function jurisdiccionDesdeNombre(string $nombre): int
    {
        $key = self::normalizarNombreProvincia($nombre);
        if ($key === '') {
            return 0;
        }

        $mapa = config('cliente_anita.jurisdiccion_por_nombre_provincia', []);
        if (! is_array($mapa)) {
            return 0;
        }

        return (int) ($mapa[$key] ?? 0);
    }

    public static function normalizarNombreProvincia(string $nombre): string
    {
        $nombre = trim($nombre);
        if ($nombre === '') {
            return '';
        }

        $sinAcentos = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $nombre);
        if (is_string($sinAcentos) && $sinAcentos !== '') {
            $nombre = $sinAcentos;
        }

        $nombre = strtoupper($nombre);
        $nombre = preg_replace('/[^A-Z0-9]+/', ' ', $nombre) ?? $nombre;

        return trim(preg_replace('/\s+/', ' ', $nombre) ?? $nombre);
    }

    /**
     * @return array<int, int>
     */
    public static function mapaPorJurisdiccion(): array
    {
        if (self::$mapaPorJurisdiccion !== null) {
            return self::$mapaPorJurisdiccion;
        }

        self::$mapaPorJurisdiccion = self::leerMapaDesdeAnita();

        if (self::$mapaPorJurisdiccion === []) {
            self::$mapaPorJurisdiccion = self::mapaFallbackConfig();
            if (self::$mapaPorJurisdiccion !== []) {
                try {
                    Log::warning('ClienteAnitaZonamult: usando fallback config cliente_anita.zonamult_por_jurisdiccion');
                } catch (\Throwable) {
                    // CLI sin permiso de escritura en storage/logs
                }
            }
        }

        return self::$mapaPorJurisdiccion;
    }

    /**
     * @return array<int, int>
     */
    private static function leerMapaDesdeAnita(): array
    {
        $mapa = [];

        try {
            $api = new ApiAnita;
            $sistema = (string) config('cliente_anita.sistema', 'ventas');
            $intentos = [
                [
                    'acc' => 'list',
                    'sistema' => $sistema,
                    'tabla' => 'zonamult',
                    'campos' => 'zonm_codigo,zonm_nombre,zonm_codjur',
                ],
                [
                    'acc' => 'list',
                    'sistema' => $sistema,
                    'tabla' => 'zonamult',
                    'campos' => 'zonm_codigo,zonm_nombre,zonm_codjur',
                    'whereArmado' => ' WHERE zonm_codigo > 0 ',
                ],
            ];

            $filas = [];
            foreach ($intentos as $payload) {
                $raw = (string) $api->apiCall($payload);
                $err = ApiAnita::extraerMensajeError($raw);
                if ($err !== null) {
                    Log::warning('ClienteAnitaZonamult: no se pudo leer zonamult', ['error' => $err]);
                    continue;
                }
                $filas = ApiAnita::decodificarListaFilas($raw);
                if (self::filasTienenJurisdiccionValida($filas)) {
                    break;
                }
                $filas = [];
            }

            foreach ($filas as $fila) {
                $jur = (int) ($fila->zonm_codjur ?? 0);
                $codigo = (int) ($fila->zonm_codigo ?? 0);
                if ($jur >= 900 && $codigo > 0) {
                    $mapa[$jur] = $codigo;
                }
            }
        } catch (\Throwable $e) {
            Log::warning('ClienteAnitaZonamult: excepción al leer zonamult', [
                'error' => $e->getMessage(),
            ]);
        }

        return $mapa;
    }

    /**
     * @param  list<object>  $filas
     */
    private static function filasTienenJurisdiccionValida(array $filas): bool
    {
        foreach ($filas as $fila) {
            if ((int) ($fila->zonm_codjur ?? 0) >= 900) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<int, int>
     */
    private static function mapaFallbackConfig(): array
    {
        $cfg = config('cliente_anita.zonamult_por_jurisdiccion', []);
        if (! is_array($cfg)) {
            return [];
        }

        $mapa = [];
        foreach ($cfg as $jur => $codigo) {
            $jur = (int) $jur;
            $codigo = (int) $codigo;
            if ($jur >= 900 && $codigo > 0) {
                $mapa[$jur] = $codigo;
            }
        }

        return $mapa;
    }
}

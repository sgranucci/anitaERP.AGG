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
            ->select(['id', 'codigo', 'jurisdiccion'])
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
        $jurisdiccion = (int) ($provincia->jurisdiccion ?? 0);
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
                Log::warning('ClienteAnitaZonamult: usando fallback config cliente_anita.zonamult_por_jurisdiccion');
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

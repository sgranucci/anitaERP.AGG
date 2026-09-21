<?php

namespace App\Support\Ventas\FacturacionLocal;

use App\Models\Ventas\FacturacionLocalParametro;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/**
 * Parámetros Facturación Local (BD) con fallback a config/env.
 * Prioridad: fila en facturacion_local_parametro → config('facturacion_local.*') → default.
 */
final class FacturacionLocalParametroSupport
{
    public const CLAVE_COSTO_DESCUENTO_PCT = 'costo_descuento_pct';

    public const CLAVE_COSTO_LISTAS_FABRICA = 'costo_listas_fabrica';

    public const CLAVE_COSTO_LISTAPRECIO_CODIGO = 'costo_listaprecio_codigo';

    private const CACHE_KEY = 'facturacion_local_parametro.mapa';

    /** @var list<array{clave:string,etiqueta:string,ayuda:string,orden:int}> */
    public const DEFINICIONES = [
        [
            'clave' => self::CLAVE_COSTO_DESCUENTO_PCT,
            'etiqueta' => 'Descuento % sobre precio fábrica',
            'ayuda' => 'Costo = precio fábrica × (1 − este % / 100). Ejemplo: 67 deja el 33 % como costo.',
            'orden' => 10,
        ],
        [
            'clave' => self::CLAVE_COSTO_LISTAS_FABRICA,
            'etiqueta' => 'Códigos de listas fábrica',
            'ayuda' => 'Códigos de listaprecio separados por coma. En Ferli: 1,2,3,4,5 (tiponumeración por talle).',
            'orden' => 20,
        ],
        [
            'clave' => self::CLAVE_COSTO_LISTAPRECIO_CODIGO,
            'etiqueta' => 'Lista fábrica forzada (opcional)',
            'ayuda' => 'Si completa un código, usa solo esa lista e ignora las de arriba. Vacío = listas fábrica + talle.',
            'orden' => 30,
        ],
    ];

    /**
     * @return array<string, string>
     */
    public static function mapa(): array
    {
        return Cache::remember(self::CACHE_KEY, 300, function () {
            $mapa = self::defaultsDesdeConfig();
            if (! Schema::hasTable('facturacion_local_parametro')) {
                return $mapa;
            }
            foreach (FacturacionLocalParametro::query()->get(['clave', 'valor']) as $fila) {
                $clave = (string) $fila->clave;
                if ($clave === '') {
                    continue;
                }
                $mapa[$clave] = trim((string) ($fila->valor ?? ''));
            }

            return $mapa;
        });
    }

    public static function valor(string $clave, ?string $default = null): string
    {
        $mapa = self::mapa();
        if (array_key_exists($clave, $mapa)) {
            return trim((string) $mapa[$clave]);
        }

        return $default !== null ? $default : '';
    }

    public static function costoDescuentoPct(): float
    {
        $raw = self::valor(self::CLAVE_COSTO_DESCUENTO_PCT, (string) config('facturacion_local.costo_descuento_pct', 67));
        $pct = (float) str_replace(',', '.', $raw);

        return max(0.0, min(100.0, $pct));
    }

    /**
     * @return list<string>
     */
    public static function costoListasFabricaCodigos(): array
    {
        $raw = self::valor(
            self::CLAVE_COSTO_LISTAS_FABRICA,
            implode(',', config('facturacion_local.costo_listas_fabrica_codigos', ['1', '2', '3', '4', '5']))
        );
        $out = [];
        foreach (explode(',', $raw) as $codigo) {
            $codigo = trim($codigo);
            if ($codigo !== '') {
                $out[] = $codigo;
            }
        }

        return $out !== [] ? array_values(array_unique($out)) : ['1', '2', '3', '4', '5'];
    }

    public static function costoListaprecioCodigo(): string
    {
        return self::valor(
            self::CLAVE_COSTO_LISTAPRECIO_CODIGO,
            (string) config('facturacion_local.costo_listaprecio_codigo', '')
        );
    }

    public static function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * @return array<string, string>
     */
    private static function defaultsDesdeConfig(): array
    {
        return [
            self::CLAVE_COSTO_DESCUENTO_PCT => (string) config('facturacion_local.costo_descuento_pct', 67),
            self::CLAVE_COSTO_LISTAS_FABRICA => implode(',', config('facturacion_local.costo_listas_fabrica_codigos', ['1', '2', '3', '4', '5'])),
            self::CLAVE_COSTO_LISTAPRECIO_CODIGO => (string) config('facturacion_local.costo_listaprecio_codigo', ''),
        ];
    }
}

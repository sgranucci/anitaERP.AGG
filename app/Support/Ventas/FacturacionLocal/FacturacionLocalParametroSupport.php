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

    public const CLAVE_SHOPPING_EMAIL = 'shopping_caballito_email';

    public const CLAVE_SHOPPING_NRO_LOCAL = 'shopping_caballito_nro_local';

    public const CLAVE_SHOPPING_CONTRATO = 'shopping_caballito_nro_contrato';

    public const CLAVE_SHOPPING_CAJA = 'shopping_caballito_nro_caja';

    public const CLAVE_SHOPPING_HORA = 'shopping_caballito_hora';

    public const CLAVE_SHOPPING_HABILITADO = 'shopping_caballito_habilitado';

    public const CLAVE_SHOPPING_LOCAL_CODIGO = 'shopping_caballito_local_codigo';

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
        [
            'clave' => self::CLAVE_SHOPPING_HABILITADO,
            'etiqueta' => 'Shopping Caballito: enviar automático',
            'ayuda' => '1 = todos los días a la hora indicada. 0 = no corre solo (igual se puede enviar a mano desde el log).',
            'orden' => 100,
        ],
        [
            'clave' => self::CLAVE_SHOPPING_HORA,
            'etiqueta' => 'Shopping Caballito: hora de envío',
            'ayuda' => 'Hora del servidor, formato HH:MM. Por ahora 21:00.',
            'orden' => 110,
        ],
        [
            'clave' => self::CLAVE_SHOPPING_EMAIL,
            'etiqueta' => 'Shopping Caballito: correo',
            'ayuda' => 'Uno o varios correos separados por coma. Ahí llega el TXT del día.',
            'orden' => 120,
        ],
        [
            'clave' => self::CLAVE_SHOPPING_LOCAL_CODIGO,
            'etiqueta' => 'Shopping Caballito: código del local',
            'ayuda' => 'Código del local en Locales de venta. Solo se exporta ese local.',
            'orden' => 130,
        ],
        [
            'clave' => self::CLAVE_SHOPPING_NRO_LOCAL,
            'etiqueta' => 'Shopping Caballito: nro de local',
            'ayuda' => '4 dígitos que asigna el shopping. No usar el 2024 del PDF: es un ejemplo.',
            'orden' => 140,
        ],
        [
            'clave' => self::CLAVE_SHOPPING_CONTRATO,
            'etiqueta' => 'Shopping Caballito: nro de contrato',
            'ayuda' => 'Hasta 10 dígitos, el contrato que pasa el shopping.',
            'orden' => 150,
        ],
        [
            'clave' => self::CLAVE_SHOPPING_CAJA,
            'etiqueta' => 'Shopping Caballito: nro de caja',
            'ayuda' => '2 dígitos. Si hay una sola caja, 01.',
            'orden' => 160,
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
            self::CLAVE_SHOPPING_HABILITADO => '1',
            self::CLAVE_SHOPPING_HORA => '21:00',
            self::CLAVE_SHOPPING_EMAIL => '',
            self::CLAVE_SHOPPING_LOCAL_CODIGO => '',
            self::CLAVE_SHOPPING_NRO_LOCAL => '',
            self::CLAVE_SHOPPING_CONTRATO => '',
            self::CLAVE_SHOPPING_CAJA => '01',
        ];
    }
}

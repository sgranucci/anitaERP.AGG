<?php

namespace App\Support\Configuracion;

use App\Models\Configuracion\Parametro_Sistema;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Parámetros editables de Configuración general.
 * Lee BD y, si no hay fila, el config()/env vigente.
 */
final class ParametroSistemaSupport
{
    public const CLAVE_LIMITE_FCE = 'limite_fce';

    public const CLAVE_TOPE_CONSUMIDOR_FINAL = 'tope_consumidor_final';

    private const CACHE_KEY = 'parametro_sistema.mapa';

    /**
     * @return array<string, array{grupo: string, etiqueta: string, ayuda: string, tipo: string, orden: int}>
     */
    public static function definiciones(): array
    {
        return [
            self::CLAVE_LIMITE_FCE => [
                'grupo' => 'Facturación ARCA',
                'etiqueta' => 'Tope FCE MiPyME',
                'ayuda' => 'Si el cliente es receptor de Factura de Crédito (FCE) y el total del comprobante alcanza este importe, se emite FCE (códigos AFIP 201 / 206).',
                'tipo' => 'decimal',
                'orden' => 10,
            ],
            self::CLAVE_TOPE_CONSUMIDOR_FINAL => [
                'grupo' => 'Facturación ARCA',
                'etiqueta' => 'Tope consumidor final',
                'ayuda' => 'Umbral RG 5700/2025: a partir de este monto hay que identificar al comprador (DNI/CUIT) en Factura B, POS y Libro IVA Digital.',
                'tipo' => 'decimal',
                'orden' => 20,
            ],
        ];
    }

    public static function limiteFce(): float
    {
        return self::decimal(self::CLAVE_LIMITE_FCE, (float) config('facturacion.LIMITE_FCE', 0));
    }

    public static function topeConsumidorFinal(): float
    {
        return self::decimal(
            self::CLAVE_TOPE_CONSUMIDOR_FINAL,
            (float) config('arca_wsfe.receptor.consumidor_final_umbral_monto', 10_000_000)
        );
    }

    public static function decimal(string $clave, float $fallback): float
    {
        $valor = self::mapa()[$clave] ?? null;
        if ($valor === null || $valor === '') {
            return $fallback;
        }

        return (float) $valor;
    }

    /**
     * @return array<string, list<array{clave: string, grupo: string, etiqueta: string, ayuda: string, tipo: string, valor: string}>>
     */
    public static function listarParaFormulario(): array
    {
        $mapa = self::mapa();
        $grupos = [];

        foreach (self::definiciones() as $clave => $def) {
            $valor = $mapa[$clave] ?? self::fallbackValor($clave);
            $grupos[$def['grupo']][] = [
                'clave' => $clave,
                'grupo' => $def['grupo'],
                'etiqueta' => $def['etiqueta'],
                'ayuda' => $def['ayuda'],
                'tipo' => $def['tipo'],
                'valor' => (string) $valor,
            ];
        }

        return $grupos;
    }

    /**
     * @param  array<string, mixed>  $valores
     */
    public static function guardar(array $valores): void
    {
        foreach (self::definiciones() as $clave => $def) {
            if (! array_key_exists($clave, $valores)) {
                continue;
            }
            $valor = is_numeric($valores[$clave])
                ? (string) $valores[$clave]
                : trim((string) $valores[$clave]);

            Parametro_Sistema::query()->updateOrCreate(
                ['clave' => $clave],
                [
                    'grupo' => $def['grupo'],
                    'etiqueta' => $def['etiqueta'],
                    'ayuda' => $def['ayuda'],
                    'tipo' => $def['tipo'],
                    'valor' => $valor,
                    'orden' => $def['orden'],
                ]
            );
        }

        self::olvidarCache();
    }

    public static function olvidarCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * @return array<string, string>
     */
    private static function mapa(): array
    {
        try {
            if (! Schema::hasTable('parametro_sistema')) {
                return [];
            }

            return Cache::remember(self::CACHE_KEY, 3600, static function (): array {
                $out = [];
                foreach (Parametro_Sistema::query()->get(['clave', 'valor']) as $fila) {
                    $out[(string) $fila->clave] = (string) $fila->valor;
                }

                return $out;
            });
        } catch (Throwable) {
            return [];
        }
    }

    private static function fallbackValor(string $clave): string
    {
        return match ($clave) {
            self::CLAVE_LIMITE_FCE => (string) (float) config('facturacion.LIMITE_FCE', 0),
            self::CLAVE_TOPE_CONSUMIDOR_FINAL => (string) (float) config(
                'arca_wsfe.receptor.consumidor_final_umbral_monto',
                10_000_000
            ),
            default => '0',
        };
    }
}

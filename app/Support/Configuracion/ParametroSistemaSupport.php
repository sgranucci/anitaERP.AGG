<?php

namespace App\Support\Configuracion;

use App\Models\Caja\Cuentacaja;
use App\Models\Configuracion\Parametro_Sistema;
use App\Support\Ventas\ArcaFceDatosAdicionalesSupport;
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

    public const CLAVE_FCE_CUENTACAJA_ID = 'fce_cuentacaja_id';

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
            self::CLAVE_FCE_CUENTACAJA_ID => [
                'grupo' => 'Facturación ARCA',
                'etiqueta' => 'Cuenta de caja FCE (CBU emisor)',
                'ayuda' => 'Cuenta cuyo CBU se envía a ARCA en FCE (dato adicional 21). En Anita era tesmae 00000032. F1 o lupa abren la consulta.',
                'tipo' => 'cuentacaja',
                'orden' => 30,
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

    public static function fceCuentacajaId(): int
    {
        $valor = self::mapa()[self::CLAVE_FCE_CUENTACAJA_ID] ?? '';
        $id = (int) $valor;
        if ($id > 0) {
            return $id;
        }

        return (int) self::fallbackValor(self::CLAVE_FCE_CUENTACAJA_ID);
    }

    public static function fceCuentacaja(): ?Cuentacaja
    {
        $id = self::fceCuentacajaId();
        if ($id <= 0) {
            return null;
        }

        return Cuentacaja::query()->find($id);
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
            $item = [
                'clave' => $clave,
                'grupo' => $def['grupo'],
                'etiqueta' => $def['etiqueta'],
                'ayuda' => $def['ayuda'],
                'tipo' => $def['tipo'],
                'valor' => (string) $valor,
            ];
            if ($def['tipo'] === 'cuentacaja') {
                $item['cuenta'] = self::cuentaParaFormulario((int) $valor);
            }
            $grupos[$def['grupo']][] = $item;
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
            $valor = $def['tipo'] === 'cuentacaja'
                ? (string) max(0, (int) $valores[$clave])
                : (is_numeric($valores[$clave])
                    ? (string) $valores[$clave]
                    : trim((string) $valores[$clave]));
            if ($def['tipo'] === 'cuentacaja' && $valor === '0') {
                $valor = '';
            }

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
            self::CLAVE_FCE_CUENTACAJA_ID => (string) self::cuentacajaIdFallbackAnita(),
            default => '0',
        };
    }

    /**
     * @return array{id:int, codigo:string, nombre:string, cbu:string}
     */
    public static function cuentaParaFormulario(int $id): array
    {
        $cta = $id > 0 ? Cuentacaja::query()->find($id) : null;

        return [
            'id' => (int) ($cta->id ?? 0),
            'codigo' => (string) ($cta->codigo ?? ''),
            'nombre' => (string) ($cta->nombre ?? ''),
            'cbu' => (string) ($cta->cbu ?? ''),
        ];
    }

    private static function cuentacajaIdFallbackAnita(): int
    {
        $codigos = [
            ArcaFceDatosAdicionalesSupport::CUENTA_TESORERIA_ANITA,
            ltrim(ArcaFceDatosAdicionalesSupport::CUENTA_TESORERIA_ANITA, '0') ?: '0',
        ];
        $id = (int) (Cuentacaja::query()->whereIn('codigo', $codigos)->value('id') ?? 0);
        if ($id > 0) {
            return $id;
        }

        $cbu = preg_replace('/\D+/', '', (string) config('arca.caea.fce.cbu_emisor', '')) ?? '';
        if (strlen($cbu) === 22) {
            return (int) (Cuentacaja::query()->where('cbu', $cbu)->value('id') ?? 0);
        }

        return 0;
    }
}

<?php

namespace App\Support\Ventas\FacturacionLocal;

use App\Models\Caja\Cuentacaja;

/**
 * Medio de pago puente NCD para cambios marketplace (cuentacaja 1131009).
 * Solo Facturación Local Ferli — no gastronomía AGG.
 */
final class CambioDevolucionMarketplacePuenteSupport
{
    public const CODIGO_CUENTACAJA_DEFAULT = '1131009';

    public static function codigoCuentacaja(): string
    {
        $codigo = trim((string) config('facturacion_local.cambio_devolucion_puente_cuentacaja_codigo', self::CODIGO_CUENTACAJA_DEFAULT));

        return $codigo !== '' ? $codigo : self::CODIGO_CUENTACAJA_DEFAULT;
    }

    public static function resolverCuentacajaId(): ?int
    {
        $codigo = self::codigoCuentacaja();
        $variantes = self::variantesCodigo($codigo);
        $cuentas = Cuentacaja::query()
            ->whereIn('codigo', $variantes)
            ->get(['id', 'codigo']);

        if ($cuentas->isEmpty()) {
            return null;
        }

        // Anita guarda tesm_cuenta en 8 caracteres (01131009). El parámetro usa 1131009.
        $exacta = $cuentas->first(fn (Cuentacaja $cuenta) => (string) $cuenta->codigo === $codigo);

        return (int) ($exacta ?? $cuentas->first())->id;
    }

    /**
     * @return list<string>
     */
    private static function variantesCodigo(string $codigo): array
    {
        $codigo = trim($codigo);
        $sinCeros = ltrim($codigo, '0');
        $sinCeros = $sinCeros === '' ? '0' : $sinCeros;
        $padded = preg_match('/^\d+$/', $sinCeros) === 1
            ? str_pad($sinCeros, 8, '0', STR_PAD_LEFT)
            : $codigo;

        return array_values(array_unique(array_filter(
            [$codigo, $sinCeros, $padded],
            static fn ($valor) => $valor !== ''
        )));
    }

    public static function resolverCuentacaja(): ?Cuentacaja
    {
        $id = self::resolverCuentacajaId();
        if ($id === null || $id <= 0) {
            return null;
        }

        return Cuentacaja::query()->find($id);
    }

    /**
     * @return list<array{cuentacaja_id:int,moneda_id:int,monto:float}>
     */
    public static function medioPagoUnico(float $monto, ?int $monedaId = null): array
    {
        $cuentacajaId = self::resolverCuentacajaId();
        if ($cuentacajaId === null || $cuentacajaId <= 0) {
            throw new \RuntimeException(
                'No se encontró la cuenta de caja puente '.self::codigoCuentacaja()
                .' (Aplicación de crédito local).'
            );
        }

        $montoAbs = round(abs($monto), 2);
        if ($montoAbs < 0.01) {
            return [];
        }

        return [[
            'cuentacaja_id' => $cuentacajaId,
            'moneda_id' => $monedaId ?? (int) config('facturacion_local.moneda_id', 1),
            'monto' => $montoAbs,
        ]];
    }
}

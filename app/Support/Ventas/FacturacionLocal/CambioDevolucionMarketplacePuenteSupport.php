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
        $id = Cuentacaja::query()
            ->where('codigo', $codigo)
            ->value('id');

        return $id !== null ? (int) $id : null;
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

<?php

namespace App\Support\Ventas\Mercadolibre;

use App\Models\Caja\Cuentacaja;
use App\Models\Stock\Depmae;
use App\Models\Ventas\Puntoventa;

/**
 * PV / depósito / cuenta 608 según canal standard vs Full.
 */
final class MercadolibreDefaultsSupport
{
    /**
     * @return array{puntoventa_id:?int,deposito_id:?int,cuentacaja_id:?int,es_full:bool}
     */
    public static function resolver(?array $shippingJson = null): array
    {
        $esFull = self::esFull($shippingJson);
        $pvCodigo = $esFull
            ? (string) config('mercadolibre.full_puntoventa_codigo', '00027')
            : (string) config('mercadolibre.puntoventa_codigo', '00021');
        $depCodigo = $esFull
            ? (string) config('mercadolibre.full_deposito_codigo', '27')
            : (string) config('mercadolibre.deposito_codigo', '10');

        return [
            'puntoventa_id' => self::puntoventaIdPorCodigo($pvCodigo),
            'deposito_id' => self::depositoIdPorCodigo($depCodigo),
            'cuentacaja_id' => self::cuentacajaId(),
            'es_full' => $esFull,
        ];
    }

    public static function esFull(?array $shippingJson): bool
    {
        if (! is_array($shippingJson)) {
            return false;
        }
        $logistic = strtolower((string) (
            $shippingJson['logistic_type']
            ?? $shippingJson['logistic']['type']
            ?? ''
        ));

        return $logistic === 'fulfillment';
    }

    public static function puntoventaIdPorCodigo(string $codigo): ?int
    {
        $codigo = trim($codigo);
        if ($codigo === '') {
            return null;
        }
        $id = Puntoventa::query()->whereIn('codigo', self::variantesCodigo($codigo))->value('id');

        return $id ? (int) $id : null;
    }

    public static function depositoIdPorCodigo(string $codigo): ?int
    {
        $codigo = trim($codigo);
        if ($codigo === '') {
            return null;
        }
        $id = Depmae::query()->whereIn('codigo', self::variantesCodigo($codigo))->value('id');

        return $id ? (int) $id : null;
    }

    public static function cuentacajaId(): ?int
    {
        $codigo = trim((string) config('mercadolibre.cuentacaja_codigo', '608'));
        $empresaId = (int) config('mercadolibre.empresa_id', 1);
        $id = Cuentacaja::query()
            ->paraEmpresa($empresaId)
            ->whereIn('codigo', self::variantesCodigo($codigo))
            ->value('id');

        return $id ? (int) $id : null;
    }

    /** @return list<string> */
    private static function variantesCodigo(string $codigo): array
    {
        $codigo = trim($codigo);
        $digits = preg_replace('/\D+/', '', $codigo) ?? '';
        $sinCeros = ltrim($digits, '0');
        if ($sinCeros === '') {
            $sinCeros = '0';
        }

        return array_values(array_unique(array_filter([
            $codigo,
            $digits,
            $sinCeros,
            str_pad($sinCeros, 8, '0', STR_PAD_LEFT),
            str_pad($sinCeros, 5, '0', STR_PAD_LEFT),
        ], static fn (string $valor) => $valor !== '')));
    }
}

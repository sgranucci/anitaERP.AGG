<?php

namespace App\Support\Ventas\Waitry;

use InvalidArgumentException;

/**
 * Mapea medios de cobro Anita (cuentacaja) al enum Waitry syncStatusPOS / pushExternalOrder.
 *
 * Solo Mercado Pago y Totalcoin conservan su tipo; cualquier otro medio se envía como cash.
 */
final class WaitryPaymentTypeSupport
{
    public const TIPO_CASH = 'cash';

    public const TIPO_CREDIT_CARD = 'credit_card';

    public const TIPO_DEBIT_CARD = 'debit_card';

    public const TIPO_MERCADOPAGO = 'mercadopago';

    public const TIPO_TOTALCOIN = 'totalcoin';

    /** @var list<string> */
    public const TIPOS_VALIDOS = [
        self::TIPO_CASH,
        self::TIPO_CREDIT_CARD,
        self::TIPO_DEBIT_CARD,
        self::TIPO_MERCADOPAGO,
        self::TIPO_TOTALCOIN,
    ];

    /** @var list<string> */
    private const TIPOS_WAITRY_ESPECIFICOS = [
        self::TIPO_MERCADOPAGO,
        self::TIPO_TOTALCOIN,
    ];

    /**
     * @param  list<array{cuentacaja_id:int,moneda_id:int,monto:float,cotizacion?:float|null,observacion?:string|null}>  $mediosPago
     */
    public function resolverDesdeMediosPago(array $mediosPago, int $empresaId): string
    {
        $medioPrincipal = $this->medioConMayorMonto($mediosPago);
        if ($medioPrincipal === null) {
            throw new InvalidArgumentException('Waitry: no hay medio de pago para sincronizar.');
        }

        $cuentacajaId = (int) ($medioPrincipal['cuentacaja_id'] ?? 0);
        if ($cuentacajaId <= 0) {
            throw new InvalidArgumentException('Waitry: cuenta de caja inválida en el medio de pago.');
        }

        return $this->resolverPorCuentacajaId($cuentacajaId, $empresaId);
    }

    /**
     * @param  list<array{cuentacaja_id:int,moneda_id:int,monto:float,cotizacion?:float|null,observacion?:string|null}>  $mediosPago
     * @return array{cuentacaja_id:int,moneda_id:int,monto:float,cotizacion?:float|null,observacion?:string|null}|null
     */
    public function medioConMayorMonto(array $mediosPago): ?array
    {
        $mejor = null;
        $maxMonto = -1.0;

        foreach ($mediosPago as $medio) {
            if (! is_array($medio)) {
                continue;
            }
            $monto = (float) ($medio['monto'] ?? 0);
            if ($monto <= 0.) {
                continue;
            }
            if ($monto > $maxMonto) {
                $maxMonto = $monto;
                $mejor = $medio;
            }
        }

        return $mejor;
    }

    /**
     * @param  list<array{cuentacaja_id:int,moneda_id:int,monto:float,cotizacion?:float|null,observacion?:string|null}>  $mediosPago
     */
    public function montoTotalMedioPrincipal(array $mediosPago): float
    {
        $medio = $this->medioConMayorMonto($mediosPago);

        return round((float) ($medio['monto'] ?? 0), 2);
    }

    /**
     * @param  list<array{cuentacaja_id:int,moneda_id:int,monto:float,cotizacion?:float|null,observacion?:string|null}>  $mediosPago
     */
    public function monedaIdMedioPrincipal(array $mediosPago): int
    {
        $medio = $this->medioConMayorMonto($mediosPago);

        return (int) ($medio['moneda_id'] ?? 0);
    }

    public function resolverPorCuentacajaId(int $cuentacajaId, int $empresaId): string
    {
        foreach (WaitryMedioPagoCuentacajaSupport::mapaTipoCuentacaja() as $tipoWaitry => $idCuenta) {
            if ($cuentacajaId === (int) $idCuenta
                && in_array($tipoWaitry, self::TIPOS_WAITRY_ESPECIFICOS, true)) {
                return $tipoWaitry;
            }
        }

        return self::TIPO_CASH;
    }
}

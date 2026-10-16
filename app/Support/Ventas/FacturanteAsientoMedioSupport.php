<?php

declare(strict_types=1);

namespace App\Support\Ventas;

use App\Models\Caja\Cuentacaja;
use InvalidArgumentException;

/**
 * Contrapartida del asiento VTA al importar Facturante: un solo asiento con el medio al Debe
 * (venta + IVA al Haber). No usa cuenta puente ni asiento de cobranza aparte.
 */
final class FacturanteAsientoMedioSupport
{
    /**
     * Código mediopago Facturante → código cuentacaja ERP.
     * Coincide con generaCuenta() / generaFactura() (Anita 00000608 → 608, etc.).
     *
     * @var array<string, string>
     */
    private const MEDIO_A_CUENTACAJA = [
        '1' => '608', // MEP — MercadoLibre
        '2' => '609', // TN — Pago Nube
        '3' => '610', // GO — Go Cuotas
        '4' => '4781/5', // TR — transferencia
        '5' => '612', // NBO — Nube Boa
    ];

    /**
     * Prefijo / sucursal Facturante → mediopago (mismo criterio que FacturanteService::resolverMedioPago).
     */
    public static function mediopagoDesdePrefijo(int $prefijo): string
    {
        if ($prefijo === 21 || $prefijo === 27) {
            return '1';
        }
        if ($prefijo === 23) {
            return '2';
        }
        if ($prefijo === 26) {
            return '5';
        }

        return '6';
    }

    public static function codigoCuentacajaDesdeMediopago(string $mediopago): ?string
    {
        $mediopago = trim($mediopago);

        return self::MEDIO_A_CUENTACAJA[$mediopago] ?? null;
    }

    public static function cuentacajaIdDesdeMediopago(string $mediopago): int
    {
        $codigo = self::codigoCuentacajaDesdeMediopago($mediopago);
        if ($codigo === null) {
            throw new InvalidArgumentException(
                'Medio de pago Facturante "'.$mediopago.'" no tiene cuenta de caja (omitido o desconocido).'
            );
        }

        $cuenta = self::buscarCuentacaja($codigo);
        if (! $cuenta) {
            throw new InvalidArgumentException(
                'No existe la cuenta de caja '.$codigo.' para el medio Facturante '.$mediopago.'.'
            );
        }
        if ((int) ($cuenta->cuentacontable_id ?? 0) <= 0) {
            throw new InvalidArgumentException(
                'La cuenta de caja '.$cuenta->codigo.' ('.$cuenta->nombre.') no tiene cuenta contable.'
            );
        }

        return (int) $cuenta->id;
    }

    /**
     * Líneas para opciones_emision.asiento_medios_pago.
     *
     * @return list<array{cuentacaja_id:int,monto:float,cotizacion:float}>
     */
    public static function lineasAsientoMedios(string $mediopago, float $total): array
    {
        $monto = round(abs($total), 2);
        if ($monto < 0.009) {
            return [];
        }

        return [[
            'cuentacaja_id' => self::cuentacajaIdDesdeMediopago($mediopago),
            'monto' => $monto,
            'cotizacion' => 1.0,
        ]];
    }

    /**
     * @return list<array{cuentacaja_id:int,monto:float,cotizacion:float}>
     */
    public static function lineasDesdePrefijo(int $prefijo, float $total): array
    {
        $mediopago = self::mediopagoDesdePrefijo($prefijo);
        if ($mediopago === '6') {
            return [];
        }

        return self::lineasAsientoMedios($mediopago, $total);
    }

    private static function buscarCuentacaja(string $codigo): ?Cuentacaja
    {
        $codigo = trim($codigo);
        if ($codigo === '') {
            return null;
        }

        $cuenta = Cuentacaja::query()->where('codigo', $codigo)->first();
        if ($cuenta) {
            return $cuenta;
        }

        // Anita a veces manda 00000608; en ERP está 608.
        $sinCeros = ltrim($codigo, '0');
        if ($sinCeros !== '' && $sinCeros !== $codigo) {
            return Cuentacaja::query()->where('codigo', $sinCeros)->first();
        }

        return null;
    }
}

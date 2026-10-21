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
     * Coincide con generaCuenta() / generaFactura() (Anita 00000608 o 608, etc.).
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

        $cuenta = self::encontrarCuentacaja($codigo);
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

    /**
     * El maestro de Ferli guarda el código Anita de 8 posiciones (00000608, 004781/5).
     * El mapeo del medio usa la forma corta (608, 4781/5).
     */
    public static function encontrarCuentacaja(string $codigo): ?Cuentacaja
    {
        $variantes = self::variantesCodigo($codigo);
        if ($variantes === []) {
            return null;
        }

        $cuentas = Cuentacaja::query()->whereIn('codigo', $variantes)->get();
        if ($cuentas->isEmpty()) {
            return null;
        }

        $buscado = trim($codigo);
        $exacta = $cuentas->first(
            static fn (Cuentacaja $cuenta) => (string) $cuenta->codigo === $buscado
        );

        return $exacta ?? $cuentas->first();
    }

    /**
     * @return list<string>
     */
    private static function variantesCodigo(string $codigo): array
    {
        $codigo = trim($codigo);
        if ($codigo === '') {
            return [];
        }

        $sinCeros = ltrim($codigo, '0');
        $sinCeros = $sinCeros === '' ? '0' : $sinCeros;

        return array_values(array_unique(array_filter([
            $codigo,
            $sinCeros,
            str_pad($codigo, 8, '0', STR_PAD_LEFT),
            str_pad($sinCeros, 8, '0', STR_PAD_LEFT),
        ], static fn ($valor) => $valor !== '')));
    }
}

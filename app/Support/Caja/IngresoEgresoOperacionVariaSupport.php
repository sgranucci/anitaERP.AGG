<?php

declare(strict_types=1);

namespace App\Support\Caja;

use App\Models\Caja\Tipotransaccion_Caja;
use App\Support\Contable\AsientoBalanceSupport;
use App\Support\Numerico\NumeroDecimalLocalSupport;
use InvalidArgumentException;

/**
 * OPV — Operación varia: solo asiento contable, sin cuentas de caja, sin comprobantes IVA ni tesmov Anita.
 *
 * Equivalente a a-tesmov.c OPV: genera el asiento sin mover tesorería.
 */
final class IngresoEgresoOperacionVariaSupport
{
    public const ABREV_OPV = 'OPV';

    public const OPERACION = 'V';

    public const NOMBRE = 'Operación varia';

    public static function esOpv(?Tipotransaccion_Caja $tipo): bool
    {
        if (! $tipo) {
            return false;
        }

        return strtoupper(trim((string) ($tipo->abreviatura ?? ''))) === self::ABREV_OPV
            || strtoupper(trim((string) ($tipo->operacion ?? ''))) === self::OPERACION;
    }

    public static function esOpvPorId(null|int|string $tipoId): bool
    {
        $id = (int) $tipoId;
        if ($id <= 0) {
            return false;
        }

        $tipo = Tipotransaccion_Caja::query()->find($id);

        return self::esOpv($tipo);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function esOpvDesdeData(array $data): bool
    {
        $abrev = strtoupper(trim((string) ($data['tipo_abreviatura'] ?? '')));
        if ($abrev === self::ABREV_OPV) {
            return true;
        }
        $op = strtoupper(trim((string) ($data['tipo_operacion'] ?? '')));
        if ($op === self::OPERACION) {
            return true;
        }

        return self::esOpvPorId($data['tipotransaccion_caja_id'] ?? 0);
    }

    /**
     * OPV no admite líneas de caja: si hay alguna cuenta elegida, se corta.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws InvalidArgumentException
     */
    public static function assertSinCuentasCaja(array $data): void
    {
        if (! self::esOpvDesdeData($data)) {
            return;
        }

        foreach (array_values((array) ($data['cuentacaja_ids'] ?? [])) as $id) {
            if ((int) $id > 0) {
                throw new InvalidArgumentException(
                    'La operación varia (OPV) no mueve cuentas de caja. Quitá las cuentas de caja y dejá solo el asiento contable.'
                );
            }
        }
    }

    /**
     * OPV no admite comprobantes IVA / liquidación tarjeta.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws InvalidArgumentException
     */
    public static function assertSinComprobantesIva(array $data): void
    {
        if (! self::esOpvDesdeData($data)) {
            return;
        }

        $raw = $data['comprobantes_ivacompra_json'] ?? null;
        if ($raw === null || $raw === '' || $raw === []) {
            return;
        }

        $lista = is_string($raw) ? (json_decode($raw, true) ?: []) : (is_array($raw) ? $raw : []);
        if ($lista !== []) {
            throw new InvalidArgumentException(
                'La operación varia (OPV) no admite comprobantes. Quitá los comprobantes de la solapa y dejá solo el asiento contable.'
            );
        }
    }

    /**
     * Exige asiento con importe y balanceado. No valida cuadre contra caja.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws InvalidArgumentException
     */
    public static function assertAsientoSolo(array $data): void
    {
        if (! self::esOpvDesdeData($data)) {
            return;
        }

        self::assertSinCuentasCaja($data);
        self::assertSinComprobantesIva($data);

        $totales = AsientoBalanceSupport::totalesEnMonedaPrimeraLinea(
            (array) ($data['debeasientos'] ?? $data['debes'] ?? []),
            (array) ($data['haberasientos'] ?? $data['haberes'] ?? []),
            (array) ($data['monedaasiento_ids'] ?? $data['moneda_ids'] ?? []),
            (array) ($data['cotizacionasientos'] ?? $data['cotizaciones'] ?? []),
        );

        $total = max(
            round((float) $totales['total_debe'], 2),
            round((float) $totales['total_haber'], 2)
        );

        if ($total <= IngresoEgresoCuadreCajaAsientoSupport::TOLERANCIA) {
            throw new InvalidArgumentException(
                'La operación varia (OPV) requiere un asiento contable con importe.'
            );
        }

        if (! ($totales['balanceado'] ?? false)) {
            throw new InvalidArgumentException(
                'El asiento de la operación varia no está balanceado (Debe ≠ Haber).'
            );
        }

        // Defensa: montos de caja residuales (ids vacíos pero montos) no deben pasar.
        foreach (array_values((array) ($data['montos'] ?? [])) as $monto) {
            if (abs(NumeroDecimalLocalSupport::aFloat($monto)) > 0.000001) {
                throw new InvalidArgumentException(
                    'La operación varia (OPV) no mueve cuentas de caja. Dejá los montos de caja en cero.'
                );
            }
        }
    }
}

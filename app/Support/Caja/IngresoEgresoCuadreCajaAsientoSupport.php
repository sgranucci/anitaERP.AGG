<?php

declare(strict_types=1);

namespace App\Support\Caja;

use App\Support\Numerico\NumeroDecimalLocalSupport;
use InvalidArgumentException;

/**
 * Cuadre cuentas de caja ↔ asiento en IE.
 *
 * Si hay una sola cuenta de caja en monto 0 y el asiento tiene importe,
 * copia el total del asiento a esa cuenta (incidente 97721).
 * El error de discrepancia solo aplica cuando la caja ya tenía monto ≠ 0.
 */
final class IngresoEgresoCuadreCajaAsientoSupport
{
    public const TOLERANCIA = 0.02;

    /**
     * Si hay exactamente una cuenta de caja con monto 0 y el asiento tiene total,
     * asigna ese total (positivo) al monto de la cuenta.
     *
     * @param  array<string, mixed>  $data
     * @return bool true si se copió un monto
     */
    public static function aplicarMontoAsientoSiCajaEnCero(array &$data): bool
    {
        $indices = self::indicesCuentasCajaCargadas($data);
        if (count($indices) !== 1) {
            return false;
        }

        $i = $indices[0];
        $montos = array_values((array) ($data['montos'] ?? []));
        $montoActual = NumeroDecimalLocalSupport::aFloat($montos[$i] ?? 0);
        if (abs($montoActual) > 0.000001) {
            return false;
        }

        $totalesAsiento = self::totalesAsiento($data);
        $totalAsiento = max($totalesAsiento['debe'], $totalesAsiento['haber']);
        if ($totalAsiento <= self::TOLERANCIA) {
            return false;
        }

        while (count($montos) <= $i) {
            $montos[] = 0;
        }
        $montos[$i] = round($totalAsiento, 2);
        $data['montos'] = $montos;

        return true;
    }

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws InvalidArgumentException
     */
    public static function assertCuadre(array $data): void
    {
        $totalesCaja = self::totalesOperacionCaja($data);
        $totalesAsiento = self::totalesAsiento($data);

        $totalOperacion = max($totalesCaja['debe'], $totalesCaja['haber']);
        $totalAsiento = max($totalesAsiento['debe'], $totalesAsiento['haber']);

        // Caja en cero y asiento con importe: no se pudo autocompletar (p. ej. varias cuentas)
        if ($totalAsiento > self::TOLERANCIA && $totalOperacion <= self::TOLERANCIA) {
            throw new InvalidArgumentException(
                'Debe cargar el monto en las cuentas de caja. El asiento tiene importe pero las cuentas de caja quedaron en cero.'
            );
        }

        if ($totalOperacion > self::TOLERANCIA && $totalAsiento <= self::TOLERANCIA) {
            throw new InvalidArgumentException(
                'Debe generar el asiento contable. Las cuentas de caja tienen importe pero el asiento quedó en cero.'
            );
        }

        // Discrepancia solo si la caja ya tenía (o quedó con) importe distinto de cero
        if ($totalOperacion > self::TOLERANCIA
            && abs($totalOperacion - $totalAsiento) > self::TOLERANCIA) {
            throw new InvalidArgumentException(
                'Problemas en el asiento, no coincide el monto total de la operación con el asiento contable.'
            );
        }

        self::assertLineasCajaConMonto($data);
    }

    /**
     * Aplica autocompletado y valida; deja `$data['montos']` listo para persistir.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws InvalidArgumentException
     */
    public static function prepararYAssertCuadre(array &$data): void
    {
        self::aplicarMontoAsientoSiCajaEnCero($data);
        self::assertCuadre($data);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<int>
     */
    public static function indicesCuentasCajaCargadas(array $data): array
    {
        $cuentacajaIds = array_values((array) ($data['cuentacaja_ids'] ?? []));
        $indices = [];
        foreach ($cuentacajaIds as $i => $id) {
            if ((int) $id > 0) {
                $indices[] = (int) $i;
            }
        }

        return $indices;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{debe: float, haber: float}
     */
    public static function totalesOperacionCaja(array $data): array
    {
        $debe = 0.0;
        $haber = 0.0;

        $montos = array_values((array) ($data['montos'] ?? []));
        $cuentacajaIds = array_values((array) ($data['cuentacaja_ids'] ?? []));
        $n = max(count($montos), count($cuentacajaIds));

        for ($i = 0; $i < $n; $i++) {
            $cuentaId = (int) ($cuentacajaIds[$i] ?? 0);
            if ($cuentaId <= 0) {
                continue;
            }
            $monto = NumeroDecimalLocalSupport::aFloat($montos[$i] ?? 0);
            if ($monto >= 0) {
                $debe += $monto;
            } else {
                $haber += abs($monto);
            }
        }

        foreach ((array) ($data['montocheque_recibidos'] ?? []) as $m) {
            $v = NumeroDecimalLocalSupport::aFloat($m);
            if ($v > 0) {
                $debe += $v;
            }
        }
        foreach ((array) ($data['montocheque_emitidos'] ?? []) as $m) {
            $v = NumeroDecimalLocalSupport::aFloat($m);
            if ($v > 0) {
                $haber += $v;
            }
        }

        return ['debe' => round($debe, 2), 'haber' => round($haber, 2)];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{debe: float, haber: float}
     */
    public static function totalesAsiento(array $data): array
    {
        $debe = 0.0;
        $haber = 0.0;

        foreach ((array) ($data['debeasientos'] ?? $data['debes'] ?? []) as $m) {
            $debe += NumeroDecimalLocalSupport::aFloat($m);
        }
        foreach ((array) ($data['haberasientos'] ?? $data['haberes'] ?? []) as $m) {
            $haber += NumeroDecimalLocalSupport::aFloat($m);
        }

        return ['debe' => round($debe, 2), 'haber' => round($haber, 2)];
    }

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws InvalidArgumentException
     */
    private static function assertLineasCajaConMonto(array $data): void
    {
        $montos = array_values((array) ($data['montos'] ?? []));
        $cuentacajaIds = array_values((array) ($data['cuentacaja_ids'] ?? []));
        $n = max(count($montos), count($cuentacajaIds));

        for ($i = 0; $i < $n; $i++) {
            $cuentaId = (int) ($cuentacajaIds[$i] ?? 0);
            if ($cuentaId <= 0) {
                continue;
            }
            $monto = NumeroDecimalLocalSupport::aFloat($montos[$i] ?? 0);
            if (abs($monto) < 0.000001) {
                throw new InvalidArgumentException(
                    'Ingrese un monto en todas las cuentas de caja seleccionadas.'
                );
            }
        }
    }
}

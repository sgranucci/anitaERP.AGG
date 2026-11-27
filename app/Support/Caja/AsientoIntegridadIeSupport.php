<?php

namespace App\Support\Caja;

use App\Models\Caja\Caja_Movimiento_Cuentacaja;
use App\Models\Caja\Cuentacaja;
use App\Models\Contable\Asiento;
use App\Models\Contable\Asiento_Movimiento;
use App\Support\Numerico\NumeroDecimalLocalSupport;
use InvalidArgumentException;

/**
 * Integridad asiento ↔ ingreso/egreso de caja.
 *
 * Un asiento que nace de un I/E (caja_movimiento_id) tiene líneas de banco cuyas cuentas
 * contables salen de las cuentas de caja del movimiento. Su cuenta e importe se cambian
 * desde el I/E, que regenera el asiento y reescribe tesmov en Anita. Desde el asiento
 * solo se pueden cambiar las demás líneas: si se toca una línea de banco, el asiento deja
 * de coincidir con caja/tesmov (incidente EGR 56282, 26/11/2026: asiento a 25.524.400
 * con caja y tesmov a 25.884.400).
 *
 * Compara, en pesos, la lista de líneas de banco (cuenta contable + importe) antes y
 * después del guardado. Cualquier diferencia se rechaza.
 */
final class AsientoIntegridadIeSupport
{
    public const MENSAJE_BLOQUEO = 'Este asiento viene de un ingreso/egreso de caja. '
        .'La cuenta bancaria y el importe de sus líneas de banco solo se cambian desde el I/E.';

    /**
     * @param  array<string, mixed>  $payload  Payload del formulario de asiento (cuentacontable_ids, debes, haberes, moneda_ids, cotizaciones)
     *
     * @throws InvalidArgumentException
     */
    public static function assertPuedeGuardar(Asiento $asiento, array $payload): void
    {
        $cajaId = (int) ($asiento->caja_movimiento_id ?? 0);
        if ($cajaId <= 0) {
            return;
        }

        $cuentasBanco = self::cuentasContablesBanco($cajaId);
        if ($cuentasBanco === []) {
            return;
        }

        self::assertFirmasIguales(
            self::firmasDesdeBase($asiento, $cuentasBanco),
            self::firmasDesdePayload($payload, $cuentasBanco, $asiento->fecha)
        );
    }

    /**
     * @param  list<string>  $firmasBase
     * @param  list<string>  $firmasPayload
     *
     * @throws InvalidArgumentException
     */
    public static function assertFirmasIguales(array $firmasBase, array $firmasPayload): void
    {
        sort($firmasBase);
        sort($firmasPayload);

        if ($firmasBase !== $firmasPayload) {
            throw new InvalidArgumentException(self::MENSAJE_BLOQUEO);
        }
    }

    /**
     * Cuentas contables de las cuentas de caja del movimiento (líneas de banco del asiento).
     *
     * @return list<int>
     */
    public static function cuentasContablesBanco(int $cajaMovimientoId): array
    {
        $cuentacajaIds = Caja_Movimiento_Cuentacaja::query()
            ->where('caja_movimiento_id', $cajaMovimientoId)
            ->pluck('cuentacaja_id')
            ->all();
        if ($cuentacajaIds === []) {
            return [];
        }

        return Cuentacaja::query()
            ->whereIn('id', $cuentacajaIds)
            ->where('cuentacontable_id', '>', 0)
            ->pluck('cuentacontable_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  list<int>  $cuentasBanco
     * @return list<string>
     */
    private static function firmasDesdeBase(Asiento $asiento, array $cuentasBanco): array
    {
        $firmas = [];
        $lineas = Asiento_Movimiento::query()
            ->where('asiento_id', $asiento->id)
            ->whereIn('cuentacontable_id', $cuentasBanco)
            ->get(['cuentacontable_id', 'monto', 'moneda_id', 'cotizacion']);

        foreach ($lineas as $linea) {
            $importe = (float) $linea->monto;
            if (abs($importe) < 0.0001) {
                continue;
            }
            $pesos = IngresoEgresoImputacionDiariaSupport::aPesos(
                $importe,
                (int) ($linea->moneda_id ?: 1),
                $linea->cotizacion ?? 1,
                $asiento->fecha,
            );
            $firmas[] = self::firma((int) $linea->cuentacontable_id, $pesos);
        }

        return $firmas;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  list<int>  $cuentasBanco
     * @return list<string>
     */
    private static function firmasDesdePayload(array $payload, array $cuentasBanco, mixed $fecha): array
    {
        $cuentas = $payload['cuentacontable_ids'] ?? [];
        $debes = $payload['debes'] ?? [];
        $haberes = $payload['haberes'] ?? [];
        $monedas = $payload['moneda_ids'] ?? [];
        $cotizaciones = $payload['cotizaciones'] ?? [];

        $firmas = [];
        foreach ($cuentas as $i => $cuentaRaw) {
            $cuenta = (int) $cuentaRaw;
            if (! in_array($cuenta, $cuentasBanco, true)) {
                continue;
            }

            $importe = NumeroDecimalLocalSupport::aFloat($debes[$i] ?? null)
                - NumeroDecimalLocalSupport::aFloat($haberes[$i] ?? null);
            if (abs($importe) < 0.0001) {
                continue;
            }

            $moneda = (int) ($monedas[$i] ?? 1);
            $pesos = IngresoEgresoImputacionDiariaSupport::aPesos(
                $importe,
                $moneda > 0 ? $moneda : 1,
                $cotizaciones[$i] ?? 1,
                $fecha,
            );
            $firmas[] = self::firma($cuenta, $pesos);
        }

        return $firmas;
    }

    private static function firma(int $cuentaContableId, float $pesos): string
    {
        return $cuentaContableId.'|'.number_format(round($pesos, 2), 2, '.', '');
    }
}

<?php

declare(strict_types=1);

namespace App\Support\Caja\RendicionMaquina;

use App\Models\Caja\Cuentacaja;

/**
 * Precarga del valor TotalCoin QR Máquinas en mañana y Completo:
 * drop QR rodillo (neto WIGOS) + impuesto QR.
 *
 * Distingue TOTAL COIN MAQUINAS de TOTAL COIN CAJA y de M0QR (QR Máquinas).
 *
 * En mañana la planilla de depósitos usa Neto = TotalCoin − impuesto QR.
 * Si el tesorero tipea el TotalCoin de la planilla, el drop QR tiene que
 * seguir ese neto; si no, la transferencia se mueve.
 *
 * Completo (fecha F): el QR del día es el mismo dato que carga la Mañana de F+1
 * (desfase jornada). Si esa Mañana ya tiene TotalCoin (con ajustes), prevalece
 * sobre drop+impuesto del Completo para no perder el ajuste.
 */
final class RendicionMaquinaValorQrPrecargaSupport
{
    /**
     * @param  array<string, float|int|string>  $inputs
     */
    public static function montoDesdeInputs(array $inputs): float
    {
        $drop = self::input($inputs, 'dropqr_rodillo');
        $impuesto = self::input($inputs, 'impuesto_qr');

        return round($drop + $impuesto, 2);
    }

    public static function dropQrDesdeTotalCoin(float $totalCoin, float $impuestoQr): float
    {
        return round($totalCoin - $impuestoQr, 2);
    }

    /**
     * @param  list<array<string, mixed>>  $valores
     */
    public static function montoTotalCoinEnValores(array $valores): ?float
    {
        foreach ($valores as $linea) {
            if (! self::esTotalCoinQrMaquinas($linea)) {
                continue;
            }

            return round((float) ($linea['monto'] ?? 0), 2);
        }

        return null;
    }

    /**
     * Completa nombre/descripcion desde cuentacaja cuando el payload solo trae id+monto.
     *
     * @param  list<array<string, mixed>>  $valores
     * @return list<array<string, mixed>>
     */
    public static function hidratarNombresValores(array $valores): array
    {
        $ids = [];
        foreach ($valores as $linea) {
            $texto = trim(implode(' ', [
                (string) ($linea['nombre'] ?? ''),
                (string) ($linea['descripcion_operaciones'] ?? ''),
                (string) ($linea['nombre_maestro'] ?? ''),
            ]));
            if ($texto !== '') {
                continue;
            }
            $id = (int) ($linea['cuentacaja_id'] ?? 0);
            if ($id > 0) {
                $ids[] = $id;
            }
        }
        $ids = array_values(array_unique($ids));
        if ($ids === []) {
            return $valores;
        }

        $cuentas = Cuentacaja::query()
            ->whereIn('id', $ids)
            ->get(['id', 'nombre', 'descripcion_operaciones'])
            ->keyBy('id');

        foreach ($valores as $i => $linea) {
            $id = (int) ($linea['cuentacaja_id'] ?? 0);
            $cc = $cuentas->get($id);
            if ($cc === null) {
                continue;
            }
            $nombreOp = trim((string) $cc->descripcion_operaciones);
            $nombreMae = trim((string) $cc->nombre);
            $valores[$i]['nombre'] = (string) ($linea['nombre'] ?? ($nombreOp !== '' ? $nombreOp : $nombreMae));
            $valores[$i]['descripcion_operaciones'] = (string) ($linea['descripcion_operaciones'] ?? $cc->descripcion_operaciones);
            $valores[$i]['nombre_maestro'] = (string) ($linea['nombre_maestro'] ?? $cc->nombre);
        }

        return $valores;
    }

    /**
     * Mañana: drop QR rodillo = TotalCoin QR Máquinas − impuesto QR (planilla).
     * No pisa el drop si TotalCoin está vacío (alta antes de Traer WIGOS).
     *
     * @param  array<string, float|int|string>  $inputs
     * @param  list<array<string, mixed>>  $valores
     * @return array<string, float|int|string>
     */
    public static function alinearDropQrConTotalCoinManiana(string $turno, array $inputs, array $valores): array
    {
        if (! RendicionMaquinaTurno::esManiana($turno)) {
            return $inputs;
        }

        $totalCoin = self::montoTotalCoinEnValores($valores);
        if ($totalCoin === null) {
            return $inputs;
        }

        $dropActual = self::input($inputs, 'dropqr_rodillo');
        if (abs($totalCoin) < 0.005 && abs($dropActual) > 0.005) {
            return $inputs;
        }

        $impuesto = self::input($inputs, 'impuesto_qr');
        $dropEsperado = self::dropQrDesdeTotalCoin($totalCoin, $impuesto);
        if (abs($dropEsperado - $dropActual) < 0.005) {
            return $inputs;
        }

        $inputs['dropqr_rodillo'] = $dropEsperado;

        return $inputs;
    }

    /**
     * @param  array<string, mixed>  $linea
     */
    public static function esTotalCoinQrMaquinas(array $linea): bool
    {
        $texto = mb_strtolower(trim(implode(' ', [
            (string) ($linea['nombre'] ?? ''),
            (string) ($linea['descripcion_operaciones'] ?? ''),
            (string) ($linea['nombre_maestro'] ?? ''),
        ])));
        if ($texto === '') {
            return false;
        }

        $esTotalCoin = str_contains($texto, 'totalcoin') || str_contains($texto, 'total coin');
        $esMaquina = str_contains($texto, 'maquin');

        return $esTotalCoin && $esMaquina;
    }

    /**
     * Monto a precargar en Completo: TotalCoin de la Mañana del día siguiente
     * (mismo QR de jornada, con ajustes) o, si no hay, drop QR + impuesto QR.
     */
    public static function montoPrecargaCompleto(array $inputs, ?float $totalCoinManianaDiaSiguiente): float
    {
        if ($totalCoinManianaDiaSiguiente !== null && abs($totalCoinManianaDiaSiguiente) >= 0.005) {
            return round($totalCoinManianaDiaSiguiente, 2);
        }

        return self::montoDesdeInputs($inputs);
    }

    /**
     * @param  array<string, float|int|string>  $inputs
     * @param  list<array<string, mixed>>  $valores
     * @return list<array{cuentacaja_id: int, monto: float}>
     */
    public static function lineasPrecarga(array $inputs, array $valores, ?float $montoOverride = null): array
    {
        $monto = $montoOverride !== null
            ? round($montoOverride, 2)
            : self::montoDesdeInputs($inputs);
        $out = [];
        foreach ($valores as $linea) {
            if (! self::esTotalCoinQrMaquinas($linea)) {
                continue;
            }
            $id = (int) ($linea['cuentacaja_id'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            $out[] = [
                'cuentacaja_id' => $id,
                'monto' => $monto,
            ];
        }

        return $out;
    }

    /**
     * @param  array<string, float|int|string>  $inputs
     */
    private static function input(array $inputs, string $clave): float
    {
        if (array_key_exists($clave, $inputs)) {
            return (float) $inputs[$clave];
        }
        $ruta = 'inputs.'.$clave;
        if (array_key_exists($ruta, $inputs)) {
            return (float) $inputs[$ruta];
        }

        return 0.0;
    }
}

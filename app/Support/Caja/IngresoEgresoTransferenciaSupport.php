<?php

declare(strict_types=1);

namespace App\Support\Caja;

use App\Models\Caja\Cuentacaja;
use App\Models\Caja\Tipotransaccion_Caja;
use App\Models\Configuracion\Empresa;
use App\Support\Numerico\NumeroDecimalLocalSupport;
use InvalidArgumentException;

/**
 * Transferencia entre cuentas de caja vía Ingreso/Egreso (TRA).
 *
 * Montos: positivos = entrada (Debe), negativos = salida (Haber).
 * Tipo con signo I para no invertir los signos cargados por el usuario.
 */
final class IngresoEgresoTransferenciaSupport
{
    public const ABREV_TRA = 'TRA';

    public const OPERACION = 'T';

    public const NOMBRE = 'Transferencia';

    public const TOLERANCIA = 0.02;

    public static function esTransferencia(?Tipotransaccion_Caja $tipo): bool
    {
        if (! $tipo) {
            return false;
        }

        return strtoupper(trim((string) ($tipo->abreviatura ?? ''))) === self::ABREV_TRA
            || strtoupper(trim((string) ($tipo->operacion ?? ''))) === self::OPERACION;
    }

    public static function esTransferenciaPorId(null|int|string $tipoId): bool
    {
        $id = (int) $tipoId;
        if ($id <= 0) {
            return false;
        }

        $tipo = Tipotransaccion_Caja::query()->find($id);

        return self::esTransferencia($tipo);
    }

    /**
     * Valida balance financiero (cuentas de caja) y contable (asiento).
     *
     * @param  array<string, mixed>  $data
     *
     * @throws InvalidArgumentException
     */
    public static function assertBalanceado(array $data): void
    {
        $tipoId = (int) ($data['tipotransaccion_caja_id'] ?? 0);
        if (! self::esTransferenciaPorId($tipoId)) {
            return;
        }

        $montos = array_values((array) ($data['montos'] ?? []));
        $cuentacajaIds = array_values((array) ($data['cuentacaja_ids'] ?? []));
        $cotizaciones = array_values((array) ($data['cotizaciones'] ?? []));
        $monedaIds = array_values((array) ($data['moneda_ids'] ?? []));

        $totalEntrada = 0.0;
        $totalSalida = 0.0;
        $lineasValidas = 0;
        $hayEntrada = false;
        $haySalida = false;

        $n = max(count($montos), count($cuentacajaIds));
        for ($i = 0; $i < $n; $i++) {
            $cuentaId = (int) ($cuentacajaIds[$i] ?? 0);
            $monto = self::parseMonto($montos[$i] ?? null);
            if ($cuentaId <= 0 || abs($monto) < 0.000001) {
                continue;
            }

            $lineasValidas++;
            $coef = 1.0;
            if (function_exists('calculaCoeficienteMoneda')) {
                $monedaRef = (int) ($monedaIds[0] ?? 0);
                $monedaLin = (int) ($monedaIds[$i] ?? $monedaRef);
                $cotiz = (float) ($cotizaciones[$i] ?? 1);
                if ($monedaRef > 0 && $monedaLin > 0) {
                    $coef = (float) calculaCoeficienteMoneda($monedaRef, $monedaLin, $cotiz);
                }
            }

            $importe = round($monto * $coef, 2);
            if ($importe > 0) {
                $totalEntrada += $importe;
                $hayEntrada = true;
            } else {
                $totalSalida += abs($importe);
                $haySalida = true;
            }
        }

        if ($lineasValidas < 2 || ! $hayEntrada || ! $haySalida) {
            throw new InvalidArgumentException(
                'Transferencia: cargue al menos una cuenta de entrada (monto positivo) y una de salida (monto negativo).'
            );
        }

        if (abs($totalEntrada - $totalSalida) > self::TOLERANCIA || $totalEntrada < self::TOLERANCIA) {
            throw new InvalidArgumentException(
                'Transferencia: las cuentas de caja deben quedar balanceadas (entradas = salidas). '
                .'Entradas: '.number_format($totalEntrada, 2, ',', '.')
                .' / Salidas: '.number_format($totalSalida, 2, ',', '.')
            );
        }

        $debes = array_values((array) ($data['debeasientos'] ?? []));
        $haberes = array_values((array) ($data['haberasientos'] ?? []));
        $cuentasContables = array_values((array) ($data['cuentacontable_ids'] ?? []));

        $totalDebe = 0.0;
        $totalHaber = 0.0;
        $lineasAsiento = 0;

        $m = max(count($debes), count($haberes), count($cuentasContables));
        for ($i = 0; $i < $m; $i++) {
            $cta = (int) ($cuentasContables[$i] ?? 0);
            $debe = self::parseMonto($debes[$i] ?? null);
            $haber = self::parseMonto($haberes[$i] ?? null);
            if ($cta <= 0 && abs($debe) < 0.000001 && abs($haber) < 0.000001) {
                continue;
            }
            if ($cta <= 0) {
                throw new InvalidArgumentException(
                    'Transferencia: el asiento contable tiene renglones sin cuenta contable.'
                );
            }
            $lineasAsiento++;
            $totalDebe += abs($debe);
            $totalHaber += abs($haber);
        }

        if ($lineasAsiento < 2) {
            throw new InvalidArgumentException(
                'Transferencia: debe generar el asiento contable (solapa Asiento Contable) con al menos dos imputaciones.'
            );
        }

        $totalDebe = round($totalDebe, 2);
        $totalHaber = round($totalHaber, 2);

        if (abs($totalDebe - $totalHaber) > self::TOLERANCIA || $totalDebe < self::TOLERANCIA) {
            throw new InvalidArgumentException(
                'Transferencia: el asiento contable debe estar balanceado (Debe = Haber). '
                .'Debe: '.number_format($totalDebe, 2, ',', '.')
                .' / Haber: '.number_format($totalHaber, 2, ',', '.')
            );
        }

        if (abs($totalDebe - $totalEntrada) > self::TOLERANCIA) {
            throw new InvalidArgumentException(
                'Transferencia: el total del asiento debe coincidir con el total de la operación de caja. '
                .'Operación: '.number_format($totalEntrada, 2, ',', '.')
                .' / Asiento: '.number_format($totalDebe, 2, ',', '.')
            );
        }
    }

    /**
     * Las cuentas de caja del movimiento tienen que ser de la empresa del encabezado
     * (o multiempresa). Cambiar el selector de empresa no habilita cuentas de otra.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws InvalidArgumentException
     */
    public static function assertCuentasDeLaEmpresa(array $data): void
    {
        $empresaId = (int) ($data['empresa_id'] ?? 0);
        if ($empresaId <= 0) {
            return;
        }

        $ids = [];
        foreach (['cuentacaja_ids', 'cuentacaja_emitido_ids', 'cuentacaja_reemplazo_ids'] as $campo) {
            foreach ((array) ($data[$campo] ?? []) as $id) {
                $id = (int) $id;
                if ($id > 0) {
                    $ids[$id] = $id;
                }
            }
        }
        if ($ids === []) {
            return;
        }

        $cuentas = Cuentacaja::query()
            ->with('empresas:id,nombre')
            ->whereIn('id', array_values($ids))
            ->get();

        $encontradas = $cuentas->map(fn (Cuentacaja $c) => (int) $c->id)->all();
        if (array_diff(array_values($ids), $encontradas) !== []) {
            throw new InvalidArgumentException('Hay cuentas de caja que no existen.');
        }

        $ajenas = $cuentas->filter(fn (Cuentacaja $c) => ! $c->perteneceAEmpresa($empresaId))->values();
        if ($ajenas->isEmpty()) {
            return;
        }

        $nombreMov = (string) (Empresa::query()->whereKey($empresaId)->value('nombre') ?: 'la empresa del movimiento');
        $lista = $ajenas->map(function (Cuentacaja $c) {
            $duena = trim((string) ($c->empresas->nombre ?? 'otra empresa'));

            return trim((string) $c->codigo.' '.$c->nombre).' ('.$duena.')';
        })->implode(', ');

        throw new InvalidArgumentException(
            'No se puede grabar el movimiento con cuentas de otra empresa. Es de '.$nombreMov
            .' y estas cuentas no le pertenecen: '.$lista.'.'
        );
    }

    private static function parseMonto(mixed $valor): float
    {
        return NumeroDecimalLocalSupport::aFloat($valor, 0.0);
    }
}

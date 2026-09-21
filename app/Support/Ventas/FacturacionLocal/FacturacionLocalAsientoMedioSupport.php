<?php

namespace App\Support\Ventas\FacturacionLocal;

use App\Models\Caja\Cuentacaja;
use InvalidArgumentException;

/**
 * Contrapartida contable del asiento VTA en POS Local: cuentas de los medios de pago
 * (no deudores por ventas). Escala los montos cobrados al total del comprobante FAC.
 */
final class FacturacionLocalAsientoMedioSupport
{
    public const IMPORTE_MINIMO_ARCA = 0.01;

    /**
     * @param  list<array{cuentacaja_id?:int,monto?:float,cotizacion?:float|null}>  $medios
     * @return list<array{cuentacontable_id:int,monto:float}>
     */
    public static function contrapartidasDesdeMedios(array $medios, float $totalAsiento, int $empresaId): array
    {
        $totalAsiento = round(abs($totalAsiento), 2);
        if ($totalAsiento < 0.009 || $medios === []) {
            return [];
        }

        $ids = [];
        foreach ($medios as $medio) {
            $id = (int) ($medio['cuentacaja_id'] ?? 0);
            if ($id > 0) {
                $ids[] = $id;
            }
        }
        if ($ids === []) {
            return [];
        }

        $cuentas = Cuentacaja::query()
            ->whereIn('id', array_values(array_unique($ids)))
            ->get(['id', 'codigo', 'nombre', 'cuentacontable_id', 'empresa_id'])
            ->keyBy('id');

        $brutos = [];
        $sumaBruto = 0.;
        foreach ($medios as $medio) {
            $cuentacajaId = (int) ($medio['cuentacaja_id'] ?? 0);
            $cuenta = $cuentas->get($cuentacajaId);
            if (! $cuenta) {
                throw new InvalidArgumentException(
                    'Medio de pago inexistente (cuenta de caja id '.$cuentacajaId.').'
                );
            }
            if (! $cuenta->perteneceAEmpresa($empresaId)) {
                throw new InvalidArgumentException(
                    'La cuenta de caja '.$cuenta->codigo.' no pertenece a la empresa del local.'
                );
            }
            $ctaId = (int) ($cuenta->cuentacontable_id ?? 0);
            if ($ctaId <= 0) {
                throw new InvalidArgumentException(
                    'La cuenta de caja '.$cuenta->codigo.' ('.$cuenta->nombre.') no tiene cuenta contable. Configure el medio de pago.'
                );
            }
            $cot = (float) ($medio['cotizacion'] ?? 1.);
            if ($cot <= 0.) {
                $cot = 1.;
            }
            $monto = round((float) ($medio['monto'] ?? 0) * $cot, 2);
            if ($monto < 0.009) {
                continue;
            }
            $brutos[] = ['cuentacontable_id' => $ctaId, 'monto' => $monto];
            $sumaBruto += $monto;
        }

        if ($brutos === [] || $sumaBruto < 0.009) {
            return [];
        }

        $factor = $totalAsiento / $sumaBruto;
        $out = [];
        $acum = 0.;
        $ultimo = count($brutos) - 1;
        foreach ($brutos as $i => $fila) {
            if ($i === $ultimo) {
                $monto = round($totalAsiento - $acum, 2);
            } else {
                $monto = round($fila['monto'] * $factor, 2);
                $acum += $monto;
            }
            if ($monto < 0.009) {
                continue;
            }
            $ctaId = (int) $fila['cuentacontable_id'];
            if (isset($out[$ctaId])) {
                $out[$ctaId]['monto'] = round($out[$ctaId]['monto'] + $monto, 2);
            } else {
                $out[$ctaId] = [
                    'cuentacontable_id' => $ctaId,
                    'monto' => $monto,
                ];
            }
        }

        return array_values($out);
    }
}

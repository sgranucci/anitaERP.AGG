<?php

namespace App\Support\Contable\Efe;

use App\Support\Contable\MayorConcepto\MayorConceptoMemoriaMotor;

/**
 * Columnas O (Pagos) y P (Cobros) de la solapa Datos del EFE.
 */
class EfeDatosPagosCobrosSupport
{
    /**
     * @param  array<string, mixed>  $linea
     * @return array{pagos: ?float, cobros: ?float}|null  null = omitir
     */
    public function resolver(array $linea): ?array
    {
        $debe = (float) ($linea['debe'] ?? 0);
        $haber = (float) ($linea['haber'] ?? 0);

        if ($debe < 0 || $haber < 0) {
            return null;
        }

        if ($debe <= 0 && $haber <= 0) {
            return null;
        }

        $cuenta = (int) ($linea['cuenta'] ?? 0);
        $cuentaDisp = (int) ($linea['cuenta_disponibilidad'] ?? 0);
        $esCuentaDisponibilidad = $this->esCuentaDisponibilidadMostrada($cuenta, $cuentaDisp);

        if ($esCuentaDisponibilidad) {
            $pagos = $haber > 0 ? round($haber, 2) : null;
            $cobros = $debe > 0 ? round($debe, 2) : null;
        } else {
            $pagos = $debe > 0 ? round($debe, 2) : null;
            $cobros = $haber > 0 ? round($haber, 2) : null;
        }

        if (($pagos ?? 0) <= 0 && ($cobros ?? 0) <= 0) {
            return null;
        }

        if ($this->esCuentaIntercoCredito($cuenta)) {
            return ['pagos' => $cobros, 'cobros' => $pagos];
        }

        return ['pagos' => $pagos, 'cobros' => $cobros];
    }

    /** Solo préstamo HW (116010-004): Anita invierte pagos/cobros vs mayor. */
    private function esCuentaIntercoCredito(int $cuenta): bool
    {
        return $cuenta === 116010004;
    }

    private function esCuentaDisponibilidadMostrada(int $cuenta, int $cuentaDisp): bool
    {
        if ($cuentaDisp > 0) {
            return $cuenta === $cuentaDisp;
        }

        return $cuenta > 0 && $cuenta <= MayorConceptoMemoriaMotor::LIMITE_DISPONIBILIDAD;
    }
}

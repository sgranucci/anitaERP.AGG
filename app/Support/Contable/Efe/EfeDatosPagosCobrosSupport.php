<?php

namespace App\Support\Contable\Efe;

use App\Support\Contable\MayorConcepto\MayorConceptoMemoriaMotor;

/**
 * Columnas O (Pagos) y P (Cobros) de la solapa Datos del EFE.
 */
class EfeDatosPagosCobrosSupport
{
    public function __construct(
        private readonly EfeClasificacionConceptoSupport $clasificacionSupport = new EfeClasificacionConceptoSupport(),
    ) {
    }

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
        $origen = (string) ($linea['origen'] ?? '');
        $esCuentaDisponibilidad = $this->esCuentaDisponibilidadMostrada($cuenta, $cuentaDisp, $origen);

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

    /**
     * Inversión O/P solo en traspasos compensables (concepto 53); IZV/OPP en caja usan debe→Pagos.
     */
    private function esCuentaDisponibilidadMostrada(int $cuenta, int $cuentaDisp, string $origen): bool
    {
        if (! $this->clasificacionSupport->esTraspasoCompensableEfe($origen)) {
            return false;
        }

        if ($cuentaDisp > 0 && $cuenta === $cuentaDisp) {
            return $cuenta > 0 && $cuenta <= MayorConceptoMemoriaMotor::LIMITE_DISPONIBILIDAD;
        }

        return $cuenta > 0 && $cuenta <= MayorConceptoMemoriaMotor::LIMITE_DISPONIBILIDAD;
    }
}

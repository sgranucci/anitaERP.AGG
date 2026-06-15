<?php

namespace App\Support\Contable\MayorConcepto;

use App\Models\Caja\Cuentacaja;
use App\Models\Contable\Cuentacontable;

/**
 * Resuelve tipos auxpag de medio de pago (tctes / mediopago ERP) a cuenta contable.
 */
class MayorConceptoMediopagoSupport
{
    /** @var array<string, int> */
    private array $cuentaPorMedio = [];

    public function esMedioPagoAuxpag(string $tipoAp): bool
    {
        return in_array(strtoupper(trim($tipoAp)), MayorConceptoMemoriaMotor::TIPOS_MEDIO_PAGO_AUXPAG, true);
    }

    public function esAuxpagIgnorado(string $tipoAp): bool
    {
        return in_array(strtoupper(trim($tipoAp)), MayorConceptoMemoriaMotor::TIPOS_AUXPAG_IGNORAR, true);
    }

    public function cuentaContableDesdeMedio(string $tipoAp): int
    {
        $clave = strtoupper(trim($tipoAp));
        if ($clave === '') {
            return 0;
        }

        if (! array_key_exists($clave, $this->cuentaPorMedio)) {
            $this->cuentaPorMedio[$clave] = $this->resolverCuentaContable($clave);
        }

        return $this->cuentaPorMedio[$clave];
    }

    private function resolverCuentaContable(string $codigoMedio): int
    {
        $mediopago = \App\Models\Caja\Mediopago::query()
            ->where('codigo', $codigoMedio)
            ->with('cuentacajas.cuentacontables')
            ->first();

        if ($mediopago === null || $mediopago->cuentacajas === null) {
            return 0;
        }

        $cuentacaja = $mediopago->cuentacajas;
        $cuenta = $cuentacaja->cuentacontables;

        return (int) ($cuenta->codigo ?? 0);
    }
}

<?php

namespace App\Support\Configuracion\ReArbolTriggerEvaluators;

use App\Models\Configuracion\Arbolaprobacion_ReTrigger;
use App\Support\Configuracion\ReArbolRamaSupport;
use App\Support\Configuracion\ReArbolTriggerCatalog;

final class LineaSinCuentaEvaluator implements ReArbolTriggerEvaluatorInterface
{
    public function codigo(): string
    {
        return ReArbolTriggerCatalog::EVAL_LINEA_SIN_CUENTA;
    }

    public function aplica(ReArbolTriggerEvalContext $ctx, Arbolaprobacion_ReTrigger $trigger): bool
    {
        $ids = ReArbolRamaSupport::cuentacontableIdsDesdeRequisicion($ctx->requisicion);
        if ($ids === []) {
            return true;
        }

        foreach ($ids as $cuentaId) {
            if ((int) $cuentaId <= 0) {
                return true;
            }
        }

        return false;
    }
}

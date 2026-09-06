<?php

namespace App\Support\Configuracion\ReArbolTriggerEvaluators;

use App\Models\Configuracion\Arbolaprobacion_ReTrigger;
use App\Support\Configuracion\ReArbolRamaSupport;
use App\Support\Configuracion\ReArbolTriggerCatalog;

final class CuentaEspecificaEvaluator implements ReArbolTriggerEvaluatorInterface
{
    public function codigo(): string
    {
        return ReArbolTriggerCatalog::EVAL_CUENTA_ESPECIFICA;
    }

    public function aplica(ReArbolTriggerEvalContext $ctx, Arbolaprobacion_ReTrigger $trigger): bool
    {
        $cuentaCfg = (int) ($trigger->param_cuentacontable_id ?? 0);
        if ($cuentaCfg <= 0) {
            return false;
        }

        $ids = ReArbolRamaSupport::cuentacontableIdsDesdeRequisicion($ctx->requisicion);

        return in_array($cuentaCfg, $ids, true);
    }
}

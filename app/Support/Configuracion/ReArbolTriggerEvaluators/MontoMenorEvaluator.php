<?php

namespace App\Support\Configuracion\ReArbolTriggerEvaluators;

use App\Models\Configuracion\Arbolaprobacion_ReTrigger;
use App\Support\Configuracion\ReArbolTriggerCatalog;

final class MontoMenorEvaluator implements ReArbolTriggerEvaluatorInterface
{
    public function codigo(): string
    {
        return ReArbolTriggerCatalog::EVAL_MONTO_MENOR;
    }

    public function aplica(ReArbolTriggerEvalContext $ctx, Arbolaprobacion_ReTrigger $trigger): bool
    {
        $umbral = (float) ($trigger->param_monto ?? 0);
        if ($umbral <= 0) {
            return false;
        }

        $monedaCfg = (int) ($trigger->param_moneda_id ?? 0);
        if ($monedaCfg > 0 && $monedaCfg !== $ctx->monedaId) {
            return false;
        }

        return $ctx->monto < $umbral;
    }
}

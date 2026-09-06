<?php

namespace App\Support\Configuracion\ReArbolTriggerEvaluators;

use App\Models\Configuracion\Arbolaprobacion_ReTrigger;
use App\Support\Configuracion\ReArbolTriggerCatalog;

final class SiempreEvaluator implements ReArbolTriggerEvaluatorInterface
{
    public function codigo(): string
    {
        return ReArbolTriggerCatalog::EVAL_SIEMPRE;
    }

    public function aplica(ReArbolTriggerEvalContext $ctx, Arbolaprobacion_ReTrigger $trigger): bool
    {
        return true;
    }
}

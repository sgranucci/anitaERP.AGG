<?php

namespace App\Support\Configuracion\ReArbolTriggerEvaluators;

use App\Models\Configuracion\Arbolaprobacion_ReTrigger;
use App\Support\Configuracion\ReArbolRamaCatalog;
use App\Support\Configuracion\ReArbolRamaSupport;
use App\Support\Configuracion\ReArbolTriggerCatalog;

final class CuentasAllowlistTodasEvaluator implements ReArbolTriggerEvaluatorInterface
{
    public function codigo(): string
    {
        return ReArbolTriggerCatalog::EVAL_CUENTAS_ALLOWLIST_TODAS;
    }

    public function aplica(ReArbolTriggerEvalContext $ctx, Arbolaprobacion_ReTrigger $trigger): bool
    {
        $rama = ReArbolRamaSupport::resolverRama($ctx->arbol, $ctx->requisicion, $ctx->centrocostoArbol);

        return $rama === ReArbolRamaCatalog::RAMA_A;
    }
}

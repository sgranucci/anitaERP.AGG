<?php

namespace App\Support\Configuracion\OcArbolTriggerEvaluators;

use App\Models\Compras\Ordencompra;
use App\Models\Configuracion\Arbolaprobacion_OcTrigger;

interface OcArbolTriggerEvaluatorInterface
{
    public function codigo(): string;

    public function aplica(Ordencompra $ordencompra, Arbolaprobacion_OcTrigger $trigger): bool;
}

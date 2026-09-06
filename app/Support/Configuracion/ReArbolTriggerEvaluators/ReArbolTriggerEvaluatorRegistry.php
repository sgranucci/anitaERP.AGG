<?php

namespace App\Support\Configuracion\ReArbolTriggerEvaluators;

use App\Models\Configuracion\Arbolaprobacion_ReTrigger;

final class ReArbolTriggerEvaluatorRegistry
{
    /** @var array<string, ReArbolTriggerEvaluatorInterface> */
    private array $evaluadores;

    public function __construct()
    {
        $this->evaluadores = [];
        foreach ([
            new CuentasAllowlistTodasEvaluator,
            new CuentasAllowlistAlgunaFueraEvaluator,
            new LineaSinCuentaEvaluator,
            new MontoMayorIgualEvaluator,
            new MontoMenorEvaluator,
            new CuentaEspecificaEvaluator,
            new SiempreEvaluator,
        ] as $ev) {
            $this->evaluadores[$ev->codigo()] = $ev;
        }
    }

    public function aplica(ReArbolTriggerEvalContext $ctx, Arbolaprobacion_ReTrigger $trigger): bool
    {
        $codigo = strtoupper(trim((string) ($trigger->evaluador ?? '')));
        if ($codigo === '' || ! isset($this->evaluadores[$codigo])) {
            return false;
        }

        return $this->evaluadores[$codigo]->aplica($ctx, $trigger);
    }

    /** @return list<string> */
    public function codigos(): array
    {
        return array_keys($this->evaluadores);
    }
}

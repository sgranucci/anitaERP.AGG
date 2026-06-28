<?php

namespace App\Support\Configuracion\OcArbolTriggerEvaluators;

use App\Models\Compras\Ordencompra;
use App\Models\Configuracion\Arbolaprobacion_OcTrigger;
use App\Support\Configuracion\OcArbolTriggerCatalog;

final class OcArbolTriggerEvaluatorRegistry
{
    /** @var array<string, OcArbolTriggerEvaluatorInterface> */
    private array $evaluadores;

    public function __construct()
    {
        $this->evaluadores = [];
        foreach ([new CapexMesExcedidoEvaluator] as $ev) {
            $this->evaluadores[$ev->codigo()] = $ev;
        }
    }

    public function aplicaCondicion(Ordencompra $ordencompra, Arbolaprobacion_OcTrigger $trigger): bool
    {
        $codigo = trim((string) ($trigger->evaluador ?? ''));
        if ($codigo === '' || ! isset($this->evaluadores[$codigo])) {
            return false;
        }

        return $this->evaluadores[$codigo]->aplica($ordencompra, $trigger);
    }

    public function aplicaEventoCambioSector(
        Arbolaprobacion_OcTrigger $trigger,
        ?int $sectorAnteriorId,
        int $sectorNuevoId
    ): bool {
        if ($trigger->evento !== OcArbolTriggerCatalog::EVENTO_CAMBIO_SECTOR) {
            return false;
        }

        $destinoCfg = (int) ($trigger->sector_destino_id ?? 0);
        if ($destinoCfg > 0 && $destinoCfg !== $sectorNuevoId) {
            return false;
        }

        $origenCfg = (int) ($trigger->sector_origen_id ?? 0);
        if ($origenCfg > 0) {
            return $origenCfg === (int) ($sectorAnteriorId ?? 0);
        }

        return true;
    }
}

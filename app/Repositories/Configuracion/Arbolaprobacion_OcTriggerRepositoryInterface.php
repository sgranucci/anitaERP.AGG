<?php

namespace App\Repositories\Configuracion;

interface Arbolaprobacion_OcTriggerRepositoryInterface
{
    public function syncFromRequest(array $data, int $arbolaprobacionId): void;
}

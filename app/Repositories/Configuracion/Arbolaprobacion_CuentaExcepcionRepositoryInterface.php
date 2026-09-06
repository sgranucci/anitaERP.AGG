<?php

namespace App\Repositories\Configuracion;

interface Arbolaprobacion_CuentaExcepcionRepositoryInterface
{
    public function syncFromRequest(array $data, int $arbolaprobacionId): void;

    public function deleteByArbol(int $arbolaprobacionId): void;
}

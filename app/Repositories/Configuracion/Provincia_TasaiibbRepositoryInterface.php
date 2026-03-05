<?php

namespace App\Repositories\Configuracion;

interface Provincia_TasaiibbRepositoryInterface extends RepositoryInterface
{

    public function all();
    public function leePorProvincia($provincia_id);
    public function leePorProvinciaCondicioniibb($provincia_id, $condicioniibb_id);
    public function deletePorProvincia($provincia_id);
}


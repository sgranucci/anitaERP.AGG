<?php

namespace App\Repositories\Uif;

interface Cliente_UifRepositoryInterface extends RepositoryInterface
{

    public function leeCliente_Uif($busqueda, $flPaginando = null);

    /**
     * Indica si hay al menos un cliente UIF en base (consulta directa, sin filtros de listado/búsqueda).
     */
    public function hayRegistrosClienteUifLocales(): bool;

}


<?php

namespace App\Repositories\Uif;

interface Cliente_UifRepositoryInterface extends RepositoryInterface
{

    /**
     * @param  array<string, mixed>|string|null  $filtros
     */
    public function leeCliente_Uif($filtros, $flPaginando = null);

    /**
     * Indica si hay al menos un cliente UIF en base (consulta directa, sin filtros de listado/búsqueda).
     */
    public function hayRegistrosClienteUifLocales(): bool;

}


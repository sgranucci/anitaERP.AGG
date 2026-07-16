<?php

namespace App\Repositories\Solicitudpago;

interface Sector_SolicitudpagoRepositoryInterface extends RepositoryInterface
{
    public function all();

    public function sincronizarConAnita();

    public function findPorCodigo(int $codigo);
}

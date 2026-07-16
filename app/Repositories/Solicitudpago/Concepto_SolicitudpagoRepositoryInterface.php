<?php

namespace App\Repositories\Solicitudpago;

interface Concepto_SolicitudpagoRepositoryInterface extends RepositoryInterface
{
    public function all();

    public function sincronizarConAnita(): array;

    public function findPorCodigo(int $codigo);

    public function guardarCompleto(array $data, ?int $id = null);
}

<?php

namespace App\Repositories\Ventas;

interface ClienteRepositoryInterface extends RepositoryInterface
{

    public function sincronizarConAnita();
    public function updateEmiteNc($id);
    public function findPorCodigo($codigo);
    public function actualizaPadronMipyme($modo);
    public function actualizaPadronMipymePorCuit($cuit, $modo);

}


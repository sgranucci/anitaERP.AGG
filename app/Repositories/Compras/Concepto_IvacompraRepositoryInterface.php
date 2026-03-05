<?php

namespace App\Repositories\Compras;

interface Concepto_IvacompraRepositoryInterface extends RepositoryInterface
{

    public function all();
    public function findPorCodigo($codigo);
    public function sincronizarConAnita();
    public function traerRegistroDeAnita($key);
	public function guardarAnita($request);
	public function actualizarAnita($request, $id);
	public function eliminarAnita($id);

}


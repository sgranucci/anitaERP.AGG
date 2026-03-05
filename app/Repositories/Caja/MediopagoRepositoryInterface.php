<?php

namespace App\Repositories\Caja;

interface MediopagoRepositoryInterface extends RepositoryInterface
{

    public function all();
    public function findPorCodigo($codigo);
    public function sincronizarConAnita();
    public function traerRegistroDeAnita($key);
	public function guardarAnita($request);
	public function actualizarAnita($request, $id);
	public function eliminarAnita($id);
    

}


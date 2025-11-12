<?php

namespace App\Repositories\Stock;

interface CodigosenasaRepositoryInterface extends RepositoryInterface
{

    public function all();
    public function sincronizarConAnita();
    public function traerRegistroDeAnita($key);
	public function guardarAnita($request);
	public function actualizarAnita($request, $id);
	public function eliminarAnita($id);

}


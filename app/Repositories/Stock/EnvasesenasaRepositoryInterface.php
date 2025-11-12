<?php

namespace App\Repositories\Stock;

interface EnvasesenasaRepositoryInterface extends RepositoryInterface
{

    public function all();
    public function sincronizarConAnita();
    public function traerRegistroDeAnita($key);
	public function guardarAnita($request, $id);
	public function actualizarAnita($request, $id);
	public function eliminarAnita($id);

}


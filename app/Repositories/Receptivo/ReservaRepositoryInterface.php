<?php

namespace App\Repositories\Receptivo;

interface ReservaRepositoryInterface extends RepositoryInterface
{

    public function all();
    public function findPorId($id);
    public function findPorCodigo($codigo);
    public function sincronizarConAnita();
    public function traerRegistroDeAnita($key);
	public function guardarAnita($request, $id);
	public function actualizarAnita($request, $id);
	public function eliminarAnita($id);
    
}


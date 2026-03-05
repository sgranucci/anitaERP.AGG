<?php

namespace App\Repositories\Caja;

interface ChequeRepositoryInterface extends RepositoryInterface
{

    public function all();
    public function sincronizarConAnita();
    public function traerRegistroDeAnita($key1, $key2, $key3);
	public function guardarAnita($request);
	public function actualizarAnita($request, $id);
	public function eliminarAnita($origen, $cuenta, $numeroCheque);
    public function findPorNumeroCheque($codigo);

}


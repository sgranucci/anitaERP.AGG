<?php

namespace App\Repositories\Configuracion;

interface ImpuestoRepositoryInterface extends RepositoryInterface
{

    public function all();
    public function sincronizarConAnita();
    public function traerRegistroDeAnita($key);
	public function guardarAnita($request);
	public function actualizarAnita($request, $id);
	public function eliminarAnita($id);
    public function findPorId($id);
    public function findPorCodigo($codigo);
    public function findPorValor($valor);

}


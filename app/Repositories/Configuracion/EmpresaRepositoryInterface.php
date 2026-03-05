<?php

namespace App\Repositories\Configuracion;

interface EmpresaRepositoryInterface extends RepositoryInterface
{

    public function all();
    public function sincronizarConAnita();
    public function traerRegistroDeAnita($key);
	public function guardarAnita($request);
	public function actualizarAnita($request);
	public function eliminarAnita($id);
    public function findPorId($id);
    public function findPorCodigo($codigo);
    public function traeEmpresasAsignadas();

}


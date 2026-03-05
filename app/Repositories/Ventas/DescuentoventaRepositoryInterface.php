<?php

namespace App\Repositories\Ventas;

interface DescuentoventaRepositoryInterface extends RepositoryInterface
{

    public function all();
	public function guardarAnita($request, $id);
	public function actualizarAnita($request, $id);
	public function eliminarAnita($id);

}


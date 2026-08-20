<?php

namespace App\Repositories\Stock;

use App\Models\Stock\Codigosenasa;

interface CodigosenasaRepositoryInterface extends RepositoryInterface
{

    public function all();
    public function sincronizarConAnita();
    public function findPorCodigo(string $codigo): ?Codigosenasa;
    public function consultaCodigosenasa(string $consulta): string;
    public function traerRegistroDeAnita($key);
	public function guardarAnita($request);
	public function actualizarAnita($request, $id);
	public function eliminarAnita($id);

}


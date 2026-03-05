<?php

namespace App\Repositories\Ventas;

interface OrdentrabajoRepositoryInterface extends RepositoryInterface
{

    public function all();
    public function create(array $data);
    public function update(array $data, $id);
    public function delete($id);
    public function deletePorCodigo($codigo);
    public function find($id);
    public function findPorCodigo($codigo);
    public function findOrFail($id);
    public function sincronizarConAnita();
	public function actualizarNumeradorOtAnita($nro);

}


<?php

namespace App\Repositories\Ventas;

interface Ordentrabajo_TareaRepositoryInterface 
{

    public function all();
	public function create($data);
    public function delete($id, $nro_orden);
    public function find($id);
    public function update(array $data, $id);
    public function findOrFail($id);
    public function findPorOrdentrabajoId($id, $tarea_id = null);
    public function deleteporordentrabajo($ordentrabajo_id, $nro_orden);
    public function sincronizarConAnita();
    public function findPorRangoFecha($desdefecha, $hastafecha, $ordenestrabajo = null);

}


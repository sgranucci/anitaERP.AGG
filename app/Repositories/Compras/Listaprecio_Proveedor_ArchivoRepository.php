<?php

namespace App\Repositories\Compras;

use App\Models\Compras\Listaprecio_Proveedor_Archivo;

class Listaprecio_Proveedor_ArchivoRepository implements Listaprecio_Proveedor_ArchivoRepositoryInterface
{
    protected $model;

    public function __construct(Listaprecio_Proveedor_Archivo $model)
    {
        $this->model = $model;
    }

    public function create($request, $listaprecio_proveedor_id)
    {
        return $this->guardaArchivo($request, 'create', $listaprecio_proveedor_id);
    }

    public function update($request, $listaprecio_proveedor_id)
    {
        return $this->guardaArchivo($request, 'update', $listaprecio_proveedor_id);
    }

    private function guardaArchivo($request, $funcion, $id)
    {
        if ($funcion === 'update') {
            $this->model->where('listaprecio_proveedor_id', $id)->delete();
        }

        $nombrearchivos = $request->file('nombrearchivos');

        if ($nombrearchivos ?? '') {
            foreach ($nombrearchivos as $archivo) {
                if ($archivo) {
                    $path = public_path().'/storage/archivos/listaprecio_proveedor/'.$id;
                    if (! is_dir($path)) {
                        mkdir($path, 0777, true);
                    }
                    $file = $archivo->getClientOriginalName();
                    $nombreStored = $id.'-'.$file;
                    $archivo->move($path, $nombreStored);

                    $this->model->create([
                        'listaprecio_proveedor_id' => $id,
                        'nombrearchivo' => $nombreStored,
                    ]);
                }
            }
        }

        if ($request->nombresanteriores ?? '') {
            for ($i = 0; $i < count($request->nombresanteriores); $i++) {
                $fl_encontro = false;
                if ($nombrearchivos) {
                    foreach ($nombrearchivos as $archivo) {
                        if ($archivo && $archivo->getClientOriginalName() == $request->nombresanteriores[$i]) {
                            $fl_encontro = true;
                        }
                    }
                }
                if (! $fl_encontro && $request->nombresanteriores[$i] != '') {
                    $this->model->create([
                        'listaprecio_proveedor_id' => $id,
                        'nombrearchivo' => $request->nombresanteriores[$i],
                    ]);
                }
            }
        }

        return '1';
    }
}

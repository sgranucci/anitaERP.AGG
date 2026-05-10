<?php

namespace App\Repositories\Compras;

use App\Models\Compras\Requisicion_Archivo;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class Requisicion_ArchivoRepository implements Requisicion_ArchivoRepositoryInterface
{
    protected $model;

    public function __construct(Requisicion_Archivo $requisicion_archivo)
    {
        $this->model = $requisicion_archivo;
    }

    public function create($request, $id)
    {
        return self::guardaArchivo($request, 'create', $id);
    }

    public function createDesdeAnita($data)
    {
        return $this->model->create($data);
    }

    public function update($request, $id)
    {
        return self::guardaArchivo($request, 'update', $id);
    }

    private function guardaArchivo($request, $funcion, $id = null)
    {
        $nombresAntes = [];
        if ($funcion == 'update') {
            $nombresAntes = $this->model->where('requisicion_id', $id)->pluck('nombrearchivo')->all();
            $this->deletePorRequisicion($id);
        }

        $nombrearchivos = $request->file('nombrearchivos');

        if ($nombrearchivos ?? '') {
            foreach ($nombrearchivos as $archivo) {
                if ($archivo) {
                    $path = public_path().'/storage/archivos/requisiciones/'.$id;
                    if (!is_dir($path)) {
                        mkdir($path, 0777, true);
                    }
                    $file = $archivo->getClientOriginalName();
                    $archivo->move($path, $file);

                    $this->model->create([
                        'requisicion_id' => $id,
                        'nombrearchivo' => $file,
                    ]);
                }
            }
        }

        if ($request->nombresanteriores ?? '') {
            for ($i_archivo = 0; $i_archivo < count($request->nombresanteriores); $i_archivo++) {
                $fl_encontro = false;
                if ($nombrearchivos) {
                    foreach ($nombrearchivos as $archivo) {
                        if ($archivo && $archivo->getClientOriginalName() == $request->nombresanteriores[$i_archivo]) {
                            $fl_encontro = true;
                        }
                    }
                }
                if (!$fl_encontro && $request->nombresanteriores[$i_archivo] != '') {
                    $this->model->create([
                        'requisicion_id' => $id,
                        'nombrearchivo' => $request->nombresanteriores[$i_archivo],
                    ]);
                }
            }
        }

        if ($funcion == 'update' && $nombresAntes !== []) {
            $nombresDespues = $this->model->where('requisicion_id', $id)->pluck('nombrearchivo')->all();
            $pathBase = public_path().'/storage/archivos/requisiciones/'.$id;
            foreach (array_diff($nombresAntes, $nombresDespues) as $nombre) {
                if ($nombre === '' || $nombre === null) {
                    continue;
                }
                $full = $pathBase.'/'.basename((string) $nombre);
                if (is_file($full)) {
                    @unlink($full);
                }
            }
        }

        return '1';
    }

    private function deletePorRequisicion($requisicion_id)
    {
        return $this->model->where('requisicion_id', $requisicion_id)->delete();
    }
}

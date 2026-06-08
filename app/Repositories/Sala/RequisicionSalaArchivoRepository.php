<?php

namespace App\Repositories\Sala;

use App\Models\Sala\RequisicionSalaArchivo;

class RequisicionSalaArchivoRepository implements RequisicionSalaArchivoRepositoryInterface
{
    public function __construct(protected RequisicionSalaArchivo $model)
    {
    }

    public function all()
    {
        return $this->model->all();
    }

    public function create($request, $id)
    {
        return self::guardaArchivo($request, 'create', $id);
    }

    public function update($request, $id)
    {
        return self::guardaArchivo($request, 'update', $id);
    }

    public function delete($id)
    {
        return $this->model->destroy($id);
    }

    public function find($id)
    {
        return $this->model->findOrFail($id);
    }

    public function findOrFail($id)
    {
        return $this->model->findOrFail($id);
    }

    private function guardaArchivo($request, string $funcion, $id = null)
    {
        $nombresAntes = [];
        if ($funcion === 'update') {
            $nombresAntes = $this->model->where('requisicion_sala_id', $id)->pluck('nombrearchivo')->all();
            $this->model->where('requisicion_sala_id', $id)->delete();
        }

        $nombrearchivos = $request->file('nombrearchivos');
        if ($nombrearchivos ?? '') {
            foreach ($nombrearchivos as $archivo) {
                if ($archivo) {
                    $path = public_path().'/storage/archivos/requisiciones_sala/'.$id;
                    if (! is_dir($path)) {
                        mkdir($path, 0777, true);
                    }
                    $file = $archivo->getClientOriginalName();
                    $archivo->move($path, $file);
                    $this->model->create([
                        'requisicion_sala_id' => $id,
                        'nombrearchivo' => $file,
                    ]);
                }
            }
        }

        if ($request->nombresanteriores ?? '') {
            for ($i = 0; $i < count($request->nombresanteriores); $i++) {
                $flEncontro = false;
                if ($nombrearchivos) {
                    foreach ($nombrearchivos as $archivo) {
                        if ($archivo && $archivo->getClientOriginalName() == $request->nombresanteriores[$i]) {
                            $flEncontro = true;
                        }
                    }
                }
                if (! $flEncontro && $request->nombresanteriores[$i] != '') {
                    $this->model->create([
                        'requisicion_sala_id' => $id,
                        'nombrearchivo' => $request->nombresanteriores[$i],
                    ]);
                }
            }
        }

        if ($funcion === 'update' && $nombresAntes !== []) {
            $nombresDespues = $this->model->where('requisicion_sala_id', $id)->pluck('nombrearchivo')->all();
            $pathBase = public_path().'/storage/archivos/requisiciones_sala/'.$id;
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
}

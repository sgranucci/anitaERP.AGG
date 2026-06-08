<?php

namespace App\Repositories\Stock;

use App\Models\Stock\Recuento_Archivo;

class Recuento_ArchivoRepository implements Recuento_ArchivoRepositoryInterface
{
    public function __construct(
        private readonly Recuento_Archivo $model,
    ) {}

    public function create($request, int $id)
    {
        return $this->guardaArchivo($request, 'create', $id);
    }

    public function update($request, int $id)
    {
        return $this->guardaArchivo($request, 'update', $id);
    }

    private function guardaArchivo($request, string $funcion, int $id)
    {
        $nombresAntes = [];
        if ($funcion === 'update') {
            $nombresAntes = $this->model->where('recuento_id', $id)->pluck('nombrearchivo')->all();
            $this->deletePorRecuento($id);
        }

        $nombrearchivos = $request->file('nombrearchivos');

        if ($nombrearchivos ?? '') {
            foreach ($nombrearchivos as $archivo) {
                if ($archivo) {
                    $path = public_path().'/storage/archivos/recuentos/'.$id;
                    if (! is_dir($path)) {
                        mkdir($path, 0777, true);
                    }
                    $file = $archivo->getClientOriginalName();
                    $archivo->move($path, $file);

                    $this->model->create([
                        'recuento_id' => $id,
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
                        'recuento_id' => $id,
                        'nombrearchivo' => $request->nombresanteriores[$i],
                    ]);
                }
            }
        }

        if ($funcion === 'update' && $nombresAntes !== []) {
            $nombresDespues = $this->model->where('recuento_id', $id)->pluck('nombrearchivo')->all();
            $pathBase = public_path().'/storage/archivos/recuentos/'.$id;
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

    private function deletePorRecuento(int $recuentoId)
    {
        return $this->model->where('recuento_id', $recuentoId)->delete();
    }
}

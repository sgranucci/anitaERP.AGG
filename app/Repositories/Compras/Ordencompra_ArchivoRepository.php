<?php

namespace App\Repositories\Compras;

use App\Models\Compras\Ordencompra_Archivo;
use Illuminate\Http\Request;

class Ordencompra_ArchivoRepository implements Ordencompra_ArchivoRepositoryInterface
{
    public function __construct(private Ordencompra_Archivo $model)
    {
    }

    public function create(Request $request, int $id)
    {
        return $this->guardaArchivo($request, 'create', $id);
    }

    public function update(Request $request, int $id)
    {
        return $this->guardaArchivo($request, 'update', $id);
    }

    private function guardaArchivo(Request $request, string $funcion, int $id): string
    {
        $nombresAntes = [];
        if ($funcion === 'update') {
            $nombresAntes = $this->model->where('ordencompra_id', $id)->pluck('nombrearchivo')->all();
            $this->deletePorOrdencompra($id);
        }

        $nombrearchivos = $request->file('nombrearchivos');

        if ($nombrearchivos ?? '') {
            foreach ($nombrearchivos as $archivo) {
                if ($archivo) {
                    $path = public_path().'/storage/archivos/ordencompras/'.$id;
                    if (! is_dir($path)) {
                        mkdir($path, 0777, true);
                    }
                    $file = $archivo->getClientOriginalName();
                    $archivo->move($path, $file);

                    $this->model->create([
                        'ordencompra_id' => $id,
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
                if (! $fl_encontro && $request->nombresanteriores[$i_archivo] != '') {
                    $this->model->create([
                        'ordencompra_id' => $id,
                        'nombrearchivo' => $request->nombresanteriores[$i_archivo],
                    ]);
                }
            }
        }

        if ($funcion === 'update' && $nombresAntes !== []) {
            $nombresDespues = $this->model->where('ordencompra_id', $id)->pluck('nombrearchivo')->all();
            $pathBase = public_path().'/storage/archivos/ordencompras/'.$id;
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

    private function deletePorOrdencompra(int $ordencompra_id): void
    {
        $this->model->where('ordencompra_id', $ordencompra_id)->delete();
    }
}

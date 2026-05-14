<?php

namespace App\Repositories\Stock;

use App\Models\Stock\Formula_Articulo_Archivo;

class Formula_Articulo_ArchivoRepository implements Formula_Articulo_ArchivoRepositoryInterface
{
    protected $model;

    public function __construct(Formula_Articulo_Archivo $model)
    {
        $this->model = $model;
    }

    public function create($request, $id)
    {
        return self::guardaArchivo($request, 'create', $id);
    }

    public function update($request, $id)
    {
        return self::guardaArchivo($request, 'update', $id);
    }

    private function guardaArchivo($request, $funcion, $id = null)
    {
        $nombresAntes = [];
        if ($funcion == 'update') {
            $nombresAntes = $this->model->where('formula_articulo_id', $id)->pluck('nombrearchivo')->all();
            $this->deletePorFormula($id);
        }

        $nombrearchivos = $request->file('nombrearchivos');

        if ($nombrearchivos ?? '') {
            foreach ($nombrearchivos as $archivo) {
                if ($archivo) {
                    $path = public_path().'/storage/archivos/formulas_articulo/'.$id;
                    if (! is_dir($path)) {
                        mkdir($path, 0777, true);
                    }
                    $file = $archivo->getClientOriginalName();
                    $archivo->move($path, $file);

                    $this->model->create([
                        'formula_articulo_id' => $id,
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
                        'formula_articulo_id' => $id,
                        'nombrearchivo' => $request->nombresanteriores[$i_archivo],
                    ]);
                }
            }
        }

        if ($funcion == 'update' && $nombresAntes !== []) {
            $nombresDespues = $this->model->where('formula_articulo_id', $id)->pluck('nombrearchivo')->all();
            $pathBase = public_path().'/storage/archivos/formulas_articulo/'.$id;
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

    private function deletePorFormula($formula_articulo_id)
    {
        return $this->model->where('formula_articulo_id', $formula_articulo_id)->delete();
    }
}

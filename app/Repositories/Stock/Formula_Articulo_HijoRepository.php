<?php

namespace App\Repositories\Stock;

use App\Models\Stock\Formula_Articulo_Hijo;
use App\Support\Stock\FormulaArticuloGastronomia;
use Illuminate\Support\Facades\Schema;

class Formula_Articulo_HijoRepository implements Formula_Articulo_HijoRepositoryInterface
{
    protected $model;

    public function __construct(Formula_Articulo_Hijo $model)
    {
        $this->model = $model;
    }

    public function syncFromRequest(array $data, int $formula_articulo_id)
    {
        $gastronomiaOpcional = FormulaArticuloGastronomia::opcionalesHabilitados();
        $tieneRanura = Schema::hasColumn($this->model->getTable(), 'ranura');
        $tieneOrdenOpcional = Schema::hasColumn($this->model->getTable(), 'ordenopcional');

        $articulo_ids = $data['articulo_ids'] ?? [];
        if (! is_array($articulo_ids)) {
            $this->model->where('formula_articulo_id', $formula_articulo_id)->delete();

            return;
        }

        $idsExistentes = $this->model->where('formula_articulo_id', $formula_articulo_id)->pluck('id')->all();
        $idsExistentesFlip = array_flip($idsExistentes);

        $idsEntrantes = $data['formula_articulo_hijo_ids'] ?? [];

        $idsAConservar = [];
        $aActualizar = [];
        $aInsertar = [];

        $n = count($articulo_ids);
        for ($i = 0; $i < $n; $i++) {
            $articulo_id = $articulo_ids[$i] ?? null;
            $formula_hija_id = $data['formula_hija_ids'][$i] ?? null;
            $articulo_id = ($articulo_id === '' || $articulo_id === null) ? null : (int) $articulo_id;
            $formula_hija_id = ($formula_hija_id === '' || $formula_hija_id === null) ? null : (int) $formula_hija_id;

            if ($articulo_id === null && $formula_hija_id === null) {
                continue;
            }

            $cantidad = (float) ($data['cantidades'][$i] ?? 0);
            $factorcosto = (float) ($data['factorcostos'][$i] ?? 0);
            $esopcional = $gastronomiaOpcional
                && isset($data['esopcional'][$i])
                && (string) ($data['esopcional'][$i] ?? '') === '1';
            $deposito_id = $data['deposito_ids'][$i] ?? null;
            $deposito_id = ($deposito_id === '' || $deposito_id === null) ? null : (int) $deposito_id;

            $payload = [
                'formula_articulo_id' => $formula_articulo_id,
                'articulo_id' => $articulo_id,
                'cantidad' => $cantidad,
                'factorcosto' => $factorcosto,
                'formula_hija_id' => $formula_hija_id,
                'esopcional' => $esopcional,
                'deposito_id' => $deposito_id,
            ];

            if ($tieneOrdenOpcional) {
                if ($gastronomiaOpcional && $esopcional) {
                    $oo = $data['ordenopcionales'][$i] ?? null;
                    $payload['ordenopcional'] = ($oo === '' || $oo === null) ? null : (int) $oo;
                } else {
                    $payload['ordenopcional'] = null;
                }
            }

            if ($tieneRanura) {
                $ran = $data['ranuras'][$i] ?? null;
                $payload['ranura'] = ($ran === '' || $ran === null) ? null : (int) $ran;
            }

            $idCandidato = $idsEntrantes[$i] ?? null;
            $idCandidato = ($idCandidato === null || $idCandidato === '') ? null : (int) $idCandidato;

            if ($idCandidato !== null && isset($idsExistentesFlip[$idCandidato])) {
                $aActualizar[$idCandidato] = $payload;
                $idsAConservar[] = $idCandidato;
            } else {
                $aInsertar[] = $payload;
            }
        }

        $queryEliminar = $this->model->where('formula_articulo_id', $formula_articulo_id);
        if (! empty($idsAConservar)) {
            $queryEliminar->whereNotIn('id', $idsAConservar);
        }
        $queryEliminar->delete();

        foreach ($aActualizar as $id => $payload) {
            $registro = $this->model->where('id', $id)->where('formula_articulo_id', $formula_articulo_id)->first();
            if ($registro) {
                $registro->update($payload);
            }
        }

        foreach ($aInsertar as $payload) {
            $this->model->create($payload);
        }
    }
}

<?php

namespace App\Repositories\Configuracion;

use App\Models\Configuracion\Arbolaprobacion_Nivel;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class Arbolaprobacion_NivelRepository implements Arbolaprobacion_NivelRepositoryInterface
{
    protected $model;

    public function __construct(Arbolaprobacion_Nivel $arbolaprobacion_nivel)
    {
        $this->model = $arbolaprobacion_nivel;
    }

    public function create(array $data, $id)
    {
        return self::guardarArbolaprobacion_Nivel($data, $id);
    }

    public function createUnique(array $data)
    {
        $this->model->create($data);
    }

    public function update(array $data, $id)
    {
        return self::guardarArbolaprobacion_Nivel($data, $id);
    }

    public function delete($arbolaprobacion_id)
    {
        return $this->model->where('arbolaprobacion_id', $arbolaprobacion_id)->delete();
    }

    public function find($id)
    {
        if (null == $arbolaprobacion_nivel = $this->model->with('empresas')->with('centrocostos')->with('monedas')->find($id)) {
            throw new ModelNotFoundException('Registro no encontrado');
        }

        return $arbolaprobacion_nivel;
    }

    public function findOrFail($id)
    {
        if (null == $arbolaprobacion_nivel = $this->model->with('empresas')->with('centrocostos')->with('monedas')->findOrFail($id)) {
            throw new ModelNotFoundException('Registro no encontrado');
        }

        return $arbolaprobacion_nivel;
    }

    private function guardarArbolaprobacion_Nivel(array $data, $arbolaprobacion_id)
    {
        if (! isset($data['niveles']) || ! is_array($data['niveles'])) {
            return null;
        }

        $niveles = $data['niveles'];
        $count = count($niveles);
        $ids = $data['ids'] ?? [];
        $centrocosto_ids = $data['centrocosto_ids'] ?? [];
        $usuario_ids = $data['usuario_ids'] ?? [];
        $desdemontos = $data['desdemontos'] ?? [];
        $hastamontos = $data['hastamontos'] ?? [];
        $moneda_ids = $data['moneda_ids'] ?? [];
        $estados_req = $data['documento_estado_al_aprobar'] ?? [];

        $guardados = [];

        for ($i = 0; $i < $count; $i++) {
            $rowId = isset($ids[$i]) && $ids[$i] !== '' ? (int) $ids[$i] : null;
            $usuarioId = isset($usuario_ids[$i]) && $usuario_ids[$i] !== '' ? $usuario_ids[$i] : null;
            $estReq = isset($estados_req[$i]) && $estados_req[$i] !== '' ? $estados_req[$i] : null;

            $payload = [
                'arbolaprobacion_id' => $arbolaprobacion_id,
                'nivel' => $niveles[$i],
                'centrocosto_id' => $centrocosto_ids[$i],
                'usuario_id' => $usuarioId,
                'desdemonto' => $desdemontos[$i] ?? null,
                'hastamonto' => $hastamontos[$i] ?? null,
                'moneda_id' => $moneda_ids[$i],
                'documento_estado_al_aprobar' => $estReq,
            ];

            if ($rowId) {
                $existente = $this->model->where('id', $rowId)->where('arbolaprobacion_id', $arbolaprobacion_id)->first();
                if ($existente) {
                    $existente->update($payload);
                    $guardados[] = $rowId;

                    continue;
                }
            }

            $creado = $this->model->create($payload);
            $guardados[] = $creado->id;
        }

        $this->model->where('arbolaprobacion_id', $arbolaprobacion_id)
            ->whereNotIn('id', $guardados)
            ->delete();

        return true;
    }
}

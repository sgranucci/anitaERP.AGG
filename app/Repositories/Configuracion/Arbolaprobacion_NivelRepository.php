<?php

namespace App\Repositories\Configuracion;

use App\Models\Compras\Requisicion_Estado;
use App\Models\Configuracion\Arbolaprobacion;
use App\Models\Configuracion\Arbolaprobacion_Nivel;
use App\Support\Database\EloquentAuditDeleteSupport;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

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
        EloquentAuditDeleteSupport::each(
            $this->model->newQuery()->where('arbolaprobacion_id', $arbolaprobacion_id)
        );

        return true;
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
        $dobles = $data['doble_aprobacions'] ?? [];
        $ramas = $data['ramas'] ?? [];
        $tipoarbol = $data['tipoarbol'] ?? DB::table('arbolaprobacion')->where('id', $arbolaprobacion_id)->value('tipoarbol');
        $nombreTipoRequisiciones = Arbolaprobacion::$enumTipoArbol[array_search('RE', array_column(Arbolaprobacion::$enumTipoArbol, 'valor'))]['nombre'];
        $estadoAprobadaRequisicion = Requisicion_Estado::$enumEstado[array_search('A', array_column(Requisicion_Estado::$enumEstado, 'valor'))]['nombre'];

        $guardados = [];

        for ($i = 0; $i < $count; $i++) {
            $rowId = isset($ids[$i]) && $ids[$i] !== '' ? (int) $ids[$i] : null;
            $usuarioId = isset($usuario_ids[$i]) && $usuario_ids[$i] !== '' ? $usuario_ids[$i] : null;
            $estReq = isset($estados_req[$i]) && $estados_req[$i] !== '' ? $estados_req[$i] : null;
            if ($estReq === null && $tipoarbol === $nombreTipoRequisiciones) {
                $estReq = $estadoAprobadaRequisicion;
            }
            $doble = strtoupper(trim((string) ($dobles[$i] ?? 'N')));
            $doble = $doble === 'S' ? 'S' : 'N';
            $ramaRaw = strtoupper(trim((string) ($ramas[$i] ?? '')));
            $rama = in_array($ramaRaw, ['A', 'B'], true) ? $ramaRaw : null;

            $payload = [
                'arbolaprobacion_id' => $arbolaprobacion_id,
                'nivel' => $niveles[$i],
                'centrocosto_id' => $centrocosto_ids[$i],
                'usuario_id' => $usuarioId,
                'desdemonto' => $desdemontos[$i] ?? null,
                'hastamonto' => $hastamontos[$i] ?? null,
                'moneda_id' => $moneda_ids[$i],
                'documento_estado_al_aprobar' => $estReq,
                'doble_aprobacion' => $doble,
                'rama' => $rama,
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

        $this->borrarNivelesNoConservados((int) $arbolaprobacion_id, $guardados);

        return true;
    }

    /**
     * @param  list<int>  $idsConservar
     */
    private function borrarNivelesNoConservados(int $arbolaprobacionId, array $idsConservar): void
    {
        if ($idsConservar === []) {
            return;
        }

        EloquentAuditDeleteSupport::each(
            $this->model->newQuery()
                ->where('arbolaprobacion_id', $arbolaprobacionId)
                ->whereNotIn('id', $idsConservar)
        );
    }
}

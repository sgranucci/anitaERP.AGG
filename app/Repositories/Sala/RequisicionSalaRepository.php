<?php

namespace App\Repositories\Sala;

use App\Models\Sala\RequisicionSala;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class RequisicionSalaRepository implements RequisicionSalaRepositoryInterface
{
    public function __construct(protected RequisicionSala $model)
    {
    }

    public function all()
    {
        return $this->model->orderBy('id', 'desc')->get();
    }

    public function create(array $data)
    {
        $data = self::limpiaPayloadCabecera($data);
        $data['numerorequisicion'] = self::ultimaRequisicion($data['empresa_id']);

        return $this->model->create($data);
    }

    public function update(array $data, $id)
    {
        $data = self::limpiaPayloadCabecera($data);

        return $this->model->findOrFail($id)->update($data);
    }

    public function delete($id)
    {
        return $this->model->destroy($id);
    }

    public function find($id)
    {
        if (null == $req = $this->model->with([
            'requisicion_sala_estados.usuarios',
            'requisicion_sala_archivos',
            'empresas', 'centrocostos', 'depositos', 'zona_salas', 'prioridad_salas',
            'usuarios', 'solicitante',
            'requisicion_sala_articulos.articulos',
        ])->find($id)) {
            throw new ModelNotFoundException('Registro no encontrado');
        }

        return $req;
    }

    public function findOrFail($id)
    {
        return $this->find($id);
    }

    private static function limpiaPayloadCabecera(array $data): array
    {
        unset(
            $data['articulo_ids'], $data['cantidades'], $data['detalle_articulos'],
            $data['fueradeservicios'], $data['uids'], $data['destinos'], $data['estados_linea'],
            $data['numeropartes'], $data['requisicion_sala_articulo_ids'],
            $data['fechas'], $data['estados'], $data['usuario_ids'], $data['observacionestados'],
            $data['_token'], $data['_method']
        );

        return $data;
    }

    private function ultimaRequisicion(int $empresa_id): int
    {
        $ultimo = $this->model->select('numerorequisicion')
            ->where('empresa_id', $empresa_id)
            ->orderBy('numerorequisicion', 'desc')
            ->first();

        return $ultimo ? ((int) $ultimo->numerorequisicion + 1) : 1;
    }
}

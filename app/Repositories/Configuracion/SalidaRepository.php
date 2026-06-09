<?php

namespace App\Repositories\Configuracion;

use App\Models\Configuracion\Salida;
use App\Support\Configuracion\SalidaParaProgramaSupport;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SalidaRepository implements SalidaRepositoryInterface
{
    protected $model;

    protected $tableAnita = 'salida';

    protected $keyField = 'id';

    public function __construct(Salida $salida)
    {
        $this->model = $salida;
    }

    public function all()
    {
        return $this->model
            ->with(['ubicacionImpresora', 'usoSalidaImpresoras'])
            ->orderBy('nombre')
            ->get();
    }

    /**
     * Impresoras disponibles para configurar salida en un programa (seteo por pantalla).
     *
     * @return Collection<int, Salida>
     */
    public function paraProgramaSeteo(?string $programa, ?int $incluirSalidaId = null): Collection
    {
        $query = $this->model
            ->with(['ubicacionImpresora', 'usoSalidaImpresoras'])
            ->orderBy('nombre');

        SalidaParaProgramaSupport::aplicarFiltroQuery($query, $programa);

        $salidas = $query->get();

        if ($incluirSalidaId && ! $salidas->contains('id', $incluirSalidaId)) {
            $extra = $this->model
                ->with(['ubicacionImpresora', 'usoSalidaImpresoras'])
                ->find($incluirSalidaId);

            if ($extra) {
                $salidas->push($extra);
                $salidas = $salidas->sortBy('nombre', SORT_NATURAL | SORT_FLAG_CASE)->values();
            }
        }

        return $salidas;
    }

    public function create(array $data)
    {
        $usoIds = array_key_exists('uso_salida_impresora_ids', $data)
            ? array_filter(array_map('intval', (array) ($data['uso_salida_impresora_ids'] ?? [])))
            : [];
        unset($data['uso_salida_impresora_ids']);

        DB::beginTransaction();
        try {
            $salida = $this->model->create($data);
            $salida->usoSalidaImpresoras()->sync($usoIds);
            DB::commit();

            return $salida;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function update(array $data, $id)
    {
        $usoIds = null;
        if (array_key_exists('uso_salida_impresora_ids', $data)) {
            $usoIds = array_filter(array_map('intval', (array) ($data['uso_salida_impresora_ids'] ?? [])));
            unset($data['uso_salida_impresora_ids']);
        }

        DB::beginTransaction();
        try {
            $salida = $this->model->findOrFail($id);
            $salida->update($data);
            if (is_array($usoIds)) {
                $salida->usoSalidaImpresoras()->sync($usoIds);
            }
            DB::commit();

            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function delete($id)
    {
        return $this->model->destroy($id);
    }

    public function find($id)
    {
        if (null == $salida = $this->model->find($id)) {
            throw new ModelNotFoundException('Registro no encontrado');
        }

        return $salida;
    }

    public function findOrFail($id)
    {
        return $this->model
            ->with(['ubicacionImpresora', 'usoSalidaImpresoras'])
            ->findOrFail($id);
    }
}

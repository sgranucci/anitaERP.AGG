<?php

namespace App\Repositories\Configuracion;

use App\Models\Configuracion\Modeloetiqueta;
use App\Models\Configuracion\SeteoModeloetiqueta;
use App\Support\Configuracion\SeteoSalidaProgramaSupport;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class SeteoModeloetiquetaRepository implements SeteoModeloetiquetaRepositoryInterface
{
    protected $model;

    public function __construct(SeteoModeloetiqueta $seteoModeloetiqueta)
    {
        $this->model = $seteoModeloetiqueta;
    }

    public function all()
    {
        return $this->model->get();
    }

    public function create(array $data)
    {
        return $this->model->create($data);
    }

    public function update(array $data, $id)
    {
        $seteoModeloetiqueta = $this->model->findOrFail($id)
            ->update($data);

        return $seteoModeloetiqueta;
    }

    public function delete($id)
    {
        $seteoModeloetiqueta = Modeloetiqueta::find($id);

        $seteoModeloetiqueta = $this->model->destroy($id);

        return $seteoModeloetiqueta;
    }

    public function find($id)
    {
        if (null == $seteoModeloetiqueta = $this->model->find($id)) {
            throw new ModelNotFoundException('Registro no encontrado');
        }

        return $seteoModeloetiqueta;
    }

    public function findOrFail($id)
    {
        if (null == $seteoModeloetiqueta = $this->model->findOrFail($id)) {
            throw new ModelNotFoundException('Registro no encontrado');
        }

        return $seteoModeloetiqueta;
    }

    public function buscaSeteoModeloetiqueta($usuario_id, $opcion = null)
    {
        $programa = $this->armaNombrePrograma($opcion);

        $seteoModeloetiqueta = $this->model->where('usuario_id', $usuario_id)
            ->where('programa', $programa)
            ->with('modeloetiquetas')
            ->first();

        if ($seteoModeloetiqueta) {
            return $seteoModeloetiqueta;
        }

        foreach (SeteoSalidaProgramaSupport::clavesLegacy($opcion) as $legacy) {
            if ($legacy === $programa) {
                continue;
            }

            $seteoModeloetiqueta = $this->model->where('usuario_id', $usuario_id)
                ->where('programa', $legacy)
                ->with('modeloetiquetas')
                ->first();

            if ($seteoModeloetiqueta) {
                return $seteoModeloetiqueta;
            }
        }

        return null;
    }

    public function leeSeteo($usuario_id, $programa)
    {
        $programaCanonico = SeteoSalidaProgramaSupport::resolver($programa);

        $seteoModeloetiqueta = $this->model->where('usuario_id', $usuario_id)
            ->where('programa', $programaCanonico)
            ->with('modeloetiquetas')
            ->first();

        if ($seteoModeloetiqueta) {
            return $seteoModeloetiqueta;
        }

        if ($programa !== null && $programa !== '' && $programa !== $programaCanonico) {
            return $this->model->where('usuario_id', $usuario_id)
                ->where('programa', $programa)
                ->with('modeloetiquetas')
                ->first();
        }

        return null;
    }

    public function armaNombrePrograma($opcion = null)
    {
        return SeteoSalidaProgramaSupport::resolver($opcion);
    }
}

<?php

namespace App\Repositories\Configuracion;

use App\Support\Configuracion\SeteoSalidaProgramaSupport;
use Illuminate\Http\Request;
use App\Models\Configuracion\Seteosalida;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Str;
use Auth;

class SeteosalidaRepository implements SeteosalidaRepositoryInterface
{
    protected $model;
    protected $keyField = 'id';

    /**
     * PostRepository constructor.
     *
     * @param Post $post
     */
    public function __construct(Seteosalida $seteosalida)
    {
        $this->model = $seteosalida;
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
        $salida = $this->model->findOrFail($id)
            ->update($data);

		return $salida;
    }

    public function delete($id)
    {
    	$salida = $this->model->find($id);
		
        $salida = $this->model->destroy($id);

		return $salida;
    }

    public function find($id)
    {
        if (null == $salida = $this->model->find($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $salida;
    }

    public function findOrFail($id)
    {
        if (null == $seteosalida = $this->model->findOrFail($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $seteosalida;
    }

    public function buscaSeteo($usuario_id, $opcion = null)
    {
        $programa = $this->armaNombrePrograma($opcion);

        $seteosalida = $this->model->where('usuario_id', $usuario_id)
            ->where('programa', $programa)
            ->with('salidas.ubicacionImpresora')
            ->first();

        if ($seteosalida) {
            return $seteosalida;
        }

        foreach (SeteoSalidaProgramaSupport::clavesLegacy($opcion) as $legacy) {
            if ($legacy === $programa) {
                continue;
            }

            $seteosalida = $this->model->where('usuario_id', $usuario_id)
                ->where('programa', $legacy)
                ->with('salidas.ubicacionImpresora')
                ->first();

            if ($seteosalida) {
                return $seteosalida;
            }
        }

        return null;
    }

    public function leeSeteo($usuario_id, $programa)
    {
        $programaCanonico = SeteoSalidaProgramaSupport::resolver($programa);

        $seteosalida = $this->model->where('usuario_id', $usuario_id)
            ->where('programa', $programaCanonico)
            ->with('salidas.ubicacionImpresora')
            ->first();

        if ($seteosalida) {
            return $seteosalida;
        }

        if ($programa !== null && $programa !== '' && $programa !== $programaCanonico) {
            return $this->model->where('usuario_id', $usuario_id)
                ->where('programa', $programa)
                ->with('salidas.ubicacionImpresora')
                ->first();
        }

        return null;
    }

    public function armaNombrePrograma($opcion = null)
    {
        return SeteoSalidaProgramaSupport::resolver($opcion);
    }
}

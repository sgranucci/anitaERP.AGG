<?php

namespace App\Repositories\Configuracion;

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
    	$salida = salida::find($id);
		
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
                                    ->with('salidas')
                                    ->first();
        return $seteosalida;
    }

    public function leeSeteo($usuario_id, $programa)
    {
        $seteosalida = $this->model->where('usuario_id', $usuario_id)
                                    ->where('programa', $programa)
                                    ->with('salidas')
                                    ->first();
        return $seteosalida;
    }

    public function armaNombrePrograma($opcion = null)
    {
        if ($opcion == 'xx')
            $opcion = null;

        $programa = request()->server('HTTP_REFERER');

        // Puentea seteo desde botones de impresion de OT en pedidos y ordenes de trabajo
        if (strstr($programa, 'pedido'))
            $programa = "http://160.132.0.209/anitaERP/public/ventas/repemisionot";

        if (strstr($programa, 'ordenestrabajo'))
            $programa = "http://160.132.0.209/anitaERP/public/ventas/repemisionot";

        // Agrega programa enviado a la url completa
        $urlCompleta = str_replace('/', '_', $programa);
        $programa = $urlCompleta.($opcion ? '_'.Str::slug($opcion, '_'): '');

        return $programa;
    }
}

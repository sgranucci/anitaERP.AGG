<?php

namespace App\Repositories\Configuracion;

use App\Models\Configuracion\Arbolaprobacion;
use App\Repositories\Configuracion\ArbolaprobacionRepositoryInterface;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Exception;
use Carbon\Carbon;
use Auth;
use DB;

class ArbolaprobacionRepository implements ArbolaprobacionRepositoryInterface
{
    protected $model;
    protected $empresaRepository;

    /**
     * PostRepository constructor.
     *
     * @param Post $post
     */
    public function __construct(Arbolaprobacion $arbolaprobacion,
                                EmpresaRepositoryInterface $empresarepository)
    {
        $this->model = $arbolaprobacion;
        $this->empresaRepository = $empresarepository;
    }

	public function all()
    {
        $arbolaprobacion = $this->model;

        // Define filtro por rol
        $tipoarbol = [];
        if (session()->get('rol_nombre') != 'administrador')
        {
            $permisos = traePermisosUsuario();

            if (in_array("actualiza-arbol-requisiciones", $permisos['permisos']) ||
                in_array("consulta-arbol-requisiciones", $permisos['permisos']))
                $tipoarbol[] = Arbolaprobacion::$enumTipoArbol[0]['nombre']; 

            if (in_array("actualiza-arbol-ordenes-de-compra", $permisos['permisos']) ||
                in_array("consulta-arbol-ordenes-de-compra", $permisos['permisos']))
                $tipoarbol[] = Arbolaprobacion::$enumTipoArbol[1]['nombre']; 

            if (in_array("actualiza-arbol-solicitudes-de-pago", $permisos['permisos']) ||
                in_array("consulta-arbol-solicitudes-de-pago", $permisos['permisos']))
                $tipoarbol[] = Arbolaprobacion::$enumTipoArbol[2]['nombre']; 

            if (in_array("actualiza-arbol-ordenes-de-venta", $permisos['permisos']) ||
                in_array("consulta-arbol-ordenes-de-venta", $permisos['permisos']))
                $tipoarbol[] = Arbolaprobacion::$enumTipoArbol[3]['nombre'];            
        }

        // Lee empresas asignadas
        $empresas = $this->empresaRepository->traeEmpresasAsignadas();
        
        $arbolaprobacion = $arbolaprobacion->with('arbolaprobacion_niveles');

        if (count($empresas) > 0)
            $arbolaprobacion = $arbolaprobacion->whereIn('empresa_id', $empresas);

        if (count($tipoarbol) > 0)
            $arbolaprobacion = $arbolaprobacion->whereIn('tipoarbol', $tipoarbol);

        $arbolaprobacion = $arbolaprobacion->get();

        return $arbolaprobacion;
    }

    public function create(array $data)
    {
		return $this->model->create($data);
    }

    public function update(array $data, $id)
    {
		return $this->model->findOrFail($id)->update($data);
    }

    public function delete($id)
    {
		return $this->model->destroy($id);
    }

    public function find($id)
    {
        if (null == $arbolaprobacion = $this->model->with(['arbolaprobacion_niveles' => function ($query) 
                                    {
                                        $query->orderBy('centrocosto_id', 'asc');
                                        $query->orderBy('nivel', 'asc');  
                                    }
                                    ])->findOrFail($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $arbolaprobacion;
    }

    public function findOrFail($id)
    {
        if (null == $arbolaprobacion = $this->model
                                    ->with(['arbolaprobacion_niveles' => function ($query) 
                                    {
                                        $query->orderBy('centrocosto_id', 'asc');
                                        $query->orderBy('nivel', 'asc');  
                                    }
                                    ])->findOrFail($id))
			{
            throw new ModelNotFoundException("Registro no encontrado");
        }
        return $arbolaprobacion;
    }

    public function findPorTipoArbol($tipoarbol)
    {
        $arbolaprobacion = $this->model->where('tipoarbol', $tipoarbol)
                                    ->where('estado', 'ACTIVO')
                                    ->with(['arbolaprobacion_niveles' => function ($query) 
                                    {
                                        $query->orderBy('nivel', 'asc'); // O el nombre de la columna que necesites ordenar
                                    }
                                    ])->get();

        return $arbolaprobacion;
    }

}    

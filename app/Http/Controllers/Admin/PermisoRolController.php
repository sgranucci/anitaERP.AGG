<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Admin\Permiso;
use App\Models\Admin\Rol;
use App\Repositories\Contable\CentrocostoRepositoryInterface;

class PermisoRolController extends Controller
{
    private $centrocostoRepository;

    public function __construct(CentrocostoRepositoryInterface $centrocostorepository)
    {
        $this->centrocostoRepository = $centrocostorepository;
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        if (isset($request->centrocosto))
        {
            $centrocosto = $request->centrocosto;

            // Si es numerico lo que trae asume que es el numero del centro de costo
            if (is_numeric($centrocosto))
            {
                $centrocostos = $this->centrocostoRepository->findPorCodigo($centrocosto);

                $rols = Rol::where('centrocosto_id', $centrocostos->id)->orderBy('id')->pluck('nombre', 'id')->toArray();
            }
            else
            {
                $centrocostos = $this->centrocostoRepository->findPorNombre($centrocosto);

                $rols = Rol::whereIn('centrocosto_id', $centrocostos)->orderBy('id')->pluck('nombre', 'id')->toArray();
            }
        }
        else
            $rols = Rol::orderBy('id')->pluck('nombre', 'id')->toArray();   
        
        if (isset($request->permiso))
        {
            $permiso = $request->permiso;
            $permisos = Permiso::where('nombre', 'LIKE', '%'.$permiso.'%')->get();
        }
        else           
            $permisos = Permiso::get();

        $permisosRols = Permiso::with('roles')->get()->pluck('roles', 'id')->toArray();

        return view('admin.permiso-rol.index', compact('rols', 'permisos', 'permisosRols'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function guardar(Request $request)
    {
        if ($request->ajax()) {
            $permisos = new Permiso();
            if ($request->input('estado') == 1) {
                $permisos->find($request->input('permiso_id'))->roles()->attach($request->input('rol_id'));
                return response()->json(['respuesta' => 'El rol se asigno correctamente']);
            } else {
                $permisos->find($request->input('permiso_id'))->roles()->detach($request->input('rol_id'));
                return response()->json(['respuesta' => 'El rol se elimino correctamente']);
            }
        } else {
            abort(404);
        }
    }
}

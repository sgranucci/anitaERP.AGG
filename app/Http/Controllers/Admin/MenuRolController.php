<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Admin\Rol;
use App\Models\Admin\Menu;
use App\Repositories\Contable\CentrocostoRepositoryInterface;

class MenuRolController extends Controller
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
        $menus = Menu::getMenu(false, 1);
        $menusRols = Menu::with('roles')->get()->pluck('roles', 'id')->toArray();
        return view('admin.menu-rol.index', compact('rols', 'menus', 'menusRols'));
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
            $menus = new Menu();
            if ($request->input('estado') == 1) {
                $menus->find($request->input('menu_id'))->roles()->attach($request->input('rol_id'));
                return response()->json(['respuesta' => 'El rol se asigno correctamente']);
            } else {
                $menus->find($request->input('menu_id'))->roles()->detach($request->input('rol_id'));
                return response()->json(['respuesta' => 'El rol se elimino correctamente']);
            }
        } else {
            abort(404);
        }
    }
}

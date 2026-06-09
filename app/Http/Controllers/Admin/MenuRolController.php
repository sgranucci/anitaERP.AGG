<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Admin\Menu;
use App\Models\Admin\Permiso;
use App\Models\Admin\Rol;
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
        $rols = $this->obtenerRolsFiltrados($request);
        $menus = Menu::getMenu(false, 1);
        $menusRols = Menu::with('roles')->get()->pluck('roles', 'id')->toArray();

        return view('admin.menu-rol.index', compact('rols', 'menus', 'menusRols'));
    }

    /**
     * Permisos del menú y matriz por rol (mismos roles que la vista según filtro de centro de costo).
     */
    public function permisosPorMenu(Request $request)
    {
        $request->validate([
            'menu_id' => 'required|integer|exists:menu,id',
        ]);

        $menu = Menu::findOrFail($request->input('menu_id'));
        $rols = $this->obtenerRolsFiltrados($request);
        $rolIds = array_keys($rols);

        $menuIds = $this->collectMenuIdsConDescendientes((int) $menu->id);
        $nombresMenu = Menu::whereIn('id', $menuIds)->pluck('nombre', 'id');

        $permisos = Permiso::whereIn('menu_id', $menuIds)
            ->with('roles')
            ->orderBy('nombre')
            ->get()
            ->map(function (Permiso $p) use ($rolIds, $nombresMenu, $menu) {
                $ids = $p->roles->pluck('id')->all();
                $menuOrigen = (int) $p->menu_id !== (int) $menu->id
                    ? ($nombresMenu[$p->menu_id] ?? '')
                    : '';

                return [
                    'id' => $p->id,
                    'nombre' => $menuOrigen !== ''
                        ? $p->nombre.' ('.$menuOrigen.')'
                        : $p->nombre,
                    'roles_ids' => array_values(array_intersect($ids, $rolIds)),
                ];
            });

        return response()->json([
            'menu' => ['id' => $menu->id, 'nombre' => $menu->nombre],
            'roles' => $rols,
            'permisos' => $permisos,
        ]);
    }

    /**
     * Incluye permisos de submenús (ej. Estacionamiento → Categorías de automóviles).
     *
     * @return list<int>
     */
    private function collectMenuIdsConDescendientes(int $menuId): array
    {
        $ids = [$menuId];
        $hijos = Menu::where('menu_id', $menuId)->pluck('id')->all();

        foreach ($hijos as $hijoId) {
            $ids = array_merge($ids, $this->collectMenuIdsConDescendientes((int) $hijoId));
        }

        return array_values(array_unique($ids));
    }

    /**
     * @return array<int, string> id => nombre
     */
    private function obtenerRolsFiltrados(Request $request): array
    {
        if (! isset($request->centrocosto) || $request->centrocosto === '') {
            return Rol::orderBy('id')->pluck('nombre', 'id')->toArray();
        }

        $centrocosto = $request->centrocosto;

        if (is_numeric($centrocosto)) {
            $centrocostos = $this->centrocostoRepository->findPorCodigo($centrocosto);
            if ($centrocostos === null) {
                return [];
            }

            return Rol::where('centrocosto_id', $centrocostos->id)->orderBy('id')->pluck('nombre', 'id')->toArray();
        }

        $centrocostos = $this->centrocostoRepository->findPorNombre($centrocosto);

        if (count($centrocostos) === 0) {
            return [];
        }

        return Rol::whereIn('centrocosto_id', $centrocostos)->orderBy('id')->pluck('nombre', 'id')->toArray();
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
            $menu = Menu::find($request->input('menu_id'));
            if (! $menu) {
                abort(404);
            }
            if ($request->input('estado') == 1) {
                $menu->auditAttach('roles', $request->input('rol_id'));
                return response()->json(['respuesta' => 'El rol se asigno correctamente']);
            } else {
                $menu->auditDetach('roles', $request->input('rol_id'));
                return response()->json(['respuesta' => 'El rol se elimino correctamente']);
            }
        } else {
            abort(404);
        }
    }
}

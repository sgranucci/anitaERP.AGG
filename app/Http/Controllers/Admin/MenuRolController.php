<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Admin\Menu;
use App\Models\Admin\Permiso;
use App\Models\Admin\Rol;
use App\Models\Contable\Centrocosto;
use App\Repositories\Contable\CentrocostoRepositoryInterface;
use App\Support\Compras\OrdencompraSectorVisibilidadSupport;
use Illuminate\Support\Collection;

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

        $centrocostosFiltro = $this->obtenerCentrocostosConRoles();
        $modulosMenu = $this->obtenerModulosMenu($menus);
        $centrocostoId = (string) $request->input('centrocosto_id', '');
        $totalRolesSistema = Rol::query()->count();
        $hayRolesSinCentrocosto = Rol::query()->whereNull('centrocosto_id')->exists();

        return view('admin.menu-rol.index', compact(
            'rols',
            'menus',
            'menusRols',
            'centrocostosFiltro',
            'modulosMenu',
            'centrocostoId',
            'totalRolesSistema',
            'hayRolesSinCentrocosto'
        ));
    }

    /**
     * Usuarios asignados a un rol (pantalla menú–rol; incluye suspendidos con marca).
     */
    public function usuariosPorRol(Request $request)
    {
        $request->validate([
            'rol_id' => 'required|integer|exists:rol,id',
        ]);

        $rol = Rol::query()->findOrFail((int) $request->input('rol_id'));

        $usuarios = $rol->usuarios()
            ->orderBy('nombre')
            ->get(['usuario.id', 'usuario.usuario', 'usuario.nombre', 'usuario.email', 'usuario.suspendido'])
            ->map(static function ($u) {
                return [
                    'id' => (int) $u->id,
                    'usuario' => (string) ($u->usuario ?? ''),
                    'nombre' => (string) ($u->nombre ?? ''),
                    'email' => (string) ($u->email ?? ''),
                    'suspendido' => (bool) ($u->suspendido ?? false),
                    'url_editar' => route('editar_usuario', ['id' => $u->id]),
                ];
            })
            ->values();

        return response()->json([
            'rol' => ['id' => (int) $rol->id, 'nombre' => (string) $rol->nombre],
            'usuarios' => $usuarios,
        ]);
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
        $slugsExtra = OrdencompraSectorVisibilidadSupport::slugsExtraParaMenuIds($menuIds);

        $permisos = Permiso::query()
            ->where(function ($q) use ($menuIds, $slugsExtra) {
                $q->whereIn('menu_id', $menuIds);
                if ($slugsExtra !== []) {
                    $q->orWhereIn('slug', $slugsExtra);
                }
            })
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
     * Centros de costo que tienen al menos un rol asignado (para el select de filtro).
     *
     * @return Collection<int, Centrocosto>
     */
    private function obtenerCentrocostosConRoles(): Collection
    {
        $ids = Rol::query()
            ->whereNotNull('centrocosto_id')
            ->distinct()
            ->orderBy('centrocosto_id')
            ->pluck('centrocosto_id');

        if ($ids->isEmpty()) {
            return collect();
        }

        return Centrocosto::query()
            ->whereIn('id', $ids)
            ->orderBy('nombre')
            ->get(['id', 'codigo', 'nombre']);
    }

    /**
     * Módulos raíz del menú (nivel 1) para filtrar opciones en cliente.
     *
     * @param  array<int, array<string, mixed>>  $menus
     * @return list<array{id: int, nombre: string}>
     */
    private function obtenerModulosMenu(array $menus): array
    {
        $modulos = [];

        foreach ($menus as $menu) {
            if ((int) ($menu['menu_id'] ?? 0) !== 0) {
                break;
            }
            $modulos[] = [
                'id' => (int) $menu['id'],
                'nombre' => (string) ($menu['nombre'] ?? ''),
            ];
        }

        return $modulos;
    }

    /**
     * @return array<int, string> id => nombre
     */
    private function obtenerRolsFiltrados(Request $request): array
    {
        $query = Rol::query()->orderBy('nombre');

        $centrocostoId = $request->input('centrocosto_id');

        // Compatibilidad con filtro texto legacy ?centrocosto=
        if (($centrocostoId === null || $centrocostoId === '') && $request->filled('centrocosto')) {
            return $this->obtenerRolsPorTextoLegacy((string) $request->input('centrocosto'));
        }

        if ($centrocostoId === 'sin') {
            return $query->whereNull('centrocosto_id')->pluck('nombre', 'id')->toArray();
        }

        if ($centrocostoId !== null && $centrocostoId !== '') {
            return $query->where('centrocosto_id', (int) $centrocostoId)->pluck('nombre', 'id')->toArray();
        }

        return $query->pluck('nombre', 'id')->toArray();
    }

    /**
     * @return array<int, string>
     */
    private function obtenerRolsPorTextoLegacy(string $centrocosto): array
    {
        if (is_numeric($centrocosto)) {
            $centrocostos = $this->centrocostoRepository->findPorCodigo($centrocosto);
            if ($centrocostos === null) {
                return [];
            }

            return Rol::query()
                ->where('centrocosto_id', $centrocostos->id)
                ->orderBy('nombre')
                ->pluck('nombre', 'id')
                ->toArray();
        }

        $ids = $this->centrocostoRepository->findPorNombre($centrocosto);
        if (count($ids) === 0) {
            return [];
        }

        return Rol::query()
            ->whereIn('centrocosto_id', $ids)
            ->orderBy('nombre')
            ->pluck('nombre', 'id')
            ->toArray();
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

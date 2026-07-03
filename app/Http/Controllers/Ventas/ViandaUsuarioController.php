<?php

namespace App\Http\Controllers\Ventas;

use App\Http\Controllers\Controller;
use App\Http\Requests\ValidacionViandaUsuario;
use App\Models\Contable\Centrocosto;
use App\Models\Ventas\ViandaTipoMenu;
use App\Models\Ventas\ViandaUsuario;
use App\Repositories\Ventas\ViandaUsuarioRepositoryInterface;
use App\Services\Ventas\ViandaUsuarioAnitaSyncService;
use App\Support\Ventas\ViandaUsuarioListadoFiltros;
use App\Support\Ventas\ViandaUsuarioTipoSupport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ViandaUsuarioController extends Controller
{
    public function __construct(
        private ViandaUsuarioRepositoryInterface $repository,
        private ViandaUsuarioAnitaSyncService $anitaSyncService,
    ) {
    }

    public function index(Request $request)
    {
        can('listar-vianda-usuario-gastronomia');

        if (config('app.anita_sync_vianda_usuario_gastronomia_index')
            && ! $this->repository->existeRegistro()) {
            try {
                $this->anitaSyncService->sincronizarConAnita();
            } catch (\Throwable $e) {
                Log::warning('ViandaUsuario index auto-sync Anita: '.$e->getMessage(), ['exception' => $e]);
            }
        }

        $filtros = ViandaUsuarioListadoFiltros::resolverDesdeRequest($request);
        $datas = $this->repository->leeUsuarios($filtros, true);
        $sinRegistros = $datas->total() === 0 && ! ViandaUsuarioListadoFiltros::tieneCriteriosAplicados($filtros);

        return view('ventas.vianda_usuario.index', [
            'datas' => $datas,
            'filtros' => $filtros,
            'filtrosQuery' => ViandaUsuarioListadoFiltros::paraQueryString($filtros),
            'sinRegistros' => $sinRegistros,
            'tiposUsuario' => ViandaUsuarioTipoSupport::ETIQUETAS,
        ]);
    }

    public function crear()
    {
        can('crear-vianda-usuario-gastronomia');

        $data = new ViandaUsuario(['estado' => 'A', 'tipo_usuario' => 'L']);
        $centrocosto_query = Centrocosto::query()->orderBy('codigo')->get(['id', 'codigo', 'nombre']);
        $tipo_menu_query = ViandaTipoMenu::query()->where('estado', 'A')->orderBy('nombre')->get(['id', 'nombre']);

        return view('ventas.vianda_usuario.crear', compact(
            'data',
            'centrocosto_query',
            'tipo_menu_query',
        ));
    }

    public function guardar(ValidacionViandaUsuario $request)
    {
        can('crear-vianda-usuario-gastronomia');

        $this->repository->create($request->all());

        return redirect('ventas/gastronomia/viandas/usuarios')->with('mensaje', 'Usuario de vianda creado con éxito');
    }

    public function editar($id)
    {
        can('editar-vianda-usuario-gastronomia');

        $data = $this->repository->findOrFail($id);
        $centrocosto_query = Centrocosto::query()->orderBy('codigo')->get(['id', 'codigo', 'nombre']);
        $tipo_menu_query = ViandaTipoMenu::query()->orderBy('nombre')->get(['id', 'nombre', 'estado']);

        return view('ventas.vianda_usuario.editar', compact(
            'data',
            'centrocosto_query',
            'tipo_menu_query',
        ));
    }

    public function actualizar(ValidacionViandaUsuario $request, $id)
    {
        can('actualizar-vianda-usuario-gastronomia');

        $this->repository->update($request->all(), $id);

        return redirect('ventas/gastronomia/viandas/usuarios')->with('mensaje', 'Usuario de vianda actualizado con éxito');
    }

    public function eliminar(Request $request, $id)
    {
        can('borrar-vianda-usuario-gastronomia');

        if ($request->ajax()) {
            if ($this->repository->delete($id)) {
                return response()->json(['mensaje' => 'ok']);
            }

            return response()->json(['mensaje' => 'ng']);
        }

        abort(404);
    }

    public function sincronizarDesdeAnita(Request $request)
    {
        can('sincronizar-vianda-usuario-gastronomia-anita');

        if (! config('app.anita_sync_vianda_usuario_gastronomia_index')) {
            abort(403);
        }

        if (! $request->isMethod('post')) {
            abort(405);
        }

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');
        set_time_limit(0);
        ignore_user_abort(true);

        try {
            $ret = $this->anitaSyncService->sincronizarConAnita();

            $msg = 'Sincronización desde Anita: '.$ret['importados'].' nuevos, '.$ret['actualizados'].' actualizados.';
            if ($ret['omitidos'] > 0) {
                $msg .= ' Omitidos/inactivos: '.$ret['omitidos'].'.';
            }
            if (! empty($ret['errores'])) {
                $msg .= ' '.implode(' ', array_slice($ret['errores'], 0, 5));
            }

            return redirect()->route('consultar_vianda_usuario_gastronomia')->with('mensaje', $msg);
        } catch (\Throwable $e) {
            Log::warning('ViandaUsuario sincronizarDesdeAnita: '.$e->getMessage(), ['exception' => $e]);

            return redirect()->route('consultar_vianda_usuario_gastronomia')->with('errores', [
                'No se completó la sincronización desde Anita. Si el error fue por tiempo de espera, ejecute: '
                .'php artisan vianda:sincronizar-usuarios-anita — Detalle: '.$e->getMessage(),
            ]);
        }
    }
}

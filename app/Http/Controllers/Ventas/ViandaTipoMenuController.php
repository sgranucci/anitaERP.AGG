<?php

namespace App\Http\Controllers\Ventas;

use App\Http\Controllers\Controller;
use App\Http\Requests\ValidacionViandaTipoMenu;
use App\Models\Ventas\ViandaTipoMenu;
use App\Repositories\Ventas\ViandaTipoMenuRepositoryInterface;
use App\Services\Ventas\ViandaTipoMenuAnitaSyncService;
use App\Support\Ventas\ViandaDiaSemanaSupport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ViandaTipoMenuController extends Controller
{
    public function __construct(
        private ViandaTipoMenuRepositoryInterface $repository,
        private ViandaTipoMenuAnitaSyncService $anitaSyncService,
    ) {
    }

    public function index()
    {
        can('listar-vianda-tipo-menu-gastronomia');

        if (config('app.anita_sync_vianda_tipo_menu_gastronomia_index')
            && ! $this->repository->existeRegistro()) {
            try {
                $this->anitaSyncService->sincronizarConAnita();
            } catch (\Throwable $e) {
                Log::warning('ViandaTipoMenu index auto-sync Anita: '.$e->getMessage(), ['exception' => $e]);
            }
        }

        $datas = $this->repository->all();
        $sinRegistros = $datas->isEmpty();
        $diasSemana = ViandaDiaSemanaSupport::ETIQUETAS;

        return view('ventas.vianda_tipo_menu.index', compact('datas', 'sinRegistros', 'diasSemana'));
    }

    public function crear()
    {
        can('crear-vianda-tipo-menu-gastronomia');

        $data = new ViandaTipoMenu(['estado' => 'A']);
        $diasSemana = ViandaDiaSemanaSupport::ETIQUETAS;
        $articulosPorDia = $this->repository->agruparArticulosPorDia($data);

        return view('ventas.vianda_tipo_menu.crear', compact('data', 'diasSemana', 'articulosPorDia'));
    }

    public function guardar(ValidacionViandaTipoMenu $request)
    {
        can('crear-vianda-tipo-menu-gastronomia');

        $this->repository->create($request->all());

        return redirect('ventas/gastronomia/viandas/tipos-menu')->with('mensaje', 'Tipo de menú de vianda creado con éxito');
    }

    public function editar($id)
    {
        can('editar-vianda-tipo-menu-gastronomia');

        $data = $this->repository->findOrFail($id);
        $diasSemana = ViandaDiaSemanaSupport::ETIQUETAS;
        $articulosPorDia = $this->repository->agruparArticulosPorDia($data);

        return view('ventas.vianda_tipo_menu.editar', compact('data', 'diasSemana', 'articulosPorDia'));
    }

    public function actualizar(ValidacionViandaTipoMenu $request, $id)
    {
        can('actualizar-vianda-tipo-menu-gastronomia');

        $this->repository->update($request->all(), $id);

        return redirect('ventas/gastronomia/viandas/tipos-menu')->with('mensaje', 'Tipo de menú de vianda actualizado con éxito');
    }

    public function eliminar(Request $request, $id)
    {
        can('borrar-vianda-tipo-menu-gastronomia');

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
        can('sincronizar-vianda-tipo-menu-gastronomia-anita');

        if (! config('app.anita_sync_vianda_tipo_menu_gastronomia_index')) {
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

            $msg = 'Sincronización desde Anita: '.$ret['importados'].' nuevos, '.$ret['actualizados'].' actualizados, '
                .$ret['articulos_lineas'].' líneas de artículos.';
            if (! empty($ret['errores'])) {
                $msg .= ' '.implode(' ', array_slice($ret['errores'], 0, 5));
            }

            return redirect()->route('consultar_vianda_tipo_menu_gastronomia')->with('mensaje', $msg);
        } catch (\Throwable $e) {
            Log::warning('ViandaTipoMenu sincronizarDesdeAnita: '.$e->getMessage(), ['exception' => $e]);

            return redirect()->route('consultar_vianda_tipo_menu_gastronomia')->with('errores', [
                'No se completó la sincronización desde Anita. Detalle: '.$e->getMessage(),
            ]);
        }
    }
}

<?php

namespace App\Http\Controllers\Ventas;

use App\Http\Controllers\Controller;
use App\Http\Requests\ValidacionCategoriafidelidadGastronomia;
use App\Models\Ventas\CategoriafidelidadGastronomia;
use App\Repositories\Ventas\CategoriafidelidadGastronomiaRepositoryInterface;
use App\Services\Ventas\CategoriafidelidadGastronomiaAnitaSyncService;
use App\Services\Ventas\CategoriafidelidadGastronomiaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class CategoriafidelidadGastronomiaController extends Controller
{
    public function __construct(
        private CategoriafidelidadGastronomiaRepositoryInterface $repository,
        private CategoriafidelidadGastronomiaService $categoriafidelidadService,
        private CategoriafidelidadGastronomiaAnitaSyncService $categoriafidelidadAnitaSyncService,
    ) {
    }

    public function index()
    {
        can('listar-categoria-fidelidad-gastronomia');

        if (filter_var(env('CATEGORIA_FIDELIDAD_GASTRONOMIA_SYNC_ANITA_INDEX', false), FILTER_VALIDATE_BOOLEAN)
            && ! $this->repository->existeRegistro()) {
            $this->categoriafidelidadAnitaSyncService->sincronizarConAnita();
        }

        $datas = $this->repository->all();
        $sinCategoriasCargadas = $datas->isEmpty();

        return view('ventas.categoriafidelidad_gastronomia.index', compact('datas', 'sinCategoriasCargadas'));
    }

    public function crear()
    {
        can('crear-categoria-fidelidad-gastronomia');
        $data = new CategoriafidelidadGastronomia();

        return view('ventas.categoriafidelidad_gastronomia.crear', compact('data'));
    }

    public function guardar(ValidacionCategoriafidelidadGastronomia $request)
    {
        can('crear-categoria-fidelidad-gastronomia');
        $this->categoriafidelidadService->guardar($request->all());

        return redirect('ventas/categoria-fidelidad-gastronomia')->with('mensaje', 'Categoría de fidelidad creada con éxito');
    }

    public function editar($id)
    {
        can('editar-categoria-fidelidad-gastronomia');
        $data = CategoriafidelidadGastronomia::query()
            ->with(['articulos.articulo'])
            ->findOrFail($id);

        return view('ventas.categoriafidelidad_gastronomia.editar', compact('data'));
    }

    public function actualizar(ValidacionCategoriafidelidadGastronomia $request, $id)
    {
        can('actualizar-categoria-fidelidad-gastronomia');
        $this->categoriafidelidadService->actualizar($request->all(), (int) $id);

        return redirect('ventas/categoria-fidelidad-gastronomia')->with('mensaje', 'Categoría de fidelidad actualizada con éxito');
    }

    public function eliminar(Request $request, $id)
    {
        can('borrar-categoria-fidelidad-gastronomia');

        if ($request->ajax()) {
            if ($this->repository->delete($id)) {
                return response()->json(['mensaje' => 'ok']);
            }

            return response()->json(['mensaje' => 'ng']);
        }

        abort(404);
    }

    /**
     * Importación masiva desde Anita (ApiAnita). Si hay timeout (504), usar:
     * php artisan categoria-fidelidad-gastronomia:sincronizar-anita
     */
    public function sincronizarDesdeAnita(Request $request)
    {
        can('actualizar-categoria-fidelidad-gastronomia');

        if (! config('app.anita_sync_categoria_fidelidad_gastronomia_index')) {
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
            $ret = $this->categoriafidelidadAnitaSyncService->sincronizarConAnita();
            $msg = 'Sincronización desde Anita: '
                .$ret['importados'].' categorías nuevas, '.$ret['actualizados'].' actualizadas, '
                .$ret['articulos_importados'].' artículos vinculados, '
                .$ret['entregas_importadas'].' entregas nuevas, '.$ret['entregas_actualizadas'].' entregas actualizadas '
                .'(desde '.config('categoriafidelidad_gastronomia_anita.fecha_desde').').';
            if (! empty($ret['errores'])) {
                $msg .= ' '.implode(' ', array_slice($ret['errores'], 0, 5));
            }

            return redirect()->route('consultar_categoria_fidelidad_gastronomia')->with('mensaje', $msg);
        } catch (\Throwable $e) {
            Log::warning('CategoriafidelidadGastronomia sincronizarDesdeAnita: '.$e->getMessage(), ['exception' => $e]);

            return redirect()->route('consultar_categoria_fidelidad_gastronomia')->with('errores', [
                'No se completó la sincronización desde Anita. Si el error fue por tiempo de espera (504), ejecute en el servidor: php artisan categoria-fidelidad-gastronomia:sincronizar-anita — Detalle: '.$e->getMessage(),
            ]);
        }
    }
}

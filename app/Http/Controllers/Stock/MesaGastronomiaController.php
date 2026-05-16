<?php

namespace App\Http\Controllers\Stock;

use App\Http\Controllers\Controller;
use App\Http\Requests\ValidacionMesaGastronomia;
use App\Models\Configuracion\Empresa;
use App\Models\Stock\MesaGastronomia;
use App\Repositories\Stock\MesaGastronomiaRepositoryInterface;
use App\Repositories\Stock\UbicacionGastronomiaRepositoryInterface;
use App\Services\Stock\MesaGastronomiaAnitaSyncService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class MesaGastronomiaController extends Controller
{
    public function __construct(
        private MesaGastronomiaRepositoryInterface $repository,
        private MesaGastronomiaAnitaSyncService $mesaGastronomiaAnitaSyncService,
        private UbicacionGastronomiaRepositoryInterface $ubicacionGastronomiaRepository,
    ) {
    }

    public function index()
    {
        can('listar-mesa-gastronomia');

        if (filter_var(env('MESA_GASTRONOMIA_SYNC_ANITA_INDEX', false), FILTER_VALIDATE_BOOLEAN)
            && ! $this->repository->existeRegistro()) {
            $this->mesaGastronomiaAnitaSyncService->sincronizarConAnita();
        }

        $datas = $this->repository->all();

        return view('stock.mesa_gastronomia.index', compact('datas'));
    }

    public function crear()
    {
        can('crear-mesa-gastronomia');
        $data = new MesaGastronomia();
        $empresa_query = Empresa::orderBy('nombre')->get();
        $ubicacion_query = $this->ubicacionGastronomiaRepository->listarParaSelect();

        return view('stock.mesa_gastronomia.crear', compact('data', 'empresa_query', 'ubicacion_query'));
    }

    public function guardar(ValidacionMesaGastronomia $request)
    {
        $this->repository->create($request->all());

        return redirect('stock/mesa-gastronomia')->with('mensaje', 'Mesa creada con éxito');
    }

    public function editar($id)
    {
        can('editar-mesa-gastronomia');
        $data = $this->repository->findOrFail($id);
        $empresa_query = Empresa::orderBy('nombre')->get();
        $ubicacion_query = $this->ubicacionGastronomiaRepository->listarParaSelect($data->empresa_id);

        return view('stock.mesa_gastronomia.editar', compact('data', 'empresa_query', 'ubicacion_query'));
    }

    public function actualizar(ValidacionMesaGastronomia $request, $id)
    {
        can('actualizar-mesa-gastronomia');
        $this->repository->update($request->all(), $id);

        return redirect('stock/mesa-gastronomia')->with('mensaje', 'Mesa actualizada con éxito');
    }

    public function eliminar(Request $request, $id)
    {
        can('borrar-mesa-gastronomia');

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
     * php artisan mesa-gastronomia:sincronizar-anita
     */
    public function sincronizarDesdeAnita(Request $request)
    {
        can('actualizar-mesa-gastronomia');

        if (! config('app.anita_sync_mesa_gastronomia_index')) {
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
            $ret = $this->mesaGastronomiaAnitaSyncService->sincronizarConAnita();
            $msg = 'Sincronización desde Anita: '.$ret['importados'].' nuevas, '.$ret['actualizadas'].' actualizadas.';
            if (! empty($ret['errores'])) {
                $msg .= ' '.implode(' ', array_slice($ret['errores'], 0, 5));
            }

            return redirect()->route('consultar_mesa_gastronomia')->with('mensaje', $msg);
        } catch (\Throwable $e) {
            Log::warning('MesaGastronomia sincronizarDesdeAnita: '.$e->getMessage(), ['exception' => $e]);

            return redirect()->route('consultar_mesa_gastronomia')->with('errores', [
                'No se completó la sincronización desde Anita. Si el error fue por tiempo de espera (504), ejecute en el servidor: php artisan mesa-gastronomia:sincronizar-anita — Detalle: '.$e->getMessage(),
            ]);
        }
    }
}

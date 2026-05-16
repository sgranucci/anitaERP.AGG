<?php

namespace App\Http\Controllers\Stock;

use App\Http\Controllers\Controller;
use App\Http\Requests\ValidacionMozoGastronomia;
use App\Models\Configuracion\Empresa;
use App\Models\Stock\MozoGastronomia;
use App\Repositories\Stock\MozoGastronomiaRepositoryInterface;
use App\Services\Stock\MozoGastronomiaAnitaSyncService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class MozoGastronomiaController extends Controller
{
    public function __construct(
        private MozoGastronomiaRepositoryInterface $repository,
        private MozoGastronomiaAnitaSyncService $mozoGastronomiaAnitaSyncService,
    ) {
    }

    public function index()
    {
        can('listar-mozo-gastronomia');

        if (filter_var(env('MOZO_GASTRONOMIA_SYNC_ANITA_INDEX', false), FILTER_VALIDATE_BOOLEAN)
            && ! $this->repository->existeRegistro()) {
            $this->mozoGastronomiaAnitaSyncService->sincronizarConAnita();
        }

        $datas = $this->repository->all();
        $sinMozosCargados = $datas->isEmpty();

        return view('stock.mozo_gastronomia.index', compact('datas', 'sinMozosCargados'));
    }

    public function crear()
    {
        can('crear-mozo-gastronomia');
        $data = new MozoGastronomia();
        $empresa_query = Empresa::orderBy('nombre')->get();

        return view('stock.mozo_gastronomia.crear', compact('data', 'empresa_query'));
    }

    public function guardar(ValidacionMozoGastronomia $request)
    {
        $this->repository->create($request->all());

        return redirect('stock/mozo-gastronomia')->with('mensaje', 'Mozo creado con éxito');
    }

    public function editar($id)
    {
        can('editar-mozo-gastronomia');
        $data = $this->repository->findOrFail($id);
        $empresa_query = Empresa::orderBy('nombre')->get();

        return view('stock.mozo_gastronomia.editar', compact('data', 'empresa_query'));
    }

    public function actualizar(ValidacionMozoGastronomia $request, $id)
    {
        can('actualizar-mozo-gastronomia');
        $this->repository->update($request->all(), $id);

        return redirect('stock/mozo-gastronomia')->with('mensaje', 'Mozo actualizado con éxito');
    }

    public function eliminar(Request $request, $id)
    {
        can('borrar-mozo-gastronomia');

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
     * php artisan mozo-gastronomia:sincronizar-anita
     */
    public function sincronizarDesdeAnita(Request $request)
    {
        can('actualizar-mozo-gastronomia');

        if (! config('app.anita_sync_mozo_gastronomia_index')) {
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
            $ret = $this->mozoGastronomiaAnitaSyncService->sincronizarConAnita();
            $msg = 'Sincronización desde Anita: '.$ret['importados'].' nuevos, '.$ret['actualizados'].' actualizados.';
            if (! empty($ret['errores'])) {
                $msg .= ' '.implode(' ', array_slice($ret['errores'], 0, 5));
            }

            return redirect()->route('consultar_mozo_gastronomia')->with('mensaje', $msg);
        } catch (\Throwable $e) {
            Log::warning('MozoGastronomia sincronizarDesdeAnita: '.$e->getMessage(), ['exception' => $e]);

            return redirect()->route('consultar_mozo_gastronomia')->with('errores', [
                'No se completó la sincronización desde Anita. Si el error fue por tiempo de espera (504), ejecute en el servidor: php artisan mozo-gastronomia:sincronizar-anita — Detalle: '.$e->getMessage(),
            ]);
        }
    }
}

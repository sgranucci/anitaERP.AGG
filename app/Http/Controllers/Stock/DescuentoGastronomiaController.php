<?php

namespace App\Http\Controllers\Stock;

use App\Http\Controllers\Controller;
use App\Http\Requests\ValidacionDescuentoGastronomia;
use App\Models\Stock\DescuentoGastronomia;
use App\Repositories\Stock\DescuentoGastronomiaRepositoryInterface;
use App\Services\Stock\DescuentoGastronomiaAnitaSyncService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class DescuentoGastronomiaController extends Controller
{
    public function __construct(
        private DescuentoGastronomiaRepositoryInterface $repository,
        private DescuentoGastronomiaAnitaSyncService $descuentoGastronomiaAnitaSyncService,
    ) {
    }

    public function index()
    {
        can('listar-descuento-gastronomia');

        if (filter_var(env('DESCUENTO_GASTRONOMIA_SYNC_ANITA_INDEX', false), FILTER_VALIDATE_BOOLEAN)
            && ! $this->repository->existeRegistro()) {
            $this->descuentoGastronomiaAnitaSyncService->sincronizarConAnita();
        }

        $datas = $this->repository->all();
        $sinDescuentosCargados = $datas->isEmpty();
        $tiposValor = DescuentoGastronomia::tiposValor();

        return view('stock.descuento_gastronomia.index', compact('datas', 'sinDescuentosCargados', 'tiposValor'));
    }

    public function crear()
    {
        can('crear-descuento-gastronomia');
        $data = new DescuentoGastronomia();
        $tiposValor = DescuentoGastronomia::tiposValor();

        return view('stock.descuento_gastronomia.crear', compact('data', 'tiposValor'));
    }

    public function guardar(ValidacionDescuentoGastronomia $request)
    {
        $this->repository->create($request->all());

        return redirect('stock/descuento-gastronomia')->with('mensaje', 'Descuento creado con éxito');
    }

    public function editar($id)
    {
        can('editar-descuento-gastronomia');
        $data = $this->repository->findOrFail($id);
        $tiposValor = DescuentoGastronomia::tiposValor();

        return view('stock.descuento_gastronomia.editar', compact('data', 'tiposValor'));
    }

    public function actualizar(ValidacionDescuentoGastronomia $request, $id)
    {
        can('actualizar-descuento-gastronomia');
        $this->repository->update($request->all(), $id);

        return redirect('stock/descuento-gastronomia')->with('mensaje', 'Descuento actualizado con éxito');
    }

    public function eliminar(Request $request, $id)
    {
        can('borrar-descuento-gastronomia');

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
     * php artisan descuento-gastronomia:sincronizar-anita
     */
    public function sincronizarDesdeAnita(Request $request)
    {
        can('actualizar-descuento-gastronomia');

        if (! config('app.anita_sync_descuento_gastronomia_index')) {
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
            $ret = $this->descuentoGastronomiaAnitaSyncService->sincronizarConAnita();
            $msg = 'Sincronización desde Anita: '.$ret['importados'].' nuevos, '.$ret['actualizadas'].' actualizados.';
            if (! empty($ret['errores'])) {
                $msg .= ' '.implode(' ', array_slice($ret['errores'], 0, 5));
            }

            return redirect()->route('consultar_descuento_gastronomia')->with('mensaje', $msg);
        } catch (\Throwable $e) {
            Log::warning('DescuentoGastronomia sincronizarDesdeAnita: '.$e->getMessage(), ['exception' => $e]);

            return redirect()->route('consultar_descuento_gastronomia')->with('errores', [
                'No se completó la sincronización desde Anita. Si el error fue por tiempo de espera (504), ejecute en el servidor: php artisan descuento-gastronomia:sincronizar-anita — Detalle: '.$e->getMessage(),
            ]);
        }
    }
}

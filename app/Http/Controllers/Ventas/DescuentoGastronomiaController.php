<?php

namespace App\Http\Controllers\Ventas;

use App\Http\Controllers\Controller;
use App\Http\Requests\ValidacionDescuentoGastronomia;
use App\Models\Ventas\DescuentoGastronomia;
use App\Repositories\Ventas\DescuentoGastronomiaRepositoryInterface;
use App\Services\Ventas\DescuentoGastronomiaAnitaSyncService;
use App\Support\Ventas\GastronomiaDescuentoConsultaAccesoSupport;
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
        $tiposConsumo = DescuentoGastronomia::tiposConsumo();

        return view('ventas.descuento_gastronomia.index', compact('datas', 'sinDescuentosCargados', 'tiposValor', 'tiposConsumo'));
    }

    public function crear()
    {
        can('crear-descuento-gastronomia');
        $data = new DescuentoGastronomia();
        $tiposValor = DescuentoGastronomia::tiposValor();
        $tiposConsumo = DescuentoGastronomia::tiposConsumo();

        return view('ventas.descuento_gastronomia.crear', compact('data', 'tiposValor', 'tiposConsumo'));
    }

    public function guardar(ValidacionDescuentoGastronomia $request)
    {
        $this->repository->create($request->all());

        return redirect('ventas/descuento-gastronomia')->with('mensaje', 'Descuento creado con éxito');
    }

    public function editar($id)
    {
        can('editar-descuento-gastronomia');
        $data = DescuentoGastronomia::query()->with('cliente')->findOrFail($id);
        $tiposValor = DescuentoGastronomia::tiposValor();
        $tiposConsumo = DescuentoGastronomia::tiposConsumo();

        return view('ventas.descuento_gastronomia.editar', compact('data', 'tiposValor', 'tiposConsumo'));
    }

    public function actualizar(ValidacionDescuentoGastronomia $request, $id)
    {
        can('actualizar-descuento-gastronomia');
        $this->repository->update($request->all(), $id);

        return redirect('ventas/descuento-gastronomia')->with('mensaje', 'Descuento actualizado con éxito');
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

    public function consultaDescuento(Request $request)
    {
        GastronomiaDescuentoConsultaAccesoSupport::assert();

        return $this->repository->consultaDescuento((string) ($request->get('consulta') ?? ''));
    }

    public function leeUnDescuentoPorCodigo(string $codigo)
    {
        GastronomiaDescuentoConsultaAccesoSupport::assert();

        $descuento = $this->repository->findPorCodigo($codigo);
        if (! $descuento) {
            return response()->json(['error' => 'Descuento no encontrado'], 404);
        }

        $cli = $descuento->cliente;

        return response()->json([
            'id' => $descuento->id,
            'codigo' => $descuento->codigo,
            'nombre' => $descuento->nombre,
            'tipovalor' => $descuento->tipovalor,
            'valor' => (float) $descuento->valor,
            'cliente_id' => $descuento->cliente_id,
            'cliente' => $cli ? [
                'id' => $cli->id,
                'codigo' => $cli->codigo,
                'nombre' => $cli->nombre,
            ] : null,
        ]);
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

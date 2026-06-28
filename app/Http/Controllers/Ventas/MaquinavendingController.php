<?php

namespace App\Http\Controllers\Ventas;

use App\Http\Controllers\Controller;
use App\Http\Requests\ValidacionMaquinavending;
use App\Models\Stock\Depmae;
use App\Models\Stock\Listaprecio;
use App\Models\Ventas\Maquinavending;
use App\Models\Ventas\Puntoventa;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Repositories\Ventas\MaquinavendingRepositoryInterface;
use App\Services\Ventas\MaquinavendingAnitaSyncService;
use App\Repositories\Ventas\UbicacionGastronomiaRepositoryInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class MaquinavendingController extends Controller
{
    public function __construct(
        private MaquinavendingRepositoryInterface $repository,
        private EmpresaRepositoryInterface $empresaRepository,
        private UbicacionGastronomiaRepositoryInterface $ubicacionGastronomiaRepository,
        private MaquinavendingAnitaSyncService $maquinavendingAnitaSyncService,
    ) {
    }

    public function index()
    {
        can('listar-maquinavending-gastronomia');

        if (config('app.anita_sync_maquinavending_gastronomia_index')
            && ! $this->repository->existeRegistro()) {
            try {
                $this->maquinavendingAnitaSyncService->sincronizarConAnita();
            } catch (\Throwable $e) {
                Log::warning('Maquinavending index auto-sync Anita: '.$e->getMessage(), ['exception' => $e]);
            }
        }

        $datas = $this->repository->all();
        $sinMaquinasCargadas = $datas->isEmpty();

        return view('ventas.maquinavending.index', compact('datas', 'sinMaquinasCargadas'));
    }

    public function crear()
    {
        can('crear-maquinavending-gastronomia');

        $data = new Maquinavending();
        $empresa_query = $this->empresaRepository->allFiltrado();
        $empresaDefaultId = (int) ($empresa_query->first()?->id ?? config('cliente.EMPRESA_DEFAULT_ID'));
        $this->cargarSelects(
            $empresaDefaultId > 0 ? $empresaDefaultId : null,
            $ubicacion_query,
            $puntoventa_query,
            $depositoModel,
            $listaprecio_query,
        );

        return view('ventas.maquinavending.crear', compact(
            'data',
            'empresa_query',
            'ubicacion_query',
            'puntoventa_query',
            'depositoModel',
            'listaprecio_query',
        ));
    }

    public function guardar(ValidacionMaquinavending $request)
    {
        $this->repository->create($request->all());

        return redirect('ventas/gastronomia/maquinas-vending')->with('mensaje', 'Máquina vending creada con éxito');
    }

    public function editar($id)
    {
        can('editar-maquinavending-gastronomia');

        $data = $this->repository->findOrFail($id);
        $empresa_query = $this->empresaRepository->allFiltrado();
        $this->cargarSelects(
            (int) $data->empresa_id,
            $ubicacion_query,
            $puntoventa_query,
            $depositoModel,
            $listaprecio_query,
            $data,
        );

        return view('ventas.maquinavending.editar', compact(
            'data',
            'empresa_query',
            'ubicacion_query',
            'puntoventa_query',
            'depositoModel',
            'listaprecio_query',
        ));
    }

    public function actualizar(ValidacionMaquinavending $request, $id)
    {
        can('actualizar-maquinavending-gastronomia');

        $this->repository->update($request->all(), $id);

        return redirect('ventas/gastronomia/maquinas-vending')->with('mensaje', 'Máquina vending actualizada con éxito');
    }

    public function eliminar(Request $request, $id)
    {
        can('borrar-maquinavending-gastronomia');

        if ($request->ajax()) {
            if ($this->repository->delete($id)) {
                return response()->json(['mensaje' => 'ok']);
            }

            return response()->json(['mensaje' => 'ng']);
        }

        abort(404);
    }

    public function selectsPorEmpresa(int $empresaId): JsonResponse
    {
        can('listar-maquinavending-gastronomia');

        if ($empresaId <= 0 || ! $this->empresaRepository->empresaIdPermitida($empresaId)) {
            return response()->json([
                'ubicaciones' => [],
                'puntoventas' => [],
            ]);
        }

        $ubicaciones = $this->ubicacionGastronomiaRepository->listarParaSelect($empresaId)
            ->map(fn ($u) => ['id' => $u->id, 'nombre' => $u->nombre])
            ->values();

        $puntoventas = Puntoventa::query()
            ->where('empresa_id', $empresaId)
            ->where('estado', 'A')
            ->orderBy('codigo')
            ->get(['id', 'codigo', 'nombre'])
            ->map(fn ($pv) => [
                'id' => $pv->id,
                'label' => trim($pv->codigo.' — '.$pv->nombre),
            ])
            ->values();

        return response()->json([
            'ubicaciones' => $ubicaciones,
            'puntoventas' => $puntoventas,
        ]);
    }

    /**
     * Importación masiva desde Anita (maqvmae / ubimvending). Si hay timeout (504), usar:
     * php artisan maquinavending:sincronizar-anita
     */
    public function sincronizarDesdeAnita(Request $request)
    {
        can('sincronizar-maquinavending-gastronomia-anita');

        if (! config('app.anita_sync_maquinavending_gastronomia_index')) {
            abort(403);
        }

        if (! $request->isMethod('post')) {
            abort(405);
        }

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');
        set_time_limit(0);
        ignore_user_abort(true);

        $empresaId = (int) $request->input('empresa_id', 0);

        try {
            $ret = $empresaId > 0
                ? $this->maquinavendingAnitaSyncService->sincronizarEmpresaDesdeAnita($empresaId)
                : $this->maquinavendingAnitaSyncService->sincronizarConAnita();

            $msg = 'Sincronización desde Anita: '.$ret['importados'].' nuevas, '.$ret['actualizados'].' actualizadas, '
                .$ret['articulos_lineas'].' líneas de rulo.';
            if (! empty($ret['errores'])) {
                $msg .= ' '.implode(' ', array_slice($ret['errores'], 0, 5));
            }

            return redirect()->route('consultar_maquinavending_gastronomia')->with('mensaje', $msg);
        } catch (\Throwable $e) {
            Log::warning('Maquinavending sincronizarDesdeAnita: '.$e->getMessage(), ['exception' => $e]);

            return redirect()->route('consultar_maquinavending_gastronomia')->with('errores', [
                'No se completó la sincronización desde Anita. Si el error fue por tiempo de espera (504), ejecute en el servidor: '
                .'php artisan maquinavending:sincronizar-anita — Detalle: '.$e->getMessage(),
            ]);
        }
    }

    private function cargarSelects(
        ?int $empresaId,
        &$ubicacion_query,
        &$puntoventa_query,
        &$depositoModel,
        &$listaprecio_query,
        ?Maquinavending $data = null,
    ): void {
        $ubicacion_query = $this->ubicacionGastronomiaRepository->listarParaSelect($empresaId);
        $puntoventa_query = collect();
        if ($empresaId !== null && $empresaId > 0) {
            $puntoventa_query = Puntoventa::query()
                ->where('empresa_id', $empresaId)
                ->where('estado', 'A')
                ->orderBy('codigo')
                ->get(['id', 'codigo', 'nombre']);
        }

        $listaprecio_query = Listaprecio::query()
            ->orderBy('id')
            ->get(['id', 'nombre']);

        $depositoModel = null;
        if ($data !== null && (int) $data->deposito_id > 0) {
            $depositoModel = Depmae::query()->find((int) $data->deposito_id);
        }
    }
}

<?php

namespace App\Http\Controllers\Ventas;

use App\Http\Controllers\Controller;
use App\Http\Requests\ValidacionMozoGastronomia;
use App\Models\Ventas\MozoGastronomia;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Repositories\Ventas\MozoGastronomiaRepositoryInterface;
use App\Services\Ventas\MozoGastronomiaAnitaSyncService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class MozoGastronomiaController extends Controller
{
    public function __construct(
        private MozoGastronomiaRepositoryInterface $repository,
        private MozoGastronomiaAnitaSyncService $mozoGastronomiaAnitaSyncService,
        private EmpresaRepositoryInterface $empresaRepository,
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

        return view('ventas.mozo_gastronomia.index', compact('datas', 'sinMozosCargados'));
    }

    public function crear()
    {
        can('crear-mozo-gastronomia');
        $data = new MozoGastronomia();
        $empresa_query = $this->empresaRepository->allFiltrado();
        $empresaIdSugerencia = $this->resolverEmpresaIdSugerenciaCodigo($empresa_query);

        $data->codigo = $this->repository->proximoCodigoLocal($empresaIdSugerencia);

        return view('ventas.mozo_gastronomia.crear', compact('data', 'empresa_query'));
    }

    public function guardar(ValidacionMozoGastronomia $request)
    {
        $data = $request->all();
        if (trim((string) ($data['codigo'] ?? '')) === '') {
            $empresaId = (int) ($data['empresa_id'] ?? 0);
            if ($empresaId <= 0) {
                return redirect()->back()->withInput()->withErrors([
                    'codigo' => 'Seleccione la empresa para calcular el próximo código.',
                ]);
            }
            $data['codigo'] = $this->repository->proximoCodigoLocal($empresaId);
        }

        $empresaId = (int) ($data['empresa_id'] ?? 0);
        $codigo = trim((string) ($data['codigo'] ?? ''));
        if ($this->repository->codigoExisteEnEmpresa($empresaId, $codigo)) {
            return redirect()->back()->withInput()->withErrors([
                'codigo' => 'El código '.$codigo.' ya está en uso para esta empresa en el ERP.',
            ]);
        }

        $this->repository->create($data);

        return redirect('ventas/mozo-gastronomia')->with('mensaje', 'Mozo creado con éxito');
    }

    public function proximoCodigo(Request $request)
    {
        can('crear-mozo-gastronomia');

        $empresaId = (int) $request->get('empresa_id');
        if ($empresaId <= 0 || ! $this->empresaRepository->empresaIdPermitida($empresaId)) {
            return response()->json(['error' => 'Empresa inválida.'], 422);
        }

        return response()->json([
            'codigo' => $this->repository->proximoCodigoLocal($empresaId),
        ]);
    }

    public function editar($id)
    {
        can('editar-mozo-gastronomia');
        $data = $this->repository->findOrFail($id);
        $empresa_query = $this->empresaRepository->allFiltrado();

        return view('ventas.mozo_gastronomia.editar', compact('data', 'empresa_query'));
    }

    public function actualizar(ValidacionMozoGastronomia $request, $id)
    {
        can('actualizar-mozo-gastronomia');

        $data = $request->all();
        $empresaId = (int) ($data['empresa_id'] ?? 0);
        $codigo = trim((string) ($data['codigo'] ?? ''));
        if ($codigo !== '' && $this->repository->codigoExisteEnEmpresa($empresaId, $codigo, (int) $id)) {
            return redirect()->back()->withInput()->withErrors([
                'codigo' => 'El código '.$codigo.' ya está en uso para esta empresa en el ERP.',
            ]);
        }

        $this->repository->update($data, $id);

        return redirect('ventas/mozo-gastronomia')->with('mensaje', 'Mozo actualizado con éxito');
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
    public function consultaMozo(Request $request)
    {
        if ($request->filled('empresa_id')) {
            can('usar-proceso-facturacion-gastronomia');
            $empresaId = (int) $request->get('empresa_id');

            return $this->repository->consultaMozo(
                (string) ($request->get('consulta') ?? ''),
                $empresaId,
                false,
            );
        }

        can('listar-mozo-gastronomia');

        return $this->repository->consultaMozo(
            (string) ($request->get('consulta') ?? ''),
            0,
            true,
        );
    }

    public function leeUnMozoPorCodigo(Request $request, string $codigo)
    {
        can('usar-proceso-facturacion-gastronomia');

        $empresaId = (int) ($request->get('empresa_id') ?: config('cliente.EMPRESA_DEFAULT_ID'));
        $mozo = $this->repository->findPorCodigo($codigo, $empresaId, false);

        if (! $mozo) {
            return response()->json(['error' => 'Mozo no encontrado'], 404);
        }

        return response()->json([
            'id' => $mozo->id,
            'codigo' => $mozo->codigo,
            'nombre' => $mozo->nombre,
        ]);
    }

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

    /**
     * @param  \Illuminate\Support\Collection<int, \App\Models\Configuracion\Empresa>  $empresaQuery
     */
    private function resolverEmpresaIdSugerenciaCodigo($empresaQuery): int
    {
        if ($empresaQuery->count() === 1) {
            return (int) $empresaQuery->first()->id;
        }

        return (int) config('mozo_gastronomia_anita.empresa_default_id', config('cliente.EMPRESA_DEFAULT_ID', 1));
    }
}

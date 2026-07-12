<?php

namespace App\Http\Controllers\Ventas;

use App\Exports\Ventas\ClienteVipGastronomiaListadoExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\ValidacionClienteVipGastronomia;
use App\Models\Ventas\ClienteVipGastronomia;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Repositories\Ventas\ClienteVipGastronomiaRepositoryInterface;
use App\Services\Ventas\ClienteVipGastronomiaAnitaSyncService;
use App\Support\Ventas\ClienteVipGastronomiaListadoFiltros;
use App\Support\Listado\QueryRetornoListado;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ClienteVipGastronomiaController extends Controller
{
    public function __construct(
        private ClienteVipGastronomiaRepositoryInterface $repository,
        private ClienteVipGastronomiaAnitaSyncService $anitaSyncService,
        private EmpresaRepositoryInterface $empresaRepository,
    ) {
    }

    public function index(Request $request)
    {
        can('listar-cliente-vip-gastronomia');

        if (config('app.anita_sync_cliente_vip_gastronomia_index')
            && ! $this->repository->existeRegistro()) {
            $this->anitaSyncService->sincronizarConAnita();
        }

        $filtros = ClienteVipGastronomiaListadoFiltros::resolverDesdeRequest($request);
        $filtros['empresas_asignadas'] = $this->empresaRepository->traeEmpresasAsignadas();
        $datas = $this->repository->leeClienteVip($filtros, true);
        $empresa_query = $this->empresaRepository->allFiltrado();
        $sinRegistros = $datas->total() === 0 && ! ClienteVipGastronomiaListadoFiltros::tieneCriteriosAplicados($filtros);

        return view('ventas.gastronomia.canjes.cliente_vip.index', [
            'datas' => $datas,
            'filtros' => $filtros,
            'filtrosQuery' => ClienteVipGastronomiaListadoFiltros::paraQueryString($filtros),
            'camposFiltro' => ClienteVipGastronomiaListadoFiltros::CAMPOS,
            'empresa_query' => $empresa_query,
            'sinRegistros' => $sinRegistros,
        ]);
    }

    public function listar(Request $request, $formato = null, $busqueda = null)
    {
        can('listar-cliente-vip-gastronomia');

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $filtros = ClienteVipGastronomiaListadoFiltros::resolverDesdeRequest($request, $busqueda);
        $filtros['empresas_asignadas'] = $this->empresaRepository->traeEmpresasAsignadas();

        switch ($formato) {
            case 'PDF':
                $datas = $this->repository->leeClienteVip($filtros, false);
                $view = \View::make('ventas.gastronomia.canjes.cliente_vip.listado', compact('datas'))->render();
                $path = storage_path('pdf/listados');
                $nombrePdf = 'listado_cliente_vip_gastronomia';
                $pdf = \App::make('dompdf.wrapper');
                $pdf->setPaper('legal', 'landscape');
                $pdf->loadHTML($view)->save($path.'/'.$nombrePdf.'.pdf');

                return response()->download($path.'/'.$nombrePdf.'.pdf');

            case 'EXCEL':
                return (new ClienteVipGastronomiaListadoExport($this->repository))
                    ->parametros($filtros)
                    ->download('clientes_vip_gastronomia.xlsx');

            case 'CSV':
                return (new ClienteVipGastronomiaListadoExport($this->repository))
                    ->parametros($filtros)
                    ->download('clientes_vip_gastronomia.csv', \Maatwebsite\Excel\Excel::CSV);
        }

        return redirect()->route('consultar_cliente_vip_gastronomia', ClienteVipGastronomiaListadoFiltros::paraQueryString($filtros));
    }

    public function crear(Request $request)
    {
        can('crear-cliente-vip-gastronomia');
        $data = new ClienteVipGastronomia();
        $empresa_query = $this->empresaRepository->allFiltrado();
        $filtrosQuery = QueryRetornoListado::desdeRequest($request, ClienteVipGastronomiaListadoFiltros::class);

        return view('ventas.gastronomia.canjes.cliente_vip.crear', compact('data', 'empresa_query', 'filtrosQuery'));
    }

    public function guardar(ValidacionClienteVipGastronomia $request)
    {
        $this->repository->create($request->all());

        return redirect()->route('consultar_cliente_vip_gastronomia', QueryRetornoListado::desdeRequest($request, ClienteVipGastronomiaListadoFiltros::class))
            ->with('mensaje', 'Cliente VIP creado con éxito');
    }

    public function editar(Request $request, $id)
    {
        can('editar-cliente-vip-gastronomia');
        $data = $this->repository->findOrFail($id);
        $empresa_query = $this->empresaRepository->allFiltrado();
        $filtrosQuery = QueryRetornoListado::desdeRequest($request, ClienteVipGastronomiaListadoFiltros::class);

        return view('ventas.gastronomia.canjes.cliente_vip.editar', compact('data', 'empresa_query', 'filtrosQuery'));
    }

    public function actualizar(ValidacionClienteVipGastronomia $request, $id)
    {
        can('actualizar-cliente-vip-gastronomia');
        $this->repository->update($request->all(), $id);

        return redirect()->route('consultar_cliente_vip_gastronomia', QueryRetornoListado::desdeRequest($request, ClienteVipGastronomiaListadoFiltros::class))
            ->with('mensaje', 'Cliente VIP actualizado con éxito');
    }

    public function eliminar(Request $request, $id)
    {
        can('borrar-cliente-vip-gastronomia');

        if ($request->ajax()) {
            if ($this->repository->delete($id)) {
                return response()->json(['mensaje' => 'ok']);
            }

            return response()->json(['mensaje' => 'ng']);
        }

        abort(404);
    }

    /**
     * Importación masiva desde Anita (base_admin.clivipg).
     * Si hay timeout (504), usar: php artisan cliente-vip-gastronomia:sincronizar-anita
     */
    public function sincronizarDesdeAnita(Request $request)
    {
        can('actualizar-cliente-vip-gastronomia');

        if (! config('app.anita_sync_cliente_vip_gastronomia_index')) {
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
            if (! empty($ret['errores'])) {
                $msg .= ' '.implode(' ', array_slice($ret['errores'], 0, 5));
            }

            return redirect()->route('consultar_cliente_vip_gastronomia')->with('mensaje', $msg);
        } catch (\Throwable $e) {
            Log::warning('ClienteVipGastronomia sincronizarDesdeAnita: '.$e->getMessage(), ['exception' => $e]);

            return redirect()->route('consultar_cliente_vip_gastronomia')->with('errores', [
                'No se completó la sincronización desde Anita. Si el error fue por tiempo de espera (504), ejecute en el servidor: php artisan cliente-vip-gastronomia:sincronizar-anita — Detalle: '.$e->getMessage(),
            ]);
        }
    }
}

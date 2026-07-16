<?php

namespace App\Http\Controllers\Caja;

use App\Exports\Caja\ClienteVipCajaListadoExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\ValidacionClienteVipCaja;
use App\Models\Caja\ClienteVipCaja;
use App\Repositories\Caja\ClienteVipCajaRepositoryInterface;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Services\Caja\ClienteVipCajaAnitaSyncService;
use App\Support\Caja\ClienteVipCajaListadoFiltros;
use App\Support\Listado\QueryRetornoListado;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ClienteVipCajaController extends Controller
{
    public function __construct(
        private ClienteVipCajaRepositoryInterface $repository,
        private ClienteVipCajaAnitaSyncService $anitaSyncService,
        private EmpresaRepositoryInterface $empresaRepository,
    ) {
    }

    public function index(Request $request)
    {
        can('listar-cliente-vip-caja');

        if (config('app.anita_sync_cliente_vip_caja_index')
            && ! $this->repository->existeRegistro()) {
            $this->anitaSyncService->sincronizarConAnita();
        }

        $filtros = ClienteVipCajaListadoFiltros::resolverDesdeRequest($request);
        $filtros['empresas_asignadas'] = $this->empresaRepository->traeEmpresasAsignadas();
        $datas = $this->repository->leeClienteVip($filtros, true);
        $empresa_query = $this->empresaRepository->allFiltrado();
        $sinRegistros = $datas->total() === 0 && ! ClienteVipCajaListadoFiltros::tieneCriteriosAplicados($filtros);

        return view('caja.cliente_vip.index', [
            'datas' => $datas,
            'filtros' => $filtros,
            'filtrosQuery' => ClienteVipCajaListadoFiltros::paraQueryString($filtros),
            'camposFiltro' => ClienteVipCajaListadoFiltros::CAMPOS,
            'empresa_query' => $empresa_query,
            'sinRegistros' => $sinRegistros,
        ]);
    }

    public function listar(Request $request, $formato = null, $busqueda = null)
    {
        can('listar-cliente-vip-caja');

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $filtros = ClienteVipCajaListadoFiltros::resolverDesdeRequest($request, $busqueda);
        $filtros['empresas_asignadas'] = $this->empresaRepository->traeEmpresasAsignadas();

        switch ($formato) {
            case 'PDF':
                $datas = $this->repository->leeClienteVip($filtros, false);
                $view = \View::make('caja.cliente_vip.listado', compact('datas'))->render();
                $path = storage_path('pdf/listados');
                $nombrePdf = 'listado_cliente_vip_caja';
                $pdf = \App::make('dompdf.wrapper');
                $pdf->setPaper('legal', 'landscape');
                $pdf->loadHTML($view)->save($path.'/'.$nombrePdf.'.pdf');

                return response()->download($path.'/'.$nombrePdf.'.pdf');

            case 'EXCEL':
                return (new ClienteVipCajaListadoExport($this->repository))
                    ->parametros($filtros)
                    ->download('clientes_vip_caja.xlsx');

            case 'CSV':
                return (new ClienteVipCajaListadoExport($this->repository))
                    ->parametros($filtros)
                    ->download('clientes_vip_caja.csv', \Maatwebsite\Excel\Excel::CSV);
        }

        return redirect()->route('consultar_cliente_vip_caja', ClienteVipCajaListadoFiltros::paraQueryString($filtros));
    }

    public function crear(Request $request)
    {
        can('crear-cliente-vip-caja');
        $data = new ClienteVipCaja();
        $empresa_query = $this->empresaRepository->allFiltrado();
        $filtrosQuery = QueryRetornoListado::desdeRequest($request, ClienteVipCajaListadoFiltros::class);

        return view('caja.cliente_vip.crear', compact('data', 'empresa_query', 'filtrosQuery'));
    }

    public function guardar(ValidacionClienteVipCaja $request)
    {
        $this->repository->create($request->all());

        return redirect()->route('consultar_cliente_vip_caja', QueryRetornoListado::desdeRequest($request, ClienteVipCajaListadoFiltros::class))
            ->with('mensaje', 'Cliente VIP creado con éxito');
    }

    public function editar(Request $request, $id)
    {
        can('editar-cliente-vip-caja');
        $data = $this->repository->findOrFail($id);
        $empresa_query = $this->empresaRepository->allFiltrado();
        $filtrosQuery = QueryRetornoListado::desdeRequest($request, ClienteVipCajaListadoFiltros::class);

        return view('caja.cliente_vip.editar', compact('data', 'empresa_query', 'filtrosQuery'));
    }

    public function actualizar(ValidacionClienteVipCaja $request, $id)
    {
        can('actualizar-cliente-vip-caja');
        $this->repository->update($request->all(), $id);

        return redirect()->route('consultar_cliente_vip_caja', QueryRetornoListado::desdeRequest($request, ClienteVipCajaListadoFiltros::class))
            ->with('mensaje', 'Cliente VIP actualizado con éxito');
    }

    public function eliminar(Request $request, $id)
    {
        can('borrar-cliente-vip-caja');

        if ($request->ajax()) {
            if ($this->repository->delete($id)) {
                return response()->json(['mensaje' => 'ok']);
            }

            return response()->json(['mensaje' => 'ng']);
        }

        abort(404);
    }

    /**
     * Importación masiva desde Anita (base_admin.clivip). Solo lectura Anita.
     * Si hay timeout (504), usar: php artisan cliente-vip-caja:sincronizar-anita
     */
    public function sincronizarDesdeAnita(Request $request)
    {
        can('actualizar-cliente-vip-caja');

        if (! config('app.anita_sync_cliente_vip_caja_index')) {
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

            return redirect()->route('consultar_cliente_vip_caja')->with('mensaje', $msg);
        } catch (\Throwable $e) {
            Log::warning('ClienteVipCaja sincronizarDesdeAnita: '.$e->getMessage(), ['exception' => $e]);

            return redirect()->route('consultar_cliente_vip_caja')->with('errores', [
                'No se completó la sincronización desde Anita. Si el error fue por tiempo de espera (504), ejecute en el servidor: php artisan cliente-vip-caja:sincronizar-anita — Detalle: '.$e->getMessage(),
            ]);
        }
    }
}

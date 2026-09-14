<?php

namespace App\Http\Controllers\Ventas\FacturacionLocal;

use App\Exports\Ventas\LocalVentaListadoExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\ValidacionLocalVenta;
use App\Models\Caja\Cuentacaja;
use App\Models\Contable\Cuentacontable;
use App\Models\Stock\Depmae;
use App\Models\Stock\Listaprecio;
use App\Models\Ventas\LocalVenta;
use App\Models\Ventas\Puntoventa;
use App\Models\Ventas\Tipotransaccion;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Services\Ventas\FacturacionLocal\DepmaeLocalAnitaSyncService;
use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\Ventas\FacturacionLocal\FacturacionLocalUsoCuentacajaSupport;
use App\Support\Ventas\LocalVentaListadoFiltros;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;

class LocalVentaController extends Controller
{
    public function __construct(
        private readonly EmpresaRepositoryInterface $empresaRepository,
    ) {
    }

    public function index(Request $request)
    {
        $this->assertFerli();
        can('listar-local-venta');

        $filtros = LocalVentaListadoFiltros::resolverDesdeRequest($request);
        $datas = $this->leeLocales($filtros, true);

        return view('ventas.facturacion_local.local_venta.index', [
            'datas' => $datas,
            'filtros' => $filtros,
            'filtrosQuery' => LocalVentaListadoFiltros::paraQueryString($filtros),
            'camposFiltro' => LocalVentaListadoFiltros::CAMPOS,
        ]);
    }

    public function listar(Request $request, $formato = null, $busqueda = null)
    {
        $this->assertFerli();
        can('listar-local-venta');

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $filtros = LocalVentaListadoFiltros::resolverDesdeRequest($request, $busqueda);

        switch ($formato) {
            case 'PDF':
                $datas = $this->leeLocales($filtros, false);
                $view = View::make('ventas.facturacion_local.local_venta.listado', compact('datas'))->render();
                $path = storage_path('pdf/listados');
                if (! is_dir($path)) {
                    mkdir($path, 0755, true);
                }
                $nombrePdf = 'listado_local_venta';
                $pdf = \App::make('dompdf.wrapper');
                $pdf->setPaper('legal', 'landscape');
                $pdf->loadHTML($view)->save($path.'/'.$nombrePdf.'.pdf');

                return response()->download($path.'/'.$nombrePdf.'.pdf');

            case 'EXCEL':
                return (new LocalVentaListadoExport())
                    ->parametros($filtros)
                    ->download('local_venta.xlsx');

            case 'CSV':
                return (new LocalVentaListadoExport())
                    ->parametros($filtros)
                    ->download('local_venta.csv', \Maatwebsite\Excel\Excel::CSV);
        }

        return redirect()->route('facturacion_local_locales', LocalVentaListadoFiltros::paraQueryString($filtros));
    }

    public function crear()
    {
        $this->assertFerli();
        can('crear-local-venta');
        $data = new LocalVenta([
            'activo' => true,
            'anita_servidor' => config('facturacion_local.anita_servidor_default'),
            'anita_ifx_server' => config('facturacion_local.anita_ifx_server_default'),
            'tipotransaccion_fac_id' => config('facturacion_local.tipotransaccion_fac_id'),
            'tipotransaccion_nc_id' => config('facturacion_local.tipotransaccion_nc_id'),
            'tipotransaccion_caja_id' => config('facturacion_local.tipotransaccion_caja_id'),
            'tipotransaccion_caja_devolucion_id' => config('facturacion_local.tipotransaccion_caja_devolucion_id'),
        ]);

        return view('ventas.facturacion_local.local_venta.crear', $this->formData($data));
    }

    public function guardar(ValidacionLocalVenta $request)
    {
        $this->assertFerli();
        can('crear-local-venta');
        $payload = $this->payloadPersistencia($request);
        $data = LocalVenta::query()->create($payload['atributos']);
        $this->syncPuntoventas($data, $payload['puntoventa_ids'], $payload['puntoventa_id']);
        $this->syncCuentas($data, $payload['cuentacaja_ids']);

        return redirect()->route('facturacion_local_locales')->with('mensaje', 'Local creado con éxito');
    }

    public function editar($id)
    {
        $this->assertFerli();
        can('editar-local-venta');
        $data = LocalVenta::query()
            ->with(['cuentacajas', 'puntoventas', 'deposito', 'listaprecio', 'cuentacajaEfectivo', 'cuentacontableVenta', 'tipotransaccionFac', 'tipotransaccionNc'])
            ->findOrFail($id);

        return view('ventas.facturacion_local.local_venta.editar', $this->formData($data));
    }

    public function syncDepositosAnita(Request $request, DepmaeLocalAnitaSyncService $sync, $id = null)
    {
        $this->assertFerli();
        if (! can('editar-local-venta', false) && ! can('crear-local-venta', false) && ! can('actualizar-local-venta', false)) {
            abort(403);
        }

        $local = null;
        if ($id !== null && (int) $id > 0) {
            $local = LocalVenta::query()->findOrFail((int) $id);
        } else {
            $empresaId = (int) $request->input('empresa_id', 0);
            $codigo = trim((string) $request->input('codigo', 'TMP'));
            if ($empresaId <= 0) {
                return response()->json(['ok' => false, 'error' => 'Seleccione empresa antes de importar depósitos.'], 422);
            }
            $local = new LocalVenta([
                'codigo' => $codigo !== '' ? $codigo : 'TMP',
                'empresa_id' => $empresaId,
                'anita_servidor' => $request->input('anita_servidor') ?: config('facturacion_local.anita_servidor_default'),
                'anita_ifx_server' => $request->input('anita_ifx_server') ?: config('facturacion_local.anita_ifx_server_default'),
            ]);
        }

        // Permitir override del bridge desde el form (aún no guardado).
        if ($request->filled('anita_servidor')) {
            $local->anita_servidor = trim((string) $request->input('anita_servidor'));
        }
        if ($request->filled('anita_ifx_server')) {
            $local->anita_ifx_server = trim((string) $request->input('anita_ifx_server'));
        }
        if ($request->filled('empresa_id') && (int) $request->input('empresa_id') > 0) {
            $local->empresa_id = (int) $request->input('empresa_id');
        }

        try {
            $ret = $sync->sincronizarDesdeLocal($local);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 422);
        }

        return response()->json(['ok' => true] + $ret);
    }

    public function actualizar(ValidacionLocalVenta $request, $id)
    {
        $this->assertFerli();
        can('actualizar-local-venta');
        $data = LocalVenta::query()->findOrFail($id);
        $payload = $this->payloadPersistencia($request);
        $data->update($payload['atributos']);
        $this->syncPuntoventas($data, $payload['puntoventa_ids'], $payload['puntoventa_id']);
        $this->syncCuentas($data, $payload['cuentacaja_ids']);

        return redirect()->route('facturacion_local_locales')->with('mensaje', 'Local actualizado con éxito');
    }

    public function eliminar(Request $request, $id)
    {
        $this->assertFerli();
        can('borrar-local-venta');
        if (! $request->ajax()) {
            abort(404);
        }
        $data = LocalVenta::query()->findOrFail($id);
        if ($data->turnos()->where('estado', 'abierto')->exists()) {
            return response()->json(['mensaje' => 'ng', 'error' => 'Hay un turno abierto.']);
        }
        $data->cuentacajas()->detach();
        $data->puntoventas()->detach();
        $data->delete();

        return response()->json(['mensaje' => 'ok']);
    }

    /**
     * @return array{atributos: array<string, mixed>, puntoventa_ids: list<int>, puntoventa_id: ?int, cuentacaja_ids: list<int>}
     */
    private function payloadPersistencia(ValidacionLocalVenta $request): array
    {
        $validated = $request->validated();
        $pvIds = array_values(array_map('intval', $validated['puntoventa_ids'] ?? []));
        $cuentaIds = array_values(array_map('intval', $validated['cuentacaja_ids'] ?? []));
        $defaultPv = isset($validated['puntoventa_id']) ? (int) $validated['puntoventa_id'] : 0;
        if ($defaultPv <= 0 || ! in_array($defaultPv, $pvIds, true)) {
            $defaultPv = $pvIds[0] ?? 0;
        }
        unset($validated['puntoventa_ids'], $validated['cuentacaja_ids']);
        $validated['puntoventa_id'] = $defaultPv > 0 ? $defaultPv : null;

        return [
            'atributos' => $validated,
            'puntoventa_ids' => $pvIds,
            'puntoventa_id' => $defaultPv > 0 ? $defaultPv : null,
            'cuentacaja_ids' => $cuentaIds,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(LocalVenta $data): array
    {
        $empresa_query = $this->empresaRepository->allFiltrado();
        $usocuentacaja_local_id = FacturacionLocalUsoCuentacajaSupport::resolverId() ?? 0;

        $depositoModel = null;
        if ((int) old('deposito_id', $data->deposito_id ?? 0) > 0) {
            $depositoModel = $data->deposito
                ?? Depmae::query()->find((int) old('deposito_id', $data->deposito_id));
        }

        $listaprecioModel = null;
        if ((int) old('listaprecio_id', $data->listaprecio_id ?? 0) > 0) {
            $listaprecioModel = $data->listaprecio
                ?? Listaprecio::query()->find((int) old('listaprecio_id', $data->listaprecio_id));
        }

        $cuentacajaEfectivoModel = null;
        if ((int) old('cuentacaja_efectivo_id', $data->cuentacaja_efectivo_id ?? 0) > 0) {
            $cuentacajaEfectivoModel = $data->cuentacajaEfectivo
                ?? Cuentacaja::query()->find((int) old('cuentacaja_efectivo_id', $data->cuentacaja_efectivo_id));
        }

        $cuentacontableVentaModel = null;
        if ((int) old('cuentacontable_venta_id', $data->cuentacontable_venta_id ?? 0) > 0) {
            $cuentacontableVentaModel = $data->relationLoaded('cuentacontableVenta')
                ? $data->cuentacontableVenta
                : Cuentacontable::query()->find((int) old('cuentacontable_venta_id', $data->cuentacontable_venta_id));
        }

        $tipoFac = null;
        if ((int) old('tipotransaccion_fac_id', $data->tipotransaccion_fac_id ?? 0) > 0) {
            $tipoFac = $data->tipotransaccionFac
                ?? Tipotransaccion::query()->find((int) old('tipotransaccion_fac_id', $data->tipotransaccion_fac_id));
        }
        $tipoNc = null;
        if ((int) old('tipotransaccion_nc_id', $data->tipotransaccion_nc_id ?? 0) > 0) {
            $tipoNc = $data->tipotransaccionNc
                ?? Tipotransaccion::query()->find((int) old('tipotransaccion_nc_id', $data->tipotransaccion_nc_id));
        }

        $puntoventasSeleccionados = [];
        $oldPvIds = old('puntoventa_ids');
        if (is_array($oldPvIds) && $oldPvIds !== []) {
            $defaultOld = (int) old('puntoventa_id', 0);
            foreach ($oldPvIds as $i => $pvId) {
                $pvId = (int) $pvId;
                if ($pvId <= 0) {
                    continue;
                }
                $pv = Puntoventa::query()->find($pvId);
                if (! $pv) {
                    continue;
                }
                $puntoventasSeleccionados[] = [
                    'id' => $pv->id,
                    'codigo' => $pv->codigo,
                    'nombre' => $pv->nombre,
                    'es_default' => $defaultOld > 0 ? $defaultOld === (int) $pv->id : $i === 0,
                ];
            }
        } elseif ($data->exists) {
            $pvs = $data->relationLoaded('puntoventas')
                ? $data->puntoventas
                : $data->puntoventas()->get();
            if ($pvs->isEmpty() && $data->puntoventa_id) {
                $pv = $data->puntoventa ?? Puntoventa::query()->find($data->puntoventa_id);
                if ($pv) {
                    $puntoventasSeleccionados[] = [
                        'id' => $pv->id,
                        'codigo' => $pv->codigo,
                        'nombre' => $pv->nombre,
                        'es_default' => true,
                    ];
                }
            } else {
                foreach ($pvs as $pv) {
                    $puntoventasSeleccionados[] = [
                        'id' => $pv->id,
                        'codigo' => $pv->codigo,
                        'nombre' => $pv->nombre,
                        'es_default' => (bool) ($pv->pivot->es_default ?? false),
                    ];
                }
            }
        }
        if ($puntoventasSeleccionados === []) {
            $puntoventasSeleccionados[] = ['id' => '', 'codigo' => '', 'nombre' => '', 'es_default' => true];
        }

        $cuentasSeleccionadas = [];
        $oldCcIds = old('cuentacaja_ids');
        if (is_array($oldCcIds)) {
            foreach ($oldCcIds as $ccId) {
                $ccId = (int) $ccId;
                if ($ccId <= 0) {
                    continue;
                }
                $cc = Cuentacaja::query()->find($ccId);
                if ($cc) {
                    $cuentasSeleccionadas[] = [
                        'id' => $cc->id,
                        'codigo' => $cc->codigo,
                        'nombre' => $cc->nombre,
                    ];
                }
            }
        } elseif ($data->exists) {
            foreach ($data->cuentacajas as $cc) {
                $cuentasSeleccionadas[] = [
                    'id' => $cc->id,
                    'codigo' => $cc->codigo,
                    'nombre' => $cc->nombre,
                ];
            }
        }
        if ($cuentasSeleccionadas === []) {
            $cuentasSeleccionadas[] = ['id' => '', 'codigo' => '', 'nombre' => ''];
        }

        return compact(
            'data',
            'empresa_query',
            'depositoModel',
            'listaprecioModel',
            'cuentacajaEfectivoModel',
            'cuentacontableVentaModel',
            'tipoFac',
            'tipoNc',
            'puntoventasSeleccionados',
            'cuentasSeleccionadas',
            'usocuentacaja_local_id'
        );
    }

    /**
     * @param  list<int>  $ids
     */
    private function syncPuntoventas(LocalVenta $local, array $ids, ?int $defaultId): void
    {
        $sync = [];
        $orden = 0;
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), fn ($id) => $id > 0)));
        if ($defaultId === null || $defaultId <= 0 || ! in_array($defaultId, $ids, true)) {
            $defaultId = $ids[0] ?? null;
        }
        foreach ($ids as $id) {
            $sync[$id] = [
                'orden' => $orden++,
                'es_default' => $id === $defaultId,
            ];
        }
        $local->puntoventas()->sync($sync);
        $local->puntoventa_id = $defaultId;
        $local->save();
    }

    /**
     * @param  list<int|string>  $ids
     */
    private function syncCuentas(LocalVenta $local, array $ids): void
    {
        $sync = [];
        $orden = 0;
        foreach ($ids as $id) {
            $cid = (int) $id;
            if ($cid <= 0) {
                continue;
            }
            $orden++;
            $sync[$cid] = ['orden' => $orden - 1, 'es_default' => $orden === 1, 'medio' => null];
        }
        $local->cuentacajas()->sync($sync);
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator|\Illuminate\Support\Collection
     */
    public function leeLocales(array $filtros, bool $paginar = true)
    {
        $query = LocalVenta::query()
            ->select('local_venta.*')
            ->leftJoin('empresa', 'empresa.id', '=', 'local_venta.empresa_id')
            ->leftJoin('puntoventa', 'puntoventa.id', '=', 'local_venta.puntoventa_id')
            ->leftJoin('depmae', 'depmae.id', '=', 'local_venta.deposito_id')
            ->leftJoin('listaprecio', 'listaprecio.id', '=', 'local_venta.listaprecio_id')
            ->with([
                'puntoventa:id,codigo,nombre',
                'puntoventas:id,codigo,nombre',
                'deposito:id,codigo,nombre',
                'listaprecio:id,codigo,nombre',
                'empresa:id,nombre',
            ])
            ->orderBy('local_venta.codigo');

        if (LocalVentaListadoFiltros::tieneCriteriosAplicados($filtros)) {
            LocalVentaListadoFiltros::aplicar($query, $filtros);
        }

        return $paginar ? $query->paginate(15) : $query->get();
    }

    private function assertFerli(): void
    {
        if (! EntornoEmpresaSupport::esFerli()) {
            abort(404);
        }
    }
}

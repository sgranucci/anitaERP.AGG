<?php

namespace App\Http\Controllers\Seguridad;

use App\Exports\Seguridad\IngresoProveedorListadoExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\ValidacionIngresoProveedor;
use App\Models\Seguridad\IngresoProveedor;
use App\Models\Seguridad\IngresoProveedorArea;
use App\Models\Seguridad\IngresoProveedorMotivo;
use App\Models\Seguridad\IngresoProveedorPunto;
use App\Models\Seguridad\IngresoProveedorSector;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Repositories\Seguridad\IngresoProveedorRepositoryInterface;
use App\Support\Listado\QueryRetornoListado;
use App\Support\Seguridad\IngresoProveedorEstados;
use App\Support\Seguridad\IngresoProveedorListadoFiltros;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class IngresoProveedorController extends Controller
{
    public function __construct(
        private readonly IngresoProveedorRepositoryInterface $repository,
        private readonly EmpresaRepositoryInterface $empresaRepository,
    ) {
    }

    public function index(Request $request)
    {
        can('listar-ingreso-proveedor');
        $filtros = $this->resolverFiltros($request);
        $datas = $this->repository->leeIngresoProveedor($filtros, true);

        return view('seguridad.ingreso_proveedor.index', [
            'datas' => $datas,
            'filtros' => $filtros,
            'filtrosQuery' => IngresoProveedorListadoFiltros::paraQueryString($filtros),
            'camposFiltro' => IngresoProveedorListadoFiltros::CAMPOS,
            'empresa_query' => $this->empresaRepository->allFiltrado(),
        ]);
    }

    public function listar(Request $request, $formato = null, $busqueda = null)
    {
        can('listar-ingreso-proveedor');
        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $filtros = $this->resolverFiltros($request, $busqueda);
        $filtrosQuery = IngresoProveedorListadoFiltros::paraQueryString($filtros);

        switch ($formato) {
            case 'PDF':
                $datas = $this->repository->leeIngresoProveedor($filtros, false);
                $view = \View::make('seguridad.ingreso_proveedor.listado', compact('datas'))->render();
                $path = storage_path('pdf/listados');
                if (! is_dir($path)) {
                    mkdir($path, 0755, true);
                }
                $pdf = \PDF::loadHTML($view)->setPaper('legal', 'landscape');
                $pdf->save($path.'/listado_ingreso_proveedor.pdf');

                return $pdf->download('listado_ingreso_proveedor.pdf');
            case 'EXCEL':
                return Excel::download(
                    (new IngresoProveedorListadoExport($this->repository))->parametros($filtros),
                    'listado_ingreso_proveedor.xlsx'
                );
            case 'CSV':
                return Excel::download(
                    (new IngresoProveedorListadoExport($this->repository))->parametros($filtros),
                    'listado_ingreso_proveedor.csv',
                    \Maatwebsite\Excel\Excel::CSV
                );
            default:
                return redirect()->route('ingreso_proveedor', $filtrosQuery);
        }
    }

    public function crear(Request $request)
    {
        can('crear-ingreso-proveedor');

        return view('seguridad.ingreso_proveedor.crear', $this->datosFormulario(null, $request));
    }

    public function guardar(ValidacionIngresoProveedor $request)
    {
        can('crear-ingreso-proveedor');
        $ticket = $this->repository->create($request->all());

        if ($request->input('origen') === 'modal_consulta') {
            return redirect()->route('editar_ingreso_proveedor', [
                'id' => $ticket->id,
                'origen' => 'modal_consulta',
                'vista' => 'consulta',
            ])->with('mensaje', 'Ticket de ingreso creado con éxito');
        }

        return redirect()->route('ingreso_proveedor')->with('mensaje', 'Ticket de ingreso creado con éxito');
    }

    public function editar($id)
    {
        can('editar-ingreso-proveedor');
        $data = $this->repository->findOrFail((int) $id);

        return view('seguridad.ingreso_proveedor.editar', array_merge(
            $this->datosFormulario($data, request()),
            ['data' => $data]
        ));
    }

    public function actualizar(ValidacionIngresoProveedor $request, $id)
    {
        can('actualizar-ingreso-proveedor');
        $this->repository->update($request->all(), (int) $id);

        if ($request->input('origen') === 'modal_consulta') {
            return redirect()->route('editar_ingreso_proveedor', [
                'id' => (int) $id,
                'origen' => 'modal_consulta',
                'vista' => 'consulta',
            ])->with('mensaje', 'Ticket de ingreso actualizado con éxito');
        }

        return redirect()->route('ingreso_proveedor')->with('mensaje', 'Ticket de ingreso actualizado con éxito');
    }

    public function eliminar($id)
    {
        can('borrar-ingreso-proveedor');
        $this->repository->delete((int) $id);

        return redirect()->route('ingreso_proveedor')->with('mensaje', 'Ticket de ingreso eliminado');
    }

    /**
     * @return array<string, mixed>
     */
    private function datosFormulario(?IngresoProveedor $data = null, ?Request $request = null): array
    {
        $request = $request ?? request();
        $filtros = $this->resolverFiltros($request);
        $prefill = $this->prefillDesdeOrigen($request, $data);

        $soloConsulta = $request->query('origen') === 'modal_consulta'
            || $request->input('origen') === 'modal_consulta';

        return [
            'empresa_query' => $this->empresaRepository->allFiltrado(),
            'motivos' => IngresoProveedorMotivo::query()->where('activo', true)->orderBy('nombre')->get(),
            'puntos' => IngresoProveedorPunto::query()->where('activo', true)->orderBy('nombre')->get(),
            'areas' => IngresoProveedorArea::query()->where('activo', true)->orderBy('nombre')->get(),
            'sectores' => IngresoProveedorSector::query()->where('activo', true)->orderBy('nombre')->get(),
            'estados' => IngresoProveedorEstados::META,
            'filtrosQuery' => QueryRetornoListado::retornoLinksDesdeFiltrosQuery(
                IngresoProveedorListadoFiltros::paraQueryString($filtros)
            ),
            'prefill' => $prefill,
            'soloConsulta' => $soloConsulta,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function resolverFiltros(Request $request, ?string $busquedaRuta = null): array
    {
        $empresaDefault = optional($this->empresaRepository->allFiltrado()->first())->id;

        return IngresoProveedorListadoFiltros::resolverDesdeRequest(
            $request,
            $busquedaRuta,
            $empresaDefault ? (int) $empresaDefault : null
        );
    }

    /**
     * @return array{empresa_id:?int,proveedor_id:?int,ordencompra_id:?int,proveedor:?object}
     */
    private function prefillDesdeOrigen(Request $request, ?IngresoProveedor $data): array
    {
        if ($data) {
            return [
                'empresa_id' => $data->empresa_id,
                'proveedor_id' => $data->proveedor_id,
                'ordencompra_id' => $data->ordencompra_id,
                'proveedor' => $data->proveedores,
            ];
        }

        $proveedorId = (int) $request->input('proveedor_id', 0);
        $proveedor = $proveedorId > 0
            ? \App\Models\Compras\Proveedor::query()->find($proveedorId)
            : null;

        return [
            'empresa_id' => (int) $request->input('empresa_id', 0) ?: null,
            'proveedor_id' => $proveedorId ?: null,
            'ordencompra_id' => (int) $request->input('ordencompra_id', 0) ?: null,
            'proveedor' => $proveedor,
        ];
    }
}

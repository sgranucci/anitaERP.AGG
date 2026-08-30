<?php

declare(strict_types=1);

namespace App\Http\Controllers\Ventas;

use App\Exports\Ventas\ConceptoVentaListadoExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\ValidacionConcepto_Venta;
use App\Models\Stock\Unidadmedida;
use App\Models\Ventas\Concepto_Venta;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Repositories\Configuracion\ImpuestoRepositoryInterface;
use App\Repositories\Ventas\Concepto_VentaRepositoryInterface;
use App\Repositories\Ventas\TipotransaccionRepositoryInterface;
use App\Support\Ventas\ConceptoVentaMostradorSupport;
use App\Support\Listado\QueryRetornoListado;
use App\Support\Ventas\ConceptoVentaListadoFiltros;
use App\Support\Ventas\ConceptoVentaUsoSupport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class ConceptoVentaController extends Controller
{
    public function __construct(
        private readonly Concepto_VentaRepositoryInterface $repository,
        private readonly EmpresaRepositoryInterface $empresaRepository,
        private readonly ImpuestoRepositoryInterface $impuestoRepository,
        private readonly TipotransaccionRepositoryInterface $tipotransaccionRepository,
    ) {}

    public function index(Request $request)
    {
        can('listar-conceptos-venta');

        $filtros = ConceptoVentaListadoFiltros::resolverDesdeRequest($request);
        $datas = $this->repository->leeConceptoVenta($filtros, true);

        return view('ventas.concepto_venta.index', [
            'datas' => $datas,
            'filtros' => $filtros,
            'filtrosQuery' => ConceptoVentaListadoFiltros::paraQueryString($filtros),
            'camposFiltro' => ConceptoVentaListadoFiltros::CAMPOS,
        ]);
    }

    public function listar(Request $request, $formato = null, $busqueda = null)
    {
        can('listar-conceptos-venta');

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $filtros = ConceptoVentaListadoFiltros::resolverDesdeRequest($request, $busqueda);

        switch ($formato) {
            case 'PDF':
                $datas = $this->repository->leeConceptoVenta($filtros, false);
                $view = \View::make('ventas.concepto_venta.listado', compact('datas'))->render();
                $path = storage_path('pdf/listados');
                $nombrePdf = 'listado_concepto_venta';

                if (! is_dir($path)) {
                    @mkdir($path, 0775, true);
                }

                $pdf = \App::make('dompdf.wrapper');
                $pdf->setPaper('legal', 'landscape');
                $pdf->loadHTML($view)->save($path.'/'.$nombrePdf.'.pdf');

                return response()->download($path.'/'.$nombrePdf.'.pdf');

            case 'EXCEL':
                return (new ConceptoVentaListadoExport($this->repository))
                    ->parametros($filtros)
                    ->download('conceptos_venta.xlsx');

            case 'CSV':
                return (new ConceptoVentaListadoExport($this->repository))
                    ->parametros($filtros)
                    ->download('conceptos_venta.csv', \Maatwebsite\Excel\Excel::CSV);
        }

        return redirect()->route('concepto_venta', ConceptoVentaListadoFiltros::paraQueryString($filtros));
    }

    public function crear(Request $request)
    {
        can('crear-conceptos-venta');
        $data = new Concepto_Venta(['activo' => true, 'unidades_mtx' => 1]);

        return view('ventas.concepto_venta.crear', $this->datosFormulario($request, $data));
    }

    public function guardar(ValidacionConcepto_Venta $request)
    {
        can('crear-conceptos-venta');
        $concepto = $this->repository->create($request->validated());
        $this->sincronizarHijos($request, (int) $concepto->id);

        return redirect()->route('concepto_venta', QueryRetornoListado::desdeRequest($request, ConceptoVentaListadoFiltros::class))
            ->with('mensaje', 'Concepto de venta creado con éxito');
    }

    public function editar(Request $request, $id)
    {
        $soloConsulta = $request->query('origen') === 'modal_consulta';
        if ($soloConsulta) {
            if (! can('listar-conceptos-venta', false) && ! can('editar-conceptos-venta', false)) {
                abort(403, 'No tiene permiso para consultar el concepto.');
            }
        } else {
            can('editar-conceptos-venta');
        }

        $data = $this->repository->findOrFail($id);

        return view('ventas.concepto_venta.editar', array_merge(
            $this->datosFormulario($request, $data),
            [
                'solo_consulta' => $soloConsulta,
                'puede_actualizar' => can('actualizar-conceptos-venta', false),
            ]
        ));
    }

    public function actualizar(ValidacionConcepto_Venta $request, $id)
    {
        can('actualizar-conceptos-venta');
        try {
            $this->repository->update($request->validated(), $id);
            $this->sincronizarHijos($request, (int) $id);
        } catch (RuntimeException $e) {
            return back()->withErrors(['activo' => $e->getMessage()])->withInput();
        }

        return redirect()->route('concepto_venta', QueryRetornoListado::desdeRequest($request, ConceptoVentaListadoFiltros::class))
            ->with('mensaje', 'Concepto de venta actualizado con éxito');
    }

    public function eliminar(Request $request, $id)
    {
        can('borrar-conceptos-venta');

        if (! $request->ajax()) {
            abort(404);
        }

        try {
            if ($this->repository->delete($id)) {
                return response()->json(['mensaje' => 'ok']);
            }

            return response()->json(['mensaje' => 'ng']);
        } catch (RuntimeException $e) {
            return response()->json(['mensaje' => 'ng', 'error' => $e->getMessage()], 422);
        }
    }

    public function consultaConceptoVenta(Request $request): JsonResponse
    {
        if (! $this->puedeConsultar()) {
            abort(403);
        }

        $consulta = (string) ($request->input('consulta') ?? '');
        $data = $this->repository->listadoActivosParaConsulta($consulta);
        $puedeAbrirAbm = can('editar-conceptos-venta', false) || can('listar-conceptos-venta', false);

        $output = ['data' => ''];
        if ($data->isEmpty()) {
            $output['data'] = '<tr><td colspan="6">Sin resultados</td></tr>';
        } else {
            foreach ($data as $row) {
                $output['data'] .= '<tr data-impuesto-id="'.e((string) ($row->impuesto_id ?? '')).'">';
                $output['data'] .= '<td class="concepto_venta_id">'.e((string) $row->id).'</td>';
                $output['data'] .= '<td class="codigoconceptoventa">'.e((string) $row->codigo).'</td>';
                $output['data'] .= '<td class="nombreconceptoventa">'.e((string) $row->nombre).'</td>';
                $output['data'] .= '<td class="descripcionconceptoventa">'.e((string) $row->descripcion).'</td>';
                $output['data'] .= '<td class="gtinconceptoventa">'.e((string) ($row->codigo_gtin ?? '')).'</td>';
                $output['data'] .= '<td class="text-nowrap">';
                $output['data'] .= '<a class="btn btn-warning btn-sm eligeconsultaconceptoventa">Elegir</a>';
                if ($puedeAbrirAbm) {
                    $url = route('editar_concepto_venta', [
                        'id' => $row->id,
                        'origen' => 'modal_consulta',
                        'vista' => 'consulta',
                    ]);
                    $output['data'] .= ' <a class="btn btn-info btn-sm" href="'.e($url).'" target="_blank" rel="noopener">Consultar</a>';
                }
                $output['data'] .= '</td>';
                $output['data'] .= '</tr>';
            }
        }

        return response()->json($output);
    }

    public function leeUnConceptoPorCodigo(Request $request, $codigo)
    {
        if (! $this->puedeConsultar()) {
            abort(403);
        }

        $concepto = $this->repository->findPorCodigo((string) $codigo);
        if ($concepto === null || ! $concepto->activo) {
            return response()->json(['ok' => false]);
        }

        $fecha = trim((string) $request->query('fecha', date('Y-m-d')));
        $tipoId = (int) $request->query('tipotransaccion_id', 0);
        $linea = ConceptoVentaMostradorSupport::aLinea(
            $concepto,
            0,
            $tipoId > 0 ? $tipoId : null,
            $fecha !== '' ? $fecha : null,
        );

        return response()->json([
            'ok' => true,
            'id' => $concepto->id,
            'codigo' => $concepto->codigo,
            'nombre' => $concepto->nombre,
            'descripcion' => $concepto->descripcion,
            'codigo_gtin' => $concepto->codigo_gtin,
            'impuesto_id' => $concepto->impuesto_id,
            'unidadmedida_id' => $concepto->unidadmedida_id,
            'precio' => $linea['precio'],
        ]);
    }

    private function puedeConsultar(): bool
    {
        return can('listar-conceptos-venta', false)
            || can('crear-conceptos-venta', false)
            || can('editar-conceptos-venta', false)
            || can('crear-factura', false)
            || can('editar-factura', false)
            || can('crear-tipos-transacciones', false)
            || can('editar-tipos-transacciones', false)
            || can('listar-ventas-por-concepto', false);
    }

    /**
     * @return array<string, mixed>
     */
    private function datosFormulario(Request $request, Concepto_Venta $data): array
    {
        return [
            'data' => $data,
            'filtrosQuery' => QueryRetornoListado::desdeRequest($request, ConceptoVentaListadoFiltros::class),
            'empresa_query' => $this->empresaRepository->allFiltrado(),
            'impuesto_query' => $this->impuestoRepository->all(),
            'unidadmedida_query' => Unidadmedida::query()->orderBy('nombre')->get(),
            'tipo_query' => $this->tipotransaccionRepository->all(['V', 'C', 'U'])->sortBy('abreviatura')->values(),
            'usoConcepto' => $data->exists ? ConceptoVentaUsoSupport::resumen((int) $data->id) : ['emisiones' => 0, 'tipos' => 0],
        ];
    }

    private function sincronizarHijos(ValidacionConcepto_Venta $request, int $conceptoId): void
    {
        $empresas = (array) $request->input('empresa_ids', []);
        $cuentas = (array) $request->input('cuentacontable_ids', []);
        $tipos = (array) $request->input('tipotransaccion_ids', []);
        $desde = (array) $request->input('vigencia_desde', []);
        $hasta = (array) $request->input('vigencia_hasta', []);
        $ccs = (array) $request->input('centrocosto_ids', []);
        $usuarios = (array) $request->input('creousuario_cuentacontable_ids', []);
        $filasCuenta = [];
        $n = max(count($empresas), count($cuentas));
        for ($i = 0; $i < $n; $i++) {
            $filasCuenta[] = [
                'empresa_id' => $empresas[$i] ?? 0,
                'cuentacontable_id' => $cuentas[$i] ?? 0,
                'tipotransaccion_id' => $tipos[$i] ?? 0,
                'vigencia_desde' => $desde[$i] ?? null,
                'vigencia_hasta' => $hasta[$i] ?? null,
                'centrocosto_id' => $ccs[$i] ?? 0,
                'creousuario_id' => $usuarios[$i] ?? 0,
            ];
        }
        $this->repository->sincronizarCuentas($conceptoId, $filasCuenta);

        $precios = (array) $request->input('precios', []);
        $pDesde = (array) $request->input('precio_vigencia_desde', []);
        $pHasta = (array) $request->input('precio_vigencia_hasta', []);
        $pUsuarios = (array) $request->input('creousuario_precio_ids', []);
        $filasPrecio = [];
        foreach ($precios as $i => $precio) {
            $filasPrecio[] = [
                'precio' => $precio,
                'vigencia_desde' => $pDesde[$i] ?? null,
                'vigencia_hasta' => $pHasta[$i] ?? null,
                'creousuario_id' => $pUsuarios[$i] ?? 0,
            ];
        }
        $this->repository->sincronizarPrecios($conceptoId, $filasPrecio);
    }
}

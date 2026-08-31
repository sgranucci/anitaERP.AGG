<?php

declare(strict_types=1);

namespace App\Http\Controllers\Ventas;

use App\Exports\Ventas\ContratoVentaListadoExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\ValidacionContrato_Venta;
use App\Models\Ventas\Contrato_Venta;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Repositories\Ventas\Contrato_VentaRepositoryInterface;
use App\Support\Listado\QueryRetornoListado;
use App\Support\Ventas\ContratoVentaListadoFiltros;
use App\Support\Ventas\ContratoVentaPrefillSupport;
use App\Support\Ventas\ContratoVentaSupport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ContratoVentaController extends Controller
{
    public function __construct(
        private readonly Contrato_VentaRepositoryInterface $repository,
        private readonly EmpresaRepositoryInterface $empresaRepository,
    ) {}

    public function index(Request $request)
    {
        can('listar-contratos-venta');

        $filtros = ContratoVentaListadoFiltros::resolverDesdeRequest($request);
        $datas = $this->repository->leeContratoVenta($filtros, true);

        return view('ventas.contrato_venta.index', [
            'datas' => $datas,
            'filtros' => $filtros,
            'filtrosQuery' => ContratoVentaListadoFiltros::paraQueryString($filtros),
            'camposFiltro' => ContratoVentaListadoFiltros::CAMPOS,
        ]);
    }

    public function listar(Request $request, $formato = null, $busqueda = null)
    {
        can('listar-contratos-venta');

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $filtros = ContratoVentaListadoFiltros::resolverDesdeRequest($request, $busqueda);

        switch ($formato) {
            case 'PDF':
                $datas = $this->repository->leeContratoVenta($filtros, false);
                $view = \View::make('ventas.contrato_venta.listado', compact('datas'))->render();
                $path = storage_path('pdf/listados');
                $nombrePdf = 'listado_contrato_venta';

                if (! is_dir($path)) {
                    @mkdir($path, 0775, true);
                }

                $pdf = \App::make('dompdf.wrapper');
                $pdf->setPaper('legal', 'landscape');
                $pdf->loadHTML($view)->save($path.'/'.$nombrePdf.'.pdf');

                return response()->download($path.'/'.$nombrePdf.'.pdf');

            case 'EXCEL':
                return (new ContratoVentaListadoExport($this->repository))
                    ->parametros($filtros)
                    ->download('contratos_venta.xlsx');

            case 'CSV':
                return (new ContratoVentaListadoExport($this->repository))
                    ->parametros($filtros)
                    ->download('contratos_venta.csv', \Maatwebsite\Excel\Excel::CSV);
        }

        return redirect()->route('contrato_venta', ContratoVentaListadoFiltros::paraQueryString($filtros));
    }

    public function crear(Request $request)
    {
        can('crear-contratos-venta');
        $data = new Contrato_Venta([
            'estado' => ContratoVentaSupport::ESTADO_ACTIVO,
            'periodicidad' => ContratoVentaSupport::PERIODICIDAD_MENSUAL,
            'dia_facturacion' => 1,
            'vigencia_desde' => date('Y-m-d'),
        ]);

        return view('ventas.contrato_venta.crear', $this->datosFormulario($request, $data));
    }

    public function guardar(ValidacionContrato_Venta $request)
    {
        can('crear-contratos-venta');
        $contrato = $this->repository->create($request->validated());
        $this->sincronizarDatos($request, (int) $contrato->id);

        return redirect()->route('contrato_venta', QueryRetornoListado::desdeRequest($request, ContratoVentaListadoFiltros::class))
            ->with('mensaje', 'Abono / contrato de venta creado con éxito');
    }

    public function editar(Request $request, $id)
    {
        $soloConsulta = $request->query('origen') === 'modal_consulta';
        if ($soloConsulta) {
            if (! can('listar-contratos-venta', false) && ! can('editar-contratos-venta', false)) {
                abort(403, 'No tiene permiso para consultar el abono.');
            }
        } else {
            can('editar-contratos-venta');
        }

        $data = $this->repository->findOrFail($id);

        return view('ventas.contrato_venta.editar', array_merge(
            $this->datosFormulario($request, $data),
            [
                'solo_consulta' => $soloConsulta,
                'puede_actualizar' => can('actualizar-contratos-venta', false),
            ]
        ));
    }

    public function actualizar(ValidacionContrato_Venta $request, $id)
    {
        can('actualizar-contratos-venta');
        $this->repository->update($request->validated(), $id);
        $this->sincronizarDatos($request, (int) $id);

        $soloConsulta = $request->query('origen') === 'modal_consulta';
        if ($soloConsulta) {
            return redirect()->route('editar_contrato_venta', [
                'id' => $id,
                'origen' => 'modal_consulta',
                'vista' => 'consulta',
            ])->with('mensaje', 'Abono / contrato de venta actualizado con éxito');
        }

        return redirect()->route('contrato_venta', QueryRetornoListado::desdeRequest($request, ContratoVentaListadoFiltros::class))
            ->with('mensaje', 'Abono / contrato de venta actualizado con éxito');
    }

    public function eliminar(Request $request, $id)
    {
        can('borrar-contratos-venta');

        if (! $request->ajax()) {
            abort(404);
        }

        if ($this->repository->delete($id)) {
            return response()->json(['mensaje' => 'ok']);
        }

        return response()->json(['mensaje' => 'ng']);
    }

    public function consultaContratoVenta(Request $request): JsonResponse
    {
        if (! $this->puedeConsultar()) {
            abort(403);
        }

        $consulta = (string) ($request->input('consulta') ?? '');
        $empresaId = (int) ($request->input('empresa_id') ?? 0);
        $data = $this->repository->listadoActivosParaConsulta(
            $consulta,
            $empresaId > 0 ? $empresaId : null
        );
        $puedeAbrirAbm = can('editar-contratos-venta', false) || can('listar-contratos-venta', false);

        $output = ['data' => ''];
        if ($data->isEmpty()) {
            $output['data'] = '<tr><td colspan="7">Sin resultados</td></tr>';
        } else {
            foreach ($data as $row) {
                $output['data'] .= '<tr>';
                $output['data'] .= '<td class="contrato_venta_id">'.e((string) $row->id).'</td>';
                $output['data'] .= '<td class="codigocontratoventa">'.e((string) $row->codigo).'</td>';
                $output['data'] .= '<td class="clientecontratoventa">'.e((string) ($row->cliente->nombre ?? '')).'</td>';
                $output['data'] .= '<td class="conceptocontratoventa">'.e((string) ($row->conceptoVenta->codigo ?? '')).' — '.e((string) ($row->conceptoVenta->nombre ?? '')).'</td>';
                $output['data'] .= '<td class="estadocontratoventa">'.e((string) $row->estado).'</td>';
                $output['data'] .= '<td class="empresacontratoventa">'.e((string) ($row->empresa->nombre ?? '')).'</td>';
                $output['data'] .= '<td class="text-nowrap">';
                $output['data'] .= '<a class="btn btn-warning btn-sm eligeconsultacontratoventa">Elegir</a>';
                if ($puedeAbrirAbm) {
                    $url = route('editar_contrato_venta', [
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

    public function leeUnContratoPorCodigo(Request $request, $codigo)
    {
        if (! $this->puedeConsultar()) {
            abort(403);
        }

        $empresaId = (int) $request->query('empresa_id', 0);
        $contrato = $this->repository->findPorCodigo(
            (string) $codigo,
            $empresaId > 0 ? $empresaId : null
        );
        if ($contrato === null || ContratoVentaSupport::normalizarEstado((string) $contrato->estado) !== ContratoVentaSupport::ESTADO_ACTIVO) {
            return response()->json(['ok' => false]);
        }

        $fecha = trim((string) $request->query('fecha', date('Y-m-d')));
        $prefill = ContratoVentaPrefillSupport::armarLinea($contrato, $fecha !== '' ? $fecha : null);

        return response()->json(array_merge(['ok' => true], $prefill, [
            'id' => $contrato->id,
            'codigo_contrato' => $contrato->codigo,
            'cliente_id' => $contrato->cliente_id,
            'cliente_nombre' => $contrato->cliente->nombre ?? '',
            'empresa_id' => $contrato->empresa_id,
        ]));
    }

    public function prefillFactura(Request $request): JsonResponse
    {
        if (! $this->puedeConsultar()) {
            abort(403);
        }

        $contratoId = (int) $request->input('contrato_id', $request->query('contrato_id', 0));
        $codigo = trim((string) $request->input('codigo', $request->query('codigo', '')));
        $empresaId = (int) $request->input('empresa_id', $request->query('empresa_id', 0));
        $fecha = trim((string) $request->input('fecha', $request->query('fecha', date('Y-m-d'))));

        $contrato = null;
        if ($contratoId > 0) {
            try {
                $contrato = $this->repository->findOrFail($contratoId);
            } catch (\Throwable) {
                $contrato = null;
            }
        } elseif ($codigo !== '') {
            $contrato = $this->repository->findPorCodigo($codigo, $empresaId > 0 ? $empresaId : null);
        }

        if ($contrato === null) {
            return response()->json(['ok' => false, 'error' => 'Abono no encontrado'], 404);
        }

        $linea = ContratoVentaPrefillSupport::armarLinea($contrato, $fecha !== '' ? $fecha : null);

        return response()->json(array_merge(['ok' => true], $linea));
    }

    private function puedeConsultar(): bool
    {
        return can('listar-contratos-venta', false)
            || can('crear-contratos-venta', false)
            || can('editar-contratos-venta', false)
            || can('listar-contrato-venta-cola', false)
            || can('facturar-contrato-venta-cola', false)
            || can('crear-factura', false)
            || can('editar-factura', false);
    }

    /**
     * @return array<string, mixed>
     */
    private function datosFormulario(Request $request, Contrato_Venta $data): array
    {
        return [
            'data' => $data,
            'filtrosQuery' => QueryRetornoListado::desdeRequest($request, ContratoVentaListadoFiltros::class),
            'empresa_query' => $this->empresaRepository->allFiltrado(),
            'estados' => ContratoVentaSupport::ESTADOS,
            'periodicidades' => ContratoVentaSupport::PERIODICIDADES,
        ];
    }

    private function sincronizarDatos(ValidacionContrato_Venta $request, int $contratoId): void
    {
        $claves = (array) $request->input('dato_claves', []);
        $valores = (array) $request->input('dato_valores', []);
        $filas = [];
        foreach ($claves as $i => $clave) {
            $filas[] = [
                'clave' => $clave,
                'valor' => $valores[$i] ?? '',
            ];
        }
        $this->repository->sincronizarDatos($contratoId, $filas);
    }
}

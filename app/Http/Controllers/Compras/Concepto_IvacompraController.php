<?php

namespace App\Http\Controllers\Compras;

use App\Exports\Compras\ConceptoIvacompraListadoExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\ValidacionConcepto_Ivacompra;
use App\Models\Compras\Concepto_Ivacompra;
use App\Repositories\Compras\Columna_IvacompraRepositoryInterface;
use App\Repositories\Compras\Concepto_IvacompraRepositoryInterface;
use App\Repositories\Configuracion\CondicionivaRepositoryInterface;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Repositories\Configuracion\ImpuestoRepositoryInterface;
use App\Repositories\Contable\CuentacontableRepositoryInterface;
use App\Support\Compras\ConceptoIvacompraListadoFiltros;
use App\Support\Compras\ConceptoIvacompraConsultaSupport;
use App\Support\Listado\FiltrosListadoRequest;
use App\Support\Listado\QueryRetornoListado;
use Illuminate\Http\Request;

class Concepto_IvacompraController extends Controller
{
    public function __construct(
        private readonly Concepto_IvacompraRepositoryInterface $concepto_ivacompraRepository,
        private readonly Columna_IvacompraRepositoryInterface $columna_ivacompraRepository,
        private readonly CuentacontableRepositoryInterface $cuentacontableRepository,
        private readonly ImpuestoRepositoryInterface $impuestoRepository,
        private readonly CondicionivaRepositoryInterface $condicionivaRepository,
        private readonly EmpresaRepositoryInterface $empresaRepository,
    ) {}

    public function index(Request $request)
    {
        can('listar-concepto-iva-compra');

        $filtros = $this->resolverFiltrosListado($request);
        $datas = $this->concepto_ivacompraRepository->leeConceptoIvacompra($filtros, true);

        return view('compras.concepto_ivacompra.index', [
            'datas' => $datas,
            'filtros' => $filtros,
            'filtrosQuery' => ConceptoIvacompraListadoFiltros::paraQueryString($filtros),
            'camposFiltro' => ConceptoIvacompraListadoFiltros::CAMPOS,
            'empresa_query' => $this->empresaRepository->allFiltrado(),
        ]);
    }

    public function listar(Request $request, $formato = null, $busqueda = null)
    {
        can('listar-concepto-iva-compra');

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $filtros = $this->resolverFiltrosListado($request, $busqueda);

        switch ($formato) {
            case 'PDF':
                $datas = $this->concepto_ivacompraRepository->leeConceptoIvacompra($filtros, false);
                $view = \View::make('compras.concepto_ivacompra.listado', compact('datas'))->render();
                $path = storage_path('pdf/listados');
                if (! is_dir($path)) {
                    mkdir($path, 0755, true);
                }
                $nombrePdf = 'listado_concepto_ivacompra';

                $pdf = \App::make('dompdf.wrapper');
                $pdf->setPaper('legal', 'landscape');
                $pdf->loadHTML($view)->save($path.'/'.$nombrePdf.'.pdf');

                return response()->download($path.'/'.$nombrePdf.'.pdf');

            case 'EXCEL':
                return (new ConceptoIvacompraListadoExport($this->concepto_ivacompraRepository))
                    ->parametros($filtros)
                    ->download('conceptos_iva_compras.xlsx');

            case 'CSV':
                return (new ConceptoIvacompraListadoExport($this->concepto_ivacompraRepository))
                    ->parametros($filtros)
                    ->download('conceptos_iva_compras.csv', \Maatwebsite\Excel\Excel::CSV);
        }

        return redirect()->route('concepto_ivacompra', ConceptoIvacompraListadoFiltros::paraQueryString($filtros));
    }

    public function crear(Request $request)
    {
        can('crear-concepto-iva-compra');

        $data = new Concepto_Ivacompra();
        $filtrosQuery = QueryRetornoListado::desdeRequest($request, ConceptoIvacompraListadoFiltros::class);

        return view('compras.concepto_ivacompra.crear', $this->datosFormulario($data) + [
            'filtrosQuery' => $filtrosQuery,
        ]);
    }

    public function guardar(ValidacionConcepto_Ivacompra $request)
    {
        can('crear-concepto-iva-compra');

        $this->concepto_ivacompraRepository->create($request->validated());

        return redirect()
            ->route('concepto_ivacompra', QueryRetornoListado::desdeRequest($request, ConceptoIvacompraListadoFiltros::class))
            ->with('mensaje', 'Concepto IVA compras creado con éxito');
    }

    public function editar(Request $request, $id)
    {
        can('editar-concepto-iva-compra');

        $data = $this->concepto_ivacompraRepository->find($id);
        $filtrosQuery = QueryRetornoListado::desdeRequest($request, ConceptoIvacompraListadoFiltros::class);

        return view('compras.concepto_ivacompra.editar', $this->datosFormulario($data) + [
            'filtrosQuery' => $filtrosQuery,
        ]);
    }

    public function actualizar(ValidacionConcepto_Ivacompra $request, $id)
    {
        can('actualizar-concepto-iva-compra');

        $this->concepto_ivacompraRepository->update($request->validated(), $id);

        return redirect()
            ->route('concepto_ivacompra', QueryRetornoListado::desdeRequest($request, ConceptoIvacompraListadoFiltros::class))
            ->with('mensaje', 'Concepto IVA compras actualizado con éxito');
    }

    public function eliminar(Request $request, $id)
    {
        can('borrar-concepto-iva-compra');

        if ($request->ajax()) {
            if ($this->concepto_ivacompraRepository->delete($id)) {
                return response()->json(['mensaje' => 'ok']);
            }

            return response()->json(['mensaje' => 'ng']);
        }

        abort(404);
    }

    /**
     * Replica cuentas Debe/Haber por código a las demás empresas del selector.
     *
     * @return list<array<string, mixed>>
     */
    public function replicarCuentasPorEmpresa(Request $request, $empresa_id, $cuentacontabledebe_id)
    {
        if (! can('crear-concepto-iva-compra', false)
            && ! can('editar-concepto-iva-compra', false)
            && ! can('actualizar-concepto-iva-compra', false)) {
            abort(403);
        }

        $empresaOrigenId = (int) $empresa_id;
        $debeOrigenId = (int) $cuentacontabledebe_id;
        $haberOrigenId = (int) $request->input('cuentacontablehaber_id', 0);

        if ($empresaOrigenId <= 0 || ($debeOrigenId <= 0 && $haberOrigenId <= 0)) {
            return response()->json([]);
        }

        $codigoDebe = '';
        if ($debeOrigenId > 0) {
            $debeOrigen = $this->cuentacontableRepository->find($debeOrigenId);
            $codigoDebe = (string) ($debeOrigen->codigo ?? '');
        }

        $codigoHaber = '';
        if ($haberOrigenId > 0) {
            $haberOrigen = $this->cuentacontableRepository->find($haberOrigenId);
            $codigoHaber = (string) ($haberOrigen->codigo ?? '');
        }

        $resultado = [];
        foreach ($this->empresaRepository->allFiltrado() as $empresa) {
            $empresaId = (int) $empresa->id;
            if ($empresaId <= 0 || $empresaId === $empresaOrigenId) {
                continue;
            }

            $debeId = null;
            $debeCodigo = '';
            $debeNombre = '';
            if ($codigoDebe !== '') {
                $debe = $this->cuentacontableRepository->findPorCodigo($empresaId, $codigoDebe);
                if ($debe !== null) {
                    $debeId = (int) $debe->id;
                    $debeCodigo = (string) ($debe->codigo ?? '');
                    $debeNombre = (string) ($debe->nombre ?? '');
                }
            }

            $haberId = null;
            $haberCodigo = '';
            $haberNombre = '';
            if ($codigoHaber !== '') {
                $haber = $this->cuentacontableRepository->findPorCodigo($empresaId, $codigoHaber);
                if ($haber !== null) {
                    $haberId = (int) $haber->id;
                    $haberCodigo = (string) ($haber->codigo ?? '');
                    $haberNombre = (string) ($haber->nombre ?? '');
                }
            }

            if ($debeId === null && $haberId === null) {
                continue;
            }

            $resultado[] = [
                'empresa_id' => $empresaId,
                'empresa_nombre' => (string) ($empresa->nombre ?? ''),
                'cuentacontabledebe_id' => $debeId,
                'codigocuentadebe' => $debeCodigo,
                'nombrecuentadebe' => $debeNombre,
                'cuentacontablehaber_id' => $haberId,
                'codigocuentahaber' => $haberCodigo,
                'nombrecuentahaber' => $haberNombre,
            ];
        }

        return response()->json($resultado);
    }

    /**
     * @return array<string, mixed>
     */
    private function datosFormulario(Concepto_Ivacompra $data): array
    {
        return [
            'data' => $data,
            'tipoconcepto_enum' => Concepto_Ivacompra::$enumTipoConcepto,
            'retiene_enum' => Concepto_Ivacompra::$enumRetiene,
            'impuesto_query' => $this->impuestoRepository->all(),
            'columna_ivacompra_query' => $this->columna_ivacompraRepository->all(),
            'condicioniva_query' => $this->condicionivaRepository->all(),
            'empresa_query' => $this->empresaRepository->allFiltrado(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function resolverFiltrosListado(Request $request, ?string $busquedaRuta = null): array
    {
        $filtros = ConceptoIvacompraListadoFiltros::resolverDesdeRequest($request, $busquedaRuta);
        $asignadas = $this->empresaRepository->traeEmpresasAsignadas();
        $filtros['empresas_asignadas'] = $asignadas;

        if (FiltrosListadoRequest::solicitudLimpiaFiltros($request)) {
            return $filtros;
        }

        $empresaQuery = $this->empresaRepository->allFiltrado();
        $empresaId = (int) ($filtros['empresa_id'] ?? 0);

        if ($empresaId <= 0 && count($asignadas) === 1 && ! $request->has('empresa_id')) {
            $first = $empresaQuery->first();
            $filtros['empresa_id'] = $first !== null ? (int) $first->id : 0;
        } elseif ($empresaId > 0 && count($asignadas) >= 1 && ! in_array($empresaId, $asignadas, true)) {
            $first = $empresaQuery->first();
            $filtros['empresa_id'] = $first !== null ? (int) $first->id : 0;
        }

        return $filtros;
    }

    public function consultaConceptoIvacompra(Request $request)
    {
        if (! can('listar-concepto-iva-compra', false)
            && ! can('crear-comprobante-proveedor', false)
            && ! can('editar-comprobante-proveedor', false)
            && ! can('crear-precarga-proveedores', false)
            && ! can('editar-precarga-proveedores', false)) {
            abort(403);
        }

        $tipoId = (int) $request->input('tipotransaccion_compra_id', 0);
        $consulta = (string) $request->input('consulta', '');

        if ($tipoId <= 0) {
            return response()->json([
                'data' => '<tr><td colspan="5" class="text-muted">Seleccione primero el tipo de comprobante.</td></tr>',
                'sin_config' => false,
            ]);
        }

        if (! ConceptoIvacompraConsultaSupport::tipoTieneConceptosConfigurados($tipoId)) {
            return response()->json([
                'data' => '<tr><td colspan="5" class="text-warning">No hay conceptos IVA configurados para este tipo de comprobante.</td></tr>',
                'sin_config' => true,
            ]);
        }

        $conceptos = ConceptoIvacompraConsultaSupport::listarPorTipoTransaccion($tipoId, $consulta);
        if ($conceptos->isEmpty()) {
            return response()->json([
                'data' => '<tr><td colspan="5" class="text-muted">Sin resultados</td></tr>',
                'sin_config' => false,
            ]);
        }

        $html = '';
        foreach ($conceptos as $concepto) {
            $html .= '<tr>'
                .'<td class="concepto_ivacompra_id_celda">'.(int) $concepto->id.'</td>'
                .'<td class="codigo">'.e((string) $concepto->codigo).'</td>'
                .'<td class="nombre">'.e((string) $concepto->nombre).'</td>'
                .'<td><small>'.e((string) ($concepto->tipoconcepto ?? '')).'</small></td>'
                .'<td><button type="button" class="btn btn-sm btn-outline-primary eligeconsultaconcepto_ivacompra">Elegir</button></td>'
                .'</tr>';
        }

        return response()->json([
            'data' => $html,
            'sin_config' => false,
        ]);
    }

    public function resolverConceptoIvacompra(Request $request)
    {
        if (! can('listar-concepto-iva-compra', false)
            && ! can('crear-comprobante-proveedor', false)
            && ! can('editar-comprobante-proveedor', false)
            && ! can('crear-precarga-proveedores', false)
            && ! can('editar-precarga-proveedores', false)) {
            abort(403);
        }

        $tipoId = (int) $request->input('tipotransaccion_compra_id', 0);
        $valor = (string) $request->input('valor', $request->input('codigo', ''));

        $concepto = ConceptoIvacompraConsultaSupport::resolverPorCodigoOId($tipoId, $valor);
        if (! $concepto) {
            return response()->json([
                'ok' => false,
                'mensaje' => $tipoId <= 0
                    ? 'Seleccione el tipo de comprobante.'
                    : 'Concepto no encontrado para este tipo de comprobante.',
            ], 404);
        }

        return response()->json([
            'ok' => true,
            'id' => (int) $concepto->id,
            'codigo' => (string) $concepto->codigo,
            'nombre' => (string) $concepto->nombre,
            'tipoconcepto' => (string) ($concepto->tipoconcepto ?? ''),
        ]);
    }
}

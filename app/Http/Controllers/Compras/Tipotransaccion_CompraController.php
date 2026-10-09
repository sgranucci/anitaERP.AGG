<?php

namespace App\Http\Controllers\Compras;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Compras\Concepto_Ivacompra;
use App\Models\Compras\Tipotransaccion_Compra;
use App\Models\Configuracion\Empresa;
use App\Models\Contable\Centrocosto;
use Illuminate\Support\Facades\Storage;
use App\Http\Requests\ValidacionTipotransaccion_Compra;
use App\Exports\Compras\TipotransaccionCompraListadoExport;
use App\Services\Arca\ArcaTiposComprobanteCatalogoService;
use App\Support\Compras\TipotransaccionCompraListadoFiltros;
use App\Support\Reportes\DompdfListadoSupport;
use App\Repositories\Compras\Tipotransaccion_CompraRepositoryInterface;
use App\Repositories\Compras\Tipotransaccion_Compra_CentrocostoRepositoryInterface;
use App\Repositories\Compras\Tipotransaccion_Compra_Concepto_IvacompraRepositoryInterface;
use App\Repositories\Compras\Concepto_IvacompraRepositoryInterface;
use App\Repositories\Contable\CentrocostoRepositoryInterface;
use App\Support\Compras\ConceptoIvacompraConsultaSupport;
use App\Support\Compras\ConceptoIvacompraFormulaSupport;
use Exception;
use Illuminate\Http\JsonResponse;
use DB;

class Tipotransaccion_CompraController extends Controller
{
	private $repository;
    private $tipotransaccion_compra_centrocostoRepository;
    private $tipotransaccion_concepto_ivacompraRepository;
    private $concepto_ivacompraRepository;
	private $centrocostoRepository;
    private ArcaTiposComprobanteCatalogoService $arcaTiposComprobanteCatalogo;

    public function __construct(Tipotransaccion_CompraRepositoryInterface $repository,
                                Concepto_IvacompraRepositoryInterface $concepto_ivacomprarepository,
                                CentrocostoRepositoryInterface $centrocostorepository,
                                Tipotransaccion_Compra_CentrocostoRepositoryInterface $tipotransaccion_compra_centrocostorepository,
                                Tipotransaccion_Compra_Concepto_IvacompraRepositoryInterface $tipotransaccion_compra_concepto_ivacomprarepository,
                                ArcaTiposComprobanteCatalogoService $arcaTiposComprobanteCatalogo
                                )
    {
        $this->repository = $repository;
        $this->concepto_ivacompraRepository = $concepto_ivacomprarepository;
		$this->centrocostoRepository = $centrocostorepository;
        $this->tipotransaccion_compra_centrocostoRepository = $tipotransaccion_compra_centrocostorepository;
        $this->tipotransaccion_concepto_ivacompraRepository = $tipotransaccion_compra_concepto_ivacomprarepository;
        $this->arcaTiposComprobanteCatalogo = $arcaTiposComprobanteCatalogo;
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        can('listar-tipo-transaccion-compra');

        $filtros = TipotransaccionCompraListadoFiltros::resolverDesdeRequest($request);
        $datas = $this->repository->leeListado($filtros, true);

        return view('compras.tipotransaccion_compra.index', [
            'datas' => $datas,
            'filtros' => $filtros,
            'filtrosQuery' => TipotransaccionCompraListadoFiltros::paraQueryString($filtros),
            'camposFiltro' => TipotransaccionCompraListadoFiltros::CAMPOS,
        ]);
    }

    public function listar(Request $request, $formato = null, $busqueda = null)
    {
        can('listar-tipo-transaccion-compra');

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $filtros = TipotransaccionCompraListadoFiltros::resolverDesdeRequest($request, $busqueda);

        switch ($formato) {
            case 'PDF':
                $datas = $this->repository->leeListado($filtros, false);
                $html = view('compras.tipotransaccion_compra.listado', [
                    'datas' => $datas,
                    'subtitulo' => trim((string) ($filtros['valor'] ?? '')),
                ])->render();
                $ruta = storage_path('pdf/listados/listado_tipotransaccion_compra.pdf');
                if (! is_dir(dirname($ruta))) {
                    mkdir(dirname($ruta), 0755, true);
                }
                DompdfListadoSupport::guardarLegalLandscape($html, $ruta, [
                    'titulo_corto' => 'Tipos de comprobante',
                ]);

                return response()->download($ruta);

            case 'EXCEL':
                return (new TipotransaccionCompraListadoExport($this->repository))
                    ->parametros($filtros)
                    ->download('tipos_comprobante_compras.xlsx');

            case 'CSV':
                return (new TipotransaccionCompraListadoExport($this->repository))
                    ->parametros($filtros)
                    ->download('tipos_comprobante_compras.csv', \Maatwebsite\Excel\Excel::CSV);
        }

        return redirect()->route('tipotransaccion_compra', TipotransaccionCompraListadoFiltros::paraQueryString($filtros));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function crear()
    {
        can('crear-tipo-transaccion-compra');
        $operacionEnum = Tipotransaccion_Compra::$enumOperacion;
        $signoEnum = Tipotransaccion_Compra::$enumSigno;
        $subdiarioEnum = Tipotransaccion_Compra::$enumSubdiario;
        $asientocontableEnum = Tipotransaccion_Compra::$enumAsientoContable;
        $estadoEnum = Tipotransaccion_Compra::$enumEstado;
        $retieneEnum = Tipotransaccion_Compra::$enumRetiene;

        return view('compras.tipotransaccion_compra.crear', array_merge([
            'operacionEnum' => $operacionEnum,
            'signoEnum' => $signoEnum,
            'subdiarioEnum' => $subdiarioEnum,
            'asientocontableEnum' => $asientocontableEnum,
            'estadoEnum' => $estadoEnum,
            'retieneEnum' => $retieneEnum,
            'filasCentrocosto' => $this->filasCentrocosto(null),
            'filasConcepto' => $this->filasConcepto(null),
        ], $this->datosArcaFormulario()));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function guardar(ValidacionTipotransaccion_Compra $request)
    {
        DB::beginTransaction();
        try
        {
            $tipotransaccion = $this->repository->create($request->all());

            // Guarda tablas asociadas
            if ($tipotransaccion)
            {
                $tipotransaccion_centrocosto = $this->tipotransaccion_compra_centrocostoRepository->create($request->all(), $tipotransaccion->id);
                $tipotransaccion_concepto = $this->tipotransaccion_concepto_ivacompraRepository->create($request->all(), $tipotransaccion->id);
            }
            DB::commit();
        } catch (\Exception $e) {
            DB::rollback();
            return ['errores' => $e->getMessage()];
        }
        return redirect('compras/tipotransaccion_compra')->with('mensaje', 'Tipo de transacción creada con exito');
    }


    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function editar($id)
    {
        can('editar-tipo-transaccion-compra');
        $data = $this->repository->findOrFail($id);
        $operacionEnum = Tipotransaccion_Compra::$enumOperacion;
        $signoEnum = Tipotransaccion_Compra::$enumSigno;
        $subdiarioEnum = Tipotransaccion_Compra::$enumSubdiario;
        $asientocontableEnum = Tipotransaccion_Compra::$enumAsientoContable;
        $estadoEnum = Tipotransaccion_Compra::$enumEstado;
        $retieneEnum = Tipotransaccion_Compra::$enumRetiene;

        return view('compras.tipotransaccion_compra.editar', array_merge([
            'data' => $data,
            'operacionEnum' => $operacionEnum,
            'signoEnum' => $signoEnum,
            'subdiarioEnum' => $subdiarioEnum,
            'asientocontableEnum' => $asientocontableEnum,
            'estadoEnum' => $estadoEnum,
            'retieneEnum' => $retieneEnum,
            'filasCentrocosto' => $this->filasCentrocosto($data),
            'filasConcepto' => $this->filasConcepto($data),
        ], $this->datosArcaFormulario()));
    }

    /**
     * Updote the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function actualizar(ValidacionTipotransaccion_Compra $request, $id)
    {
        can('actualizar-tipo-transaccion-compra');

        DB::beginTransaction();
        try
        {
            // Graba proveedor
            $this->repository->update($request->all(), $id);

            // Graba centros de costos
            $this->tipotransaccion_compra_centrocostoRepository->update($request->all(), $id);

            // Graba conceptos de compra
            $this->tipotransaccion_concepto_ivacompraRepository->update($request->all(), $id);

            DB::commit();
        } catch (\Exception $e) {
            DB::rollback();

            dd($e->getMessage());
            return ['errores' => $e->getMessage()];
        }

        return redirect('compras/tipotransaccion_compra')->with('mensaje', 'Tipo de transacción actualizada con exito');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function eliminar(Request $request, $id)
    {
        can('borrar-tipo-transaccion-compra');

        if ($request->ajax()) {
        	if ($this->repository->delete($id)) {
                return response()->json(['mensaje' => 'ok']);
            } else {
                return response()->json(['mensaje' => 'ng']);
            }
        } else {
            abort(404);
        }
    }

    public function consultaTipotransaccionCompra(Request $request)
    {
        if (! $this->puedeConsultarTipotransaccionCompra()) {
            abort(403);
        }

        $consulta = strtoupper(trim((string) ($request->get('consulta') ?? '')));
        $centrocostoId = (int) ($request->input('centrocosto_id') ?: 0) ?: null;

        $data = $this->repository->listarParaConsulta($consulta !== '' ? $consulta : null, $centrocostoId);
        $puedeAbrirAbm = can('editar-tipo-transaccion-compra', false) || can('listar-tipo-transaccion-compra', false);

        $output = ['data' => ''];
        if ($data->isEmpty()) {
            $output['data'] = '<tr><td colspan="7">Sin resultados</td></tr>';
        } else {
            foreach ($data as $row) {
                $output['data'] .= '<tr>';
                $output['data'] .= '<td class="id">'.e($row->id).'</td>';
                $output['data'] .= '<td class="abreviatura">'.e($row->abreviatura).'</td>';
                $output['data'] .= '<td class="nombre">'.e($row->nombre).'</td>';
                $output['data'] .= '<td>'.e($this->etiquetaRetiene($row->retieneiva ?? null)).'</td>';
                $output['data'] .= '<td>'.e($this->etiquetaRetiene($row->retieneganancia ?? null)).'</td>';
                $output['data'] .= '<td>'.e($this->etiquetaRetiene($row->getAttribute('retieneIIBB'))).'</td>';
                $output['data'] .= '<td class="text-nowrap">';
                $output['data'] .= '<a class="btn btn-warning btn-sm eligeconsultatipotransaccioncompra">Elegir</a>';
                if ($puedeAbrirAbm) {
                    $urlConsulta = route('editar_tipotransaccion_compra', [
                        'id' => $row->id,
                        'origen' => 'modal_consulta',
                        'vista' => 'consulta',
                    ]);
                    $output['data'] .= ' <a class="btn btn-info btn-sm" href="'.e($urlConsulta).'" target="_blank" rel="noopener">Consultar</a>';
                }
                $output['data'] .= '</td>';
                $output['data'] .= '</tr>';
            }
        }

        return json_encode($output, JSON_UNESCAPED_UNICODE);
    }

    public function leeUnTipotransaccionPorAbreviatura(Request $request, string $abreviatura)
    {
        if (! $this->puedeConsultarTipotransaccionCompra()) {
            abort(403);
        }

        $centrocostoId = (int) ($request->input('centrocosto_id') ?: 0) ?: null;
        $abrev = strtoupper(trim($abreviatura));
        $tipo = $this->repository->findPorAbreviaturaFiltrado($abrev, $centrocostoId);

        if (! $tipo) {
            return response()->json(['id' => null]);
        }

        return response()->json([
            'id' => (int) $tipo->id,
            'abreviatura' => (string) $tipo->abreviatura,
            'nombre' => (string) $tipo->nombre,
        ]);
    }

    public function conceptosIvaPorTipo(int $id, Request $request)
    {
        if (! $this->puedeConsultarTipotransaccionCompra()) {
            abort(403);
        }

        $numeroOc = trim((string) (
            $request->query('numero_oc')
            ?? $request->query('numeroordencompra')
            ?? $request->input('numero_oc')
            ?? $request->input('numeroordencompra')
            ?? ''
        ));
        $numeroOc = $numeroOc !== '' ? $numeroOc : null;

        $lista = ConceptoIvacompraConsultaSupport::listarPorTipoTransaccion($id, null, $numeroOc);

        $tipo = Tipotransaccion_Compra::query()->find($id);
        $abrev = strtoupper(trim((string) ($tipo->abreviatura ?? '')));
        $esProrrateo = \App\Support\Compras\PrecargaProveedor\PrecargaProveedorProrrateoMultiCcSupport::esTipoProrrateado($abrev);

        $conceptos = $lista->map(function ($c) {
                $formula = (string) ($c->formula ?? '');
                $parsed = ConceptoIvacompraFormulaSupport::parse($formula);
                $tipo = (string) ($c->tipoconcepto ?? '');
                if ($parsed !== null && ! in_array(strtoupper($tipo), ['I', 'G', 'E'], true)) {
                    $tipo = 'I';
                }

                $cuentaDebe = $c->cuentacontablesdebe;
                $cuentasDetalleEmpresa = [];
                foreach ($c->concepto_ivacompra_empresas as $lineaEmpresa) {
                    $empresaLinea = (int) ($lineaEmpresa->empresa_id ?? 0);
                    $cuentaLineaId = (int) ($lineaEmpresa->cuentacontabledebe_id ?? 0);
                    if ($empresaLinea <= 0 || $cuentaLineaId <= 0) {
                        continue;
                    }
                    $cuentaLinea = $lineaEmpresa->cuentacontabledebe;
                    $cuentasDetalleEmpresa[$empresaLinea] = [
                        'id' => $cuentaLineaId,
                        'codigo' => (string) ($cuentaLinea?->codigo ?? ''),
                        'nombre' => (string) ($cuentaLinea?->nombre ?? ''),
                    ];
                }

                return [
                    'id' => (int) $c->id,
                    'codigo' => (string) ($c->codigo ?? ''),
                    'nombre' => (string) ($c->nombre ?? ''),
                    'tipoconcepto' => $tipo,
                    'cuentacontable_id' => $c->cuentacontable_id ? (int) $c->cuentacontable_id : null,
                    'formula' => trim($formula),
                    'formula_codigo_base' => $parsed['codigo_base'] ?? '',
                    'formula_coeficiente' => $parsed['coeficiente'] ?? 0.0,
                    'impuesto_tasa' => $parsed !== null
                        ? ConceptoIvacompraFormulaSupport::tasaPorcentajeDesdeFormula($formula)
                        : round((float) ($c->impuestos->valor ?? 0), 3),
                    'cuenta_debe_id' => (int) ($c->cuentacontabledebe_id ?? 0),
                    'cuenta_debe_codigo' => (string) ($cuentaDebe?->codigo ?? ''),
                    'cuenta_debe_nombre' => (string) ($cuentaDebe?->nombre ?? ''),
                    'cuentas_por_empresa' => method_exists($c, 'mapaCuentaDebePorEmpresa')
                        ? $c->mapaCuentaDebePorEmpresa()
                        : [],
                    'cuentas_detalle_por_empresa' => $cuentasDetalleEmpresa,
                ];
            })->keyBy('id')->all();

        $conceptos = array_values(ConceptoIvacompraFormulaSupport::enriquecerMetaCliente($conceptos));

        return response()->json([
            'ok' => true,
            'prorrateo_multi_cc' => $esProrrateo && $numeroOc !== null,
            'numero_oc' => $numeroOc,
            'conceptos' => $conceptos,
        ]);
    }

    /**
     * @return list<array{id: string, codigo: string, nombre: string}>
     */
    private function filasCentrocosto(?Tipotransaccion_Compra $data): array
    {
        if (is_array(old('centrocosto_ids'))) {
            return $this->filasDesdeIds(old('centrocosto_ids'), Centrocosto::class);
        }

        $filas = [];
        foreach ($data->tipotransaccion_compra_centrocostos ?? [] as $linea) {
            $cc = $linea->centrocostos;
            $filas[] = [
                'id' => (string) ($linea->centrocosto_id ?? ''),
                'codigo' => (string) ($cc->codigo ?? ''),
                'nombre' => (string) ($cc->nombre ?? ''),
            ];
        }

        return $filas !== [] ? $filas : [['id' => '', 'codigo' => '', 'nombre' => '']];
    }

    /**
     * @return list<array{id: string, codigo: string, nombre: string}>
     */
    private function filasConcepto(?Tipotransaccion_Compra $data): array
    {
        if (is_array(old('concepto_ivacompra_ids'))) {
            return $this->filasDesdeIds(old('concepto_ivacompra_ids'), Concepto_Ivacompra::class);
        }

        $filas = [];
        foreach ($data->tipotransaccion_compra_concepto_ivacompras ?? [] as $linea) {
            $concepto = $linea->concepto_ivacompras;
            $filas[] = [
                'id' => (string) ($linea->concepto_ivacompra_id ?? ''),
                'codigo' => (string) ($concepto->codigo ?? ''),
                'nombre' => (string) ($concepto->nombre ?? ''),
            ];
        }

        return $filas !== [] ? $filas : [['id' => '', 'codigo' => '', 'nombre' => '']];
    }

    /**
     * @param  list<mixed>  $ids
     * @param  class-string<\Illuminate\Database\Eloquent\Model>  $modelo
     * @return list<array{id: string, codigo: string, nombre: string}>
     */
    private function filasDesdeIds(array $ids, string $modelo): array
    {
        $numericos = array_values(array_filter(array_map('intval', $ids)));
        $registros = $numericos === []
            ? collect()
            : $modelo::query()->whereIn('id', $numericos)->get()->keyBy('id');

        $filas = [];
        foreach ($ids as $id) {
            $registro = $registros->get((int) $id);
            $filas[] = [
                'id' => $registro ? (string) $id : '',
                'codigo' => $registro ? (string) $registro->codigo : '',
                'nombre' => $registro ? (string) $registro->nombre : '',
            ];
        }

        return $filas !== [] ? $filas : [['id' => '', 'codigo' => '', 'nombre' => '']];
    }

    private function etiquetaRetiene(mixed $valor): string
    {
        $clave = strtoupper(trim((string) $valor));

        return Tipotransaccion_Compra::$enumRetiene[$clave] ?? '';
    }

    /**
     * Tipos de comprobante AFIP de los web services con puntos de venta activos.
     */
    public function tiposCbteArca(Request $request): JsonResponse
    {
        if (! can('crear-tipo-transaccion-compra', false) && ! can('editar-tipo-transaccion-compra', false)) {
            abort(403, 'No tiene permiso');
        }

        $request->validate([
            'empresa_id' => ['required', 'integer', 'min:1'],
            'refresh' => ['sometimes', 'boolean'],
        ]);

        $empresaId = (int) $request->input('empresa_id');
        $webservices = $this->arcaTiposComprobanteCatalogo->webservicesActivos($empresaId);
        $webservice = $webservices[0] ?? $this->arcaTiposComprobanteCatalogo->webserviceParaEmpresa($empresaId);
        $diagnostico = $this->arcaTiposComprobanteCatalogo->diagnosticoCertificado($empresaId, $webservice);

        try {
            $resultado = $this->arcaTiposComprobanteCatalogo->obtenerTiposComprobanteActivos(
                $empresaId,
                $request->boolean('refresh')
            );
        } catch (Exception $e) {
            return response()->json([
                'ok' => false,
                'message' => $this->mensajeErrorArcaTiposCbte($e, $webservice, $diagnostico),
                'webservice' => $webservice,
                'webservices' => $webservices,
                'diagnostico' => $diagnostico,
            ], 500);
        }

        $usados = $resultado['webservices'] ?? $webservices;

        return response()->json([
            'ok' => true,
            'empresa_id' => $empresaId,
            'webservice' => $webservice,
            'webservices' => $usados,
            'webservice_etiqueta' => $this->arcaTiposComprobanteCatalogo->etiquetasWebservices($usados),
            'diagnostico' => $diagnostico,
            'origen' => $resultado['origen'],
            'sincronizado_at' => $resultado['sincronizado_at'],
            'persistido' => (bool) ($resultado['persistido'] ?? false),
            'registros_guardados' => (int) ($resultado['registros_guardados'] ?? 0),
            'advertencias' => $resultado['advertencias'] ?? [],
            'tipos' => $resultado['tipos'],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function datosArcaFormulario(): array
    {
        $empresa_query = $this->empresasArcaQuery();
        $empresaArcaId = (int) old('empresa_arca_id', $this->empresaArcaDefaultId($empresa_query));
        $webservicesArca = $empresaArcaId > 0
            ? $this->arcaTiposComprobanteCatalogo->webservicesActivos($empresaArcaId)
            : [];
        $webserviceArcaEtiqueta = $webservicesArca !== []
            ? $this->arcaTiposComprobanteCatalogo->etiquetasWebservices($webservicesArca)
            : '';
        $tiposCbteArca = [];
        $sincronizadoArcaTexto = null;

        if ($empresaArcaId > 0 && $this->arcaTiposComprobanteCatalogo->tieneCatalogoActivoEnBd($empresaArcaId)) {
            $tiposCbteArca = $this->arcaTiposComprobanteCatalogo->listarDesdeBdActivos($empresaArcaId);
            $ultima = $this->arcaTiposComprobanteCatalogo->ultimaSincronizacionActivos($empresaArcaId);
            $sincronizadoArcaTexto = $ultima?->format('d/m/Y H:i');
        }

        return compact(
            'empresa_query',
            'empresaArcaId',
            'webserviceArcaEtiqueta',
            'tiposCbteArca',
            'sincronizadoArcaTexto'
        );
    }

    /**
     * @return \Illuminate\Support\Collection<int, Empresa>
     */
    private function empresasArcaQuery()
    {
        $ids = $this->arcaTiposComprobanteCatalogo->empresasConCertificadoArca();
        if ($ids === []) {
            return collect();
        }

        return Empresa::query()
            ->whereIn('id', $ids)
            ->orderBy('nombre')
            ->get();
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Empresa>  $empresas
     */
    private function empresaArcaDefaultId($empresas): int
    {
        if ($empresas->isEmpty()) {
            return 0;
        }

        $preferido = (int) config('cliente.EMPRESA_DEFAULT_ID', 1);
        if ($empresas->contains('id', $preferido)) {
            return $preferido;
        }

        return (int) $empresas->first()->id;
    }

    /**
     * @param  array<string, mixed>  $diagnostico
     */
    private function mensajeErrorArcaTiposCbte(Exception $e, string $webservice, array $diagnostico = []): string
    {
        $msg = $e->getMessage();
        $wsaa = (string) ($diagnostico['wsaa_service'] ?? '');
        $certPath = (string) ($diagnostico['cert_path'] ?? '');
        $cuitCert = (string) ($diagnostico['cuit_certificado'] ?? '');
        $cuitEmp = (string) ($diagnostico['cuit_empresa'] ?? '');

        if (stripos($msg, 'lista de relaciones') !== false || stripos($msg, 'ValidacionDeToken') !== false) {
            $etiqueta = $this->arcaTiposComprobanteCatalogo->etiquetaWebservice($webservice);
            $extra = $certPath !== '' ? " Cert: {$certPath}." : '';

            return $msg.' — '.$etiqueta.' (WSAA «'.$wsaa.'»). CUIT certificado='.$cuitCert.', CUIT empresa='.$cuitEmp.'.'.$extra;
        }

        if (
            stripos($msg, 'Parsing WSDL') !== false
            || stripos($msg, 'failed to load external entity') !== false
            || stripos($msg, 'Couldn\'t load from') !== false
        ) {
            $env = (string) config('arca.env', 'homo');
            $subdir = $webservice === ArcaTiposComprobanteCatalogoService::WS_MTXCA ? 'mtxca' : 'wsfe';
            $archivo = $webservice === ArcaTiposComprobanteCatalogoService::WS_MTXCA
                ? 'MTXCAService.wsdl'
                : 'service.wsdl';
            $local = storage_path("app/arca/{$subdir}/wsdl/{$env}/{$archivo}");

            return $msg.' — Copie el WSDL en '.$local.' o defina ARCA_'.strtoupper($subdir).'_WSDL_LOCAL en .env.';
        }

        return $msg;
    }

    private function puedeConsultarTipotransaccionCompra(): bool
    {
        return can('listar-tipo-transaccion-compra', false)
            || can('crear-comprobante-proveedor', false)
            || can('editar-comprobante-proveedor', false)
            || can('actualizar-comprobante-proveedor', false)
            || can('listar-comprobante-proveedor', false)
            || can('crear-precarga-proveedores', false)
            || can('editar-precarga-proveedores', false)
            || can('listar-precarga-proveedores', false)
            || can('crear-ingresos-egresos-caja', false)
            || can('editar-ingresos-egresos-caja', false)
            || can('actualizar-ingresos-egresos-caja', false)
            || can('listar-ingresos-egresos-caja', false);
    }
}

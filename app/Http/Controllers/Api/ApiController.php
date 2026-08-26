<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Repositories\Contable\CentrocostoRepositoryInterface;
use App\Repositories\Compras\Precarga_Comprobante_ProveedorRepositoryInterface;
use App\Repositories\Compras\Precarga_Comprobante_Proveedor_ConceptoRepositoryInterface;
use App\Repositories\Compras\ProveedorRepositoryInterface;
use App\Repositories\Compras\Concepto_IvacompraRepositoryInterface;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Repositories\Configuracion\MonedaRepositoryInterface;
use App\Services\Compras\OrdencompraService;
use App\Services\Compras\ComprobanteService;
use App\Services\Compras\PrecargaComprobanteAnitaSyncService;
use App\Support\Compras\ApiPrecargaProveedorLogger;
use App\Support\Compras\ComprobanteProveedorConceptosIibbPadronCotejoSupport;
use App\Support\Compras\ComprobanteProveedorConceptosIvaCoherenciaSupport;
use App\Support\Compras\ComprobanteProveedorUnicidadSupport;
use App\Support\Compras\PrecargaProveedor\PrecargaProveedorCentrocostoDestinoSupport;
use App\Support\Compras\PrecargaProveedor\PrecargaProveedorCuitCoincidenciaSupport;
use App\Support\Compras\PrecargaProveedor\PrecargaProveedorNumeroOcSupport;
use App\Support\Compras\PrecargaProveedor\PrecargaProveedorOcCuitMensajeSupport;
use App\Support\Compras\PrecargaProveedor\PrecargaProveedorResolucionSupport;
use App\Support\Compras\PrecargaProveedor\PrecargaProveedorTipoItemSupport;
use DB;
use Illuminate\Validation\ValidationException;

class ApiController extends Controller
{
    protected $ordencompraService;
    protected $comprobanteService;
    private $centrocostoRepository;
    private $precarga_comprobante_proveedorRepository;
    private $precarga_comprobante_proveedor_conceptoRepository;
    private $proveedorRepository;
    private $concepto_ivacompraRepository;
    private $empresaRepository;
    private $monedaRepository;
    private $precargaAnitaSync;

	public function __construct(OrdencompraService $ordencompraService,
                                CentrocostoRepositoryInterface $centrocostorepository,
                                ComprobanteService $comprobanteService,
                                Precarga_Comprobante_ProveedorRepositoryInterface $precarga_comprobante_proveedorRepository,
                                Precarga_Comprobante_Proveedor_ConceptoRepositoryInterface $precarga_comprobante_proveedor_conceptoRepository,
                                ProveedorRepositoryInterface $proveedorRepository,
                                Concepto_IvacompraRepositoryInterface $concepto_ivacompraRepository,
                                EmpresaRepositoryInterface $empresaRepository,
                                MonedaRepositoryInterface $monedaRepository,
                                PrecargaComprobanteAnitaSyncService $precargaAnitaSync)
	{
        $this->ordencompraService = $ordencompraService;
        $this->comprobanteService = $comprobanteService;
        $this->centrocostoRepository = $centrocostorepository;
        $this->precarga_comprobante_proveedorRepository = $precarga_comprobante_proveedorRepository;
        $this->precarga_comprobante_proveedor_conceptoRepository = $precarga_comprobante_proveedor_conceptoRepository;
        $this->empresaRepository = $empresaRepository;
        $this->concepto_ivacompraRepository = $concepto_ivacompraRepository;
        $this->monedaRepository = $monedaRepository;
        $this->proveedorRepository = $proveedorRepository;
        $this->precargaAnitaSync = $precargaAnitaSync;
	}

    public function listaConcepto($cuitProveedor, $numeroOc, $tipoComprobante)
    {
        $log = ApiPrecargaProveedorLogger::trace();
        try {
            $numeroOc = app(PrecargaProveedorNumeroOcSupport::class)->normalizar($numeroOc);
        } catch (\RuntimeException $e) {
            $log->warning('lista_concepto.numero_oc_invalido', [
                'numero_oc' => $numeroOc,
                'message' => $e->getMessage(),
                'status' => 422,
            ]);

            return response()->json(['message' => $e->getMessage()], 422);
        }

        $log->info('lista_concepto.inicio', [
            'cuit_proveedor' => $cuitProveedor,
            'numero_oc' => $numeroOc,
            'tipo_comprobante' => $tipoComprobante,
        ]);

        $respuesta = [];
        $conceptos = [];
        $flError = false;
        $status = 500;
        $message = 'Error interno';

        // Busca la orden de compra 
        $ordencompra = $this->ordencompraService->leeOrdenCompra($numeroOc);

        if ($ordencompra == 'OC inexistente')
        {
            $status = 404;
            $message = $ordencompra;
            $flError = true;
            $log->warning('lista_concepto.oc_inexistente', [
                'numero_oc' => $numeroOc,
                'status' => $status,
            ]);
        }

        if (!$flError)
        {
            $log->info('lista_concepto.oc_encontrada', ['numero_oc' => $numeroOc]);

            $datosOrdenCompra = $ordencompra['ordencompra'];
            $itemsOrdenCompra = $ordencompra['item'];

            $cuitOrdenCompra = str_replace("-", "", $datosOrdenCompra->prom_cuit);
            $cuitProveedor = str_replace("-", "", $cuitProveedor);
            $letraProveedor = $datosOrdenCompra->prom_letra;

            if (! PrecargaProveedorCuitCoincidenciaSupport::coinciden($cuitOrdenCompra, $cuitProveedor))
            {
                $status = 404;
                $message = app(PrecargaProveedorOcCuitMensajeSupport::class)
                    ->mensaje($numeroOc, $cuitOrdenCompra, $cuitProveedor);
                $flError = true;
                $log->warning('lista_concepto.cuit_no_coincide', [
                    'cuit_proveedor' => $cuitProveedor,
                    'cuit_orden_compra' => $cuitOrdenCompra,
                    'numero_oc' => $numeroOc,
                    'status' => $status,
                ]);
            }

            if (!$flError)
            {
                $centroCostoDestino = PrecargaProveedorCentrocostoDestinoSupport::codigoDesdeOcAnita(
                    $datosOrdenCompra,
                    $itemsOrdenCompra
                );

                $centrocosto = $this->centrocostoRepository->findPorCodigo($centroCostoDestino);

                if ($centrocosto)
                {
                    $tipoIva = $centrocosto->tipoiva;

                    if (substr($tipoIva,0,1) != 'I' &&
                        substr($tipoIva,0,1) != 'D' &&
                        substr($tipoIva,0,1) != 'N')
                    {
                        $status = 404;
                        $message = "No existe centro de costo de la OC";
                        $flError = true;
                        $log->warning('lista_concepto.tipo_iva_centro_costo_invalido', [
                            'centro_costo_destino' => $centroCostoDestino,
                            'tipo_iva' => $tipoIva,
                            'status' => $status,
                        ]);
                    }

                    if (!$flError)
                    {
                        // Verifica el tipo de item (fuerza S si el proveedor tiene medidores/servicios)
                        $tipoItem = PrecargaProveedorTipoItemSupport::resolver($itemsOrdenCompra, $cuitProveedor);
                        $esProveedorServicios = PrecargaProveedorTipoItemSupport::proveedorTieneServicios($cuitProveedor);

                        switch($tipoComprobante)
                        {
                            case 'FC':
                                $inicial = 'F';
                                break;
                            case 'ND':
                                $inicial = 'D';
                                break;                                
                            case 'NC':
                                $inicial = 'C';
                                break;
                            case 'REC':
                                $inicial = '';
                                break;
                            case 'REM':
                                $inicial = '';
                        }

                        if ($tipoComprobante != 'REC' && $tipoComprobante != 'REM')
                        {
                            switch($centroCostoDestino)
                            {
                                case 85:
                                    $abreviatura = $inicial.'GA';
                                    break;
                                case 104:
                                    $abreviatura = $inicial.'EG';
                                    break;
                                default:
                                    $abreviatura = $inicial.substr($tipoIva,0,1).$tipoItem;
                                    break;
                            }
                        }
                        else
                            $abreviatura = $tipoComprobante;

                        $log->info('lista_concepto.tipo_resuelto', [
                            'abreviatura' => $abreviatura,
                            'tipo_item' => $tipoItem,
                            'es_proveedor_servicios' => $esProveedorServicios,
                            'centro_costo_destino' => $centroCostoDestino,
                            'letra_proveedor' => $letraProveedor,
                        ]);

                        // Busca los conceptos en base al tipo de comprobante
                        $comprobante = $this->comprobanteService->leeTipoTransaccionCompraPorAbreviatura($abreviatura); 

                        if (count($comprobante->tipotransaccion_compra_concepto_ivacompras) > 0)
                        {
                            foreach ($comprobante->tipotransaccion_compra_concepto_ivacompras as $concepto)
                            {
                                $conceptos[] = [
                                    'id_concepto' => $concepto->concepto_ivacompras->codigo,
                                    'nombre' => $concepto->concepto_ivacompras->nombre,
                                    'descripcion_ai' => $concepto->concepto_ivacompras->nombre_ia ?? $concepto->concepto_ivacompras->nombre,
                                    // Código canónico de Concepto_Ivacompra::$enumTipoConcepto.
                                    // Permite al conector distinguir P (Perc. IVA), B (Perc. IIBB) y S (SIRCREB).
                                    'tipoconcepto' => (string) ($concepto->concepto_ivacompras->tipoconcepto ?? ''),
                                ];
                            }
                        }

                        $respuesta[] = [
                            'tipocomprobante' => $abreviatura,
                            'letra' => $letraProveedor,
                            'es_proveedor_servicios' => $esProveedorServicios,
                            'tipo_item' => $tipoItem,
                            'concepto' => $conceptos
                        ];

                        $status = 200;
                        $message = "devuelve lista de conceptos";
                        $log->info('lista_concepto.ok', [
                            'status' => $status,
                            'cantidad_conceptos' => count($conceptos),
                            'tipocomprobante' => $abreviatura,
                        ]);
                    }
                }
                else
                {
                    $status = 404;
                    $message = "No existe centro de costo de la OC";
                    $flError = true;
                    $log->warning('lista_concepto.centro_costo_inexistente', [
                        'centro_costo_destino' => $centroCostoDestino,
                        'status' => $status,
                    ]);
                }
            }
        }

        if ($status == 200) {
            return response()->json([
                "respuesta" => $respuesta], $status);
        }

        $log->warning('lista_concepto.respuesta_error', [
            'status' => $status,
            'message' => $message,
        ]);

        return response()->json([
            "message" => $message,
            ], $status);
    }

    public function recibeComprobante(Request $request)
    {
        $log = ApiPrecargaProveedorLogger::trace();
        $log->info('recibe_comprobante.inicio', [
            'payload' => $log->requestPayload($request),
            'ip' => $request->ip(),
        ]);

        $sucursalRaw = $request->input('sucursal');
        $numeroFacturaRaw = $request->input('numero_factura');
        $sucursal = $this->normalizarEnteroComprobante($sucursalRaw);
        $numeroFactura = $this->normalizarEnteroComprobante($numeroFacturaRaw);

        if ($sucursal === null) {
            $log->warning('recibe_comprobante.sucursal_invalida', [
                'sucursal_raw' => $sucursalRaw,
                'status' => 422,
            ]);

            return response()->json([
                'message' => 'El campo sucursal debe ser un número válido.',
            ], 422);
        }

        if ($numeroFactura === null || $numeroFactura < 1) {
            $log->warning('recibe_comprobante.numero_factura_invalido', [
                'numero_factura_raw' => $numeroFacturaRaw,
                'status' => 422,
            ]);

            return response()->json([
                'message' => 'El campo numero_factura debe ser un número válido mayor a cero.',
            ], 422);
        }

        $request->merge([
            'sucursal' => $sucursal,
            'numero_factura' => $numeroFactura,
        ]);

        $log->info('recibe_comprobante.numeros_normalizados', [
            'sucursal_raw' => $sucursalRaw,
            'sucursal' => $sucursal,
            'numero_factura_raw' => $numeroFacturaRaw,
            'numero_factura' => $numeroFactura,
        ]);

        try {
            $request->validate([
                'cuit_proveedor' => 'required|string|max:15',
                'cuit_empresa' => 'required|string|max:15',
                'tipo' => 'required|string|max:10',
                'letra' => 'required|string|size:1',
                'sucursal' => 'required|integer|min:0',
                'numero_factura' => 'required|integer|min:1',
                'numero_cae' => 'nullable|string|max:50',
                'fecha_vto_cai_cae' => 'nullable|date',
                'conceptos' => 'required|array|min:1',
                'conceptos.*.id_concepto' => 'required',
                'conceptos.*.importe' => 'required|numeric',
            ]);
        } catch (ValidationException $e) {
            $log->warning('recibe_comprobante.validacion_fallida', [
                'errores' => $e->errors(),
                'status' => 422,
            ]);

            return response()->json([
                'message' => 'Error de validación.',
                'errors' => $e->errors(),
            ], 422);
        }

        $log->info('recibe_comprobante.validacion_ok');

        $numeroOc = trim((string) $request->input('numero_oc', ''));
        if ($numeroOc !== '') {
            try {
                $numeroOc = app(PrecargaProveedorNumeroOcSupport::class)->normalizar($numeroOc);
            } catch (\RuntimeException $e) {
                $log->warning('recibe_comprobante.numero_oc_invalido', [
                    'numero_oc' => $request->input('numero_oc'),
                    'message' => $e->getMessage(),
                    'status' => 422,
                ]);

                return response()->json(['message' => $e->getMessage()], 422);
            }
        }
        try {
            $resueltoProveedor = $numeroOc !== ''
                ? $this->resolverProveedorDesdeOrdenCompra($request->cuit_proveedor, $numeroOc)
                : null;

            if ($resueltoProveedor === null) {
                $log->info('recibe_comprobante.proveedor_fallback_cuit', [
                    'numero_oc' => $numeroOc,
                    'cuit_proveedor' => $request->cuit_proveedor,
                ]);
                $resueltoProveedor = $this->resolverProveedorDesdeCuit($request->cuit_proveedor);
                $log->info('recibe_comprobante.proveedor_resuelto_cuit', [
                    'proveedor_id' => $resueltoProveedor['proveedor_id'],
                    'codigo_proveedor' => $resueltoProveedor['codigoProveedor'],
                ]);
            } else {
                $log->info('recibe_comprobante.proveedor_resuelto_oc', [
                    'numero_oc' => $numeroOc,
                    'proveedor_id' => $resueltoProveedor['proveedor_id'],
                    'codigo_proveedor' => $resueltoProveedor['codigoProveedor'],
                ]);
            }
        } catch (\RuntimeException $e) {
            $log->warning('recibe_comprobante.proveedor_error', [
                'message' => $e->getMessage(),
                'numero_oc' => $numeroOc,
                'status' => 422,
            ]);

            return response()->json(['message' => $e->getMessage()], 422);
        }

        $proveedor_id = $resueltoProveedor['proveedor_id'];
        $codigoProveedor = $resueltoProveedor['codigoProveedor'];

        $resolucionEmpresa = app(PrecargaProveedorResolucionSupport::class);
        try {
            $resueltoEmpresa = $resolucionEmpresa->resolverEmpresaPorCuit((string) $request->cuit_empresa);
        } catch (\RuntimeException $e) {
            if ($numeroOc === '') {
                $log->warning('recibe_comprobante.empresa_error', [
                    'cuit_empresa' => $request->cuit_empresa,
                    'message' => $e->getMessage(),
                    'status' => 422,
                ]);

                return response()->json(['message' => $e->getMessage()], 422);
            }
            try {
                $resueltoEmpresa = $resolucionEmpresa->resolverEmpresaPorOc($numeroOc);
                $log->warning('recibe_comprobante.empresa_fallback_oc', [
                    'cuit_empresa' => $request->cuit_empresa,
                    'numero_oc' => $numeroOc,
                    'empresa_id' => $resueltoEmpresa['empresa_id'],
                    'message' => $e->getMessage(),
                ]);
            } catch (\RuntimeException $eOc) {
                $log->warning('recibe_comprobante.empresa_error', [
                    'cuit_empresa' => $request->cuit_empresa,
                    'numero_oc' => $numeroOc,
                    'message' => $e->getMessage(),
                    'status' => 422,
                ]);

                return response()->json(['message' => $e->getMessage()], 422);
            }
        }

        if ($numeroOc !== '') {
            try {
                $empresaOc = $resolucionEmpresa->resolverEmpresaPorOc($numeroOc);
                if ((int) $empresaOc['empresa_id'] !== (int) $resueltoEmpresa['empresa_id']) {
                    $log->warning('recibe_comprobante.empresa_oc_prevalece', [
                        'cuit_empresa' => $request->cuit_empresa,
                        'empresa_cuit_id' => $resueltoEmpresa['empresa_id'],
                        'empresa_oc_id' => $empresaOc['empresa_id'],
                        'numero_oc' => $numeroOc,
                    ]);
                    $resueltoEmpresa = $empresaOc;
                }
            } catch (\RuntimeException) {
                // OC sin empresa local: se mantiene la del CUIT.
            }
        }

        $empresa_id = (int) $resueltoEmpresa['empresa_id'];
        $codigoEmpresa = (string) $resueltoEmpresa['codigo'];

        $log->info('recibe_comprobante.empresa_resuelta', [
            'empresa_id' => $empresa_id,
            'codigo_empresa' => $codigoEmpresa,
            'cuit_empresa' => $request->cuit_empresa,
        ]);

        // Busca tipo de transaccion por tipo de comprobante (abreviatura = prec_tipo en Anita)
        $comprobante = $this->comprobanteService->leeTipoTransaccionCompraPorAbreviatura($request->tipo);

        if (! $comprobante) {
            $log->warning('recibe_comprobante.tipo_comprobante_invalido', [
                'tipo' => $request->tipo,
                'status' => 422,
            ]);

            return response()->json([
                'message' => 'Tipo de comprobante no válido: '.$request->tipo,
            ], 422);
        }

        $tipotransaccion_compra_id = $comprobante->id;
        $tipoAbreviatura = $comprobante->abreviatura ?? $request->tipo;

        $log->info('recibe_comprobante.tipo_comprobante_ok', [
            'tipo_solicitado' => $request->tipo,
            'tipotransaccion_compra_id' => $tipotransaccion_compra_id,
            'tipo_abreviatura' => $tipoAbreviatura,
        ]);

        $lineasConcepto = [];
        foreach ($request->conceptos as $indice => $concepto) {
            try {
                $this->precargaAnitaSync->resolverCodigoConceptoAnita(null, $concepto['id_concepto']);
            } catch (\RuntimeException $e) {
                $log->warning('recibe_comprobante.concepto_anita_error', [
                    'indice' => $indice,
                    'id_concepto' => $concepto['id_concepto'],
                    'message' => $e->getMessage(),
                    'status' => 422,
                ]);

                return response()->json(['message' => $e->getMessage()], 422);
            }

            $concepto_ivacompra = $this->concepto_ivacompraRepository->findPorCodigo($concepto['id_concepto']);
            if (! $concepto_ivacompra) {
                $normalizado = ltrim((string) $concepto['id_concepto'], '0');
                if ($normalizado !== '') {
                    $concepto_ivacompra = $this->concepto_ivacompraRepository->findPorCodigo($normalizado);
                }
            }
            if (! $concepto_ivacompra) {
                $log->warning('recibe_comprobante.concepto_erp_inexistente', [
                    'indice' => $indice,
                    'id_concepto' => $concepto['id_concepto'],
                    'status' => 422,
                ]);

                return response()->json([
                    'message' => 'Concepto IVA compra con código Anita «'.$concepto['id_concepto'].'» no existe en el ERP.',
                ], 422);
            }

            $lineasConcepto[] = [
                'concepto_ivacompra_id' => $concepto_ivacompra->id,
                'codigo_concepto_anita' => $concepto['id_concepto'],
                'monto' => $concepto['importe'],
            ];
        }

        $avisosConceptos = [];
        $revisarPorIibb = false;
        $netoGravado = 0.0;
        $cuadre = ComprobanteProveedorConceptosIvaCoherenciaSupport::cuadreConTotal([], 0.0);
        $totalRequest = round(abs((float) ($request->total ?? 0)), 2);
        $subtotalRequest = round(abs((float) ($request->subtotal ?? 0)), 2);

        try {
            $conceptosPermitidos = ComprobanteProveedorConceptosIvaCoherenciaSupport::idsPermitidosDesdeTipoTransaccion(
                $comprobante
            );
            $lineasConcepto = ComprobanteProveedorConceptosIvaCoherenciaSupport::enriquecerCodigosAnita(
                ComprobanteProveedorConceptosIvaCoherenciaSupport::normalizarYValidar(
                    $lineasConcepto,
                    $conceptosPermitidos
                )
            );

            // Cotejo del concepto IIBB elegido por el agente (BS.AS. vs Capital, u otro régimen).
            $cotejoIibb = app(ComprobanteProveedorConceptosIibbPadronCotejoSupport::class)
                ->cotejar(
                    $lineasConcepto,
                    (int) $empresa_id,
                    (string) $request->fecha_factura,
                    (string) ($request->cuit_empresa ?? ''),
                    $conceptosPermitidos,
                );
            $lineasConcepto = ComprobanteProveedorConceptosIvaCoherenciaSupport::enriquecerCodigosAnita(
                $cotejoIibb['lineas']
            );
            $avisosConceptos = array_merge($cotejoIibb['correcciones'], $cotejoIibb['avisos']);
            $revisarPorIibb = $cotejoIibb['revisar'];

            $netoGravado = ComprobanteProveedorConceptosIvaCoherenciaSupport::netoGravadoDesdeLineas($lineasConcepto);
            $cuadre = ComprobanteProveedorConceptosIvaCoherenciaSupport::cuadreConTotal(
                $lineasConcepto,
                $totalRequest
            );

            // Subtotal de cabecera alineado al neto G tras reparación IVA.
            if ($netoGravado > 0 && (
                $subtotalRequest <= 0
                || abs($netoGravado - $subtotalRequest) > ComprobanteProveedorConceptosIvaCoherenciaSupport::TOLERANCIA
            )) {
                $subtotalRequest = $netoGravado;
            }
        } catch (\RuntimeException $e) {
            $log->warning('recibe_comprobante.coherencia_iva_error', [
                'message' => $e->getMessage(),
                'status' => 422,
            ]);

            return response()->json(['message' => $e->getMessage()], 422);
        }

        // Total que no cuadra o imputación IIBB dudosa: la precarga entra igual, marcada
        // para revisión manual.
        $pararevisar = (bool) $request->para_revisar || $revisarPorIibb;
        if ($cuadre['aplica'] && ! $cuadre['cuadra']) {
            $pararevisar = true;
            $avisosConceptos[] = $cuadre['mensaje'];
            $log->warning('recibe_comprobante.total_no_cuadra', [
                'suma_conceptos' => $cuadre['suma'],
                'total' => $cuadre['total'],
                'diferencia' => $cuadre['diferencia'],
            ]);
        }

        $log->info('recibe_comprobante.conceptos_ok', [
            'cantidad' => count($lineasConcepto),
            'lineas' => $lineasConcepto,
            'suma_conceptos' => $cuadre['suma'],
            'neto_gravado' => $netoGravado,
            'total' => $totalRequest,
            'subtotal' => $subtotalRequest,
            'pararevisar' => $pararevisar,
            'avisos' => $avisosConceptos,
        ]);

        $moneda_id = 1;
        switch(strtoupper($request->moneda))
        {
            case 'PESOS':
                $moneda_id = 1;
                break;
            case 'DOLARES':
                $moneda_id = 2;
                break;
            case 'EUROS':
                $moneda_id = 3;
                break;
        }

        $numeroCae = $this->normalizarTextoOpcional($request->input('numero_cae'));
        $fechaVtoCaiCae = $this->normalizarTextoOpcional($request->input('fecha_vto_cai_cae'));

        $data = [
			'empresa_id' => $empresa_id,
            'codigoempresa' => $codigoEmpresa,
			'proveedor_id' => $proveedor_id,
            'codigoproveedor' => $codigoProveedor,
			'tipotransaccion_compra_id' => $tipotransaccion_compra_id,
            'tipo' => $tipoAbreviatura,
            'letra' => $request->letra,
            'sucursal' => $request->sucursal,
            'numerocomprobante' => $request->numero_factura,
            'fechafactura' => $request->fecha_factura,
            'fecharecepcionemail' => $request->fecha_recepcion_email,
            'fechavencimientocaicae' => $fechaVtoCaiCae,
            'numerocae' => $numeroCae,
            'numeroordencompra' => $numeroOc,
            'rutaalmacenamiento' => $request->ruta_almacenamiento,
            'pararevisar' => $pararevisar,
            'subtotal' => $subtotalRequest,
            'total' => $totalRequest,
            'moneda' => $request->moneda,
            'moneda_id' => $moneda_id,
            'cotizacion' => $request->cotizacion,
            'estado' => 'PENDIENTE',
            'origen_entrada' => \App\Support\Compras\PrecargaComprobanteOrigenEntrada::API,
        ];

        try {
            ComprobanteProveedorUnicidadSupport::assertUnicoPrecarga(
                $empresa_id,
                $tipotransaccion_compra_id,
                (string) $request->letra,
                (int) $request->sucursal,
                (int) $request->numero_factura,
                $proveedor_id,
            );
        } catch (\RuntimeException $e) {
            $log->warning('recibe_comprobante.factura_duplicada', [
                'message' => $e->getMessage(),
                'status' => 422,
            ]);

            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }

        $data['identificacion_proveedor_cuit'] = ComprobanteProveedorUnicidadSupport::resolverCuitDigitos($proveedor_id, null);

        $log->info('recibe_comprobante.grabar_inicio', ['data' => $data]);

        DB::beginTransaction();
        try {
            $precarga_comprobante_proveedor = $this->precarga_comprobante_proveedorRepository->create($data);

            $log->info('recibe_comprobante.cabecera_creada', [
                'precarga_id' => $precarga_comprobante_proveedor->id,
            ]);

            foreach ($lineasConcepto as $linea) {
                $this->precarga_comprobante_proveedor_conceptoRepository->create([
                    'precarga_comprobante_proveedor_id' => $precarga_comprobante_proveedor->id,
                    'concepto_ivacompra_id' => $linea['concepto_ivacompra_id'],
                    'codigo_concepto_anita' => $linea['codigo_concepto_anita'],
                    'monto' => $linea['monto'],
                ]);
            }

            DB::commit();

            $log->info('recibe_comprobante.ok', [
                'precarga_id' => $precarga_comprobante_proveedor->id,
                'status' => 201,
            ]);

            return response()->json([
                'id' => $precarga_comprobante_proveedor->id,
                'message' => 'Precarga registrada en ERP y sincronizada con Anita (compras).',
                'pararevisar' => $pararevisar,
                'avisos' => $avisosConceptos,
            ], 201);
        } catch (\Throwable $e) {
            DB::rollBack();

            $log->error('recibe_comprobante.grabar_fallo', [
                'status' => 422,
            ], $e);

            return response()->json([
                'errores' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Resuelve el proveedor usando el código de la OC (penmp_proveedor).
     * Devuelve null si la OC no existe (para permitir fallback por CUIT).
     *
     * @return array{proveedor_id: int, codigoProveedor: string}|null
     */
    private function resolverProveedorDesdeOrdenCompra(string $cuit, string $numeroOc): ?array
    {
        $ordencompra = $this->ordencompraService->leeOrdenCompra($numeroOc);
        if ($ordencompra === 'OC inexistente') {
            return null;
        }

        $datosOrdenCompra = $ordencompra['ordencompra'];
        $cuitOrdenCompra = str_replace('-', '', (string) ($datosOrdenCompra->prom_cuit ?? ''));
        $cuitNormalizado = str_replace('-', '', $cuit);

        if (! PrecargaProveedorCuitCoincidenciaSupport::coinciden($cuitOrdenCompra, $cuitNormalizado)) {
            throw new \RuntimeException('OC no corresponde con el CUIT indicado');
        }

        $codigoProveedorOc = ltrim((string) ($datosOrdenCompra->penmp_proveedor ?? ''), '0');
        if ($codigoProveedorOc === '') {
            throw new \RuntimeException('La orden de compra no tiene proveedor asignado');
        }

        $proveedor = $this->proveedorRepository->findPorCodigo($codigoProveedorOc);
        if (! $proveedor) {
            throw new \RuntimeException(
                'Proveedor de la OC (código '.$codigoProveedorOc.') no existe en el ERP'
            );
        }

        if (! $this->proveedorEstaActivo($proveedor)) {
            throw new \RuntimeException(
                'Proveedor de la OC (código '.$codigoProveedorOc.') no está activo'
            );
        }

        return [
            'proveedor_id' => (int) $proveedor->id,
            'codigoProveedor' => (string) $proveedor->codigo,
        ];
    }

    /**
     * @return array{proveedor_id: int, codigoProveedor: string}
     */
    private function resolverProveedorDesdeCuit(string $cuit): array
    {
        $proveedor_id = 1;
        $codigoProveedor = '000001';

        foreach ($this->variantesDocumentoCuit($cuit) as $numerodocumento) {
            $proveedores = $this->proveedorRepository->findPorDocumento($numerodocumento);
            if (! $proveedores || $proveedores->isEmpty()) {
                continue;
            }

            foreach ($proveedores as $proveedor) {
                if ($this->proveedorEstaActivo($proveedor)) {
                    return [
                        'proveedor_id' => (int) $proveedor->id,
                        'codigoProveedor' => (string) $proveedor->codigo,
                    ];
                }
            }
        }

        return compact('proveedor_id', 'codigoProveedor');
    }

    private function proveedorEstaActivo(object $proveedor): bool
    {
        return in_array($proveedor->estado, ['0', 'Activo', '3', 'Regularizado'], true);
    }

    /**
     * @return list<string>
     */
    private function variantesDocumentoCuit(string $cuit): array
    {
        $cuit = trim($cuit);
        $sinGuiones = str_replace('-', '', $cuit);
        $variantes = [$cuit];

        if ($sinGuiones !== $cuit) {
            $variantes[] = $sinGuiones;
        }

        if (strlen($sinGuiones) === 11 && ctype_digit($sinGuiones)) {
            $conGuiones = substr($sinGuiones, 0, 2).'-'.substr($sinGuiones, 2, 8).'-'.substr($sinGuiones, 10, 1);
            if (! in_array($conGuiones, $variantes, true)) {
                $variantes[] = $conGuiones;
            }
        }

        return array_values(array_unique($variantes));
    }

    private function normalizarTextoOpcional(mixed $valor): ?string
    {
        if ($valor === null) {
            return null;
        }

        $texto = trim((string) $valor);

        return $texto === '' ? null : $texto;
    }

    /**
     * Convierte sucursal / número de factura leídos literal de la factura
     * (ej. "0014", "00037186") a entero para grabar en el ERP.
     */
    private function normalizarEnteroComprobante(mixed $valor): ?int
    {
        if (is_int($valor)) {
            return $valor;
        }

        if (is_float($valor)) {
            return (int) $valor;
        }

        $texto = trim((string) $valor);
        if ($texto === '') {
            return null;
        }

        if (! preg_match('/^\d+$/', $texto)) {
            return null;
        }

        return (int) $texto;
    }
}
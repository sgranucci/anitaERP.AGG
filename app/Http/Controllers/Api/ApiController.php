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
use DB;

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
        $respuesta = [];
        $conceptos = [];
        $flError = false;

        // Busca la orden de compra 
        $ordencompra = $this->ordencompraService->leeOrdenCompra($numeroOc);

        if ($ordencompra == 'OC inexistente')
        {
            $status = 404;
            $message = $ordencompra;
            $flError = true;
        }

        if (!$flError)
        {
            $datosOrdenCompra = $ordencompra['ordencompra'];
            $itemsOrdenCompra = $ordencompra['item'];

            $cuitOrdenCompra = str_replace("-", "", $datosOrdenCompra->prom_cuit);
            $cuitProveedor = str_replace("-", "", $cuitProveedor);
            $letraProveedor = $datosOrdenCompra->prom_letra;

            if ($cuitOrdenCompra != $cuitProveedor)
            {
                $status = 404;
                $message = "OC no corresponde con el CUIT indicado";
                $flError = true;
            }

            if (!$flError)
            {
                $centroCostoDestino = $datosOrdenCompra->penmp_ccosto_dest;

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
                    }

                    if (!$flError)
                    {
                        // Verifica el tipo de item
                        $tipoItem = 'B';
                        foreach($itemsOrdenCompra as $item)
                        {
                            if ($item->stkm_tipo_articulo == 'S')
                                $tipoItem = 'S';
                            if ($item->stkm_agrupacion == '0081')
                                $tipoItem = 'L';
                            if ($item->stkm_tipo_articulo == 'U')
                                $tipoItem = 'U';
                        }

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

                        // Busca los conceptos en base al tipo de comprobante
                        $comprobante = $this->comprobanteService->leeTipoTransaccionCompraPorAbreviatura($abreviatura); 

                        if (count($comprobante->tipotransaccion_compra_concepto_ivacompras) > 0)
                        {
                            foreach ($comprobante->tipotransaccion_compra_concepto_ivacompras as $concepto)
                            {
                                $conceptos[] = [
                                    'id_concepto' => $concepto->concepto_ivacompras->codigo,
                                    'nombre' => $concepto->concepto_ivacompras->nombre,
                                    'descripcion_ai' => $concepto->concepto_ivacompras->nombre_ia ?? $concepto->concepto_ivacompras->nombre
                                ];
                            }
                        }

                        $respuesta[] = [
                            'tipocomprobante' => $abreviatura,
                            'letra' => $letraProveedor,
                            'concepto' => $conceptos
                        ];

                        $status = 200;
                        $message = "devuelve lista de conceptos";
                    }
                }
                else
                {
                    $status = 404;
                    $message = "No existe centro de costo de la OC";
                    $flError = true;                    
                }
            }
        }

        if ($status == 200)
            return response()->json([
                "respuesta" => $respuesta], $status);
        else
            return response()->json([
                "message" => $message,
                ], $status);
    }

    public function recibeComprobante(Request $request)
    {
        $request->validate([
            'cuit_proveedor' => 'required|string|max:15',
            'cuit_empresa' => 'required|string|max:15',
            'tipo' => 'required|string|max:10',
            'conceptos' => 'required|array|min:1',
            'conceptos.*.id_concepto' => 'required',
            'conceptos.*.importe' => 'required|numeric',
        ]);

        $numeroOc = trim((string) $request->input('numero_oc', ''));
        try {
            $resueltoProveedor = $numeroOc !== ''
                ? $this->resolverProveedorDesdeOrdenCompra($request->cuit_proveedor, $numeroOc)
                : null;

            if ($resueltoProveedor === null) {
                $resueltoProveedor = $this->resolverProveedorDesdeCuit($request->cuit_proveedor);
            }
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $proveedor_id = $resueltoProveedor['proveedor_id'];
        $codigoProveedor = $resueltoProveedor['codigoProveedor'];

        // Busca empresa por documento
        $empresas = $this->empresaRepository->findPorDocumento($request->cuit_empresa);

        $empresa_id = 1;
        $codigoEmpresa = '1';
        if ($empresas)
        {
            foreach ($empresas as $empresa)
            {
                if ($empresa->codigo < '5')
                {
                    $empresa_id = $empresa->id;
                    $codigoEmpresa = $empresa->codigo;
                }
            }        
        }
        else
        {
            // Lo busca sin guiones
            $numerodocumento =  str_replace("-", "", $request->cuit_empresa); 

            $empresas = $this->empresaRepository->findPorDocumento($numerodocumento);

            if ($empresas)
            {
                foreach ($empresas as $empresa)
                {
                    if ($empresa->codigo < '5')
                    {
                        $empresa_id = $empresa->id;
                        $codigoEmpresa = $empresa->codigo;
                    }
                }
            }
        }        

        // Busca tipo de transaccion por tipo de comprobante (abreviatura = prec_tipo en Anita)
        $comprobante = $this->comprobanteService->leeTipoTransaccionCompraPorAbreviatura($request->tipo);

        if (! $comprobante) {
            return response()->json([
                'message' => 'Tipo de comprobante no válido: '.$request->tipo,
            ], 422);
        }

        $tipotransaccion_compra_id = $comprobante->id;
        $tipoAbreviatura = $comprobante->abreviatura ?? $request->tipo;

        $lineasConcepto = [];
        foreach ($request->conceptos as $concepto) {
            try {
                $this->precargaAnitaSync->resolverCodigoConceptoAnita(null, $concepto['id_concepto']);
            } catch (\RuntimeException $e) {
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
            'fechavencimientocaicae' => $request->fecha_vto_cai_cae,
            'numerocae' => $request->numero_cae,
            'numeroordencompra' => $request->numero_oc,
            'rutaalmacenamiento' => $request->ruta_almacenamiento,
            'pararevisar' => $request->para_revisar,
            'subtotal' => $request->subtotal,
            'total' => $request->total,
            'moneda' => $request->moneda,
            'moneda_id' => $moneda_id,
            'cotizacion' => $request->cotizacion,
            'estado' => 'PENDIENTE'
        ];

        DB::beginTransaction();
        try {
            $precarga_comprobante_proveedor = $this->precarga_comprobante_proveedorRepository->create($data);

            foreach ($lineasConcepto as $linea) {
                $this->precarga_comprobante_proveedor_conceptoRepository->create([
                    'precarga_comprobante_proveedor_id' => $precarga_comprobante_proveedor->id,
                    'concepto_ivacompra_id' => $linea['concepto_ivacompra_id'],
                    'codigo_concepto_anita' => $linea['codigo_concepto_anita'],
                    'monto' => $linea['monto'],
                ]);
            }

            DB::commit();

            return response()->json([
                'id' => $precarga_comprobante_proveedor->id,
                'message' => 'Precarga registrada en ERP y sincronizada con Anita (compras).',
            ], 201);
        } catch (\Throwable $e) {
            DB::rollBack();

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

        if ($cuitOrdenCompra !== $cuitNormalizado) {
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
        return in_array($proveedor->estado, ['0', 'Activo'], true);
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
}
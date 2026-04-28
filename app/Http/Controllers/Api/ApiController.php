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

	public function __construct(OrdencompraService $ordencompraService,
                                CentrocostoRepositoryInterface $centrocostorepository,
                                ComprobanteService $comprobanteService,
                                Precarga_Comprobante_ProveedorRepositoryInterface $precarga_comprobante_proveedorRepository,
                                Precarga_Comprobante_Proveedor_ConceptoRepositoryInterface $precarga_comprobante_proveedor_conceptoRepository,
                                ProveedorRepositoryInterface $proveedorRepository,
                                Concepto_IvacompraRepositoryInterface $concepto_ivacompraRepository,
                                EmpresaRepositoryInterface $empresaRepository,
                                MonedaRepositoryInterface $monedaRepository)
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
        // Validar los datos recibidos
        $validated = $request->validate([
            'cuit_proveedor' => 'required|string|max:15',
            'cuit_empresa' => 'required|string|max:15',
        ]);

        // Busca proveedor por documento
        $proveedores = $this->proveedorRepository->findPorDocumento($request->cuit_proveedor);

        $proveedor_id = 1;
        $codigoProveedor = '000001';
        if ($proveedores)
        {
            foreach ($proveedores as $proveedor)
            {
                if ($proveedor->estado == '0')
                {
                    $proveedor_id = $proveedor->id;
                    $codigoProveedor = $proveedor->codigo;
                }
            }        
        }
        else
        {
            // Lo busca sin guiones
            $numerodocumento =  str_replace("-", "", $request->cuit_proveedor); 

            $proveedores = $this->proveedorRepository->findPorDocumento($numerodocumento);

            if ($proveedores)
            {
                foreach ($proveedores as $proveedor)
                {
                    if ($proveedor->estado == '0')
                    {
                        $proveedor_id = $proveedor->id;
                        $codigoProveedor = $proveedor->codigo;
                    }
                }
            }
        }

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

        // Busca tipo de transaccion por tipo de comprobante
        $comprobante = $this->comprobanteService->leeTipoTransaccionCompraPorAbreviatura($request->tipo);

        $tipotransaccion_compra_id = null;
        if ($comprobante)
            $tipotransaccion_compra_id = $comprobante->id;

        $conceptos = $request->conceptos;

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
            'tipo' => $request->tipo,
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

        // Realiza grabacion de precarga
        DB::beginTransaction();
        try
        {        
            $precarga_comprobante_proveedor = $this->precarga_comprobante_proveedorRepository->create($data);

            // Realiza grabacion de cada concepto
            $conceptos = $request->conceptos;

            foreach ($conceptos as $concepto)
            {
                // Busca el codigo de anita del concepto
                $concepto_ivacompra = $this->concepto_ivacompraRepository->findPorCodigo($concepto['id_concepto']);

                $concepto_ivacompra_id = null;
                if ($concepto_ivacompra)
                    $concepto_ivacompra_id = $concepto_ivacompra->id;

                $data = [
                    'precarga_comprobante_proveedor_id' => $precarga_comprobante_proveedor->id,
                    'concepto_ivacompra_id' => $concepto_ivacompra_id,
                    'monto' => $concepto['importe']
                ];
                $this->precarga_comprobante_proveedor_conceptoRepository->create($data);
            }
            DB::commit();
        } catch (\Exception $e) {
            DB::rollback();

            return ['errores' => $e->getMessage()];
        }        
    }
}
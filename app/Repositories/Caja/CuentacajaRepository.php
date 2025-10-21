<?php

namespace App\Repositories\Caja;

use App\Models\Caja\Cuentacaja;
use App\Models\Contable\Cuentacontable;
use App\Models\Configuracion\Empresa;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\Repositories\Caja\BancoRepositoryInterface;
use App\ApiAnita;
use Auth;
use DB;
use Carbon\Carbon;
use Exception;

class CuentacajaRepository implements CuentacajaRepositoryInterface
{
    protected $model;
    protected $tableAnita = 'tesmae';
    protected $keyField = 'codigo';
    protected $keyFieldAnita = 'tesm_cuenta';

	private $bancoRepository;

    /**
     * PostRepository constructor.
     *
     * @param Post $post
     */
    public function __construct(Cuentacaja $cuentacaja,
                                BancoRepositoryInterface $bancorepository)
    {
        $this->model = $cuentacaja;
        $this->bancoRepository = $bancorepository;
    }

    public function all()
    {
        $hay_cuentacaja = Cuentacaja::first();

        if (!$hay_cuentacaja)
            self::sincronizarConAnita();

        return $this->model->with('empresas')->with('cuentacontables')->with("bancos")->get();
    }

    public function create(array $data)
    {
        DB::beginTransaction();
        try 
        {
            $cuentacaja = $this->model->create($data);

            // Graba anita
		    $anita = self::guardarAnita($data);

            if (strpos($anita, 'Error') !== false)
                throw new Exception('No pudo grabar ANITA');

            DB::commit();

        } catch (\Exception $e) {

            DB::rollback();

            dd($e->getMessage());

            return ['error' => $e->getMessage()];
        }
        return($cuentacaja);
    }

    public function update(array $data, $id)
    {
        DB::beginTransaction();
        try 
        {
            $cuentacaja = $this->model->findOrFail($id)->update($data);

            // Actualiza anita
		    $anita = self::actualizarAnita($data, $data['codigo']);

            if (strpos($anita, 'Error') !== false)
                throw new Exception('No pudo grabar ANITA');

            DB::commit();

        } catch (\Exception $e) {
            
            DB::rollback();

            dd($e->getMessage());
            return ['error' => $e->getMessage()];
        }
        return($cuentacaja);
    }

    public function delete($id)
    {
        DB::beginTransaction();
        try 
        {
    	    $cuentacaja = $this->model->find($id);
        		
		    // Elimina anita
		    $anita = self::eliminarAnita($cuentacaja->codigo);

            $cuentacaja = $this->model->destroy($id);

            if (strpos($anita, 'Error') !== false)
                throw new Exception('No pudo grabar ANITA');

            DB::commit();   

        } catch (\Exception $e) {
            
            DB::rollback();

            return ['error' => $e->getMessage()];
        }
		return $cuentacaja;
    }

    public function find($id)
    {
        if (null == $cuentacaja = $this->model->find($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $cuentacaja;
    }

    public function findOrFail($id)
    {
        if (null == $cuentacaja = $this->model->findOrFail($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $cuentacaja;
    }

    public function findPorCodigo($codigo)
    {
        return $this->model->where('codigo', $codigo)->first();
    }

    public function sincronizarConAnita(){
		ini_set('max_execution_time', '300');

        $apiAnita = new ApiAnita();
        $data = array( 'acc' => 'list', 
						'sistema' => 'che_ban',
						'campos' => "$this->keyFieldAnita as $this->keyField, $this->keyFieldAnita", 
						'tabla' => $this->tableAnita );
        $dataAnita = json_decode($apiAnita->apiCall($data));

        $datosLocal = Cuentacaja::all();
        $datosLocalArray = [];
        foreach ($datosLocal as $value) {
            $datosLocalArray[] = $value->{$this->keyField};
        }

        foreach ($dataAnita as $value) {
            if (!in_array(ltrim($value->{$this->keyField}, '0'), $datosLocalArray)) {
                $this->traerRegistroDeAnita($value->{$this->keyFieldAnita});
            }
        }
    }

    public function traerRegistroDeAnita($key){
        $apiAnita = new ApiAnita();
        $data = array( 
            'acc' => 'list', 'tabla' => $this->tableAnita, 
			'sistema' => 'che_ban',
            'campos' => '
                    tesm_cuenta,
                    tesm_codigo_banco,
                    tesm_desc,
                    tesm_tipo_cuenta, 
                    tesm_saldo_aper,  
                    tesm_fecha_aper,  
                    tesm_descubierto, 
                    tesm_nro_boleta,  
                    tesm_cta_contable,
                    tesm_cod_mon,
                    tesm_cta_destino,
                    tesm_fl_boleta_cl,
                    tesm_empresa 
			',
            'whereArmado' => " WHERE ".$this->keyFieldAnita." = '".$key."' " 
        );
        $dataAnita = json_decode($apiAnita->apiCall($data));
		$usuario_id = Auth::user()->id;

        if (count($dataAnita) > 0) {
            $data = $dataAnita[0];

            Self::convierteDatosDeAnita($data, $cuentacontable_id, $banco_id, $empresa_id, $tipoCuenta);

            $apiAnita = new ApiAnita();
            $datac = array( 
                'acc' => 'list', 'tabla' => 'tesmcbu', 
                'sistema' => 'che_ban',
                'campos' => '
                        tesmc_cuenta,
                        tesmc_nro_cbu
                ',
                'whereArmado' => " WHERE tesmc_cuenta = '".$data->tesm_cuenta."' " 
            );            
            $dataAnita = json_decode($apiAnita->apiCall($datac));

            if (isset($dataAnita[0]))
                $numeroCbu = $dataAnita[0]->tesmc_nro_cbu;
            else
                $numeroCbu = '';

            $arr_campos = [
                "nombre" => $data->tesm_desc,
                "codigo" => ltrim($data->tesm_cuenta, '0'),
                "tipocuenta" => $tipoCuenta,
                "banco_id" => $banco_id,
                "empresa_id" => $empresa_id,
                "cuentacontable_id" => $cuentacontable_id,
                "moneda_id" => $data->tesm_cod_mon,
                "cbu" => $numeroCbu
                ];

            $this->model->create($arr_campos);
        }
    }

	public function guardarAnita($request) {
        $apiAnita = new ApiAnita();
        
        Self::convierteDatosParaAnita($request, $banco, $tipoCuenta, $fecha, $cuentaContable, $empresa, $moneda);

        $data = array( 'tabla' => $this->tableAnita, 'acc' => 'insert',
			'sistema' => 'che_ban',
            'campos' => ' 
                tesm_cuenta,
                tesm_codigo_banco,
                tesm_desc,
                tesm_tipo_cuenta, 
                tesm_saldo_aper,  
                tesm_fecha_aper,  
                tesm_descubierto, 
                tesm_nro_boleta,  
                tesm_cta_contable,
                tesm_cod_mon,
                tesm_cta_destino,
                tesm_fl_boleta_cl,
                tesm_empresa 
				',
            'valores' => " 
				'".str_pad($request['codigo'], 8, "0", STR_PAD_LEFT)."', 
				'".$banco."',
				'".$request['nombre']."',
				'".$tipoCuenta."',
				'".'0'."',
				'".$fecha."',
				'".'0'."',
				'".'0'."',
				'".$cuentaContable."',
                '".$moneda."',
                '0',
                '0',
				'".$empresa."' "
        );
        $anita = $apiAnita->apiCall($data);

        $data = array( 'tabla' => 'tesmcbu', 'acc' => 'insert',
			'sistema' => 'che_ban',
            'campos' => ' 
                tesmc_cuenta,
                tesmc_nro_cbu
                ',
            'valores' => "
                '".str_pad($request['codigo'], 8, "0", STR_PAD_LEFT)."', 
                '".$request['cbu']."' "
        );
        $anita2 = $apiAnita->apiCall($data);

        return $anita && $anita2;
	}

	public function actualizarAnita($request, $id) {
        $apiAnita = new ApiAnita();

        Self::convierteDatosParaAnita($request, $banco, $tipoCuenta, $fecha, $cuentaContable, $empresa, $moneda);

		$data = array( 'acc' => 'update', 'tabla' => $this->tableAnita, 
				'sistema' => 'che_ban',
				'valores' => " 
                        tesm_cuenta 	                = '".str_pad($request['codigo'], 8, "0", STR_PAD_LEFT)."' ,
                        tesm_codigo_banco               = '".$banco."' ,
                        tesm_desc    	                = '".$request['nombre']."' ,
                        tesm_cta_contable               = '".$cuentaContable."' ,
                        tesm_cod_mon                    = '".$moneda."' ,
                        tesm_nro_cbu                    = '".$request['cbu']."' ,
                        tesm_empresa 	                = '".$empresa."' "
				,
				'whereArmado' => " WHERE tesm_cuenta = '".str_pad($request['codigo'], 8, "0", STR_PAD_LEFT)."' " );
        $anita = $apiAnita->apiCall($data);

        $data = array( 'tabla' => 'tesmcbu', 'acc' => 'update',
			'sistema' => 'che_ban',
            'valores' => "
                tesmc_cuenta   = '".str_pad($request['codigo'], 8, "0", STR_PAD_LEFT)."' ,
                tesmc_nro_cbu  = '".$request['cbu']."' "
            ,
            'whereArmado' => " WHERE tesmc_cuenta = '".str_pad($request['codigo'], 8, "0", STR_PAD_LEFT)."' " );
        $anita2 = $apiAnita->apiCall($data);

        return $anita && $anita2;
	}

	public function eliminarAnita($id) {
        $apiAnita = new ApiAnita();
        $data = array( 'acc' => 'delete', 'tabla' => $this->tableAnita, 
				'sistema' => 'che_ban',
				'whereArmado' => " WHERE tesm_cuenta = '".str_pad($id, 8, "0", STR_PAD_LEFT)."' " );
        $anita = $apiAnita->apiCall($data);

        $data = array( 'acc' => 'delete', 'tabla' => 'tesmcbu',
				'sistema' => 'che_ban',
				'whereArmado' => " WHERE tesmc_cuenta = '".str_pad($id, 8, "0", STR_PAD_LEFT)."' " );
        $anita2 = $apiAnita->apiCall($data);        
        
        return $anita&&$anita2;
	}

    private function convierteDatosDeAnita($data, &$cuentacontable_id, &$banco_id, &$empresa_id, &$tipocuenta)
    {
        $cuenta = Cuentacontable::select('id', 'codigo')->where('codigo' , $data->tesm_cta_contable)->first();
        if ($cuenta)
            $cuentacontable_id = $cuenta->id;
        else
            $cuentacontable_id = null;
        // Busca el banco
        $banco = $this->bancoRepository->findPorCodigo($data->tesm_codigo_banco);
        if ($banco)
            $banco_id = $banco->id;
        else    
            $banco_id = null;
        $empresa = Empresa::select('id', 'codigo')->where('codigo' , $data->tesm_empresa)->first();
        if ($empresa)
            $empresa_id = $empresa->id;
        else
            $empresa_id = NULL;
        if (substr($data->tesm_desc, 0, 1) == 'R')
            $tipocuenta = 'R';
        else    
            $tipocuenta = 'V';
    }

    private function convierteDatosParaAnita($data, &$banco, &$tipoCuenta, &$fecha, &$cuentaContable, &$empresa, &$moneda)
    {
        $fecha = Carbon::now();
		$fecha = $fecha->format('Ymd');

        // Busca el banco
        $banco = ' ';
        if (isset($data['banco_id']))
        {
            $banco = $this->bancoRepository->find($data['banco_id']);
            if ($banco)
                $banco = $banco->codigo;
            else    
                $banco = ' ';
        }
        // Busca la cuenta
        $cuenta = Cuentacontable::select('id', 'codigo')->where('id' , $data['cuentacontable_id'])->first();
        if ($cuenta)
            $cuentaContable = $cuenta->codigo;
        else
            $cuentaContable = '000000-000';
        // Busca la empresa
        if (isset($data['empresa_id']))
        {
            $empresa = Empresa::select('id', 'codigo')->where('id' , $data['empresa_id'])->first();
            if ($empresa)
                $empresa = $empresa->codigo;
            else
                $empresa = '0';
        }

        $moneda = $data['moneda_id'];
    }
}

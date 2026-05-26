<?php

namespace App\Repositories\Caja;

use App\Models\Caja\Cuentacaja;
use App\Models\Contable\Cuentacontable;
use App\Models\Configuracion\Empresa;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\Repositories\Caja\BancoRepositoryInterface;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
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
    private $empresaRepository;

    /**
     * PostRepository constructor.
     *
     * @param Post $post
     */
    public function __construct(Cuentacaja $cuentacaja,
                                BancoRepositoryInterface $bancorepository,
                                EmpresaRepositoryInterface $empresarepository)
    {
        $this->model = $cuentacaja;
        $this->bancoRepository = $bancorepository;
        $this->empresaRepository = $empresarepository;
    }

    public function all()
    {
        $hay_cuentacaja = Cuentacaja::first();

        if (!$hay_cuentacaja) {
            self::sincronizarConAnita();
        } elseif (self::usaTablaTesmcbu()) {
            self::sincronizarCbuConAnita();
        }

        $query = $this->model->with('empresas')->with('cuentacontables')->with('bancos')->with('usocuentacajas');
        $this->aplicarFiltroEmpresasAsignadas($query);

        return $query->get();
    }

    private function aplicarFiltroEmpresasAsignadas($query): void
    {
        $empresasAsignadas = $this->empresaRepository->traeEmpresasAsignadas();

        if (count($empresasAsignadas) > 1) {
            $query->where(function ($q) use ($empresasAsignadas) {
                $q->whereIn('empresa_id', $empresasAsignadas)
                    ->orWhereNull('empresa_id');
            });
        }
    }

    public function create(array $data)
    {
        $usoIds = array_key_exists('usocuentacaja_ids', $data)
            ? array_filter((array) ($data['usocuentacaja_ids'] ?? []))
            : [];
        unset($data['usocuentacaja_ids']);

        DB::beginTransaction();
        try 
        {
            $cuentacaja = $this->model->create($data);
            $cuentacaja->usocuentacajas()->sync($usoIds);

            // Graba anita
		    $anita = self::guardarAnita($data);


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
        $usoIds = null;
        if (array_key_exists('usocuentacaja_ids', $data)) {
            $usoIds = array_filter((array) ($data['usocuentacaja_ids'] ?? []));
            unset($data['usocuentacaja_ids']);
        }

        DB::beginTransaction();
        try 
        {
            $cuentacaja = $this->model->findOrFail($id);
            $cuentacaja->update($data);
            if (is_array($usoIds)) {
                $cuentacaja->usocuentacajas()->sync($usoIds);
            }

            // Actualiza anita
		    $anita = self::actualizarAnita($data, $data['codigo']);


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


            DB::commit();   

        } catch (\Exception $e) {
            
            DB::rollback();

            return ['error' => $e->getMessage()];
        }
		return $cuentacaja;
    }

    public function find($id)
    {
        return $this->model->find($id);
    }

    public function findOrFail($id)
    {
        if (null == $cuentacaja = $this->model->with('usocuentacajas')->findOrFail($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $cuentacaja;
    }

    public function findPorCodigo($codigo)
    {
        return $this->model->where('codigo', $codigo)->first();
    }

    public function sincronizarConAnita(?string $codigo = null, bool $sincronizarCbu = true): array
    {
		ini_set('max_execution_time', '300');

        $ret = [
            'en_anita' => 0,
            'importados' => 0,
            'omitidos' => 0,
            'errores' => [],
        ];

        if ($codigo !== null && $codigo !== '') {
            $codigoNorm = ltrim($codigo, '0');
            if ($this->model->where('codigo', $codigoNorm)->exists()) {
                $ret['en_anita'] = 1;
                $ret['omitidos'] = 1;

                return $ret;
            }

            $ret['en_anita'] = 1;
            try {
                $estado = $this->traerRegistroDeAnita(
                    str_pad($codigoNorm, 8, '0', STR_PAD_LEFT)
                );
                if ($estado === 'importado') {
                    $ret['importados'] = 1;
                } else {
                    $ret['errores'][] = "Cuenta Anita {$codigoNorm}: {$estado}.";
                }
            } catch (\Throwable $e) {
                $ret['errores'][] = "Cuenta Anita {$codigoNorm}: ".$e->getMessage();
            }

            return $ret;
        }

        $apiAnita = new ApiAnita();
        $data = array( 'acc' => 'list', 
						'sistema' => 'che_ban',
						'campos' => "$this->keyFieldAnita as $this->keyField, $this->keyFieldAnita", 
						'tabla' => $this->tableAnita );
        $dataAnita = json_decode($apiAnita->apiCall($data));

        if (! is_array($dataAnita)) {
            $ret['errores'][] = 'Anita no devolvió un listado válido de tesmae.';

            return $ret;
        }

        $ret['en_anita'] = count($dataAnita);

        $datosLocal = Cuentacaja::pluck($this->keyField)->all();

        foreach ($dataAnita as $value) {
            $codigoLocal = ltrim($value->{$this->keyField}, '0');
            if (in_array($codigoLocal, $datosLocal, true)) {
                $ret['omitidos']++;
                continue;
            }

            try {
                $estado = $this->traerRegistroDeAnita($value->{$this->keyFieldAnita});
                if ($estado === 'importado') {
                    $ret['importados']++;
                    $datosLocal[] = $codigoLocal;
                } else {
                    $ret['errores'][] = "Cuenta Anita {$codigoLocal}: {$estado}.";
                }
            } catch (\Throwable $e) {
                $ret['errores'][] = "Cuenta Anita {$codigoLocal}: ".$e->getMessage();
            }
        }

        if ($sincronizarCbu && self::usaTablaTesmcbu()) {
            self::sincronizarCbuConAnita();
        }

        return $ret;
    }

    public function sincronizarCbuConAnita(?string $codigo = null): array
    {
        $ret = [
            'en_anita' => 0,
            'actualizados' => 0,
            'sin_cuenta_local' => 0,
            'sin_cambios' => 0,
        ];

        if (! self::usaTablaTesmcbu()) {
            return $ret;
        }

        $apiAnita = new ApiAnita();
        $data = [
            'acc' => 'list',
            'tabla' => 'tesmcbu',
            'sistema' => 'che_ban',
            'campos' => 'tesmc_cuenta, tesmc_nro_cbu',
        ];
        if ($codigo !== null && $codigo !== '') {
            $data['whereArmado'] = " WHERE tesmc_cuenta = '".str_pad(ltrim($codigo, '0'), 8, '0', STR_PAD_LEFT)."' ";
        }
        $dataAnita = json_decode($apiAnita->apiCall($data));

        if (! is_array($dataAnita)) {
            return $ret;
        }

        $ret['en_anita'] = count($dataAnita);

        foreach ($dataAnita as $row) {
            $codigoCuenta = ltrim($row->tesmc_cuenta ?? '', '0');
            if ($codigoCuenta === '') {
                continue;
            }

            $cbu = trim($row->tesmc_nro_cbu ?? '');
            $cuentacaja = $this->model->where('codigo', $codigoCuenta)->first();
            if ($cuentacaja === null) {
                $ret['sin_cuenta_local']++;
                continue;
            }
            if ($cuentacaja->cbu === $cbu) {
                $ret['sin_cambios']++;
                continue;
            }

            $cuentacaja->update(['cbu' => $cbu]);
            $ret['actualizados']++;
        }

        return $ret;
    }

    public function traerRegistroDeAnita($key): string
    {
        $apiAnita = new ApiAnita();
        $camposTesmae = '
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
                    tesm_empresa';
        if (! self::usaTablaTesmcbu()) {
            $camposTesmae .= ',
                    tesm_nro_cbu';
        }
        $data = array( 
            'acc' => 'list', 'tabla' => $this->tableAnita, 
			'sistema' => 'che_ban',
            'campos' => $camposTesmae,
            'whereArmado' => " WHERE ".$this->keyFieldAnita." = '".$key."' " 
        );
        $dataAnita = json_decode($apiAnita->apiCall($data));

        if (! is_array($dataAnita) || count($dataAnita) === 0) {
            return 'no_encontrado';
        }

        $data = $dataAnita[0];

        Self::convierteDatosDeAnita($data, $cuentacontable_id, $banco_id, $empresa_id, $tipoCuenta);

        $numeroCbu = self::leerCbuDesdeAnita($apiAnita, $data->tesm_cuenta, $data);

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

        return 'importado';
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
        $anita = $apiAnita->apiCallEscritura($data);

        if (self::usaTablaTesmcbu()) {
            self::guardarTesmcbuAnita($apiAnita, $request);
        }

        return (bool) $anita;
	}

	public function actualizarAnita($request, $id) {
        $apiAnita = new ApiAnita();

        Self::convierteDatosParaAnita($request, $banco, $tipoCuenta, $fecha, $cuentaContable, $empresa, $moneda);

        $valoresTesmae = "
                        tesm_cuenta 	                = '".str_pad($request['codigo'], 8, "0", STR_PAD_LEFT)."' ,
                        tesm_codigo_banco               = '".$banco."' ,
                        tesm_desc    	                = '".$request['nombre']."' ,
                        tesm_cta_contable               = '".$cuentaContable."' ,
                        tesm_cod_mon                    = '".$moneda."' ,
                        tesm_empresa 	                = '".$empresa."' ";
        if (! self::usaTablaTesmcbu()) {
            $valoresTesmae .= ",
                        tesm_nro_cbu                    = '".($request['cbu'] ?? '')."' ";
        }

		$data = array( 'acc' => 'update', 'tabla' => $this->tableAnita, 
				'sistema' => 'che_ban',
				'valores' => $valoresTesmae,
				'whereArmado' => " WHERE tesm_cuenta = '".str_pad($request['codigo'], 8, "0", STR_PAD_LEFT)."' " );
        $anita = $apiAnita->apiCallEscritura($data);

        if (self::usaTablaTesmcbu()) {
            self::sincronizarTesmcbuAnita($apiAnita, $request);
        }

        return (bool) $anita;
	}

	public function eliminarAnita($id) {
        $apiAnita = new ApiAnita();
        $data = array( 'acc' => 'delete', 'tabla' => $this->tableAnita, 
				'sistema' => 'che_ban',
				'whereArmado' => " WHERE tesm_cuenta = '".str_pad($id, 8, "0", STR_PAD_LEFT)."' " );
        $anita = $apiAnita->apiCallEscritura($data);

        if (self::usaTablaTesmcbu()) {
            $data = array( 'acc' => 'delete', 'tabla' => 'tesmcbu',
                    'sistema' => 'che_ban',
                    'whereArmado' => " WHERE tesmc_cuenta = '".str_pad($id, 8, "0", STR_PAD_LEFT)."' " );
            $anita2 = $apiAnita->apiCallEscritura($data);

            return $anita && $anita2;
        }

        return (bool) $anita;
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

    private static function usaTablaTesmcbu(): bool
    {
        return config('app.empresa') === 'AGG';
    }

    private static function leerCbuDesdeAnita(ApiAnita $apiAnita, string $codigoCuenta, $dataTesmae = null): string
    {
        if (self::usaTablaTesmcbu()) {
            $datac = [
                'acc' => 'list',
                'tabla' => 'tesmcbu',
                'sistema' => 'che_ban',
                'campos' => 'tesmc_cuenta, tesmc_nro_cbu',
                'whereArmado' => " WHERE tesmc_cuenta = '".$codigoCuenta."' ",
            ];
            $dataAnita = json_decode($apiAnita->apiCall($datac));

            if (isset($dataAnita[0])) {
                return trim($dataAnita[0]->tesmc_nro_cbu ?? '');
            }

            return '';
        }

        return trim($dataTesmae->tesm_nro_cbu ?? '');
    }

    private static function guardarTesmcbuAnita(ApiAnita $apiAnita, array $request): void
    {
        $data = [
            'tabla' => 'tesmcbu',
            'acc' => 'insert',
            'sistema' => 'che_ban',
            'campos' => 'tesmc_cuenta, tesmc_nro_cbu',
            'valores' => "
                '".str_pad($request['codigo'], 8, '0', STR_PAD_LEFT)."',
                '".($request['cbu'] ?? '')."' ",
        ];
        $apiAnita->apiCallEscritura($data);
    }

    private static function sincronizarTesmcbuAnita(ApiAnita $apiAnita, array $request): void
    {
        $codigo = str_pad($request['codigo'], 8, '0', STR_PAD_LEFT);
        $cbu = $request['cbu'] ?? '';

        $dataChk = [
            'acc' => 'list',
            'tabla' => 'tesmcbu',
            'sistema' => 'che_ban',
            'campos' => 'tesmc_cuenta',
            'whereArmado' => " WHERE tesmc_cuenta = '".$codigo."' ",
        ];
        $existe = json_decode($apiAnita->apiCall($dataChk));

        if (is_array($existe) && count($existe) > 0) {
            $data = [
                'tabla' => 'tesmcbu',
                'acc' => 'update',
                'sistema' => 'che_ban',
                'valores' => "
                    tesmc_cuenta   = '".$codigo."' ,
                    tesmc_nro_cbu  = '".$cbu."' ",
                'whereArmado' => " WHERE tesmc_cuenta = '".$codigo."' ",
            ];
        } else {
            $data = [
                'tabla' => 'tesmcbu',
                'acc' => 'insert',
                'sistema' => 'che_ban',
                'campos' => 'tesmc_cuenta, tesmc_nro_cbu',
                'valores' => "
                    '".$codigo."',
                    '".$cbu."' ",
            ];
        }

        $apiAnita->apiCallEscritura($data);
    }
}

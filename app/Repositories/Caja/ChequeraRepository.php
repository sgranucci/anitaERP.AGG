<?php

namespace App\Repositories\Caja;

use App\Models\Caja\Chequera;
use App\Repositories\Caja\CuentacajaRepositoryInterface;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\ApiAnita;
use DB;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class ChequeraRepository implements ChequeraRepositoryInterface
{
    protected $model;
    protected $tableAnita = 'cprocheq';
    protected $keyField = 'codigo';
    protected $keyFieldAnita = 'cproc_nro_chequera';
    private $cuentacajaRepository;

    /**
     * @param Chequera $chequera
     */
    public function __construct(Chequera $chequera,
                                CuentacajaRepositoryInterface $cuentacajarepository)
    {
        $this->model = $chequera;
        $this->cuentacajaRepository = $cuentacajarepository;
    }

    public function all()
    {
        $hay_chequera = Chequera::first();

        if (!$hay_chequera) {
            try {
                self::sincronizarConAnita();
            } catch (\Throwable $e) {
                // No bloquear pantalla si la sync inicial falla parcialmente.
            }
        }

        return $this->model->with('cuentacajas')->get();
    }

    public function create(array $data)
    {
        DB::beginTransaction();
        try 
        {
            $chequera = $this->model->create($data);

            // Graba anita
		    $anita = self::guardarAnita($data);


            DB::commit();

        } catch (\Exception $e) {

            DB::rollback();

            dd($e->getMessage());

            return ['error' => $e->getMessage()];
        }
        return($chequera);
    }

    public function update(array $data, $id)
    {
        DB::beginTransaction();
        try 
        {
            $chequera = $this->model->findOrFail($id)->update($data);

            // Actualiza anita
		    $anita = self::actualizarAnita($data, $data['codigo']);


            DB::commit();

        } catch (\Exception $e) {
            
            DB::rollback();

            dd($e->getMessage());
            return ['error' => $e->getMessage()];
        }
        return($chequera);
    }

    public function delete($id)
    {
        DB::beginTransaction();
        try 
        {
    	    $chequera = $this->model->find($id);
        		
		    // Elimina anita
		    $anita = self::eliminarAnita($chequera->codigo);

            $chequera = $this->model->destroy($id);


            DB::commit();   

        } catch (\Exception $e) {
            
            DB::rollback();

            return ['error' => $e->getMessage()];
        }
		return $chequera;
    }

    public function find($id)
    {
        if (null == $chequera = $this->model->with('cuentacajas')->find($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $chequera;
    }

    public function findOrFail($id)
    {
        if (null == $chequera = $this->model->with('cuentacajas')->findOrFail($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $chequera;
    }

    public function findPorCodigo($codigo)
    {
        $codigo = trim((string) $codigo);
        if ($codigo === '') {
            return null;
        }

        $local = $this->model->where('codigo', $codigo)->with('cuentacajas')->first();
        if ($local) {
            return $local;
        }

        $norm = ltrim($codigo, '0');
        if ($norm === '' || $norm === $codigo) {
            return null;
        }

        return $this->model->where('codigo', $norm)->with('cuentacajas')->first();
    }

    /**
     * @return array{en_anita:int,creadas:int,actualizadas:int,omitidas:int,errores:list<string>}
     */
    public function sincronizarConAnita(): array
    {
		ini_set('max_execution_time', '300');

        $stats = [
            'en_anita' => 0,
            'creadas' => 0,
            'actualizadas' => 0,
            'omitidas' => 0,
            'errores' => [],
        ];

        $apiAnita = new ApiAnita();
        $data = [
            'acc' => 'list',
            'sistema' => 'che_ban',
            'tabla' => $this->tableAnita,
            'campos' => '
                cproc_cuenta,
                cproc_nro_chequera,
                cproc_fecha_alta,
                cproc_fecha_uso,
                cproc_desde_cheque,
                cproc_hasta_cheque,
                cproc_estado,
                cproc_tipo_cheque
            ',
        ];
        $dataAnita = json_decode($apiAnita->apiCall($data));
        if (! is_array($dataAnita) && ! ($dataAnita instanceof \Traversable)) {
            $stats['errores'][] = 'Anita no devolvió un listado válido de cprocheq.';

            return $stats;
        }

        $stats['en_anita'] = is_countable($dataAnita) ? count($dataAnita) : 0;

        foreach ($dataAnita as $row) {
            $codigo = trim((string) ($row->cproc_nro_chequera ?? ''));
            try {
                $resultado = $this->upsertDesdeFilaAnita($row);
                if ($resultado === 'created') {
                    $stats['creadas']++;
                } elseif ($resultado === 'updated') {
                    $stats['actualizadas']++;
                } else {
                    $stats['omitidas']++;
                }
            } catch (\Throwable $e) {
                $msg = ($codigo !== '' ? "chequera {$codigo}: " : '').$e->getMessage();
                $stats['errores'][] = $msg;
                Log::warning('caja.chequera.sync_anita', [
                    'codigo' => $codigo,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $stats;
    }

    /**
     * Trae/actualiza desde Anita las chequeras de una cuenta de tesorería.
     */
    public function sincronizarCuentaDesdeAnita(int $cuentacajaId): void
    {
        if ($cuentacajaId <= 0) {
            return;
        }

        try {
            $cuenta = $this->cuentacajaRepository->find($cuentacajaId);
        } catch (\Throwable $e) {
            return;
        }
        if (! $cuenta) {
            return;
        }

        $codigoCuenta = preg_replace('/\D+/', '', (string) ($cuenta->codigo ?? '')) ?? '';
        $codigoCuenta = ltrim($codigoCuenta, '0');
        if ($codigoCuenta === '') {
            return;
        }
        $cuentaPad = str_pad($codigoCuenta, 8, '0', STR_PAD_LEFT);

        ini_set('max_execution_time', '120');
        $apiAnita = new ApiAnita();
        $data = [
            'acc' => 'list',
            'sistema' => 'che_ban',
            'tabla' => $this->tableAnita,
            'campos' => '
                cproc_cuenta,
                cproc_nro_chequera,
                cproc_fecha_alta,
                cproc_fecha_uso,
                cproc_desde_cheque,
                cproc_hasta_cheque,
                cproc_estado,
                cproc_tipo_cheque
            ',
            'whereArmado' => " WHERE cproc_cuenta = '".$cuentaPad."' ",
        ];
        $dataAnita = json_decode($apiAnita->apiCall($data));
        if (! is_array($dataAnita) && ! ($dataAnita instanceof \Traversable)) {
            return;
        }

        foreach ($dataAnita as $row) {
            try {
                $this->upsertDesdeFilaAnita($row, $cuentacajaId);
            } catch (\Throwable $e) {
                Log::warning('caja.chequera.sync_cuenta_anita', [
                    'cuentacaja_id' => $cuentacajaId,
                    'codigo' => (string) ($row->cproc_nro_chequera ?? ''),
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    private function resolverCuentacajaIdDesdeAnita($codigoAnita): ?int
    {
        $codigoNorm = ltrim(trim((string) $codigoAnita), '0');
        if ($codigoNorm === '') {
            return null;
        }

        $cuentacaja = $this->cuentacajaRepository->findPorCodigo($codigoNorm);
        if ($cuentacaja) {
            return (int) $cuentacaja->id;
        }

        $estado = $this->cuentacajaRepository->traerRegistroDeAnita(
            str_pad($codigoNorm, 8, '0', STR_PAD_LEFT)
        );
        if ($estado !== 'importado') {
            return null;
        }

        $cuentacaja = $this->cuentacajaRepository->findPorCodigo($codigoNorm);

        return $cuentacaja ? (int) $cuentacaja->id : null;
    }

    public function traerRegistroDeAnita($key)
    {
        $apiAnita = new ApiAnita();
        $data = array( 
            'acc' => 'list', 'tabla' => $this->tableAnita, 
			'sistema' => 'che_ban',
            'campos' => '
                cproc_cuenta,
                cproc_nro_chequera,
                cproc_fecha_alta,
                cproc_fecha_uso,
                cproc_desde_cheque,
                cproc_hasta_cheque,
                cproc_estado,
                cproc_tipo_cheque
			',
            'whereArmado' => " WHERE ".$this->keyFieldAnita." = '".$key."' " 
        );
        $dataAnita = json_decode($apiAnita->apiCall($data));

        if (! is_array($dataAnita) && ! ($dataAnita instanceof \Traversable)) {
            return;
        }
        if (count($dataAnita) === 0) {
            return;
        }

        $this->upsertDesdeFilaAnita($dataAnita[0]);
    }

    /**
     * Alta o actualización de chequera local a partir de una fila Anita (cprocheq).
     *
     * @return 'created'|'updated'|'skipped'
     */
    private function upsertDesdeFilaAnita(object $data, ?int $cuentacajaIdForzado = null): string
    {
        $codigo = trim((string) ($data->cproc_nro_chequera ?? ''));
        if ($codigo === '') {
            return 'skipped';
        }

        $cuentacaja_id = $cuentacajaIdForzado
            ?? $this->resolverCuentacajaIdDesdeAnita($data->cproc_cuenta ?? '');
        if ($cuentacaja_id === null) {
            return 'skipped';
        }

        $fechaUsoRaw = trim((string) ($data->cproc_fecha_uso ?? ''));
        $fechaUso = '';
        if ($fechaUsoRaw !== '' && $fechaUsoRaw !== '0') {
            $ts = strtotime($fechaUsoRaw);
            if ($ts !== false) {
                $fechaUso = date('d-m-Y', $ts);
            }
        }

        $tipoCheque = (string) ($data->cproc_tipo_cheque ?? 'N');
        if ($tipoCheque === 'C') {
            $tipoCheque = 'N';
        }
        if (! in_array($tipoCheque, ['N', 'D'], true)) {
            $tipoCheque = 'N';
        }

        $estado = strtoupper(trim((string) ($data->cproc_estado ?? 'A')));
        if (! in_array($estado, ['A', 'T'], true)) {
            $estado = 'A';
        }

        $arr_campos = [
            'tipochequera' => 'E',
            'tipocheque' => $tipoCheque,
            'codigo' => $codigo,
            'cuentacaja_id' => $cuentacaja_id,
            'estado' => $estado,
            'fechauso' => $fechaUso !== '' ? $fechaUso : null,
            'desdenumerocheque' => $data->cproc_desde_cheque ?? null,
            'hastanumerocheque' => $data->cproc_hasta_cheque ?? null,
        ];

        $existente = $this->findPorCodigo($codigo);
        if ($existente) {
            $existente->fill($arr_campos);
            $existente->save();

            return 'updated';
        }

        $this->model->create($arr_campos);

        return 'created';
    }

	public function guardarAnita($request) {
        $apiAnita = new ApiAnita();
        
        $cuentacaja = $this->cuentacajaRepository->find($request['cuentacaja_id']);

        $codigo = 0;
        if ($cuentacaja)
            $codigo = $cuentacaja->codigo;

        if ($request['fechauso'])
            $fechaUso = date('Ymd',strtotime($request['fechauso']));
        else    
            $fechaUso = 0;

        $fechaAlta = Carbon::now()->format('Ymd');

        $data = array( 'tabla' => $this->tableAnita, 'acc' => 'insert',
			'sistema' => 'che_ban',
            'campos' => ' 
                cproc_cuenta,
                cproc_nro_chequera,
                cproc_fecha_alta,
                cproc_fecha_uso,
                cproc_desde_cheque,
                cproc_hasta_cheque,
                cproc_estado,
                cproc_tipo_cheque
				',
            'valores' => " 
				'".str_pad($codigo, 8, "0", STR_PAD_LEFT)."', 
				'".$request['codigo']."',
                '".$fechaAlta."',
                '".$fechaUso."',
				'".$request['desdenumerocheque']."',
                '".$request['hastanumerocheque']."',
                'A',
				'".($request['tipocheque'] == 'C' ? 'N' : $request['tipocheque'])."' "
        );
        $anita = $apiAnita->apiCallEscritura($data);

        return $anita;
	}

	public function actualizarAnita($request, $id) {
        $apiAnita = new ApiAnita();

        $cuentacaja = $this->cuentacajaRepository->find($request['cuentacaja_id']);

        $codigo = 0;
        if ($cuentacaja)
            $codigo = $cuentacaja->codigo;

        if ($request['fechauso'])
            $fechaUso = date('Ymd',strtotime($request['fechauso']));
        else    
            $fechaUso = 0;

        $fechaAlta = Carbon::now()->format('Ymd');

        $data = array( 'acc' => 'update', 'tabla' => $this->tableAnita, 
				'sistema' => 'che_ban',
				'valores' => " 
                        cproc_cuenta 	                = '".str_pad($codigo, 8, "0", STR_PAD_LEFT)."' ,
                        cproc_nro_chequera              = '".$request['codigo']."' ,
                        cproc_fecha_alta    	        = '".$fechaAlta."' ,
                        cproc_fecha_uso                 = '".$fechaUso."' ,
                        cproc_desde_cheque              = '".$request['desdenumerocheque']."' ,
                        cproc_hasta_cheque              = '".$request['hastanumerocheque']."' ,
                        cproc_estado 	                = '".$request['estado']."' ,
                        cproc_tipo_cheque 	            = '".($request['tipocheque'] == 'C' ? 'N' : $request['tipocheque'])."' "
				,
				'whereArmado' => " WHERE cproc_nro_chequera = '".$request['codigo']."' " );
        $anita = $apiAnita->apiCallEscritura($data);

        return $anita;
	}

	public function eliminarAnita($id) {
        $apiAnita = new ApiAnita();
        $data = array( 'acc' => 'delete', 'tabla' => $this->tableAnita, 
				'sistema' => 'che_ban',
				'whereArmado' => " WHERE cproc_nro_chequera = '".$id."' " );
        $anita = $apiAnita->apiCallEscritura($data);
        
        return $anita;
	}

}

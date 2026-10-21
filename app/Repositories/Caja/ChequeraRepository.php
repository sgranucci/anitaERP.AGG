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
        $data = $this->normalizarDatos($data);
        $data['codigo'] = $this->asignarCodigoLibre($data['codigo'] ?? null);

        DB::beginTransaction();
        try {
            $chequera = $this->model->create($data);
            $this->guardarAnita($data);
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        return $chequera;
    }

    public function update(array $data, $id)
    {
        $data = $this->normalizarDatos($data);

        DB::beginTransaction();
        try {
            $chequera = $this->model->findOrFail($id);
            $data['codigo'] = $chequera->codigo;
            $chequera->update($data);
            $this->actualizarAnita($data, $chequera->codigo);
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        return $chequera;
    }

    /**
     * Próximo código numérico libre en el ERP y en Anita.
     */
    public function proximoCodigo(): string
    {
        try {
            return $this->asignarCodigoLibre(null);
        } catch (\Throwable $e) {
            return (string) ($this->maxCodigoLocal() + 1);
        }
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

    public function findPorCuentaYNumero(int $cuentacajaId, int $numeroCheque): ?Chequera
    {
        if ($cuentacajaId <= 0 || $numeroCheque <= 0) {
            return null;
        }

        return $this->model->newQuery()
            ->where('cuentacaja_id', $cuentacajaId)
            ->where('desdenumerocheque', '<=', $numeroCheque)
            ->where('hastanumerocheque', '>=', $numeroCheque)
            ->orderByRaw("CASE WHEN estado = 'A' THEN 0 ELSE 1 END")
            ->orderByDesc('id')
            ->first();
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

        $cuentaPad = \App\Support\Caja\ChequePropioCpromaeAnitaMapper::cuentaTesmae((string) ($cuenta->codigo ?? ''));
        if ($cuentaPad === '' || $cuentaPad === '00000000') {
            return;
        }

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
        $codigoRaw = trim((string) $codigoAnita);
        if ($codigoRaw === '') {
            return null;
        }

        // Crown: códigos con "/" o espacios (071080/1). No ltrim ciegos.
        $cuentacaja = $this->cuentacajaRepository->findPorCodigo($codigoRaw);
        if ($cuentacaja) {
            return (int) $cuentacaja->id;
        }

        if (preg_match('/^\d+$/', $codigoRaw) === 1) {
            $codigoNorm = ltrim($codigoRaw, '0');
            if ($codigoNorm === '') {
                $codigoNorm = '0';
            }
            $cuentacaja = $this->cuentacajaRepository->findPorCodigo($codigoNorm);
            if ($cuentacaja) {
                return (int) $cuentacaja->id;
            }

            $estado = $this->cuentacajaRepository->traerRegistroDeAnita(
                str_pad($codigoNorm, 8, '0', STR_PAD_LEFT)
            );
            if ($estado === 'importado') {
                $cuentacaja = $this->cuentacajaRepository->findPorCodigo($codigoNorm);

                return $cuentacaja ? (int) $cuentacaja->id : null;
            }
        }

        return null;
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

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalizarDatos(array $data): array
    {
        $data['fechauso'] = $this->normalizarFechaUso($data['fechauso'] ?? null);
        $data['desdenumerocheque'] = preg_replace('/\D+/', '', (string) ($data['desdenumerocheque'] ?? '')) ?? '';
        $data['hastanumerocheque'] = preg_replace('/\D+/', '', (string) ($data['hastanumerocheque'] ?? '')) ?? '';
        $estado = strtoupper(trim((string) ($data['estado'] ?? 'A')));
        $data['estado'] = in_array($estado, ['A', 'T'], true) ? $estado : 'A';

        return $data;
    }

    private function normalizarFechaUso(mixed $valor): ?string
    {
        $valor = trim((string) $valor);
        if ($valor === '' || $valor === '0') {
            return null;
        }

        foreach (['Y-m-d', 'd/m/Y', 'd-m-Y'] as $formato) {
            $fecha = \DateTime::createFromFormat('!'.$formato, $valor);
            $errores = \DateTime::getLastErrors();
            $invalida = is_array($errores) && (($errores['warning_count'] ?? 0) > 0 || ($errores['error_count'] ?? 0) > 0);
            if ($fecha instanceof \DateTime && ! $invalida) {
                return $fecha->format('Y-m-d');
            }
        }

        return null;
    }

    private function fechaAnita(mixed $valor): string
    {
        $normalizada = $this->normalizarFechaUso($valor);

        return $normalizada === null ? '0' : str_replace('-', '', $normalizada);
    }

    private function estadoAnita(mixed $estado): string
    {
        $estado = strtoupper(trim((string) $estado));

        return in_array($estado, ['A', 'T'], true) ? $estado : 'A';
    }

    private function maxCodigoLocal(): int
    {
        $max = 0;
        foreach ($this->model->newQuery()->pluck('codigo') as $codigo) {
            $numero = $this->codigoNumerico($codigo);
            if ($numero !== null && $numero > $max) {
                $max = $numero;
            }
        }

        return $max;
    }

    private function codigoNumerico(mixed $codigo): ?int
    {
        $codigo = trim((string) $codigo);
        if ($codigo === '' || preg_match('/^\d+$/', $codigo) !== 1) {
            return null;
        }

        return (int) $codigo;
    }

    private function asignarCodigoLibre(?string $preferido): string
    {
        $preferidoNum = $this->codigoNumerico($preferido);
        if ($preferidoNum !== null && $preferidoNum > 0 && ! $this->codigoOcupado((string) $preferidoNum)) {
            return (string) $preferidoNum;
        }

        $siguiente = max($this->maxCodigoLocal() + 1, ($preferidoNum ?? 0) + 1, 1);
        for ($i = 0; $i < 40; $i++) {
            $candidato = (string) ($siguiente + $i);
            if (! $this->codigoOcupado($candidato)) {
                return $candidato;
            }
        }

        throw new \RuntimeException('No encontré un código de chequera libre. Sincronice desde Anita y vuelva a intentar.');
    }

    private function codigoOcupado(string $codigo): bool
    {
        if ($this->findPorCodigo($codigo)) {
            return true;
        }

        return $this->existeEnAnita($codigo);
    }

    private function existeEnAnita(string $codigo): bool
    {
        if (preg_match('/^\d+$/', $codigo) !== 1) {
            return false;
        }

        $apiAnita = new ApiAnita();
        $raw = $apiAnita->apiCall([
            'acc' => 'list',
            'sistema' => 'che_ban',
            'tabla' => $this->tableAnita,
            'campos' => 'cproc_nro_chequera',
            'whereArmado' => " WHERE cproc_nro_chequera = '".$codigo."' ",
        ]);
        $parsed = ApiAnita::parsearRespuestaLista(is_string($raw) ? $raw : null);
        if ($parsed['error_lectura'] !== null) {
            throw new \RuntimeException('No se pudo consultar el código en Anita: '.$parsed['error_lectura']);
        }

        return $parsed['filas'] !== [];
    }

	public function guardarAnita($request) {
        $apiAnita = new ApiAnita();
        
        $cuentacaja = $this->cuentacajaRepository->find($request['cuentacaja_id']);

        $codigo = 0;
        if ($cuentacaja)
            $codigo = $cuentacaja->codigo;

        $fechaUso = $this->fechaAnita($request['fechauso'] ?? null);
        $fechaAlta = Carbon::now()->format('Ymd');
        $estado = $this->estadoAnita($request['estado'] ?? 'A');

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
                '".$estado."',
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

        $fechaUso = $this->fechaAnita($request['fechauso'] ?? null);
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

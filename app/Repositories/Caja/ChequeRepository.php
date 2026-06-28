<?php

namespace App\Repositories\Caja;

use App\Models\Caja\Cheque;
use App\Models\Caja\Cuentacaja;
use App\Models\Caja\Estadocheque_Banco;
use App\Models\Contable\Cuentacontable;
use App\Models\Configuracion\Empresa;
use App\Support\Caja\ChequePropioImputacionSupport;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\Repositories\Caja\BancoRepositoryInterface;
use App\Repositories\Caja\CuentacajaRepositoryInterface;
use App\Repositories\Caja\Estadocheque_BancoRepositoryInterface;
use App\Repositories\Caja\ChequeraRepositoryInterface;
use App\Repositories\Compras\ProveedorRepositoryInterface;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Repositories\Configuracion\TipodocumentoRepositoryInterface;
use App\ApiAnita;
use Auth;
use DB;
use Carbon\Carbon;
use Exception;

class ChequeRepository implements ChequeRepositoryInterface
{
    protected $model;
    protected $tableAnita = 'cpromae';
    protected $keyField = 'codigo';
    protected $keyFieldAnita = ['cpro_cuenta', 'cpro_nro_cheque', 'cpro_fecha_cheque'];

	private $bancoRepository;
    private $cuentacajaRepository;
    private $proveedorRepository;
    private $empresaRepository;
    private $tipodocumentoRepository;
    private $estadocheque_bancoRepository;
    private $chequeraRepository;

    /**
     * PostRepository constructor.
     *
     * @param Post $post
     */
    public function __construct(Cheque $cheque,
                                CuentacajaRepositoryInterface $cuentacajarepository,
                                ProveedorRepositoryInterface $proveedorrepository,
                                EmpresaRepositoryInterface $empresarepository,
                                ChequeraRepositoryInterface $chequerarepository,
                                TipodocumentoRepositoryInterface $tipodocumentorepository,
                                BancoRepositoryInterface $bancorepository,
                                Estadocheque_BancoRepositoryInterface $estadocheque_bancorepository)
    {
        $this->model = $cheque;
        $this->cuentacajaRepository = $cuentacajarepository;
        $this->proveedorRepository = $proveedorrepository;
        $this->empresaRepository = $empresarepository;
        $this->chequeraRepository = $chequerarepository;
        $this->tipodocumentoRepository = $tipodocumentorepository;
        $this->bancoRepository = $bancorepository;
        $this->estadocheque_bancoRepository = $estadocheque_bancorepository;
    }

    public function all()
    {
        $hay_cheque = Cheque::first();

        if (!$hay_cheque)
            self::sincronizarConAnita();

        $query = $this->model->with('empresas')
            ->with('cuentacajas')
            ->with('bancos')
            ->with('tipodocumentos')
            ->with('proveedores')
            ->with('clientes')
            ->with('monedas')
            ->with('cajas')
            ->with('chequeras');

        $this->empresaRepository->aplicarFiltroEmpresasAsignadas($query);

        return $query->get();
    }

    public function create(array $data)
    {
        $cheque = $this->model->create($data);

        // Graba anita
        $anita = self::guardarAnita($data);


        return($cheque);
    }

    public function update(array $data, $id)
    {
        $cheque = $this->model->findOrFail($id)->update($data);

        // Actualiza anita
        $anita = self::actualizarAnita($data, $data['codigo']);


        return($cheque);
    }

    public function delete($id)
    {
   	    $cheque = $this->model->find($id);
        		
	    // Elimina anita
	    $anita = self::eliminarAnita($cheque->origen, $cheque->cuenta_cajas->codigo, $cheque->codigo);

        $cheque = $this->model->destroy($id);


        return $cheque;
    }

    public function find($id)
    {
        if (null == $cheque = $this->model->with('empresas')
                            ->with('cuentacajas')
                            ->with('bancos')
                            ->with('tipodocumentos')
                            ->with('proveedores')
                            ->with('clientes')
                            ->with('monedas')
                            ->with('cajas')
                            ->with('chequeras')
                            ->with('caja_movimientos')
                            ->find($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $cheque;
    }

    public function findOrFail($id)
    {
        if (null == $cheque = $this->model->with('empresas')
                            ->with('cuentacajas')
                            ->with('bancos')
                            ->with('tipodocumentos')
                            ->with('proveedores')
                            ->with('clientes')
                            ->with('monedas')
                            ->with('cajas')
                            ->with('chequeras')
                            ->with('caja_movimientos')
                            ->findOrFail($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $cheque;
    }

    public function findPorNumeroCheque($codigo)
    {
        return $this->model->where('numerocheque', $codigo)->with('empresas')
                            ->with('cuentacajas')
                            ->with('bancos')
                            ->with('tipodocumentos')
                            ->with('proveedores')
                            ->with('clientes')
                            ->with('monedas')
                            ->with('chequeras')
                            ->with('caja_movimientos')
                            ->with('cajas')->get();
    }

	public function guardarChequeCobranza($data, $funcion, $id = null)
	{
		if ($funcion == 'update')
		{
			// Trae todos los id
        	$cheque = $this->model->where('cobranza_id', $id)->get()->pluck('id')->toArray();
			$q_cheque = count($cheque);
		}

		// Graba cuentas contables
		if (isset($data['cheque_ids']))
		{
            $cheque_ids = $data['cheque_ids'];
			$fechapagos = $data['fechapagos'];
			$banco_ids = $data['banco_ids'];
			$numerocheques = $data['numerocheques'];
			$cotizacioncheques = $data['cotizacioncheques'];
			$sucursalpagos = $data['sucursalpagos'];
            $cuentalibradoras = $data['cuentalibradoras'];
            $monedacheque_ids = $data['monedacheque_ids'];
			$montocheques = $data['montocheques'];

			if ($funcion == 'update')
			{
				$_id = $cheque;

				// Borra los que sobran
				if ($q_cheque > count($cheque_ids))
				{
					for ($d = count($cheque_ids); $d < $q_cheque; $d++)
						$this->model->find($_id[$d])->delete();
				}

				// Actualiza los que ya existian
				for ($i = 0; $i < $q_cheque && $i < count($cheque_ids); $i++)
				{
					if ($i < count($cheque_ids))
					{
						$cheque = $this->model->findOrFail($_id[$i])->update([
									"cobranza_id" => $id,
                                    'origen' => 'R',
                                    'fechaemision' => $data['fecha'],
                                    'fechapago' => $fechapagos[$i],
                                    'empresa_id' => $data['empresa_id'],
                                    'caja_id' => $data['caja_id'],
                                    'numerocheque' => $numerocheques[$i],
                                    'moneda_id' => $monedacheque_ids[$i],
                                    'monto' => $montocheques[$i],
                                    'cotizacion' => $cotizacioncheques[$i],
                                    'cliente_id' => $data['cliente_id'],
                                    'sucursalpago' => $sucursalpagos[$i],
                                    'banco_id' => $banco_ids[$i],
                                    'cuentalibradora' => $cuentalibradoras[$i]
									]);
					}
				}
				if ($q_cheque > count($cheque_ids))
					$i = $d; 
			}
			else
				$i = 0;
			for ($i_movimiento = $i; $i_movimiento < count($cheque_ids); $i_movimiento++)
			{
				if ($monedacheque_ids[$i_movimiento] != '') 
                {
                    $cheque = $this->model->create([
                                "cobranza_id" => $id,
                                'origen' => 'R',
                                'fechaemision' => $data['fecha'],
                                'fechapago' => $fechapagos[$i_movimiento],
                                'empresa_id' => $data['empresa_id'],
                                'caja_id' => $data['caja_id'],
                                'numerocheque' => $numerocheques[$i_movimiento],
                                'moneda_id' => $monedacheque_ids[$i_movimiento],
                                'monto' => $montocheques[$i_movimiento],
                                'cotizacion' => $cotizacioncheques[$i_movimiento],
                                'cliente_id' => $data['cliente_id'],
                                'sucursalpago' => $sucursalpagos[$i_movimiento],
                                'banco_id' => $banco_ids[$i_movimiento],
                                'cuentalibradora' => $cuentalibradoras[$i_movimiento]
                                ]);
				}
			}
		}
		else
		{
			$cheque = $this->model->where('cobranza_id', $id)->delete();
		}
		return $cheque;
	}

    /**
     * @param  array<string, mixed>  $data
     */
    public function guardarChequeIngresoEgreso(array $data, string $funcion, int $cajaMovimientoId)
    {
        $idsPersistidos = [];

        $fechaOperacion = (string) ($data['fecha'] ?? date('Y-m-d'));
        $empresaId = (int) ($data['empresa_id'] ?? 0);
        $cajaId = isset($data['caja_id']) ? (int) $data['caja_id'] : null;

        $idsPersistidos = array_merge(
            $idsPersistidos,
            $this->persistirFilasEmitidos($data, $funcion, $cajaMovimientoId, $fechaOperacion, $empresaId, $cajaId)
        );
        $idsPersistidos = array_merge(
            $idsPersistidos,
            $this->persistirFilasRecibidos($data, $funcion, $cajaMovimientoId, $fechaOperacion, $empresaId, $cajaId)
        );
        $idsPersistidos = array_merge(
            $idsPersistidos,
            $this->persistirFilasReemplazo($data, $funcion, $cajaMovimientoId, $fechaOperacion, $empresaId, $cajaId)
        );

        if ($funcion === 'update') {
            $this->model->query()
                ->where('caja_movimiento_id', $cajaMovimientoId)
                ->whereNull('cobranza_id')
                ->whereNotIn('id', $idsPersistidos)
                ->delete();
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<int>
     */
    private function persistirFilasEmitidos(
        array $data,
        string $funcion,
        int $cajaMovimientoId,
        string $fechaOperacion,
        int $empresaId,
        ?int $cajaId
    ): array {
        $ids = [];
        if (! isset($data['numerocheque_emitidos']) || ! is_array($data['numerocheque_emitidos'])) {
            return $ids;
        }

        $chequeIds = $data['cheque_emitido_ids'] ?? [];
        $chequeraIds = $data['chequera_emitido_ids'] ?? [];
        $cuentacajaIds = $data['cuentacaja_emitido_ids'] ?? [];
        $numeros = $data['numerocheque_emitidos'];
        $fechasPago = $data['fechapago_emitidos'] ?? [];
        $monedaIds = $data['moneda_emitido_ids'] ?? [];
        $montos = $data['montocheque_emitidos'] ?? [];
        $cotizaciones = $data['cotizacioncheque_emitidos'] ?? [];
        $caracteres = $data['caracter_emitidos'] ?? [];
        $anombrede = $data['anombrede_emitidos'] ?? [];
        $proveedorIds = $data['proveedor_emitido_ids'] ?? [];

        foreach ($numeros as $i => $numero) {
            $numero = trim((string) $numero);
            if ($numero === '' || (float) ($montos[$i] ?? 0) <= 0) {
                continue;
            }

            $cuentacajaId = (int) ($cuentacajaIds[$i] ?? 0);
            $cuentacaja = $this->cuentacajaRepository->find($cuentacajaId);
            $bancoId = (int) ($cuentacaja->banco_id ?? 0);
            if ($bancoId <= 0) {
                throw new Exception('La cuenta de caja del cheque emitido no tiene banco asociado.');
            }

            $fechaPago = (string) ($fechasPago[$i] ?? $fechaOperacion);
            $payload = [
                'origen' => 'E',
                'chequera_id' => ($chequeraIds[$i] ?? '') !== '' ? (int) $chequeraIds[$i] : null,
                'caracter' => ($caracteres[$i] ?? '') !== '' ? (string) $caracteres[$i] : 'O',
                'estado' => ChequePropioImputacionSupport::estadoInicialEmitido($fechaOperacion, $fechaPago),
                'fechaemision' => $fechaOperacion,
                'fechapago' => $fechaPago,
                'cuentacaja_id' => $cuentacajaId,
                'empresa_id' => $empresaId,
                'caja_id' => $cajaId,
                'caja_movimiento_id' => $cajaMovimientoId,
                'numerocheque' => $numero,
                'moneda_id' => (int) ($monedaIds[$i] ?? 1),
                'monto' => (float) ($montos[$i] ?? 0),
                'cotizacion' => (float) ($cotizaciones[$i] ?? 1),
                'proveedor_id' => ($proveedorIds[$i] ?? '') !== '' ? (int) $proveedorIds[$i] : null,
                'anombrede' => (string) ($anombrede[$i] ?? ''),
                'banco_id' => $bancoId,
            ];

            $chequeId = (int) ($chequeIds[$i] ?? 0);
            if ($funcion === 'update' && $chequeId > 0) {
                $this->model->findOrFail($chequeId)->update($payload);
                $ids[] = $chequeId;
            } else {
                $cheque = $this->model->create($payload);
                $ids[] = (int) $cheque->id;
            }
        }

        return $ids;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<int>
     */
    private function persistirFilasRecibidos(
        array $data,
        string $funcion,
        int $cajaMovimientoId,
        string $fechaOperacion,
        int $empresaId,
        ?int $cajaId
    ): array {
        $ids = [];
        if (! isset($data['numerocheque_recibidos']) || ! is_array($data['numerocheque_recibidos'])) {
            return $ids;
        }

        $chequeIds = $data['cheque_recibido_ids'] ?? [];
        $fechasPago = $data['fechapago_recibidos'] ?? [];
        $bancoIds = $data['banco_recibido_ids'] ?? [];
        $numeros = $data['numerocheque_recibidos'];
        $sucursales = $data['sucursalpago_recibidos'] ?? [];
        $cuentasLib = $data['cuentalibradora_recibidos'] ?? [];
        $monedaIds = $data['monedacheque_recibido_ids'] ?? [];
        $montos = $data['montocheque_recibidos'] ?? [];
        $cotizaciones = $data['cotizacioncheque_recibidos'] ?? [];

        foreach ($numeros as $i => $numero) {
            $numero = trim((string) $numero);
            if ($numero === '' || (float) ($montos[$i] ?? 0) <= 0) {
                continue;
            }

            $payload = [
                'origen' => 'R',
                'caracter' => 'R',
                'estado' => ' ',
                'fechaemision' => $fechaOperacion,
                'fechapago' => (string) ($fechasPago[$i] ?? $fechaOperacion),
                'empresa_id' => $empresaId,
                'caja_id' => $cajaId,
                'caja_movimiento_id' => $cajaMovimientoId,
                'numerocheque' => $numero,
                'moneda_id' => (int) ($monedaIds[$i] ?? 1),
                'monto' => (float) ($montos[$i] ?? 0),
                'cotizacion' => (float) ($cotizaciones[$i] ?? 1),
                'sucursalpago' => (string) ($sucursales[$i] ?? ''),
                'banco_id' => (int) ($bancoIds[$i] ?? 0),
                'cuentalibradora' => (string) ($cuentasLib[$i] ?? ''),
                'cliente_id' => isset($data['cliente_id']) ? (int) $data['cliente_id'] : null,
                'proveedor_id' => isset($data['proveedor_id']) ? (int) $data['proveedor_id'] : null,
            ];

            if ($payload['banco_id'] <= 0) {
                throw new Exception('Debe indicar banco en cheque recibido.');
            }

            $chequeId = (int) ($chequeIds[$i] ?? 0);
            if ($funcion === 'update' && $chequeId > 0) {
                $this->model->findOrFail($chequeId)->update($payload);
                $ids[] = $chequeId;
            } else {
                $cheque = $this->model->create($payload);
                $ids[] = (int) $cheque->id;
            }
        }

        return $ids;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<int>
     */
    private function persistirFilasReemplazo(
        array $data,
        string $funcion,
        int $cajaMovimientoId,
        string $fechaOperacion,
        int $empresaId,
        ?int $cajaId
    ): array {
        $ids = [];
        if (! isset($data['cheque_anulado_ids']) || ! is_array($data['cheque_anulado_ids'])) {
            return $ids;
        }

        $anulados = $data['cheque_anulado_ids'];
        $origenReemplazo = $data['origen_reemplazo'] ?? [];
        $numerosReemplazo = $data['numerocheque_reemplazo'] ?? [];
        $montosReemplazo = $data['montocheque_reemplazo'] ?? [];
        $monedaReemplazo = $data['moneda_reemplazo_ids'] ?? [];
        $cotizReemplazo = $data['cotizacioncheque_reemplazo'] ?? [];
        $fechasReemplazo = $data['fechapago_reemplazo'] ?? [];
        $cuentacajaReemplazo = $data['cuentacaja_reemplazo_ids'] ?? [];
        $chequeraReemplazo = $data['chequera_reemplazo_ids'] ?? [];
        $bancoReemplazo = $data['banco_reemplazo_ids'] ?? [];

        foreach ($anulados as $i => $anuladoId) {
            $anuladoId = (int) $anuladoId;
            if ($anuladoId <= 0) {
                continue;
            }

            $anulado = $this->model->find($anuladoId);
            if ($anulado === null) {
                throw new Exception('Cheque a anular no encontrado (id '.$anuladoId.').');
            }

            $anulado->estado = 'A';
            $anulado->save();

            $montoReemplazo = (float) ($montosReemplazo[$i] ?? $anulado->monto);
            $numeroReemplazo = trim((string) ($numerosReemplazo[$i] ?? ''));
            if ($numeroReemplazo === '' || $montoReemplazo <= 0) {
                continue;
            }

            $tipoReemplazo = strtoupper((string) ($origenReemplazo[$i] ?? 'E'));
            $fechaPago = (string) ($fechasReemplazo[$i] ?? $fechaOperacion);

            if ($tipoReemplazo === 'E') {
                $cuentacajaId = (int) ($cuentacajaReemplazo[$i] ?? $anulado->cuentacaja_id ?? 0);
                $cuentacaja = $this->cuentacajaRepository->find($cuentacajaId);
                $payload = [
                    'origen' => 'E',
                    'chequera_id' => ($chequeraReemplazo[$i] ?? '') !== '' ? (int) $chequeraReemplazo[$i] : $anulado->chequera_id,
                    'caracter' => $anulado->caracter ?: 'O',
                    'estado' => ChequePropioImputacionSupport::estadoInicialEmitido($fechaOperacion, $fechaPago),
                    'fechaemision' => $fechaOperacion,
                    'fechapago' => $fechaPago,
                    'cuentacaja_id' => $cuentacajaId,
                    'empresa_id' => $empresaId,
                    'caja_id' => $cajaId,
                    'caja_movimiento_id' => $cajaMovimientoId,
                    'cheque_reemplaza_id' => $anuladoId,
                    'numerocheque' => $numeroReemplazo,
                    'moneda_id' => (int) ($monedaReemplazo[$i] ?? $anulado->moneda_id),
                    'monto' => $montoReemplazo,
                    'cotizacion' => (float) ($cotizReemplazo[$i] ?? $anulado->cotizacion),
                    'proveedor_id' => $anulado->proveedor_id,
                    'anombrede' => $anulado->anombrede,
                    'banco_id' => (int) ($cuentacaja->banco_id ?? $anulado->banco_id),
                ];
            } else {
                $payload = [
                    'origen' => 'R',
                    'caracter' => 'R',
                    'estado' => ' ',
                    'fechaemision' => $fechaOperacion,
                    'fechapago' => $fechaPago,
                    'empresa_id' => $empresaId,
                    'caja_id' => $cajaId,
                    'caja_movimiento_id' => $cajaMovimientoId,
                    'cheque_reemplaza_id' => $anuladoId,
                    'numerocheque' => $numeroReemplazo,
                    'moneda_id' => (int) ($monedaReemplazo[$i] ?? $anulado->moneda_id),
                    'monto' => $montoReemplazo,
                    'cotizacion' => (float) ($cotizReemplazo[$i] ?? $anulado->cotizacion),
                    'banco_id' => (int) ($bancoReemplazo[$i] ?? $anulado->banco_id),
                    'sucursalpago' => $anulado->sucursalpago,
                    'cuentalibradora' => $anulado->cuentalibradora,
                ];
            }

            $cheque = $this->model->create($payload);
            $ids[] = (int) $cheque->id;
        }

        return $ids;
    }

    public function sincronizarConAnita(){
		ini_set('max_execution_time', '300');

        $apiAnita = new ApiAnita();
        $data = array( 'acc' => 'list', 
						'sistema' => 'che_ban',
						'campos' => $this->keyFieldAnita[0].','.$this->keyFieldAnita[1].','.$this->keyFieldAnita[2], 
						'tabla' => $this->tableAnita );
        $dataAnita = json_decode($apiAnita->apiCall($data));

        foreach ($dataAnita as $value) {
            $this->traerRegistroDeAnita($value->{$this->keyFieldAnita[0]}, $value->{$this->keyFieldAnita[1]}, $value->{$this->keyFieldAnita[2]});
        }
    }

    public function traerRegistroDeAnita($key1, $key2, $key3){
        $apiAnita = new ApiAnita();
        $data = array( 
            'acc' => 'list', 'tabla' => $this->tableAnita, 
			'sistema' => 'che_ban',
            'campos' => '
                    cpro_cuenta,
                    cpro_nro_cheque,
                    cpro_fecha_cheque,
                    cpro_fecha_emision,
                    cpro_importe,
                    cpro_proveedor,
                    cpro_entregado_a,
                    cpro_nro_op,
                    cpro_cod_mon,
                    cpro_cotizacion,
                    cpro_estado,
                    cpro_contrapartida,
                    cpro_fecha_anula,
                    cpro_fl_imprimio,
                    cpro_a_nombre_de,
                    cpro_modelo,
                    cpro_para_dep',
                    //,
                    //cpro_fecha_entrega,
                    //cpro_empresa,
                    //cpro_negociable,
                    //cpro_estado_banco,
                    //cpro_sucursal_pago,
                    //cpro_tipo_distrib,
                    //cpro_nro_e_cheq
			//',
            'whereArmado' => " WHERE ".$this->keyFieldAnita[0]." = '".$key1."' AND ".
                    $this->keyFieldAnita[1]." = '".$key2."' AND ".
                    $this->keyFieldAnita[2]." = '".$key3."' "
        );
        $dataAnita = json_decode($apiAnita->apiCall($data));
		$usuario_id = Auth::user()->id;

        if (count($dataAnita) > 0) {
            $data = $dataAnita[0];

            Self::convierteDatosDeAnita($data, $estado, $fechaEmision, $fechaCheque, $cuentacaja_id, $empresa_id, $proveedor_id, 
                                        $estadoChequeBanco_id, $chequera_id);

            $arr_campos = [
                'origen' => 'E',
                'chequera_id' => $chequera_id,
                'caracter' => 'O',
                'estado' => $estado,
                'fechaemision' => $fechaEmision,
                'fechapago' => $fechaCheque,
                'cuentacaja_id' => $cuentacaja_id,
                'empresa_id' => $empresa_id,
                'caja_id' => null,
                'caja_movimiento_id' => null,
                'numerocheque' => $data->cpro_nro_cheque,
                'moneda_id' => $data->cpro_cod_mon,
                'monto' => $data->cpro_importe, 
                'cotizacion' => $data->cpro_cotizacion, 
                'proveedor_id' => $proveedor_id, 
                'cliente_id' => null,
                'tipodocumento_id' => null, 
                'numerodocumento' => null, 
                'entregado' => $data->cpro_entregado_a, 
                'anombrede' => $data->cpro_a_nombre_de, 
                'estadocheque_banco_id' => $estadoChequeBanco_id,
                'sucursalpago' => $data->cpro_sucursal_pago, 
                'tipodistribucion' => $data->cpro_tipo_distrib, 
                'banco_id' => null, 
                'codigopostalbanco' => null,
                'cuentalibradora' => null
                ];

            $this->model->create($arr_campos);
        }
    }

	public function guardarAnita($request) {
        $apiAnita = new ApiAnita();
        
        // Verifica si fue emitido o recibido
        if ($request['origen'] == '1')
        {

        }
        else
        {
            Self::convierteDatosParaAnita($request, $codigo, $fechaCheque, $fechaEmision, $proveedor, $modelo, 
                                            $caracter, $empresa, $negociable, $estadoBanco, $numeroEcheq,
                                            $estado);

            $data = array( 'tabla' => $this->tableAnita, 'acc' => 'insert',
                'sistema' => 'che_ban',
                'campos' => ' 
                        cpro_cuenta,
                        cpro_nro_cheque,
                        cpro_fecha_cheque,
                        cpro_fecha_emision,
                        cpro_importe,
                        cpro_proveedor,
                        cpro_entregado_a,
                        cpro_nro_op,
                        cpro_cod_mon,
                        cpro_cotizacion,
                        cpro_estado,
                        cpro_contrapartida,
                        cpro_fecha_anula,
                        cpro_fl_imprimio,
                        cpro_a_nombre_de,
                        cpro_modelo,
                        cpro_para_dep',
                        //,
                        //cpro_fecha_entrega,
                        //cpro_empresa,
                        //cpro_negociable,
                        //cpro_estado_banco,
                        //cpro_sucursal_pago,
                        //cpro_tipo_distrib,
                        //cpro_nro_e_cheq
                    //',
                'valores' => " 
                    '".str_pad($codigo, 8, "0", STR_PAD_LEFT)."', 
                    '".$request['numerocheque']."',
                    '".$fechaCheque."',
                    '".$fechaEmision."',
                    '".$request['monto']."',
                    '".str_pad($proveedor, 6, "0", STR_PAD_LEFT)."',
                    '".$request['entregado']."',
                    '0',
                    '".$request['moneda_id']."',
                    '".$request['cotizacion']."',
                    '".$estado."',
                    ' ',
                    '0',
                    ' ',
                    '".$request['anombrede']."',
                    '".$modelo."', 
                    '".$caracter."'"
                    //,
                    //'0',
                    //'".$empresa."',
                    //'".$negociable."',
                    //'".$estadoBanco."',
                    //'".$request['sucursalpago']."',
                    //'".$request['tipodistribucion']."',
                    //.".$numeroEcheq."'"
            );
        }
        $anita = $apiAnita->apiCallEscritura($data);

        return $anita;
	}

	public function actualizarAnita($request, $id) {
        $apiAnita = new ApiAnita();

        // Verifica si fue emitido o recibido
        if ($request['origen'] == '1')
        {

        }
        else
        {        
            Self::convierteDatosParaAnita($request, $codigo, $fechaCheque, $fechaEmision, $proveedor, $modelo, 
                                            $caracter, $empresa, $negociable, $estadoBanco, $numeroEcheq,
                                            $estado);

            $data = array( 'acc' => 'update', 'tabla' => $this->tableAnita, 
                    'sistema' => 'che_ban',
                    'valores' => " 
                            cpro_fecha_cheque               = '".$fechaCheque."',
                            cpro_fecha_emision              = '".$fechaEmision."',
                            cpro_importe                    = '".$request['monto']."',
                            cpro_proveedor                  = '".str_pad($proveedor, 6, "0", STR_PAD_LEFT)."',
                            cpro_entregado_a                = '".$request['entregado']."',
                            cpro_cod_mon                    = '".$request['moneda_id']."',
                            cpro_cotizacion                 = '".$request['cotizacion']."',
                            cpro_estado                     = '".$estado."',
                            cpro_fecha_anula                = '".$fechaAnula."',,
                            cpro_a_nombre_de                = '".$request['anombrede']."',
                            cpro_modelo                     = '".$modelo."',
                            cpro_para_dep                   = '".$caracter."' "
                            //,
                            //cpro_empresa                    = '".$empresa."',
                            //cpro_negociable                 = '".$negociable."',
                            //cpro_estado_banco               = '".$estadoBanco."',
                            //cpro_sucursal_pago              = '".$request['sucursalpago']."',
                            //cpro_tipo_distrib               = '".$request['tipodistribucion']."',
                            //cpro_nro_e_cheq                 = '".$numeroEcheq."' "
                    ,
                    'whereArmado' => " WHERE cpro_cuenta = '".str_pad($codigo, 8, "0", STR_PAD_LEFT)."' AND
                                        cpro_nro_cheque = '".$request['numerocheque']."'");
        }
        $anita = $apiAnita->apiCallEscritura($data);

        return $anita;
	}

	public function eliminarAnita($origen, $cuenta, $numeroCheque) {
        $apiAnita = new ApiAnita();

        if ($origen == '1')
        {

        }
        else
        {
            $data = array( 'acc' => 'delete', 'tabla' => $this->tableAnita, 
                    'sistema' => 'che_ban',
                    'whereArmado' => " WHERE cpro_cuenta = '".str_pad($cuenta, 8, "0", STR_PAD_LEFT)."' AND
                                        cpro_nro_cheque = '".$numeroCheque."'");
        }
        $anita = $apiAnita->apiCallEscritura($data);
        
        return $anita;
	}

    private function convierteDatosDeAnita($data, &$fechaEmision, &$fechaCheque, &$cuentacaja_id, &$empresa_id, 
                                            &$proveedor_id, &$estadoChequeBanco_id, &$chequera_id)
    {
        $fechaEmision = date('d-m-Y', strtotime($data->cpro_fecha_emision));
        $fechaCheque = date('d-m-Y', strtotime($data->cpro_fecha_cheque));

        $chequera = $this->chequeraRepository->findPorCodigo($data->cpro_modelo ?? '');
        if ($chequera) {
            $chequera_id = $chequera->id;
        } else {
            $chequera_id = null;
        }

        $cuentacaja = $this->cuentacajaRepository->findPorCodigo(ltrim((string) ($data->cpro_cuenta ?? ''), '0'));
        if ($cuentacaja) {
            $cuentacaja_id = $cuentacaja->id;
        } else {
            $cuentacaja_id = null;
        }

        $empresa = $this->empresaRepository->findPorCodigo($data->cpro_empresa ?? '');
        if ($empresa) {
            $empresa_id = $empresa->id;
        } else {
            $empresa_id = null;
        }

        $proveedor = $this->proveedorRepository->findPorCodigo(ltrim((string) ($data->cpro_proveedor ?? ''), '0'));
        if ($proveedor) {
            $proveedor_id = $proveedor->id;
        } else {
            $proveedor_id = null;
        }

        $estadocheque_banco = Estadocheque_Banco::query()
            ->where('codigoexterno', $data->cpro_estado_banco ?? '')
            ->first();
        if ($estadocheque_banco)
            $estadoChequeBanco_id = $estadocheque_banco->id;
        else
            $estadoChequeBanco_id = null;
    }

    private function convierteDatosParaAnita($data, &$codigo, &$fechaCheque, &$fechaEmision, &$proveedor, &$modelo, 
                                        &$caracter, &$empresa, &$negociable, &$estadoBanco, &$numeroEcheq,
                                        &$estado)
    {
        $cuentacaja = $this->cuentacajaRepository->find($data['cuentacaja_id']);
        if ($cuentacaja)
            $codigo = $cuentacaja->codigo;
        else
            $codigo = null;

        $fechaEmision = date('Ymd', $data['fechaemision']);
        $fechaCheque = date('Ymd', $data['fechacheque']);

        $proveedor = $this->proveedorRepository->find($data['proveedor_id']);
        if ($proveedor)
            $proveedor = $proveedor->codigo;
        else
            $proveedor = null;

        $caracter = ($data['caracter'] == 'N' ? 'S' : 'N');

        $empresa = $this->empresaRepository->find($data['empresa_id']);
        if ($empresa)
            $empresa = $empresa->codigo;
        else
            $empresa = null;

        $chequera = $this->chequeraRepository->find($data['chequera_id']);
        if ($chequera)
        {
            $modelo = $chequera->codigo;
            $tipoChequera = $chequera->tipochequera;
        }
        else
        {
            $modelo = null;
            $tipoChequera = null;
        }
        if ($tipoChequera == 'E')
            $caracter = 'E';
        else
            $caracter = ($data['caracter'] == 'N' ? 'S' : 'N');

        switch($tipoChequera)
        {
        case 'F': // Fisica
            $negociable = 'N';
            break;
        case 'E': // Electronica
            $negociable = 'E';
            break;
        }

        $estadocheque_banco = $this->estadocheque_bancoRepository->find($data['estadocheque_banco_id']);

        if ($estadocheque_banco)
            $estadoBanco = $estadocheque_banco->codigoexterno;
        else
            $estadoBanco = null;

        $numeroEcheq = '';
        if ($negociable == 'E')
            $numeroEcheq = $data['numerocheque'];

        switch($data['estado'])
        {
        case 'DIFERIDO':
            $estado = ' ';
            break;
        case 'DEBITADO':
            $estado = '*';
            break;
        case 'CIERRE':
            $estado = 'C';
            break;
        case 'ANULADO':
            $estado = 'A';
            break;
        case 'RECHAZADO':
            $estado = 'R';
            break;
        case 'NO_PRESENTADO':
            $estado = 'N';
            break;
        }
    }
}

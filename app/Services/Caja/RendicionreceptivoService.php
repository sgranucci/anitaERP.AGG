<?php
namespace App\Services\Caja;

use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;
use App\Repositories\Configuracion\SeteosalidaRepositoryInterface;
use App\Repositories\Configuracion\MonedaRepositoryInterface;
use App\Repositories\Caja\RendicionreceptivoRepositoryInterface;
use App\Repositories\Caja\Rendicionreceptivo_Caja_MovimientoRepositoryInterface;
use App\Repositories\Caja\Rendicionreceptivo_VoucherRepositoryInterface;
use App\Repositories\Caja\Rendicionreceptivo_FormapagoRepositoryInterface;
use App\Repositories\Caja\Rendicionreceptivo_ComisionRepositoryInterface;
use App\Repositories\Caja\Rendicionreceptivo_AdelantoRepositoryInterface;
use App\Repositories\Caja\Caja_MovimientoRepositoryInterface;
use App\Repositories\Caja\CuentacajaRepositoryInterface;
use App\Repositories\Caja\VoucherRepositoryInterface;
use App\Repositories\Caja\Voucher_GuiaRepositoryInterface;
use App\Repositories\Caja\ConceptogastoRepositoryInterface;
use App\Services\Caja\IngresoEgresoService;
use App\Services\Configuracion\CotizacionService;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Carbon\Carbon;
use App;
use Auth;
use DB;
use Exception;

class RendicionreceptivoService 
{
	private $rendicionreceptivoRepository;
    private $rendicionreceptivo_caja_movimientoRepository;
    private $rendicionreceptivo_voucherRepository;
    private $rendicionreceptivo_formapagoRepository;
    private $rendicionreceptivo_comisionRepository;
    private $rendicionreceptivo_adelantoRepository;
    private $caja_movimientoRepository;
    private $cuentacajaRepository;
    private $voucherRepository;
    private $voucher_guiaRepository;
    private $ingresoegresoService;
    private $cotizacionService;
    private $conceptogastoRepository;
    private $monedaRepository;

    public function __construct(RendicionreceptivoRepositoryInterface $rendicionreceptivorepository,
                                Rendicionreceptivo_Caja_MovimientoRepositoryInterface $rendicionreceptivo_caja_movimientorepository,
                                Rendicionreceptivo_VoucherRepositoryInterface $rendicionreceptivo_voucherrepository,
                                Rendicionreceptivo_FormapagoRepositoryInterface $rendicionreceptivo_formapagorepository,
                                Rendicionreceptivo_ComisionRepositoryInterface $rendicionreceptivo_comisionrepository,
                                Rendicionreceptivo_AdelantoRepositoryInterface $rendicionreceptivo_adelantorepository,
                                Caja_MovimientoRepositoryInterface $caja_movimientorepository,
                                CuentacajaRepositoryInterface $cuentacajarepository,
                                VoucherRepositoryInterface $voucherrepository,
                                Voucher_GuiaRepositoryInterface $voucher_guiarepository,
                                ConceptogastoRepositoryInterface $conceptogastorepository,
                                MonedaRepositoryInterface $monedarepository,
                                IngresoEgresoService $ingresoegresoservice,
                                CotizacionService $cotizacionservice
								)
    {
		$this->rendicionreceptivoRepository = $rendicionreceptivorepository;
        $this->rendicionreceptivo_caja_movimientoRepository = $rendicionreceptivo_caja_movimientorepository;
        $this->caja_movimientoRepository = $caja_movimientorepository;
        $this->voucherRepository = $voucherrepository;
        $this->voucher_guiaRepository = $voucher_guiarepository;
        $this->conceptogastoRepository = $conceptogastorepository;
        $this->cuentacajaRepository = $cuentacajarepository;
        $this->rendicionreceptivo_voucherRepository = $rendicionreceptivo_voucherrepository;
        $this->rendicionreceptivo_formapagoRepository = $rendicionreceptivo_formapagorepository;
        $this->rendicionreceptivo_adelantoRepository = $rendicionreceptivo_adelantorepository;
        $this->rendicionreceptivo_comisionRepository = $rendicionreceptivo_comisionrepository;
        $this->monedaRepository = $monedarepository;
        $this->ingresoegresoService = $ingresoegresoservice;
        $this->cotizacionService = $cotizacionservice;
    }

	public function guardaRendicionreceptivo($request)
	{
		session(['empresa_id' => 1]);
		$data = $request->all();

		DB::beginTransaction();
        try
        {
            $rendicionreceptivo = $this->rendicionreceptivoRepository->create($request->all());

            if ($rendicionreceptivo == 'Error')
                throw new Exception('Error en grabación transacción');

            // Guarda tablas asociadas
            if ($rendicionreceptivo)
            {
                $id = $rendicionreceptivo->id;
                
                $rendicionreceptivo_caja_movimiento = $this->rendicionreceptivo_caja_movimientoRepository->create($data, $rendicionreceptivo->id);
                $rendicionreceptivo_voucher = $this->rendicionreceptivo_voucherRepository->create($data, $rendicionreceptivo->id);
                $rendicionreceptivo_formapago = $this->rendicionreceptivo_formapagoRepository->create($data, $rendicionreceptivo->id);
                $rendicionreceptivo_comision = $this->rendicionreceptivo_comisionRepository->create($data, $rendicionreceptivo->id);
                $rendicionreceptivo_adelanto = $this->rendicionreceptivo_adelantoRepository->create($data, $rendicionreceptivo->id);

                // Graba gastos a compensar
                Self::armaGastoACompensar($request, 'create', $id);

                // Graba ingreso / egreso por saldo de rendicion
                if (isset($data['montorendiciones']))
                {
                    for ($i = 0; $i < count($data['montorendiciones']); $i++)
                    {
                        if ($data['montorendiciones'][$i] != 0)
                            Self::armaSaldoRendicion($request, $data['montorendiciones'][$i], $data['monedarendicion_ids'][$i], 'create', $id);
                    }
                }
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollback();

            // Borra el asiento creado

            return ['errores' => $e->getMessage()];
        }
        return ['mensaje' => 'ok'];
	}

    public function actualizaRendicionreceptivo($request, $id)
    {
        session(['empresa_id' => $request->empresa_id]);
		$data = $request->all();

        DB::beginTransaction();
        try
        {
            // Graba movimiento de caja
            $rendicionreceptivo = $this->rendicionreceptivoRepository->update($data, $id);

			if ($rendicionreceptivo === 'Error')
                throw new Exception('Error en grabación transacción.');

            // Graba movimientos de cuentas de caja
            $this->rendicionreceptivo_caja_movimientoRepository->update($data, $id);

			// Graba movimientos de voucher
            $this->rendicionreceptivo_voucherRepository->update($data, $id);

			// Graba formapagos  
            $this->rendicionreceptivo_formapagoRepository->update($data, $id);

			// Graba comisiones  
            $this->rendicionreceptivo_comisionRepository->update($data, $id);
            
			// Graba adelantos  
            $this->rendicionreceptivo_adelantoRepository->update($data, $id);

            // Graba gastos a compensar
            Self::armaGastoACompensar($request, 'update', $id);

            // Graba ingreso / egreso por saldo de rendicion
            if (isset($data['montorendiciones']))
            {
                // Compara cantidad de resultados grabados con los que hay ahora
                $cantidadRendiciones = 0;
                for ($i = 0; $i < count($data['montorendiciones']); $i++)
                {
                    if ($data['montorendiciones'][$i] != 0)
                        $cantidadRendiciones++;
                }

                // Si hay mas resultados grabados borra lo que sobra
                if ($cantidadRendiciones < count($data['resultado_ids']))
                {
					for ($d = count($data['resultado_ids']); $d < $cantidadRendiciones; $d++)
						$this->caja_movimientoRepository->find($data['resultado_ids'][$d])->delete();
                }

                for ($i = 0; $i < count($data['montorendiciones']); $i++)
                {
                    if ($data['montorendiciones'][$i] != 0)
                        Self::armaSaldoRendicion($request, $data['montorendiciones'][$i], $data['monedarendicion_ids'][$i], 'update', $id,  
                            $data['resultado_ids'][$i]);
                }
            }
            
            DB::commit();
        } catch (\Exception $e) {
            DB::rollback();

            return ['errores' => $e->getMessage()];
        }
        return ['mensaje' => 'ok'];
    }

	public function borraRendicionreceptivo($id)
	{
	}

    private function armaGastoACompensar($request, $funcion, $id)
    {
        // Por cada gasto a comensar graba movimiento de caja (ingreso / egreso) 
        if ($request->cuentacaja_ids)
        {
            for ($i = 0; $i < count($request->cuentacaja_ids); $i++)
            {
                // Lee la cuenta de caja
                $cuentacaja = $this->cuentacajaRepository->find($request->cuentacaja_ids[$i]);

                $cuentacontable_ids = [];
                $monedaasiento_ids = [];
                $centrocostoasiento_ids = [];
                $haberasientos = [];
                $debeasientos = [];
                $cotizacionasientos = [];
                $observacionasientos = [];
                if ($cuentacaja)
                {
                    $cuentacontable_ids[] = $cuentacaja->cuentacontable_id;
                    $monedaasiento_ids[] = $request->moneda_ids[$i];
                    $centrocostoasientos_ids[] = null;
                    $haberasientos[] = $request->montos[$i];
                    $debeasientos[] = 0;
                    $cotizacionasientos[] = $request->cotizaciones[$i];
                    $observacionasientos[] = ' ';
                }

                // Agrega contrapartida del asiento
                $conceptogasto = $this->conceptogastoRepository->find($request->conceptogasto_ids[$i]);

                if ($conceptogasto)
			    {   
                    // Extrae la cuenta contable del gasto
                    foreach($conceptogasto->conceptogasto_cuentacontables as $cuenta)
                    {
                        if ($cuenta->cuentacontables->empresa_id == $request->empresa_id)
                        {
                            $cuentacontable_ids[] = $cuenta->cuentacontables->id;
                            $monedaasiento_ids[] = $request->moneda_ids[$i];
                            $centrocostoasientos_ids[] = null;
                            $debeasientos[] = $request->montos[$i];
                            $haberasientos[] = 0;
                            $cotizacionasientos[] = $request->cotizaciones[$i];
                            $observacionasientos[] = ' ';
                        }
                    }
                }

                $request->merge(['cuentacontable_ids' => $cuentacontable_ids]);
                $request->merge(['monedaasiento_ids' => $monedaasiento_ids]);
                $request->merge(['centrocostoasiento_ids' => $centrocostoasientos_ids]);
                $request->merge(['debeasientos' => $debeasientos]);
                $request->merge(['haberasientos' => $haberasientos]);
                $request->merge(['cotizacionasientos' => $cotizacionasientos]);
                $request->merge(['observacionasientos' => $observacionasientos]);

                // Graba egreso de caja
                $request->merge(['tipotransaccion_caja_id' => config('receptivo.gastos_a_compensar.tipotransaccion_caja_id')]);
                $request->merge(['conceptogasto_id' => $request->conceptogasto_ids[$i]]);
                $request->merge(['rendicionreceptivo_id' => $id]);
                $request->merge(['observaciones' => [' ']]);
                $request->merge(['detalle' => 'Rendicion Nro. '.$request->id]);

                switch($funcion)
                {
                    case 'create':
                        $this->ingresoegresoService->guardaIngresoEgreso($request, "rendicionreceptivo");
                        break;
                    case 'update':
                        $this->ingresoegresoService->actualizaIngresoEgreso($request, $request->gasto_ids[$i], 'rendicionreceptivo');
                        break;
                }
            }
        }
    }

    private function armaSaldoRendicion($request, $monto, $moneda_id, $funcion, $id, $movimiento_id = null)
    {
        $moneda = $this->monedaRepository->find($moneda_id);
        $cuentacaja_id = config('receptivo.rendicion.cuentacaja.'.$moneda->abreviatura);
        if ($monto < 0)
            $conceptogasto_id = config('receptivo.rendicion.conceptogasto_egreso_id');
        else    
            $conceptogasto_id = config('receptivo.rendicion.conceptogasto_ingreso_id');

        // Calcula cotizacion
        $cotizacion = $this->cotizacionService->leeCotizacionDiaria($request->fecha, $moneda_id);
        $cotizacionVenta = $cotizacion['cotizacionventa'];

        // Pasa a arrays para grabacion
        $cuentacaja_ids = [$cuentacaja_id];
        $conceptogasto_ids = [$conceptogasto_id];
        $moneda_ids = [$moneda_id];
        $montos = [$monto];
        $cotizaciones = [$cotizacionVenta];

        $request->merge(['cuentacaja_ids' => $cuentacaja_ids]);
        $request->merge(['moneda_ids' => $moneda_ids]);
        $request->merge(['montos' => $montos]);
        $request->merge(['cotizaciones' => $cotizaciones]);

        // Arma asiento contale
        $asiento = Self::armaAsiento($request->empresa_id, $cuentacaja_id, $conceptogasto_id, $monto, $moneda_id, $cotizacionVenta);

        $request->merge(['cuentacontable_ids' => $asiento['cuentacontable_ids']]);
        $request->merge(['monedaasiento_ids' => $asiento['monedaasiento_ids']]);
        $request->merge(['centrocostoasiento_ids' => $asiento['centrocostoasiento_ids']]);
        $request->merge(['debeasientos' => $asiento['debeasientos']]);
        $request->merge(['haberasientos' => $asiento['haberasientos']]);
        $request->merge(['cotizacionasientos' => $asiento['cotizacionasientos']]);
        $request->merge(['observacionasientos' => $asiento['observacionasientos']]);

        // Graba egreso de caja
        $request->merge(['conceptogasto_id' => $conceptogasto_id]);
        $request->merge(['rendicionreceptivo_id' => $id]);
        $request->merge(['observaciones' => [' ']]);
        if ($monto < 0)
        {
            $request->merge(['tipotransaccion_caja_id' => config('receptivo.rendicion.tipotransaccion_caja_egreso_id')]);
            $request->merge(['detalle' => 'Devolucion Rendicion Nro. '.$request->id]);
        }
        else
        {
            $request->merge(['tipotransaccion_caja_id' => config('receptivo.rendicion.tipotransaccion_caja_ingreso_id')]);
            $request->merge(['detalle' => 'Ingreso por Rendicion Nro. '.$request->id]);
        }

        switch($funcion)
        {
            case 'create':
                $this->ingresoegresoService->guardaIngresoEgreso($request, "rendicionreceptivo");
                break;
            case 'update':
                $this->ingresoegresoService->actualizaIngresoEgreso($request, $movimiento_id, 'rendicionreceptivo');
                break;
        }
    }

    private function armaAsiento($empresa_id, $cuentacaja_id, $conceptogasto_id, $monto, $moneda_id, $cotizacion)
    {
        // Lee la cuenta de caja
        $cuentacaja = $this->cuentacajaRepository->find($cuentacaja_id);

        $cuentacontable_ids = [];
        $monedaasiento_ids = [];
        $centrocostoasiento_ids = [];
        $haberasientos = [];
        $debeasientos = [];
        $cotizacionasientos = [];
        $observacionasientos = [];
        if ($cuentacaja)
        {
            $cuentacontable_ids[] = $cuentacaja->cuentacontable_id;
            $monedaasiento_ids[] = $moneda_id;
            $centrocostoasientos_ids[] = null;
            if ($monto < 0)
            {
                $haberasientos[] = abs($monto);
                $debeasientos[] = 0;
            }
            else
            {
                $debeasientos[] = $monto;
                $haberasientos[] = 0;
            }
            $cotizacionasientos[] = $cotizacion;
            $observacionasientos[] = ' ';
        }

        // Agrega contrapartida del asiento
        $conceptogasto = $this->conceptogastoRepository->find($conceptogasto_id);

        if ($conceptogasto)
        {   
            // Extrae la cuenta contable del gasto
            foreach($conceptogasto->conceptogasto_cuentacontables as $cuenta)
            {
                if ($cuenta->cuentacontables->empresa_id == $empresa_id)
                {
                    $cuentacontable_ids[] = $cuenta->cuentacontables->id;
                    $monedaasiento_ids[] = $moneda_id;
                    $centrocostoasientos_ids[] = null;
                    if ($monto < 0)
                    {
                        $debeasientos[] = abs($monto);
                        $haberasientos[] = 0;
                    }
                    else
                    {
                        $haberasientos[] = $monto;
                        $debeasientos[] = 0;
                    }
                    $cotizacionasientos[] = $cotizacion;
                    $observacionasientos[] = ' ';
                }
            }
        }

        return ['cuentacontable_ids' => $cuentacontable_ids,
                'monedaasiento_ids' => $monedaasiento_ids,
                'centrocostoasiento_ids' => $centrocostoasientos_ids,
                'debeasientos' => $debeasientos,
                'haberasientos' => $haberasientos,
                'cotizacionasientos' => $cotizacionasientos,
                'observacionasientos' => $observacionasientos
                ];
    }

    public function leeGastoAnterior($ordenservicio_id)
    {
        $gastoAnterior = [];
        $adelanto = [];

        $caja_movimiento = $this->caja_movimientoRepository->leeGastoAnterior($ordenservicio_id);

        foreach($caja_movimiento as $movimiento)
        {
            if ($movimiento->abreviatura != "ADE")
                $gastoAnterior[] = [    
                            'id' => $movimiento->id,
                            'nombregasto' => $movimiento->nombreconceptogasto,
                            'codigocuentacaja' => $movimiento->codigocuentacaja,
                            'nombrecuentacaja' => $movimiento->nombrecuentacaja,
                            'abreviaturamoneda' => $movimiento->abreviaturamoneda,
                            'moneda_id' => $movimiento->moneda_id,
                            'cotizacion' => $movimiento->cotizacion,
                            'monto' => abs($movimiento->monto)
                ];
            else
                $adelanto[] = [    
                            'id' => $movimiento->id,
                            'nombregasto' => $movimiento->nombreconceptogasto,
                            'codigocuentacaja' => $movimiento->codigocuentacaja,
                            'nombrecuentacaja' => $movimiento->nombrecuentacaja,
                            'abreviaturamoneda' => $movimiento->abreviaturamoneda,
                            'moneda_id' => $movimiento->moneda_id,
                            'cotizacion' => $movimiento->cotizacion,
                            'monto' => abs($movimiento->monto)
                ];
        }
        return ['mensaje' => 'ok', 'gastoanterior' => $gastoAnterior, 'adelanto' => $adelanto];
    }

    public function leeVoucher($guia_id, $ordenservicio_id)
    {
        $arrayVoucher = [];
        $arrayComision = [];

        $vouchers = $this->voucherRepository->leeVoucherPorGuiaOrdenservicio($guia_id, $ordenservicio_id);

        $comisiones = $this->voucher_guiaRepository->leeComisionPorGuiaOrdenservicio($guia_id, $ordenservicio_id);

        foreach($vouchers as $voucher)
        {
            $arrayVoucher[] = [    
							'id' => $voucher->id,
							'fecha' => $voucher->fecha,
                            'cuentacaja_id' => $voucher->cuentacaja_id,
							'codigocuentacaja' => $voucher->codigocuentacaja,
							'nombrecuentacaja' => $voucher->nombrecuentacaja,
							'abreviaturamoneda' => $voucher->abreviaturamoneda,
							'moneda_id' => $voucher->moneda_id,
							'cotizacion' => $voucher->cotizacion,
							'monto' => abs($voucher->monto)
            ];
        }

        foreach($comisiones as $comision)
        {
            $arrayComision[] = [
							'id' => $comision->id,
							'fecha' => $comision->fecha,
                            'cuentacaja_id' => $comision->cuentacaja_id,
							'codigocuentacaja' => $comision->codigocuentacaja,
							'nombrecuentacaja' => $comision->nombrecuentacaja,
							'abreviaturamoneda' => $comision->abreviaturamoneda,
							'moneda_id' => $comision->moneda_id,
							'cotizacion' => $comision->cotizacion,
							'monto' => abs($comision->monto)
            ];
        }
        return ['mensaje' => 'ok', 'voucher' => $arrayVoucher, 'comision' => $arrayComision];
    }

    public function leeOrdenServicioPendiente()
    {
        $voucher = $this->voucher_guiaRepository->leeOrdenServicioVoucher();

        $caja_movimiento = $this->caja_movimientoRepository->leeOrdenServicioCajaMovimiento();
        $ordenservicio_ids = [];
        foreach($voucher as $orden)
        {
            for ($i = 0, $flEncontro = false; $i < count($ordenservicio_ids); $i++)
            {
                if ($ordenservicio_ids[$i] == $orden->ordenservicio_id)
                    $flEncontro = true;
            }
            if (!$flEncontro)
                $ordenservicio_ids[] = $orden->ordenservicio_id;
        }
        
        foreach($caja_movimiento as $orden)
        {
            for ($i = 0, $flEncontro = false; $i < count($ordenservicio_ids); $i++)
            {
                if ($ordenservicio_ids[$i] == $orden->ordenservicio_id)
                    $flEncontro = true;
            }
            if (!$flEncontro)
                $ordenservicio_ids[] = $orden->ordenservicio_id;
        }            

        return $ordenservicio_ids;
    }
}
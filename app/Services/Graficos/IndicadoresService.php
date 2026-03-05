<?php

namespace App\Services\Graficos;

use App\Mail\Graficos\Trade;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use DB;
use Mail;

class IndicadoresService
{
	private $desdeFecha, $hastaFecha;
    private $desdeHora, $hastaHora;

    public $especie;
    public $compresion, $compresiontxt, $factorCompresion;
    public $calculoBase, $mmCorta, $mmLarga, $calculoBase_enum;
    public $largoVMA, $largoCCI, $largoXTL, $umbralXTL;
    public $swingSize;
    public $filtroSetup;
    public $ticker, $valorTicker;
    public $totalContratos;
    public $cantidadActivaContratos;
    public $item;

    // Variables de job para generar ordenes
    public $comision;
    public $acumOpen = 0;
    public $acumClose = 0;
    public $acumLow = 0;
    public $acumHigh = 0;
    public $acumTotVolume = 0;
    public $cantLectura = 0;
    public $acumItem = 0;
    public $acumFechaLectura;
    public $acumCantLectura;
    public $acumFlEmpezoRango;
    public $acumMinutoLectura;
    public $acumFecha;
    public $acumFechaInicioRango;
    public $acumHoraInicio;
    public $acumFlBuscaEntrada, $acumFlAbrePosicion;
    public $acumOff0, $acumOff1oA;
    public $acumFlAcista, $acumFlBajista;
    public $acumFlAnulacionAbcAlcistaActiva;
    public $acumflAnulacionAbcBajistaActiva;
    public $acumFlAnulacionAbCdAlcistaActiva;
    public $acumflAnulacionAbCdBajistaActiva;
    public $acumIdSenial, $acumIdTrade;
    public $acumFlCerroPorTiempoAlcista, $acumFlCerroPorTiempoBajista, $acumFlCierraPorTiempo;
    public $acumValorEntrada, $acumStopLoss;
    public $acumQVentanaEntrada, $acumPuntoEntrada;
    public $acumT1, $acumT2, $acumT3, $acumT4;
    public $acumOffAbrePosicion;
    public $acumTipoOperacion;
    public $acumProfitAndLoss;
    public $acumTendencia, $acumBnMinActual, $acumBnMaxActual, $acumMaximoActual, $acumMinimoActual;
    public $acumBnMaximo, $acumBnMinimo, $acumBnMaximoAnterior, $acumBnMinimoAnterior;
    public $ultimoMaximoAlcista;
    public $ultimoMinimoAlcista;
    public $ultimoMaximoBajista;
    public $ultimoMinimoBajista;
    public $flSinFiltros;
    
    private $flBatch;
    private $k1, $k2;
    private $dataAnterior = [];
    private $fechaUltimaLectura;
    private $datas = [];
    public $operaciones = [];
	protected $dates = ['fecha'];
    private $offsetMinimoE;
    private $offsetMaximoD;
    private $offsetMinimoC;
    private $offsetMaximoB;
    private $offsetMinimoA;
    private $offsetMinimoD;
    private $offsetMaximoC;
    private $offsetMinimoB;
    private $offsetMaximoA;
    private $offsetD;
    private $offsetCAbc;
    private $offsetCAbCd;
    private $offsetBAbc;
    private $offsetBAbCd;
    private $offsetAAbc;
    private $offsetAAbCd;
    private $offsetMaximoCW4;
    private $offsetMinimoTW4;
    private $offsetMaximoDW4;
    private $offsetMinimoUW4;
    private $offsetMaximoOW4;
    private $offsetMinimoCW4;
    private $offsetMaximoTW4;
    private $offsetMinimoDW4;
    private $offsetMaximoUW4;
    private $offsetMinimoOW4;
    private $acumFlAnulacionW4AlcistaActiva;
    private $acumFlAnulacionW4BajistaActiva;
    private $offsetU;
    private $offsetO;
    private $offsetAbCd;
    private $offsetAbc;
    private $offset3Drives;
    private $offsetShark;
    private $offsetW4, $q = 0;
    private $offsetSp;
	private $tgt = [];
    private $pivotes = [];
    private $administracionPosicion;
    private $tiempo;
    public $flVolatilidad, $flInertia;
    public $flSpAlcista;
    public $tgtSpAlcista1;
    public $ventanaSpAlcista;
    public $flSpBajista;
    public $tgtSpBajista1;
    public $ventanaSpBajista;
    public $flAnulaCandidato;
    public $flEmpiezaOperacion;
    public $acumconFiltrosCandidato;
    public $acumFlFiltroOutBound;
    public $acumCantidadPivotes = 0;
    public $gatillo;

	public function calculaIndicadores($desdefecha, $hastafecha, $desdehora, $hastahora, $especie, $calculobase, 
                                        $mmcorta, $mmlarga, $compresion, $largovma, $largocci, $largoxtl,
                                        $umbralxtl, $calculobase_enum, $swingSize, $filtroSetup, 
                                        $totalContratos, $administracionposicion, $tiempo, $filtrosMatematicos,
                                        $gatillo)
	{
        ini_set('memory_limit', '512M');
        ini_set('max_execution_time', '2400');

        $this->desdeFecha = $desdefecha;
		$this->hastaFecha = $hastafecha;
		$this->desdeHora = $desdehora;
		$this->hastaHora = $hastahora;
        $this->especie = $especie;
		$this->compresion = $compresion;
        $this->calculoBase = $calculobase;
        $this->calculoBase_enum = $calculobase_enum;
        $this->mmCorta = $mmcorta;
        $this->mmLarga = $mmlarga;
        $this->largoVMA = $largovma;
        $this->largoCCI = $largocci;
        $this->largoXTL = $largoxtl;
        $this->umbralXTL = $umbralxtl;
        $this->swingSize = $swingSize;
        $this->filtroSetup = $filtroSetup;
        $this->totalContratos = $totalContratos;
        $this->ticker = 0.25;
        $this->valorTicker = 12.5;
        $this->comision = 2.25;
        $this->administracionPosicion = $administracionposicion;
        $this->tiempo = $tiempo;
        $this->flSinFiltros = ($filtrosMatematicos == 'S' ? false : true);
        $this->gatillo = $gatillo;

        $this->acumFlBuscaEntrada = $this->acumFlAbrePosicion = false;
        $this->acumOff0 = $this->acumOff1oA = -1;
        $this->acumFlAcista = false;
        $this->acumFlBajista = false;
        $this->flInertia = false;
        $this->flVolatilidad = false;
        $this->acumFlAnulacionAbcAlcistaActiva = false;
        $this->acumFlAnulacionAbcBajistaActiva = false;
        $this->acumFlAnulacionAbCdAlcistaActiva = false;
        $this->acumFlAnulacionAbCdBajistaActiva = false;

        // Por ahora no utiliza filtros para anular candidatos
        $this->acumconFiltrosCandidato = true;

        // Filtro outbound
        $this->acumFlFiltroOutBound = false;
        $this->acumCantidadPivotes = 0;

        $this->acumIdSenial = $this->acumIdTrade = 0;
        $this->cantidadActivaContratos = $this->totalContratos;
        $this->acumFlCerroPorTiempoAlcista = false;
        $this->acumFlCerroPorTiempoBajista = false;
        $this->acumFlCierraPorTiempo = false;
        $this->acumTipoOperacion = '';
        $this->acumProfitAndLoss = 0.;
        $this->pivotes = [];

        $this->offsetMaximoCW4 = 0;
        $this->offsetMinimoTW4 = 0;
        $this->offsetMaximoDW4 = 0;
        $this->offsetMinimoUW4 = 0;
        $this->offsetMaximoOW4 = 0;
        $this->offsetMinimoCW4 = 0;
        $this->offsetMaximoTW4 = 0;
        $this->offsetMinimoDW4 = 0;
        $this->offsetMaximoUW4 = 0;
        $this->offsetMinimoOW4 = 0;
        $this->acumFlAnulacionW4AlcistaActiva = false;
        $this->acumFlAnulacionW4BajistaActiva = false;

        $this->flSpAlcista = false;
        $this->tgtSpAlcista1 = 0;
        $this->ventanaSpAlcista = 0;

        $this->flSpBajista = false;
        $this->tgtSpBajista1 = 0;
        $this->ventanaSpBajista = 0;
        $this->flAnulaCandidato = true;
        $this->flEmpiezaOperacion = false;

        $this->ultimoMaximoBajista = 0;
        $this->ultimoMinimoBajista = 0;
        $this->ultimoMaximoAlcista = 0;
        $this->ultimoMinimoAlcista = 0;

        $this->offsetD = 0;
        $this->offsetCAbc = 0;
        $this->offsetBAbc = 0;
        $this->offsetAAbc = 0;
        $this->offsetCAbCd = 0;
        $this->offsetBAbCd = 0;
        $this->offsetAAbCd = 0;
        $this->offsetU = 0;
        $this->offsetO = 0;
        
        // Variables de calculo de swing
        $this->acumTendencia = 'Indefinida';
        $this->acumBnMinActual = $this->acumBnMaxActual = $this->acumMaximoActual = $this->acumMinimoActual = 0;
        $this->acumBnMaximo = $this->acumBnMinimo = $this->acumBnMaximoAnterior = $this->acumBnMinimoAnterior = 0;

        switch($compresion)
        {
        case 1:
            $this->compresiontxt = "1 minuto";
            $this->factorCompresion = 1;
            break;
        case 2:
            $this->compresiontxt = "5 minutos";
            $this->factorCompresion = 5;
            break;
        case 3:
            $this->compresiontxt = "15 minutos";
            $this->factorCompresion = 15;
            break;
        case 4:
            $this->compresiontxt = "1 hora";
            $this->factorCompresion = 60;
            break;
        case 5:
            $this->compresiontxt = "1 día";
            $this->factorCompresion = 3600;
            break;
        }

        if ($this->factorCompresion == 3600)
        {
            $fechaAnterior = date("d-m-Y",strtotime($this->desdeFecha."- 1 days")); 
            $desde_fecha = strtotime($fechaAnterior.' '.'19:00')*1000;
        }
        else
            $desde_fecha = strtotime($this->desdeFecha.' '.$this->desdeHora)*1000;

        $hasta_fecha = strtotime($this->hastaFecha.' '.$this->hastaHora)*1000;
        $calculoBaseTxt = $this->calculoBase_enum[$this->calculoBase];
        $this->k2 = 2 / ($this->mmCorta + $this->mmLarga);
        $this->k1 = 1 - $this->k2;
        $this->flBatch = false;
                
		$data = DB::connection('trade')->table('trade.lecturas')
				->select('fechaChar as fechastr',
                         'chartTime as fecha',
						 'openPrice as open',
						 'highPrice as high',
						 'lowPrice as low',
						 'closePrice as close',
						 'volume')
                ->where('especie', $this->especie)
				->whereBetween('chartTime', [$desde_fecha, $hasta_fecha])
                ->orderBy('fechaLectura')
                ->get();

        // Procesa compresion
        $open = $close = $low = $high = $totVolume = 0;
        $closeAnt = 0;
        $cantLectura = 0;
        $this->datas = [];
        $this->operaciones = [];
        $item = 0;
        if ($this->factorCompresion == 1)
            $flEmpezoRango = true;    
        else
            $flEmpezoRango = false;
        foreach($data as $lectura)
        {
            // Saltea fechas repetidas
            if (isset($fechaLectura))
            {
                if (date('Y-m-d H:i', ceil($lectura->fecha/1000)) == $fechaLectura)
                    continue;
            }

            $fechaLectura = date('Y-m-d H:i', ceil($lectura->fecha/1000));
            $minutoLect = date('i', ceil($lectura->fecha/1000));

            // Verifica arrancar en divisor del factor de compresion
            if ($this->factorCompresion > 1 && !$flEmpezoRango)
            {
                if ($minutoLect % $this->factorCompresion == 0)
                    $flEmpezoRango = true;
            }
            if ($flEmpezoRango)
            {
                // Corte Si es por dia
                $flCorte = false;
                if ($this->factorCompresion == 3600)
                {
                    $horaLect = date('i', ceil($lectura->fecha/1000));
                    // Corta el dia a las 17:59
                    if ($horaLect >= '17:59' && $horaLect < '19:00')
                        $flCorte = true;
                }
                else // Corte si es por minutos
                {
                    if (!isset($fechaInicioRango))
                        $fechaInicioRango = date('Y-m-d H:i', ceil($lectura->fecha/1000));
                        
                    $difMinutos = (int) abs(\Carbon\Carbon::parse($fechaInicioRango)->diffInMinutes($fechaLectura));
                    if ($difMinutos >= $this->factorCompresion ||
                        ($minutoLect % $this->factorCompresion == 0 && isset($fecha)))
                        $flCorte = true;
                }
                if ($flCorte)
                {
                    switch($this->calculoBase)
                    {
                        case 1: // HL2
                            $base = ($high + $low) / 2;
                            break;
                        case 2: // HLC3
                            $base = ($high + $low + $close) / 3;
                            break;
                        case 3: // OHLC4
                            $base = ($open + $high + $low + $close) / 4;
                            break;
                    }
                    // Calcula EWO                    
                    $item++;
                    $ewo = $bandaSup = $bandaInf = 0;
					$smac = $smal = 0;
                    $w4Up1 = $w4Up2 = $w4Dw1 = $w4Dw2 = 0;
                    $this->calculaEWO($item, $base, $smac, $smal, $ewo, $bandaSup, $bandaInf,
                                        $w4Up1, $w4Up2, $w4Dw1, $w4Dw2);
                    
                    // Calcula pivot de fibonacci
                    $this->calculaFibonacci($fechaInicioRango, $rfLim, $rfeExt, $rfeInt, $rfiExt, $rfiInt, $pp1,
                                            $poc, $pp2, $sfiInt, $sfiExt, $sfeInt, $sfeExt, $sfLim, $base);

                    $tmp1 = $tmp2 = $d1 = $d2 = $condicional = $d3 = $k = $VMA = $precioTipico = 0;
                    $SMACCI = $auxCCI = $blanco1 = $blanco2 = $CCI = $SMAXTL = $auxXTL = $CCIXTL = 0;
                    $estado = $rango = $TQRVerde = $stopTQRVerde = $tgtTQRVerde = $TQRRojo = 0;
                    $stopTQRRojo = $tgtTQRRojo = 0;

                    $trueRange = $averageTrueRange = $cciaTRadj = $obb = $osb = $atr21 = $atrmstdev = 0;
                    $regimenVolatilidad = $stdevHi = $stdevLo = $stdevH1 = $h1 = $h1Exp = $stdevH2 = 0;
                    $h2 = $h2Exp = $stdevL1 = $l1 = $liExp = $stdevL2 = $l2 = $l2Exp = $rvih0 = $rvih = $rbil0 = 0;
                    $rvil = $rviSimple = $rviExp = $x = $xCuadrado = $a = $b = $yaxb = $inertia = 0;

                    // Calcula VMA
                    $this->CalculaVMA($item, $closeAnt, $close, $tmp1, $tmp2, $d1, $d2, $condicional, $d3, $k, $VMA);
                    $closeAnt = $close;

                    // Calcula CCI
                    $this->CalculaCCI($item, $high, $low, $close, $precioTipico, $SMACCI, $auxCCI,
                        $blanco1, $blanco2, $CCI);

                    // Calcula XTL
                    $this->CalculaXTL($item, $high, $low, $precioTipico, $SMAXTL, $auxXTL, $CCIXTL, $estado, $rango, 
                                    $TQRVerde, $stopTQRVerde, $tgtTQRVerde,
                                    $TQRRojo, $stopTQRRojo, $tgtTQRRojo);

                    if ($item > 2)
                        $this->calculaNuevosIndicadores($item-1);

                    // Arma tabla 
                    $this->armaTabla($fechaStr, $fecha, $horaInicio, $open, $close, $low, $high, $totVolume, $ewo,
                                    $bandaSup, $bandaInf, $w4Up1, $w4Up2, $w4Dw1, $w4Dw2,
                                    $rfLim, $rfeExt, $rfeInt, $rfiExt, $rfiInt, $pp1, $poc, $pp2,
                                    $sfiInt, $sfiExt, $sfeInt, $sfeExt, $sfLim, $base, $smac, $smal, $tmp1, $tmp2, $d1, $d2,
                                    $condicional, $d3, $k, $VMA,  
                                    $trueRange, $averageTrueRange, $cciaTRadj, $obb, $osb, $atr21, $atrmstdev,
                                    $regimenVolatilidad, $stdevHi, $stdevLo, $stdevH1, $h1, $h1Exp, $stdevH2,
                                    $h2, $h2Exp, $stdevL1, $l1, $liExp, $stdevL2, $l2, $l2Exp, $rvih0, $rvih, $rbil0,
                                    $rvil, $rviSimple, $rviExp, $x, $xCuadrado, $a, $b, $yaxb, $inertia,
                                    $precioTipico, $SMACCI, $auxCCI, $blanco1, $blanco2,
                                    $CCI, $SMAXTL, $auxXTL, $CCIXTL, $estado, $rango, $TQRVerde, $stopTQRVerde, $tgtTQRVerde,
                                    $TQRRojo, $stopTQRRojo, $tgtTQRRojo);
                                    
                    $cantLectura = 0;
                    $low = $high = $totVolume = $open = $close = 0;

                    if ($item >= $this->swingSize)
                    {
                        // Calcula pivots
                        //$this->calculaPivot();

                        // Calcula volumen por swing y Tgt hit
                        $this->calculaSwingTgtBatch($item-2);
                    }
                }

                $fecha = $lectura->fecha;
                $totVolume += $lectura->volume;
                $cantLectura++;

                // Si es primer lectura del rango inicia variables
                if ($cantLectura == 1)
                {
                    $fechaInicioRango = $fechaLectura;
                    $fechaStr = $lectura->fechastr;
                    $horaInicio = date('H:i:s', ceil($lectura->fecha/1000));
                    $open = $lectura->open;
                    $low = $lectura->low;
                    $high = $lectura->high;
                }
                else
                {
                    if ($lectura->low < $low)
                        $low = $lectura->low;
                    if ($lectura->high > $high)
                        $high = $lectura->high;
                }  
                $close = $lectura->close;
            }
        }
        // Por si quedo ultimo rango sin cerrar
        if ($cantLectura > 1)
        {
            $this->armaTabla($fechaStr, $fecha, $horaInicio, $open, $close, $low, $high, $totVolume, $ewo,
                                $bandaSup, $bandaInf, $w4Up1, $w4Up2, $w4Dw1, $w4Dw2,
                                $rfLim, $rfeExt, $rfeInt, $rfiExt, $rfiInt, $pp1, $poc, $pp2,
                                $sfiInt, $sfiExt, $sfeInt, $sfeExt, $sfLim, $base, $smac, $smal, $tmp1, $tmp2, $d1, $d2,
                                $condicional, $d3, $k, $VMA, 
                                $trueRange, $averageTrueRange, $cciaTRadj, $obb, $osb, $atr21, $atrmstdev,
                                $regimenVolatilidad, $stdevHi, $stdevLo, $stdevH1, $h1, $h1Exp, $stdevH2,
                                $h2, $h2Exp, $stdevL1, $l1, $liExp, $stdevL2, $l2, $l2Exp, $rvih0, $rvih, $rbil0,
                                $rvil, $rviSimple, $rviExp, $x, $xCuadrado, $a, $b, $yaxb, $inertia,
                                $precioTipico, $SMACCI, $auxCCI, $blanco1, $blanco2,
                                $CCI, $SMAXTL, $auxXTL, $CCIXTL, $estado, $rango, $TQRVerde, $stopTQRVerde, $tgtTQRVerde,
                                $TQRRojo, $stopTQRRojo, $tgtTQRRojo);
                                
        }

        // Calcula pivots
        //$this->calculaPivot();

        // Calcula volumen por swing y Tgt hit
        //$this->calculaSwingTgt();
       
        return ['indicadores' => $this->datas, 'operaciones' => $this->operaciones];
    }

    private function calculaEWO($item, $base, &$smac, &$smal, &$ewo, &$bandaSup, &$bandaInf, &$w4Up1, &$w4Up2, &$w4Dw1, &$w4Dw2)
    {
        if ($item >= $this->mmLarga)
        {
            $smac = $this->promedio($base, $item, 'base', $this->mmCorta);
            $smal = $this->promedio($base, $item, 'base', $this->mmLarga);
            $ewo = $smac - $smal;

            if ($ewo > 0)
                $bandaSup = $this->datas[$item-2]['bandaSup'] + $this->k2 * ($this->k1 * $ewo - $this->datas[$item-2]['bandaSup']);
            else
                $bandaSup = $this->datas[$item-2]['bandaSup'];
            if ($ewo < 0)
                $bandaInf = $this->datas[$item-2]['bandaInf'] + $this->k2 * ($this->k1 * $ewo - $this->datas[$item-2]['bandaInf']);
            else
                $bandaInf = $this->datas[$item-2]['bandaInf'];
            $w4Up1 = $w4Up2 = $w4Dw1 = $w4Dw2 = 0;
            if ($ewo > max($bandaSup, $this->datas[$item-2]['w4Dw2']))
            {
                $w4Up1 = max($this->datas[$item-2]['ewo']*(1-0.9), $this->datas[$item-2]['w4Up1']);
                $w4Up2 = min($this->datas[$item-2]['ewo']*(1-1.3), $this->datas[$item-2]['w4Up2']);
            }
            elseif ($ewo < min($bandaInf, $this->datas[$item-2]['w4Up2']))
            {
                $w4Dw1 = min($this->datas[$item-2]['ewo']*(1-0.9), $this->datas[$item-2]['w4Dw1']);
                $w4Dw2 = max($this->datas[$item-2]['ewo']*(1-1.3), $this->datas[$item-2]['w4Dw2']);
            }
            else
            {
                $w4Up1 = $this->datas[$item-2]['w4Up1'];
                $w4Up2 = $this->datas[$item-2]['w4Up2'];
                $w4Dw1 = $this->datas[$item-2]['w4Dw1'];
                $w4Dw2 = $this->datas[$item-2]['w4Dw2'];
            }
        }
    }

    private function calculaFibonacci($fechaLectura, &$rfLim, &$rfeExt, &$rfeInt, &$rfiExt, &$rfiInt, &$pp1,
                                    &$poc, &$pp2, &$sfiInt, &$sfiExt, &$sfeInt, &$sfeExt, &$sfLim, &$base)
    {
        $oant = $hant = $lant = $cant = 0;

        $this->calculaDatosDiaAnterior($fechaLectura, $oant, $cant, $lant, $hant);

        $poc = ($hant + $lant + $cant) / 3;
        $rango = $hant - $lant;
        $tolerancia = $rango * 0.05;
        $x1 = $rango * 0.5;
        $x2 = $rango * 0.618;
        $x3 = $rango;
        $x4 = $rango * 1.382;
        $x5 = $rango * 2.618;
        $pp1 = $poc + $tolerancia;
        $pp2 = $poc - $tolerancia;
        $sfiInt = $poc - $x1 + $tolerancia;
        $sfiExt = $poc - $x2 - $tolerancia;
        $sfeInt = $poc - $x3 + $tolerancia;
        $sfeExt = $poc - $x4 - $tolerancia;
        $sfLim = $poc - $x5;
        $rfiInt = $poc + $x1 - $tolerancia;
        $rfiExt = $poc + $x2 + $tolerancia;
        $rfeInt = $poc + $x3 - $tolerancia;
        $rfeExt = $poc + $x4 + $tolerancia;
        $rfLim = $poc + $x5;
    }

    private function CalculaVMA($item, $closeAnt, $close, &$tmp1, &$tmp2, &$d1, &$d2, 
                                &$condicional, &$d3, &$k, &$VMA)
    {
        if ($close > $closeAnt && $item > 1)
            $tmp1 = $close - $closeAnt;
        else   
            $tmp1 = 0;

        if ($close < $closeAnt)
            $tmp2 = $closeAnt - $close;
        else    
            $tmp2;

        $d1 = $d2 = 0;
        if ($item >= $this->largoVMA)
        {
            $d1 = $this->acumulado($tmp1, $item, 'tmp1', $this->largoVMA);
            $d2 = $this->acumulado($tmp2, $item, 'tmp2', $this->largoVMA);
        }
        $condicional = $d1 + $d2;
        
        if ($condicional != 0)
            $d3 = ($d1 - $d2) / ($d1 + $d2) * 100;
        else   
            $d3 = 0;

        $k = (2 / ($this->largoVMA + 1)) * ABS($d3) / 100;  
        $anteriorVMA = 0;
        if ($item > 2) 
            $anteriorVMA = $this->datas[$item-2]['VMA'];
        $VMA = ($close * $k) + ($anteriorVMA * (1 - $k));
    }

    private function CalculaCCI($item, $high, $low, $close, &$precioTipico, &$SMACCI, &$auxCCI,
                        &$blanco1, &$blanco2, &$CCI)
    {
        $precioTipico = ($high + $low + $close) / 3;
        
        $SMACCI = $CCI = 0;
        if ($item >= $this->largoCCI)
        {
            $SMACCI = $this->promedio($precioTipico, $item, 'precioTipico', $this->largoCCI);

            //if ($item == 38)
                //dd($SMACCI.' '.$precioTipico.' '.$this->largoCCI);

            $this->lindev($item, $precioTipico, $this->largoCCI, $auxCCI);
            $CCI = ($precioTipico - $SMACCI) / (0.015 * $auxCCI);
        }
    }

    private function CalculaXTL($item, $high, $low, $precioTipico, &$SMAXTL, &$auxXTL, &$CCIXTL, 
                                &$estado, &$rango, 
                                &$TQRVerde, &$stopTQRVerde, &$tgtTQRVerde,
                                &$TQRRojo, &$stopTQRRojo, &$tgtTQRRojo)
    {
        $SMAXTL = $auxXTL = $CCIXTL = 0;
        if ($item >= $this->largoXTL)
        {
            $SMAXTL = $this->promedio($precioTipico, $item, 'precioTipico', $this->largoXTL);
            $this->lindev($item, $precioTipico, $this->largoXTL, $auxXTL);
        }
        if ($auxXTL != 0)
            $CCIXTL = ($precioTipico - $SMAXTL) / (0.015 * $auxXTL);
        
        $estado = "";
        if ($CCIXTL < $this->umbralXTL && $auxXTL > -$this->umbralXTL)
            $estado = "neutral";
        if ($CCIXTL > $this->umbralXTL)
            $estado = "verde";
        if ($CCIXTL < -$this->umbralXTL)
            $estado = "rojo";

        $rango = 0;
        $TQRVerde = 0;
        $stopTQRVerde = 0;
        $tgtTQRVerde = 0;
        $TQRRojo = 0;
        $stopTQRRojo = 0;
        $tgtTQRRojo = 0;
        
        if ($item > 2)
        {
            if ($estado != $this->datas[$item-2]['estado'] && $estado != 'neutral')
                $rango = ($high - $low) / 2;
            
            if ($this->datas[$item-2]['estado'] != 'verde' && $estado == 'verde')
                $TQRVerde = $high + $rango;
            elseif ($this->datas[$item-2]['estado'] == 'verde' && $estado == 'verde')
                $TQRVerde = $this->datas[$item-2]['TQRVerde'];

            if ($this->datas[$item-2]['estado'] != 'verde' && $estado == 'verde')
                $stopTQRVerde = $low - $rango;
            elseif ($this->datas[$item-2]['estado'] == 'verde' && $estado == 'verde')
                $stopTQRVerde = $this->datas[$item-2]['stopTQRVerde'];

            if ($this->datas[$item-2]['estado'] != 'verde' && $estado == 'verde')
                $tgtTQRVerde = $high + ($rango * 4);
            elseif ($this->datas[$item-2]['estado'] == 'verde' && $estado == 'verde')
                $tgtTQRVerde = $this->datas[$item-2]['tgtTQRVerde'];

            if ($this->datas[$item-2]['estado'] != 'rojo' && $estado == 'rojo')
                $TQRRojo = $low - $rango;
            elseif ($this->datas[$item-2]['estado'] == 'rojo' && $estado == 'rojo')
                $TQRRojo = $this->datas[$item-2]['TQRRojo'];

            if ($this->datas[$item-2]['estado'] != 'rojo' && $estado == 'rojo')
                $stopTQRRojo = $high + $rango;
            elseif ($this->datas[$item-2]['estado'] == 'rojo' && $estado == 'rojo')
                $stopTQRRojo = $this->datas[$item-2]['stopTQRRojo'];
            
            if ($this->datas[$item-2]['estado'] != 'rojo' && $estado == 'rojo')
                $tgtTQRRojo = $low - ($rango * 4);
            elseif ($this->datas[$item-2]['estado'] == 'rojo' && $estado == 'rojo')
                $tgtTQRRojo = $this->datas[$item-2]['tgtTQRRojo'];
        }
    }
    
    private function calculaPivot($i)
    {
        if ($this->acumTendencia == 'Indefinida')
        {
            if ($this->controlaRango($i, $this->datas[$i]['high'], 'Maximo'))
            {
                // Cambia tendencia y nuevo maximo
                $this->acumTendencia = 'Alcista';
                $this->datas[$i]['tendencia'] = 1;
                $this->datas[$i]['trendBars'] = 1;

                $this->cambiaNuevoCandidato($i, 'Maximo');
                $this->pivotes[] = $this->datas[$i]['provMax'];
            }
            elseif ($this->controlaRango($i, $this->datas[$i]['low'], 'Minimo'))
            {
                // Cambia tendencia y nuevo minimo
                $this->acumTendencia = 'Bajista';
                $this->datas[$i]['tendencia'] = -1;
                $this->datas[$i]['trendBars'] = 1;

                $this->cambiaNuevoCandidato($i, 'Minimo');
                $this->pivotes[] = $this->datas[$i]['provMin'];
            }
        }
        else
        {
            if ($this->acumTendencia == 'Bajista')
            {
                if ($this->datas[$i]['low'] <= $this->acumMinimoActual)
                {
                    // Controla si tambien es el maximo del rango
                    if ($this->controlaRango($i, $this->datas[$i]['high'], 'Maximo'))
                    {
                        // Cambia tendencia y nuevo maximo
                        $this->acumTendencia = 'Alcista';
                        $this->datas[$i]['tendencia'] = 1;
                        $this->datas[$i]['trendBars'] = 1;

                        $this->cambiaNuevoCandidato($i, 'Maximo');

                        // Pasa ultimo minimo a definitivo
                        $this->convierteDefinitivo($this->acumBnMinActual, $i, 'Minimo');
                    }
                    else
                    {
                        // Nuevo minimo
                        $this->cambiaNuevoCandidato($i, 'Minimo');
                        $this->datas[$i]['trendBars'] = $this->datas[$i-1]['trendBars'] + 1;
                        $this->datas[$i]['tendencia'] = $this->datas[$i-1]['tendencia'];
                    }
                }
                else
                {
                    // Controla si es el maximo del rango
                    if ($this->controlaRango($i, $this->datas[$i]['high'], 'Maximo'))
                    {
                        // Cambia tendencia y nuevo maximo
                        $this->acumTendencia = 'Alcista';
                        $this->datas[$i]['tendencia'] = 1;
                        $this->datas[$i]['trendBars'] = 1;

                        $this->cambiaNuevoCandidato($i, 'Maximo');

                        // Pasa ultimo maximo a definitivo
                        $this->convierteDefinitivo($this->acumBnMinActual, $i, 'Minimo');
                    }
                    else
                    {
                        $this->datas[$i]['trendBars'] = $this->datas[$i-1]['trendBars'] + 1;
                        $this->datas[$i]['tendencia'] = $this->datas[$i-1]['tendencia'];
                    }
                }
            }
            elseif ($this->acumTendencia == 'Alcista')
            {
                if ($this->datas[$i]['high'] >= $this->acumMaximoActual)
                {
                    // Controla si tambien es el minimo del rango
                    if ($this->controlaRango($i, $this->datas[$i]['low'], 'Minimo'))
                    {
                        // Cambia tendencia y nuevo minimo
                        $this->acumTendencia = 'Bajista';
                        $this->datas[$i]['tendencia'] = -1;
                        $this->datas[$i]['trendBars'] = 1;

                        $this->cambiaNuevoCandidato($i, 'Minimo');

                        // Pasa ultimo maximo a definitivo
                        $this->convierteDefinitivo($this->acumBnMaxActual, $i, 'Maximo');
                    }
                    else
                    {
                        // Nuevo maximo
                        $this->cambiaNuevoCandidato($i, 'Maximo'); 

                        $this->datas[$i]['trendBars'] = $this->datas[$i-1]['trendBars'] + 1;
                        $this->datas[$i]['tendencia'] = $this->datas[$i-1]['tendencia'];
                    }
                }
                else
                {
                    // Controla si es el minimo del rango
                    if ($this->controlaRango($i, $this->datas[$i]['low'], 'Minimo'))
                    {
                        // Cambia tendencia y nuevo maximo
                        $this->acumTendencia = 'Bajista';
                        $this->datas[$i]['tendencia'] = -1;
                        $this->datas[$i]['trendBars'] = 1;

                        $this->cambiaNuevoCandidato($i, 'Minimo');

                        // Pasa ultimo maximo a definitivo
                        $this->convierteDefinitivo($this->acumBnMaxActual, $i, 'Maximo');
                    }
                    else
                    {
                        $this->datas[$i]['trendBars'] = $this->datas[$i-1]['trendBars'] + 1;
                        $this->datas[$i]['tendencia'] = $this->datas[$i-1]['tendencia'];
                    }
                }
            }
        }
    }

    private function controlaRango($i, $valor, $opcion)
    {
        $n = $this->swingSize;
        $maxRango = 0;
        $minRango = 999999999999;

        // Encuentra maximo o minimo
        for ($j = $i - $n + 1; $j <= $i; $j++)
        {
            if ($opcion == 'Maximo')
            {
                if ($this->datas[$j]['high'] > $maxRango)
                    $maxRango = $this->datas[$j]['high'];
            }
            if ($opcion == 'Minimo')
            {
                if ($this->datas[$j]['low'] < $minRango)
                    $minRango = $this->datas[$j]['low'];
            }
        }

        $cc = false;
        if ($opcion == 'Maximo')
        {
            if ($valor == $maxRango)
                $cc = true;
        }
        elseif ($opcion == 'Minimo')
        {
            if ($valor == $minRango)
                $cc = true;
        }

        return ($cc);
    }

    private function cambiaNuevoCandidato($i, $tipoValor)
    {
        if ($tipoValor == 'Maximo')
        {
            $this->datas[$i]['provMax'] = $this->datas[$i]['high'];
            $this->acumBnMaxActual = $i;
            $this->acumMaximoActual = $this->datas[$i]['high'];
        }
        else
        {
            $this->datas[$i]['provMin'] = $this->datas[$i]['low'];
            $this->acumBnMinActual = $i;
            $this->acumMinimoActual = $this->datas[$i]['low'];
        }
    }

    private function convierteDefinitivo($i, $off, $tipoValor)
    {
        if ($tipoValor == 'Maximo')
        {
            $this->acumBnMaximoAnterior = $this->acumBnMaximo;
            $this->datas[$i]['max'] = $this->datas[$i]['provMax'];
            $this->acumBnMaximo = $i;
            
            $this->calculaBarras($this->acumBnMinimoAnterior, $i);

            // Calcula pivotes
            $this->calculaPivotes($this->acumBnMaximo, $off, 0, $this->datas[$this->acumBnMaximo]['max'], 
                                $this->acumBnMinimo, $this->acumBnMaximo, 'MAXIMO');            

            $this->datas[$i]['swingBarsPrev'] = $this->datas[$this->acumBnMinimo]['swingBars'];
        }
        else
        {
            $this->acumBnMinimoAnterior = $this->acumBnMinimo;
            $this->datas[$i]['min'] = $this->datas[$i]['provMin'];
            $this->acumBnMinimo = $i;

            $this->calculaBarras($this->acumBnMaximoAnterior, $i);

            // Calcula pivotes
            $this->calculaPivotes($this->acumBnMinimo, $off, $this->datas[$this->acumBnMinimo]['min'], 0, 
                                $this->acumBnMinimo, $this->acumBnMaximo, 'MINIMO');

            $this->datas[$i]['swingBarsPrev'] = $this->datas[$this->acumBnMaximo]['swingBars'];                                
        }
        $this->datas[$i]['swingBars'] = abs($this->acumBnMinimo-$this->acumBnMaximo);
    }

    private function calculaBarras($desde, $hasta)
    {
        $this->datas[$desde + 1]['barras'] = 1;
        $barras = 1;

        for ($i = $desde + 2; $i <= $hasta; $i++)
        {
            $this->datas[$i]['barras'] = $this->datas[$i - 1]['barras'] + 1;
            $barras = $this->datas[$i]['barras'];
        }
    }

    private function calculaSwingSize($i, $swingbars, $op)
    {
        $this->datas[$i]['swingBars'] = $swingbars;

        for ($j = $i; $j >= 0; $j--)
        {
            if ($op == 'MINIMO' && $this->datas[$j]['min'] != 0)
            {
                $this->datas[$i]['swingBarsPrev'] = $this->datas[$j]['swingBars'];
                break;
            }
            if ($op == 'MAXIMO' && $this->datas[$j]['max'] != 0)
            {
                $this->datas[$i]['swingBarsPrev'] = $this->datas[$j]['swingBars'];
                break;
            }
        }
    }

    private function calculaPivotes($i, $offRet, $minimo, $maximo, $bnMin, $bnMax, $swing)
    {
        if (($swing == 'MINIMO' && $bnMin != 0) || ($swing == 'MAXIMO' && $bnMax != 0))
        {
            if ($swing == 'MINIMO')
                $off = $bnMin;
            else
                $off = $bnMax;

            $this->apilaPivotes($swing == 'MINIMO' ? $minimo : $maximo);

            for ($p = 0; $p < count($this->pivotes); $p++)
            {
                $key = 'pivot'.$p;

                $this->datas[$off][$key] = $this->pivotes[$p];
            }

            // Si hay 5 pivotes inicia operaciones
            if (count($this->pivotes) >= 5)
                $this->flEmpiezaOperacion = true;

            if (count($this->pivotes) >= 3)
            {
                $pivot0 = $this->pivotes[0]; $pivot1 = $this->pivotes[1]; $pivot2 = $this->pivotes[2];

                if ($pivot0 != 0 && $pivot1 != 0 && $pivot2 != 0)
                {
                    $cswingProv = $pivot0 - 
                                ($swing == 'MINIMO' ? $this->datas[$offRet]['provMax'] : 
                                $this->datas[$offRet]['provMin']);
                    $swingRetroceso = $pivot1 - $pivot2;
                    $cswing = $pivot1 - $pivot0;
                    $retroceso = 0;
                    if ($swingRetroceso != 0)
                        $retroceso = $cswing / $swingRetroceso;
                    if ($cswing != 0)
                        $retrocesoProv = ABS($cswingProv / $cswing);
                    else
                        $retrocesoProv = 0;

                    //if ($offRet == 417)
                        //dd($i.' '.$pivot0.' '.$pivot1.' '.$pivot2.' '.$this->datas[$offRet]['provMin'].' '.
                               // $this->datas[$offRet]['provMax'].' '.$swing);
                    $this->datas[$offRet]['provRet'] = $retrocesoProv;
                    $this->datas[$off]['retroceso'] = $retroceso;

                    if ($pivot1 > $pivot2)
                    {
                        $this->datas[$off]['extT1'] = $pivot0 + ABS($swing) * 0.618;
                        $this->datas[$off]['extT2'] = $pivot0 + ABS($swing) * 1;
                        $this->datas[$off]['extT3'] = $pivot0 + ABS($swing) * 1.618;
                        $this->datas[$off]['extT4'] = $pivot0 + ABS($swing) * 2.618;

                        if ($pivot0 > $pivot2)
                            $this->datas[$off]['setup'] = 'LH';
                        elseif ($pivot0 < $pivot2)
                            $this->datas[$off]['setup'] = 'LL';
                        else    
                            $this->datas[$off]['setup'] = 'DB';
                    }
                    elseif ($pivot1 < $pivot2)
                    {
                        $this->datas[$off]['extT1'] = $pivot0 - ABS($swing) * 0.618;
                        $this->datas[$off]['extT2'] = $pivot0 - ABS($swing) * 1;
                        $this->datas[$off]['extT3'] = $pivot0 - ABS($swing) * 1.618;
                        $this->datas[$off]['extT4'] = $pivot0 - ABS($swing) * 2.618;

                        if ($pivot0 > $pivot2)
                            $this->datas[$off]['setup'] = 'HL';
                        elseif ($pivot0 < $pivot2)
                            $this->datas[$off]['setup'] = 'HH';
                        elseif ($pivot0 == $pivot2)
                            $this->datas[$off]['setup'] = 'DT';
                    }
                }
                if ($swing == 'MINIMO')
                {
                    $minimoAnterior = $this->datas[$this->acumBnMinimoAnterior]['min'];
                    if ($this->datas[$off]['min'] < $minimoAnterior)
                        $this->datas[$off]['setup'] = 'LL';
                    if ($this->datas[$off]['min'] > $minimoAnterior)
                        $this->datas[$off]['setup'] = 'HL';
                    if ($this->datas[$off]['min'] == $minimoAnterior)
                        $this->datas[$off]['setup'] = 'DB';                    
                }
                if ($swing == 'MAXIMO')
                {
                    $maximoAnterior = $this->datas[$this->acumBnMaximoAnterior]['max'];
                    if ($this->datas[$off]['max'] > $maximoAnterior)
                        $this->datas[$off]['setup'] = 'HH';
                    if ($this->datas[$off]['max'] < $maximoAnterior)
                        $this->datas[$off]['setup'] = 'LH';                    
                    if ($this->datas[$off]['max'] == $maximoAnterior)
                        $this->datas[$off]['setup'] = 'DT';                                        
                }
            }
        }
    }

	private function calculaProvRet($off, $valor)
	{
		if (isset($this->pivotes[1]))
		{
        	$cswingProv = $this->pivotes[0] - $valor;
    		$cswing = $this->pivotes[1] - $this->pivotes[0];
	
    		if ($cswing != 0)
    			$retrocesoProv = ABS($cswingProv / $cswing);
    		else
    			$retrocesoProv = 0;
	
    		$this->datas[$off]['provRet'] = $retrocesoProv;
		}
	}

    private function apilaPivotes($valor)
    {
        if ($valor != $this->pivotes[0])
        {
            if (count($this->pivotes) > 4)
                $tope = 5;
            else    
                $tope = count($this->pivotes);
            for ($i = $tope; $i > 0; $i--)
            {
                $this->pivotes[$i] = $this->pivotes[$i-1];
            }
            $this->pivotes[0] = $valor;
        }
    }

    private function calculaSwingTgtBatch($i)
    {
        $n = $this->swingSize;
        
        // Calcula pivotes y swing
        if ($i > $n)
            $this->calculaPivot($i);

        $fechaActual = date('Y-m-d', ceil($this->datas[$i]['fecha']/1000));
        if ($fechaActual < "2023-03-12" || 
			($fechaActual >= "2023-11-05" && $fechaActual < "2024-03-10") ||
			($fechaActual >= "2024-11-03" && $fechaActual < "2025-03-10"))
            $flDayLight = true;
        else
        {
            if (($fechaActual >= "2023-03-12" && $fechaActual < "2023-11-05") ||
                ($fechaActual >= "2024-03-10" && $fechaActual < "2024-11-03"))
                $flDayLight = false;
        }

        $pivot = $this->datas[$i]['swingBars'];
        $volumen = $this->datas[$i]['volume'];
        if ($pivot == 0 && $i > 0)
            $this->datas[$i]['volumenPorSwing'] = $volumen + $this->datas[$i-1]['volumenPorSwing'];
        else    
            $this->datas[$i]['volumenPorSwing'] = $volumen;

        // Calcula zona
        $zonaFinal = 'NN ';
        if ($this->datas[$i]['provRet'] != 0)
        {
            $zonas = [];
            $zonas[] = $this->calculaZona($this->datas[$i]['open'], $i);
            $zonas[] = $this->calculaZona($this->datas[$i]['high'], $i);
            $zonas[] = $this->calculaZona($this->datas[$i]['low'], $i);
            $zonas[] = $this->calculaZona($this->datas[$i]['close'], $i);
        
            foreach ($zonas as $zona)
            {
                if ($zona != 'NN ')
                    $zonaFinal = $zona;
            }
        }
        $this->datas[$i]['zona'] = $zonaFinal;

        // Chequea reestablecimiento del proceso en caso de corte por administracion por tiempo
        if ($this->acumFlCerroPorTiempoBajista && $this->datas[$i]['provMax'] != 0)
            $this->acumFlCerroPorTiempoBajista = false;

        if ($this->acumFlCerroPorTiempoAlcista && $this->datas[$i]['provMin'] != 0)
            $this->acumFlCerroPorTiempoAlcista = false;

        // Si tiene posicion abierta chequea contra ordenes hijas SL y PT
        if ($this->acumFlAbrePosicion)
        {
            $this->datas[$i]['e'] = $this->acumValorEntrada;
            $this->datas[$i]['t1'] = $this->datas[$this->OffAbrePosicion]['t1'];
            $this->datas[$i]['t2'] = $this->datas[$this->OffAbrePosicion]['t2'];
            $this->datas[$i]['t3'] = $this->datas[$this->OffAbrePosicion]['t3'];
            $this->datas[$i]['t4'] = $this->datas[$this->OffAbrePosicion]['t4'];
            $this->datas[$i]['p'] = '1';
            $this->datas[$i]['evento'] = $this->datas[$i-1]['evento'];

            // Redefine si es alcista o bajista
            //if ($this->datas[$i]['evento'] == 'Compra')
            //{
            //    $this->acumFlAcista = true;
            //   $this->acumFlBajista = false;
            //}
            //else
            //{
            //    $this->acumFlAcista = false;
            //    $this->acumFlBajista = true; 
            //}
            $this->datas[$i]['stoploss'] = $this->acumStopLoss;

            // Mueve SL si hay cambio de dirección de señal
            // Si encuentra un minimo definitivo es señal contraria
            if (($this->acumFlAcista ? $this->datas[$i]['provMax'] != 0 : $this->datas[$i]['provMin'] != 0))
            {
                $contratoActivo = $this->cantidadActivaContratos;
                $this->acumProfitAndLoss = $this->calculaProfitAndLoss($this->acumIdTrade, $contratoActivo, 
                                                                        $this->datas[$i]['close']);

                // Si viene ganando mueve SL a BE + 1 o si no a ultimo minimo o maximo
                if ($this->acumProfitAndLoss > 0)
                {
                    $contratoActivo = $this->totalContratos - $this->cantidadActivaContratos + 1;

                    if ($contratoActivo <= 2) // Si estoy en el 2do. contrato activo
                    {
                        $this->acumStopLoss = $this->datas[$this->OffAbrePosicion]['e']; 
                        $this->acumStopLoss = ($this->acumFlAcista ? 
                                                $this->acumStopLoss + $this->ticker : $this->acumStopLoss - $this->ticker);
                        $this->datas[$i]['entrada'] .= "Mueve SL por señal contraria en posicion con ganancia ".$this->acumProfitAndLoss." a BE";
                    }
                    else // Si no se mueve al target anterior
                    {
                        $this->acumStopLoss = $this->tgt[$contratoActivo-2];
                        $this->datas[$i]['entrada'] .= "Mueve SL por señal contraria en posicion con ganancia ".$this->acumProfitAndLoss." a TGT ".$this->acumStopLoss;
                    }

                    $this->datas[$i]['stoploss'] = $this->acumStopLoss;
                }
                else
                {
                    // Busca ultimo minimo o maximo
                    if ($this->acumFlAcista)
                    {
                        for ($r = $i; $r > 0 && $this->datas[$r]['min'] == 0; $r--)
                        {
                        }
                        $nuevoStopLoss = $this->datas[$r]['min'];
                    }
                    else
                    {
                        for ($r = $i; $r > 0 && $this->datas[$r]['max'] == 0; $r--)
                        {
                        }
                        $nuevoStopLoss = $this->datas[$r]['max'];
                    }
                    $this->acumStopLoss = $nuevoStopLoss;
                    if ($this->acumFlAcista)
                        $this->datas[$i]['stoploss'] = $this->acumStopLoss;
                    else
                        $this->datas[$i]['stoploss'] = $this->acumStopLoss;

                    $this->datas[$i]['entrada'] .= "Mueve SL por señal contraria en posicion con perdida ".$this->acumProfitAndLoss." a ultimo ".
                                                    ($this->acumFlAcista?"minimo ":"maximo");
                }
                $this->datas[$i]['entrada'] .= " SL=".$this->datas[$i]['stoploss'];
            }

            // controla administracion por tiempo
            if ($this->administracionPosicion == 'B')
            {
                // Cierra en la siguiente vela si dio cierre por tiempo con perdida
                if ($this->acumFlCierraPorTiempo)
                {
                    $this->datas[$i]['p'] = '0';
                    $this->datas[$i]['evento'] = 'NM';
                    $this->acumFlAbrePosicion = false;
                    $this->datas[$i]['entrada'] .= "Cierra NM por administracion por tiempo con perdida de ".$this->acumProfitAndLoss;

                    $this->acumFlCierraPorTiempo = false;
                }
                $horaInicio = new \DateTime($this->datas[$i]['horainicio']);
                $horaFin = new \DateTime($this->operaciones[$this->acumIdTrade-1]['desdeHora']);

                $diferencia = $horaInicio->diff($horaFin);
                $diferenciaMinutos = ($diferencia->h * 60) + $diferencia->i;
                if ($diferenciaMinutos >= intval($this->tiempo))
                {
                    $contratoActivo = $this->cantidadActivaContratos;
                    $this->acumProfitAndLoss = $this->calculaProfitAndLoss($this->acumIdTrade, $contratoActivo, $this->datas[$i]['close']);

                    // Si esta perdiendo cierra
                    if ($this->acumProfitAndLoss < 0)
                    {
                        $this->acumFlCierraPorTiempo = true;
                        $this->datas[$i]['entrada'] .= "Cierra por tiempo ";
                    }
                    else // Si gana va a BE + 1
                    {
                        $this->acumStopLoss = $this->datas[$this->OffAbrePosicion]['e']; 
                        $this->acumStopLoss = ($this->acumFlAcista ? 
                                                $this->acumStopLoss + $this->ticker : $this->acumStopLoss - $this->ticker);
                        $this->datas[$i]['entrada'] .= "Mueve SL por administracion por tiempo con ganancia ".
                                                        $this->acumProfitAndLoss." a BE + - 1";
                    }
                    if ($this->acumFlAcista)
                        $this->acumFlCerroPorTiempoAlcista = true;
                    else
                        $this->acumFlCerroPorTiempoBajista = true;
                }
            }
            // Controla si cumple eventos de cierre (TGT Hit / SL)
            $mpc = $mpf = 0;
            $this->controlaCierreTgtSl($i, $this->acumFlAcista, $this->acumFlBajista, $this->acumFlAbrePosicion, 
                                        $this->acumIdTrade, $mpc, $mpf);

            // Chequea cierre de posicion por fuera de hora (NO MERCADO)
            if ($this->acumFlAbrePosicion)
            {
                if ($this->datas[$i]['horainicio'] >= ($flDayLight ? '18:00:00' : '17:00:00'))
                {
                    $this->datas[$i]['p'] = '0';
                    $this->datas[$i]['evento'] = 'NM';
                    $this->acumFlAbrePosicion = false;
                }
            }

            // Si esta en tgt hit y tiene mas contratos cambia el SL
            if (substr($this->datas[$i]['evento'], 0, 7) == 'Tgt Hit' && $this->cantidadActivaContratos > 0 &&
                $this->totalContratos > 1)
            {
                $contratoActivo = $this->totalContratos - $this->cantidadActivaContratos + 1;
                if ($contratoActivo == 2) // Si estoy en el 2do. contrato activo
                {
                    $this->acumStopLoss = $this->datas[$this->OffAbrePosicion]['e'];
                    $this->acumStopLoss = ($this->acumFlAcista ? $this->acumStopLoss + $this->ticker : 
                                            $this->acumStopLoss - $this->ticker);
                }
                else // Si no se mueve al target anterior
                    $this->acumStopLoss = $this->tgt[$contratoActivo-2];
                $this->datas[$i]['entrada'] = 'Mueve SL por alcanzar TGT Contrato activo='.$contratoActivo.
                                                ' Contratos restantes='.$this->cantidadActivaContratos.
                                                ' nuevo SL '.$this->acumStopLoss.' TGT contrato='.
                                                $this->tgt[$contratoActivo];
            }
            // Chequea para cerrar swing
            if (!$this->acumFlAbrePosicion)
            {
                // Si hay mas de 1 contrato continua abierta la posicion
                if ($this->cantidadActivaContratos > 0 && substr($this->datas[$i]['evento'], 0, 7) == 'Tgt Hit')
                    $this->acumFlAbrePosicion = true;
                else
                {
                    $this->acumOff1oA = -1;
                    $this->cierraPosicion($i, $this->acumFlAcista, $this->acumFlBajista, $this->OffAbrePosicion,
                                        $this->acumIdSenial, $this->acumIdTrade, $this->cantidadActivaContratos, 
                                        $this->acumTipoOperacion, $mpc, $mpf);
                }
            }
        }

        if ($this->datas[$i]['provMax'] != 0 || $this->datas[$i]['provMin'] != 0) 
			$this->calculaProvRet($i, $this->datas[$i]['provMax'] != 0. ? $this->datas[$i]['provMax'] : $this->datas[$i]['provMin']);

        // Sigue una vela mas con la entrada habilitada para disparar gatillo 
        if ($this->acumFlBuscaEntrada)
        {
            // Controla ventana de tiempo para entrada
            $this->acumQVentanaEntrada++;
            $this->acumFlAbrePosicionEntrada = false;
            if ($this->acumQVentanaEntrada < 6)
            {
                // Abre posicion si la vela pasa por el punto de entrada
                if ($this->acumFlAcista)
                {
                    if ($this->datas[$i]['high'] >= $this->acumPuntoEntrada &&
                        $this->datas[$i]['low'] <= $this->acumPuntoEntrada)
                    {
                        $this->datas[$i]['entrada'] .= 
                            " ABRE POSICION ALCISTA POR PASAR POR PUNTO DE ENTRADA ".$this->acumPuntoEntrada.
                            " Vela nro.".$this->acumQVentanaEntrada;

                        $this->acumFlAbrePosicionEntrada = true;
                    }
                    else
                    {
                        if ($this->datas[$i]['open'] >= $this->acumStopLoss ||
                            $this->datas[$i]['close'] >= $this->acumStopLoss ||
                            $this->datas[$i]['high'] >= $this->acumStopLoss ||
                            $this->datas[$i]['low'] >= $this->acumStopLoss)
                        {
                            $this->datas[$i]['entrada'] .= " CIERRA VENTANA ALCISTA POR STOP LOSS ".$this->acumStopLoss;
                            $this->acumFlBuscaEntrada = false;
                            $this->acumQVentanaEntrada = 6;
                        }
                    }
                }

                if ($this->acumFlBajista)
                {
                    // Si la vela esta entre la entrada ingresa
                    if ($this->datas[$i]['high'] >= $this->acumPuntoEntrada &&
                        $this->datas[$i]['low'] <= $this->acumPuntoEntrada)
                    {
                        $this->datas[$i]['entrada'] .= 
                            " ABRE POSICION BAJISTA POR PASAR POR PUNTO DE ENTRADA ".$this->acumPuntoEntrada.
                            " Vela nro.".$this->acumQVentanaEntrada;

                        $this->acumFlAbrePosicionEntrada = true;
                    }
                    else
                    {
                        if ($this->datas[$i]['open'] >= $this->acumStopLoss ||
                            $this->datas[$i]['close'] >= $this->acumStopLoss ||
                            $this->datas[$i]['high'] >= $this->acumStopLoss ||
                            $this->datas[$i]['low'] >= $this->acumStopLoss)
                        {
                            $this->datas[$i]['entrada'] .= " CIERRA VENTANA BAJISTA POR STOP LOSS ".$this->acumStopLoss;
                            $this->acumFlBuscaEntrada = false;
                            $this->acumQVentanaEntrada = 6;
                        }
                    }
                }
            }
            else
            {
                $this->datas[$i]['entrada'] .= " CIERRA VENTANA DE PUNTO DE ENTRADA ".$this->acumPuntoEntrada;
                $this->acumFlBuscaEntrada = false;
            }

            if ($this->acumIdTrade > 1)
            {

            }

            // calcula riesgo retorno
            if ($this->acumFlAbrePosicionEntrada)
            {
                $retorno = 0;
                $riesgo = 0;
                $rrr = 0;
                if ($this->acumFlAcista)
                {
                    $retorno = $this->acumT1 - $this->datas[$i]['open'];
                    $riesgo = $this->datas[$i]['open'] - $this->acumStopLoss;
                    if ($riesgo != 0)
                        $rrr = $retorno / $riesgo;
                    else   
                        $rrr = 0;
                }
                else
                {
                    if ($this->acumFlBajista)
                    {
                        $retorno = $this->datas[$i]['open'] - $this->acumT1;
                        $riesgo = $this->acumStopLoss - $this->datas[$i]['open'];
                        if ($riesgo != 0)
                            $rrr = abs($retorno) / abs($riesgo);
                        else    
                            $rrr = 0;
                    }
                }
                if ($retorno != 0)
                    $this->datas[$i]['entrada'] .= ' Retorno '.$retorno.' Riesgo '.$riesgo.' RRR '.
                                                    $rrr.' SL '.$this->acumStopLoss;

                if (($this->flSinFiltros ? $rrr >= 1.5 : true) && 
                    $this->datas[$i]['horainicio'] >= '04:00:00' &&
                    $this->datas[$i]['horainicio'] <= ($flDayLight ? '17:00:00' : '16:00:00'))
                {
                    $this->acumValorEntrada = $this->datas[$i]['open'];

                    // Asigna valor de entrada segun filtro open-high-close-low
                    if ($this->acumFlAbrePosicionEntrada)
                        $this->acumValorEntrada = $this->acumPuntoEntrada;

                    $riesgoPuntos = abs($this->acumValorEntrada-$this->acumStopLoss);
                    $riesgoTicks = round($riesgoPuntos/$this->ticker, 0);
                    $riesgoPesos = $riesgoTicks * $this->valorTicker;

                    // Gatillo chequeando riesgo en pesos contra SL que sea menor a 500$
                    $flAbre = true;
                    if ($this->gatillo == 'B')
                    {
                        $flAbre = false;

                        if ($riesgoPesos < 500)
                            $flAbre = true;
                    }
                    if (!$this->acumFlAbrePosicion && $flAbre)
                    {
                        $this->acumFlAbrePosicion = true;

                        $this->acumFlBuscaEntrada = false;
                        $this->acumQVentanaEntrada = 6;

                        $this->datas[$i]['e'] = $this->acumValorEntrada;
                        $this->datas[$i]['stoploss'] = $this->acumStopLoss;
                        $this->datas[$i]['t1'] = $this->acumT1;
                        $this->datas[$i]['t2'] = $this->acumT2;
                        $this->datas[$i]['t3'] = $this->acumT3;
                        $this->datas[$i]['t4'] = $this->acumT4;
                        $this->datas[$i]['p'] = '1';
                        $this->datas[$i]['evento'] = ($this->acumFlAcista ? 'Compra' : 'Vende');
                        $this->OffAbrePosicion = $i;
                        $this->buscaUltimoPivot($i);
                        $this->cantidadActivaContratos = $this->totalContratos;

                        // Arma tabla de operaciones
                        $retornoPuntos = abs($this->acumValorEntrada-$this->acumT1);
                        $retornoTicks = round($retornoPuntos/$this->ticker, 0);
                        $retornoPesos = $retornoTicks * $this->valorTicker;
                        if ($riesgoTicks != 0)
                            $rrr = $retornoTicks / $riesgoTicks;
                        
                        if ($this->acumFlAcista)
                            $direccion = 1;
                        else
                            $direccion = -1;

                        if ($this->datas[$i]['swingBarsPrev'] != 0)
                            $relacionVelas = ($this->datas[$i]['swingBars']-1) / $this->datas[$i]['swingBarsPrev'];
                        else    
                            $relacionVelas = 0;

                        // Si no tiene swingbarsprev busca el anterior swingbars 
                        if ($this->datas[$i]['swingBarsPrev'] == 0)
                        {
                            // Traigo el swingbars del ultimo pivot real
                            for ($r = $i, $contraSwingBars = 0; $r > 0 && 
                                $this->datas[$r]['min'] == 0 && $this->datas[$r]['max'] == 0; $r--)
                            {
                                $contraSwingBars++;
                            }
                            $swingBars = $this->datas[$r]['swingBars'];
                        }
                        else
                        {
                            $swingBars = $this->datas[$i]['swingBarsPrev'];
                            $contraSwingBars = $this->datas[$i]['swingBars']-1;
                        }

                        if ($swingBars != 0)
                            $relacionVelas = $contraSwingBars / $swingBars;
                        else    
                            $relacionVelas = 0;

                        // Configura retroceso
                        $retroceso = $this->datas[$i-1]['provRet'];
                        if ($retroceso == 0)
                        {
                            for ($j = $i-1; $j > 0 && $retroceso == 0; $j--)
                            {
                                if ($this->datas[$j]['provRet'] != 0)
                                    $retroceso = $this->datas[$j]['provRet'];
                            }
                        }

                        $this->acumTipoOperacion = ($this->acumFlAcista ? "Buy to Open" : "Sell to Open");
                        $ret = $this->armaTablaOperaciones($this->datas[$i]['fecha'],
                                                    ++$this->acumIdTrade, 
                                                    $direccion,
                                                    $this->cantidadActivaContratos,  
                                                    $this->datas[$i]['horainicio'], 
                                                    $this->acumValorEntrada,
                                                    $this->datas[$i]['stoploss'],
                                                    $this->acumT1, $this->acumT2, $this->acumT3, $this->acumT4,
                                                    $rrr,
                                                    $swingBars,
                                                    $contraSwingBars,
                                                    $relacionVelas,
                                                    $retroceso,
                                                    $riesgoTicks,
                                                    $retornoTicks,
                                                    '', '', '', $this->acumTipoOperacion, $i);

                        if ($ret === 'ERROR')
                        {
                            $this->acumIdTrade--;
                            $this->datas[$i]['p'] = '0';
                            $this->datas[$i]['evento'] = 'TR';
                            $this->acumFlAbrePosicion = false;                            
                        }

                        // Chequea cierre de operacion por si es en la misma vela de apertura
                        // Controla si cumple eventos de cierre (TGT Hit / SL)
                        $mpc = $mpf = 0;
                        $this->controlaCierreTgtSl($i, $this->acumFlAcista, $this->acumFlBajista, 
                                                    $this->acumFlAbrePosicion, $this->acumIdTrade, $mpc, $mpf);

                        // Chequea cierre de posicion por fuera de hora (NO MERCADO)
                        if ($this->acumFlAbrePosicion)
                        {
                            if ($this->datas[$i]['horainicio'] >= ($flDayLight ? '18:00:00' : '17:00:00'))
                            {
                                $this->datas[$i]['p'] = '0';
                                $this->datas[$i]['evento'] = 'NM';
                                $this->acumFlAbrePosicion = false;
                            }
                        }

                        // Si esta en tgt hit y tiene mas contratos cambia el SL
                        if (substr($this->datas[$i]['evento'], 0, 7) == 'Tgt Hit' && $this->cantidadActivaContratos > 0 &&
                            $this->totalContratos > 1)
                        {
                            $contratoActivo = $this->totalContratos - $this->cantidadActivaContratos + 1;
                            if ($contratoActivo == 2) // Si estoy en el 2do. contrato activo
                            {
                                $this->acumStopLoss = $this->datas[$this->OffAbrePosicion]['e'];
                                $this->acumStopLoss = ($this->acumFlAcista ? $this->acumStopLoss + $this->ticker : 
                                                        $this->acumStopLoss - $this->ticker);
                            }
                            else // Si no se mueve al target anterior
                                $this->acumStopLoss = $this->tgt[$contratoActivo-2];
                            $this->datas[$i]['entrada'] = 'Mueve SL por alcanzar TGT Contrato activo='.$contratoActivo.
                                                            ' Contratos restantes='.$this->cantidadActivaContratos.
                                                            ' nuevo SL '.$this->acumStopLoss.' TGT contrato='.
                                                            $this->tgt[$contratoActivo];
                        }
                        // Chequea para cerrar swing
                        if (!$this->acumFlAbrePosicion)
                        {
                            // Si hay mas de 1 contrato continua abierta la posicion
                            if ($this->cantidadActivaContratos > 0 && substr($this->datas[$i]['evento'], 0, 7) == 'Tgt Hit')
                                $this->acumFlAbrePosicion = true;
                            else
                            {
                                $this->acumOff1oA = -1;
                                $this->cierraPosicion($i, $this->acumFlAcista, $this->acumFlBajista, 
                                                    $this->OffAbrePosicion,$this->acumIdSenial, $this->acumIdTrade, 
                                                    $this->cantidadActivaContratos, $this->acumTipoOperacion, $mpc, $mpf);
                            }
                        }
                    }
                    else
                    {
                        $this->acumStopLoss = $this->acumValorEntrada;
                        $this->acumStopLoss = ($this->acumFlAcista ? $this->acumStopLoss + $this->ticker : 
                                            $this->acumStopLoss - $this->ticker);
                        $this->datas[$i]['stoploss'] = $this->acumStopLoss;
                        $this->datas[$i]['entrada'] .= 'BE por SP contrario '.$this->datas[$i]['stoploss'];

                        // Normaliza flags de sentido del setup
                        if ($this->acumFlAcista)
                        {
                            $this->acumFlAcista = false;
                            $this->acumFlBajista = true;
                        }
                        else
                        {
                            $this->acumFlAcista = true;
                            $this->acumFlBajista = false;
                        }
                    }
                }
                else
                {
                    // Si es un pivot analiza nuevamente
                    if ($this->datas[$i]['provRet'] >= 0.382 && $this->datas[$i]['provRet'] <= 1)
                    {
                        $this->acumFlAbrePosicion = false;
                        $this->datas[$i]['entrada'] .= ' Encuentra un nuevo pivot';
                    }
                    else
                    {
                        $this->acumFlAbrePosicion = false;
                    }
                }
                $this->acumFlBuscaEntrada = false;
            }
        }

        // Controla para desactivar SP alcista
        if ($this->flSpAlcista && $i > $this->ventanaSpAlcista)
        {
            $this->datas[$i]['entrada'] .= " Anula ventana alcista ".$this->ventanaSpAlcista." offset ".$i;

            $this->flSpAlcista = false;
            $this->tgtSpAlcista1 = 0;
            $this->ventanaSpAlcista = 0;
        }

        // Controla para desactivar SP bajista
        if ($this->flSpBajista && $i > $this->ventanaSpBajista)
        {
            $this->datas[$i]['entrada'] .= " Anula ventana bajista ".$this->ventanaSpBajista." offset ".$i;

            $this->flSpBajista = false;
            $this->tgtSpBajista1 = 0;
            $this->ventanaSpBajista = 0;
        }

        if ($this->flSpBajista)
            $this->datas[$i]['filtroActivo'] .= " SBLANCA";

        if ($this->flSpAlcista)
            $this->datas[$i]['filtroActivo'] .= " SROJA";

        if ($this->acumFlAnulacionAbcBajistaActiva)
            $this->datas[$i]['filtroActivo'] .= ' ABC BLANCA';

        if ($this->acumFlAnulacionAbcAlcistaActiva)
            $this->datas[$i]['filtroActivo'] .= ' ABC ROJA';

        if ($this->acumFlAnulacionAbCdBajistaActiva)
            $this->datas[$i]['filtroActivo'] .= ' AB=CD BLANCA';

        if ($this->acumFlAnulacionAbCdAlcistaActiva)
            $this->datas[$i]['filtroActivo'] .= ' AB=CD ROJA';

        if ($this->acumFlAnulacionW4BajistaActiva)
            $this->datas[$i]['filtroActivo'] .= ' W4 BLANCA';

        if ($this->acumFlAnulacionW4AlcistaActiva)
            $this->datas[$i]['filtroActivo'] .= ' W4 ROJA';

        if ($this->acumFlFiltroOutBound)
            $this->datas[$i]['filtroActivo'] .= " OUT";

        // Busca señal alcista o bajista
        $this->flAnulaCandidato = false;

        if (($this->datas[$i]['provMax'] != 0 || $this->datas[$i]['provMin'] != 0) && 
            $this->flEmpiezaOperacion)
        {
            // Busca ultimo maximo / minimo
            $this->buscaUltimoMaximo($i, $offMax, $maximo);
            $this->buscaUltimoMinimo($i, $offMin, $minimo);

            // Marca setup
            if ($this->datas[$i]['provMax'] != 0)
            {
                $this->marcaSetup($offMax, $offMin, 'ALCISTA');
            }
            if ($this->datas[$i]['provMin'] != 0)
            {
                $this->marcaSetup($offMax, $offMin, 'BAJISTA');
            }
            //if ($i == 186)
            //{
            //    dd($offMax.' '.$this->datas[$offMax]['setup'].' '.$this->datas[$offMax]['provMax']);
            //}
            // Define outbound
            // Si estoy en HH verifica si el anterior es LL
            if ($this->datas[$i]['provMax'] != 0 && $this->acumFlFiltroOutBound && !$this->datas[$offMin]['outbound'])
            {
                $this->acumFlFiltroOutBound = false;
                $this->datas[$i]['entrada'] .= ' DESACTIVA FILTRO OUTBOUND ';
            }

            if ($this->datas[$i]['provMin'] != 0 && $this->acumFlFiltroOutBound && !$this->datas[$offMax]['outbound'])
            {
                $this->acumFlFiltroOutBound = false;
                $this->datas[$i]['entrada'] .= ' DESACTIVA FILTRO OUTBOUND ';
            }

            if ($this->datas[$i]['provMax'] > $maximo && $this->datas[$i]['provMax'] != 0 && !$this->acumFlFiltroOutBound) // Es HH
            {
                if ($this->datas[$offMin]['setup'] == 'LL')
                {
                    $this->acumFlFiltroOutBound = true;
                    $this->datas[$i]['outbound'] = true;

                    $this->datas[$i]['entrada'] .= 'ACTIVA FILTRO OUTBOUND HH / LL';

                    $this->datas[$i]['filtroActivo'] .= " Activa OUT";
                }
            }

            if ($this->datas[$i]['provMin'] < $minimo && $this->datas[$i]['provMin'] != 0 && !$this->acumFlFiltroOutBound) // Es LL
            {
                if ($this->datas[$offMax]['setup'] == 'HH')
                {
                    $this->acumFlFiltroOutBound = true;
                    $this->datas[$i]['outbound'] = true;

                    $this->datas[$i]['entrada'] .= ' ACTIVA FILTRO OUTBOUND LL / HH ';

                    $this->datas[$i]['filtroActivo'] .= " Activa OUT";
                }
            }

            // Busca si provmax es menor al ultimo maximo
            if ($this->datas[$i]['provMax'] < $maximo && $this->datas[$i]['provMax'] != 0)
            {
                // Inicia señal de anulacion alcista
                if (!$this->flSpAlcista)
                {
                    //$this->datas[$i]['filtroActivo'] .= " SROJA";
                    //$this->flSpAlcista = true;
                }
                $recorrido1oA = abs($maximo - $minimo);
                $this->tgtSpAlcista1 = Round(($this->datas[$i]['provMax'] - ($recorrido1oA * 0.618))/$this->ticker,0)*$this->ticker;
                $this->ventanaSpAlcista = $i + (abs($offMax-$offMin) * 2);
                $this->stopLossSpAlcista = $maximo;
                $this->ultimoMaximoAlcista = $this->datas[$i]['provMax'];
                $this->ultimoMinimoAlcista = $minimo;

                $this->datas[$i]['entrada'] .= ' Abre anulacion alcista TGT 1 '.$this->tgtSpAlcista1.' Ventana '.$this->ventanaSpAlcista.
                                        ' maximo '.$maximo.' minimo '.$minimo.' offset '.$i;
            }

            // Busca si provmin es mayor al ultimo minimo
            if ($this->datas[$i]['provMin'] > $minimo && $this->datas[$i]['provMin'] != 0)
            {
                // Inicia señal de anulacion bajista
                if (!$this->flSpBajista)
                {
                    //$this->datas[$i]['filtroActivo'] .= " SBLANCA";
                    //$this->flSpBajista = true;
                }
                $recorrido1oA = abs($maximo - $minimo);
                $this->tgtSpBajista1 = Round(($this->datas[$i]['provMin'] - ($recorrido1oA * 0.618))/$this->ticker,0)*$this->ticker;
                $this->ventanaSpBajista = $i + (abs($offMax-$offMin) * 2);
                $this->stopLossSpBajista = $minimo;
                $this->ultimoMaximoBajista = $maximo;
                $this->ultimoMinimoBajista = $this->datas[$i]['low'];

                $this->datas[$i]['entrada'] .= ' Abre anulacion bajista TGT 1 '.$this->tgtSpBajista1.' Ventana '.$this->ventanaSpBajista.
                                            ' maximo '.$maximo.' minimo '.$minimo.' offset '.$i;
            }

            // Si esta en un punto maximo o minimo busca criterios de anulacion
            if ($this->datas[$i]['provMax'] != 0)
                $this->calculaFiltros($i, 'ALCISTA');

            // Si esta en un punto maximo o minimo busca criterios de anulacion
            if ($this->datas[$i]['provMin'] != 0)
                $this->calculaFiltros($i, 'BAJISTA');

            // Si tiene setup alcista activo controla provmax que entre en TGT 1 y tiempo de velas
            if ($this->flSpBajista && $this->datas[$i]['provMax'] != 0)
            {
                // Controlo si el provmax esta en la ventana o cae dentro del TGT 1
                //if (($this->datas[$i]['provMax'] < $this->tgtSpBajista1 && $i <= $this->ventanaSpBajista) || 
					//$this->datas[$i]['provMax'] >= $maximo)
                {
                    $this->datas[$i]['entrada'] .= ' Anula ProvMax TGT 1 '.$this->tgtSpBajista1.' Ventana '.$this->ventanaSpBajista.
                                                ' maximo '.$maximo.' offset '.$i;
                    $this->flAnulaCandidato = true;
                }
            }
            if (!$this->flAnulaCandidato && ($this->acumFlAnulacionAbCdBajistaActiva ||
                $this->acumFlAnulacionAbcBajistaActiva))
            {
                if ($this->acumFlAnulacionAbCdBajistaActiva)
                    $this->datas[$i]['entrada'] .= ' Anula ProvMax por AB=CD Bajista Activa';
                else
                    $this->datas[$i]['entrada'] .= ' Anula ProvMax por ABC Bajista Activa';
                $this->flAnulaCandidato = true;
            }

            if (!$this->flAnulaCandidato && $this->acumFlAnulacionW4BajistaActiva)
            {
                $this->datas[$i]['entrada'] .= ' Anula ProvMax por W4 Bajista Activa';
                $this->flAnulaCandidato = true;
            }

            // Si tiene setup bajista activo controla provmax que entre en TGT 1 y tiempo de velas
            if ($this->flSpAlcista && $this->datas[$i]['provMin'] != 0)
            {
                // Controlo si el provmax esta en la ventana o cae dentro del TGT 1
                //if (($this->datas[$i]['provMin'] > $this->tgtSpAlcista1 && $i <= $this->ventanaSpAlcista) ||
					//$this->datas[$i]['provMin'] <= $minimo)
                {
                    $this->datas[$i]['entrada'] .= ' Anula ProvMin TGT 1 '.$this->tgtSpAlcista1.' Ventana '.$this->ventanaSpAlcista.
                                                    ' minimo '.$minimo.' offset '.$i;
                    $this->flAnulaCandidato = true;
                }
            }

            if (!$this->flAnulaCandidato && ($this->acumFlAnulacionAbCdAlcistaActiva ||
                $this->acumFlAnulacionAbcAlcistaActiva))
            {
                if ($this->acumFlAnulacionAbCdAlcistaActiva)
                    $this->datas[$i]['entrada'] .= ' Anula ProvMax por AB=CD Alcista Activa';
                else
                    $this->datas[$i]['entrada'] .= ' Anula ProvMax por ABC Alcista Activa';
                $this->flAnulaCandidato = true;
            }

            if (!$this->flAnulaCandidato && $this->acumFlAnulacionW4AlcistaActiva)
            {
                $this->datas[$i]['entrada'] .= ' Anula ProvMax por W4 Alcista Activa';
                $this->flAnulaCandidato = true;
            }
        }
        else
        {
            $this->flAnulaCandidato = true;
        }
        // anula SBLANCA
        if (($this->datas[$i]['low'] < $this->ultimoMinimoBajista || $i > $this->ventanaSpBajista ||
            $this->datas[$i]['high'] > $this->ultimoMaximoBajista) && $this->flSpBajista)
        {
            $this->flSpBajista = false;
            $this->tgtSpBajista1 = 0;

            if ($this->datas[$i]['high'] > $this->ultimoMaximoBajista)
                $this->datas[$i]['entrada'] .= ' Cierra SBLANCA por max > a ult_maximo '.$this->ultimoMaximoBajista;
            if ($this->datas[$i]['low'] < $this->ultimoMinimoBajista)
                $this->datas[$i]['entrada'] .= ' Cierra SBLANCA por min < min_inicio '.$this->ultimoMinimoBajista;
            if ($i > $this->ventanaSpBajista)
                $this->datas[$i]['entrada'] .= ' Cierra SBLANCA por superar tiempo de ventana';

            $this->ventanaSpBajista = 0;
            $this->ultimoMaximoBajista = 0;
            $this->ultimoMinimoBajista = 0;
        }
        // anula SROJA
		//if ($i == 520)
		//{
		//	dd($this->datas[$i]['low'].' '.$this->ultimoMinimoBajista.' '.$this->datas[$i]['high'].' '.$this->ultimoMaximoBajista);
		//}
        if (($this->datas[$i]['low'] < $this->ultimoMinimoAlcista || $i > $this->ventanaSpAlcista ||
            $this->datas[$i]['high'] > $this->ultimoMaximoAlcista) && $this->flSpAlcista)
        {
            $this->flSpAlcista = false;
            $this->tgtSpAlcista1 = 0;

            if ($this->datas[$i]['high'] > $this->ultimoMaximoAlcista)
                $this->datas[$i]['entrada'] .= ' Cierra SROJA por max > a ult_maximo '.$this->ultimoMaximoAlcista;
            if ($this->datas[$i]['low'] < $this->ultimoMinimoAlcista)
                $this->datas[$i]['entrada'] .= ' Cierra SROJA por min < ult.min.'.$this->ultimoMinimoAlcista;
            if ($i > $this->ventanaSpAlcista)
                $this->datas[$i]['entrada'] .= ' Cierra SROJA por superar tiempo de ventana';

            $this->ventanaSpAlcista = 0;
            $this->ultimoMaximoAlcista = 0;
            $this->ultimoMinimoAlcista = 0;
        }

        // Chequea por fin de anulacion por ABC/AB=CD
        if ($this->acumFlAnulacionAbcAlcistaActiva)
        {
            $this->acumFlAnulacionAbcAlcistaActiva = Self::verificaAnulacionActivaAbc($i, 'ALCISTA');
                
            if (!$this->acumFlAnulacionAbcAlcistaActiva)
                $this->datas[$i]['entrada'] .= ' Cierra ROJA ABC';
        }
        
        if ($this->acumFlAnulacionAbCdAlcistaActiva)
        {
            $this->acumFlAnulacionAbCdAlcistaActiva = Self::verificaAnulacionActivaAbCd($i, 'ALCISTA');

            if (!$this->acumFlAnulacionAbCdAlcistaActiva)
                $this->datas[$i]['entrada'] .= ' Cierra ROJA AB=CD';
        }

        if ($this->acumFlAnulacionW4AlcistaActiva)
        {
            $this->acumFlAnulacionW4AlcistaActiva = Self::verificaAnulacionActivaW4($i, 'ALCISTA');

            if (!$this->acumFlAnulacionW4AlcistaActiva)
                $this->datas[$i]['entrada'] .= ' Cierra ROJA W4';
        }

        if ($this->acumFlAnulacionAbcBajistaActiva)
        {
            $this->acumFlAnulacionAbcBajistaActiva = Self::verificaAnulacionActivaAbc($i, 'BAJISTA');
                
            if (!$this->acumFlAnulacionAbcBajistaActiva)
                $this->datas[$i]['entrada'] .= ' Cierra BLANCA ABC';
        }

        if ($this->acumFlAnulacionAbCdBajistaActiva)
        {
            $this->acumFlAnulacionAbCdBajistaActiva = Self::verificaAnulacionActivaAbCd($i, 'BAJISTA');

            if (!$this->acumFlAnulacionAbCdBajistaActiva)
                $this->datas[$i]['entrada'] .= ' Cierra BLANCA AB=CD';
        }

        if ($this->acumFlAnulacionW4BajistaActiva)
        {
            $this->acumFlAnulacionW4BajistaActiva = Self::verificaAnulacionActivaW4($i, 'BAJISTA');
                
            if (!$this->acumFlAnulacionW4BajistaActiva)
                $this->datas[$i]['entrada'] .= ' Cierra BLANCA W4';
        }
//if ($i == 934)
//{
    //dd($this->datas[$i]['low'].' '.$this->datas[$i]['provRet'].' '.$this->datas[$i]['regimenVolatilidad'].' '.
    //$this->datas[$i]['horainicio'].' x1 '.$this->acumFlCerroPorTiempoAlcista.' x2'.$this->acumFlCerroPorTiempoBajista.
    //' x3'.$this->acumFlBuscaEntrada.
    //' x4'.$this->acumconFiltrosCandidato.' x5'.$this->flAnulaCandidato.' x6'.$this->acumFlFiltroOutBound);
//}
        if (($this->filtroSetup != 'T' ? !$this->acumFlAbrePosicion : true) &&
            $this->datas[$i]['provRet'] >= 0.382 && $this->datas[$i]['provRet'] <= 1 &&
            $this->datas[$i]['regimenVolatilidad'] == 1 &&
            $this->datas[$i]['horainicio'] >= '08:00:00' &&
            $this->datas[$i]['horainicio'] <= ($flDayLight ? '17:00:00' : '16:00:00') &&
            !$this->acumFlCerroPorTiempoAlcista && !$this->acumFlCerroPorTiempoBajista && 
            !$this->acumFlBuscaEntrada &&
            //($this->acumconFiltrosCandidato ? !$this->flAnulaCandidato : true) &&
            !$this->acumFlFiltroOutBound)
        {
            // Calcula filtros de inertia y volatilidad
			if ($this->datas[$i]['provMin'] != 0 || $this->datas[$i]['provMax'] != 0)
			{
            	if (!$this->flSinFiltros)
                	$this->calculaFiltrosVolatilidadInertia($i, $this->datas[$i]['provMin'] ? "ALCISTA" : "BAJISTA");
			}

            //if (!$this->flVolatilidad && !$this->flInertia)
            {
                $minimoActual = $this->datas[$i]['low'];
                $maximoActual = $this->datas[$i]['high'];

                // Define si el candidato es alcista o bajista 
                $this->acumOff0 = $this->acumOff1oA = -1;
                
                if (!$this->acumFlAbrePosicion)
                    $this->acumFlBajista = $this->acumFlAcista = false;
                $retroceso = $relacionVelas = 0;
                if ($minimoActual == $this->datas[$i]['provMin'] && 
                    ($this->filtroSetup == 'A' || $this->filtroSetup == 'T')) // Alcista
                {
                    if (!$this->acumFlAbrePosicion)
                        self::buscaMinMaxAlcista($i, $this->acumOff1oA, $this->acumOff0, $this->acumStopLoss, $maximo1oA);
                    // Si viene con posicion abierta en mismo sentido descarta
                    if ($this->acumFlAbrePosicion && $this->acumFlAcista) 
                        $this->acumOff0 = $this->acumOff1oA = -1;
                    else
                    {
                        $this->acumFlBajista = false;
                        $this->acumFlAcista = true;

                        $this->datas[$i]['entrada'] .= 'ACTIVA ALCISTA ';
                    }

                    // Si obtiene maximo y minimo calcula valores para verificar gatillo
                    if ($this->acumOff1oA != -1 && $this->acumOff0 != -1)
                    {
                        $recorrido1oA = $maximo1oA - $this->acumStopLoss;
                        $recorrido2oB = $maximo1oA - $minimoActual;
                        $retroceso = $recorrido2oB / $recorrido1oA;

                        $barras1oA = $this->acumOff1oA - $this->acumOff0;
                        $barras2oB = $i - $this->acumOff1oA;
                        $relacionVelas = $barras2oB / $barras1oA;

                        $this->acumT1 = Round((($recorrido1oA * 0.618) + $minimoActual)/$this->ticker,0)*$this->ticker;
                        $this->acumT2 = Round((($recorrido1oA) + $minimoActual)/$this->ticker,0)*$this->ticker;
                        $this->acumT3 = Round((($recorrido1oA * 1.618) + $minimoActual)/$this->ticker,0)*$this->ticker;
                        $this->acumT4 = Round((($recorrido1oA * 2.618) + $minimoActual)/$this->ticker,0)*$this->ticker;
                        $this->acumPuntoEntrada = (abs($this->acumT1 - $this->acumStopLoss) * 0.4) + $this->acumStopLoss;
                        $this->acumPuntoEntrada = Round($this->acumPuntoEntrada/$this->ticker,0) * $this->ticker;
                        $this->acumQVentanaEntrada = 0;

                        // Descarta si el punto de entrada es menor al low
                        if ($this->acumPuntoEntrada < $this->datas[$i]['low'])
                            $this->acumOff0 = $this->acumOff1oA = -1;
                    }
                }
                if ($maximoActual == $this->datas[$i]['provMax'] &&
                    ($this->filtroSetup == 'B' || $this->filtroSetup == 'T')) // Bajista
                {
                    if (!$this->acumFlAbrePosicion)
                        self::buscaMinMaxBajista($i, $this->acumOff1oA, $this->acumOff0, $this->acumStopLoss, $minimo);
                    
                    // Si viene con posicion abierta en mismo sentido descarta
                    if ($this->acumFlAbrePosicion && $this->acumFlBajista) 
                        $this->acumOff0 = $this->acumOff1oA = -1;
                    else
                    {
                        $this->acumFlBajista = true;
                        $this->acumFlAcista = false;

                        $this->datas[$i]['entrada'] .= 'ACTIVA BAJISTA ';
                    }

                    // Si obtiene maximo y minimo calcula valores para verificar gatillo
                    if ($this->acumOff1oA != -1 && $this->acumOff0 != -1)
                    {
                        $recorrido1oA = $this->acumStopLoss - $minimo;
                        $recorrido2oB = $maximoActual - $minimo;
                        if ($recorrido1oA != 0)
                            $retroceso = $recorrido2oB / $recorrido1oA;
                        else   
                            $retroceso = 0;

                        $barras1oA = $this->acumOff0 - $this->acumOff1oA;
                        $barras2oB = $i - $this->acumOff0;
                        if ($barras1oA != 0)
                            $relacionVelas = $barras2oB / $barras1oA;
                        else
                            $relacionVelas = 0;

                        $this->acumT1 = Round(($maximoActual - ($recorrido1oA * 0.618))/$this->ticker,0)*$this->ticker;
                        $this->acumT2 = Round(($maximoActual - ($recorrido1oA * 1.))/$this->ticker,0)*$this->ticker;
                        $this->acumT3 = Round(($maximoActual - ($recorrido1oA * 1.618))/$this->ticker,0)*$this->ticker;
                        $this->acumT4 = Round(($maximoActual - ($recorrido1oA * 2.618))/$this->ticker,0)*$this->ticker;

                        $this->acumPuntoEntrada = $this->acumStopLoss - (abs($this->acumStopLoss - $this->acumT1) * 0.4);
                        $this->acumPuntoEntrada = Round($this->acumPuntoEntrada/$this->ticker,0) * $this->ticker;
                        $this->acumQVentanaEntrada = 0;

                        // Descarta si el punto de entrada es menor al low
                        if ($this->acumPuntoEntrada > $this->datas[$i]['high'])
                            $this->acumOff0 = $this->acumOff1oA = -1;
                    }
                }
                //if ($i == 2604)
                  //  dd($this->acumOff1oA.' e '.$this->acumOff0.' d '.$retroceso.' x '.$relacionVelas);
                // Si obtiene maximo y minimo verifica gatillo
                if ($this->acumOff1oA != -1 && $this->acumOff0 != -1)
                {
                    if ($retroceso >= 0.382 && $relacionVelas <= 1)
                    {
                        $this->acumFlBuscaEntrada = true;

                        $this->datas[$i]['entrada'] .= ' Retroceso '.$retroceso.' RV '.$relacionVelas.' T1 '.
                                                        $this->acumT1.' Entrada '.$this->acumPuntoEntrada;

                        if (!$this->acumFlAbrePosicion)
                        {
                            $zonaOpen = $this->calculaZona($this->datas[$i]['open'], $i);
                            $zonaHigh = $this->calculaZona($this->datas[$i]['high'], $i);
                            $zonaLow = $this->calculaZona($this->datas[$i]['low'], $i);
                            $zonaClose = $this->calculaZona($this->datas[$i]['close'], $i);
                        }
                    }
                    $this->datas[$i]['extT1'] = $this->acumT1;
                    $this->datas[$i]['extT2'] = $this->acumT2;
                    $this->datas[$i]['extT3'] = $this->acumT3;
                    $this->datas[$i]['extT4'] = $this->acumT4;
                }
            }
            //else
                //$this->datas[$i]['entrada'] .= ' Volatilidad '.$this->flVolatilidad.' Inertia '.$this->flInertia;
        }

        $sp = $this->datas[$i]['setup'];
        if ($sp != '' && !$this->acumFlBuscaEntrada)
        {
            $this->acumT1 = $this->datas[$i]['extT1'];
            $this->acumT2 = $this->datas[$i]['extT2'];
            $this->acumT3 = $this->datas[$i]['extT3'];
            $this->acumT4 = $this->datas[$i]['extT4'];
        
            $t1Hit = $t2Hit = $t3Hit = $t4Hit = 0;

            switch($sp)
            {
            case 'HL':
            case 'LL':
            case 'DB':
                $columna = 'high';
                break;
            case 'LH':
            case 'HH':
            case 'DT':
                $columna = 'low';
                break;
            }
        }
    }

    private function calculaFiltros($i, $op)
    {
        if (self::calculaW4($i, $op, true))
        {
            if ($op == 'BAJISTA')
            {
                //$this->acumFlAnulacionW4BajistaActiva = true;

                //$this->datas[$i]['filtroActivo'] .= ' W4 BLANCA';
            }
            else
            {
                //$this->acumFlAnulacionW4AlcistaActiva = true;

                //$this->datas[$i]['filtroActivo'] .= ' W4 ROJA';
            }
        }     
        if (self::calculaAbCd($i, $op, true))
        {
            if ($op == 'BAJISTA')
            {
                //$this->acumFlAnulacionAbCdBajistaActiva = true;

                //$this->datas[$i]['filtroActivo'] .= ' AB=CD BLANCA';
            }
            else
            {
                //$this->acumFlAnulacionAbCdAlcistaActiva = true;

                //$this->datas[$i]['filtroActivo'] .= ' AB=CD ROJA';
            }
        }
        // Calcula filtros ABC / AB=CD
        if (self::calculaAbc($i, $op, true))
        {
            if ($op == 'BAJISTA')
            {
                //$this->acumFlAnulacionAbcBajistaActiva = true;

                //$this->datas[$i]['filtroActivo'] .= ' ABC BLANCA';
            }
            else
            {
                //$this->acumFlAnulacionAbcAlcistaActiva = true;

                //$this->datas[$i]['filtroActivo'] .= ' ABC ROJA';
            }
        }
    }
    
    private function calculaFiltrosVolatilidadInertia($i, $op)
    {
        $this->flVolatilidad = false;
        if ($this->datas[$i]['regimenVolatilidad'] == 0)
            $this->flVolatilidad = true;

        $this->flInertia = true;
        if ($op == 'ALCISTA')
        {
            if (($this->datas[$i]['inertia'] < -5 && $this->datas[$i]['cciaTRadj'] > $this->datas[$i]['osb']) || $this->datas[$i]['inertia'] > -5)
                $this->flInertia = false;
            
            $this->datas[$i]['entrada'] = " Filtros volatilidad ".$op." Reg.Vol. ".$this->datas[$i]['regimenVolatilidad']." Inertia: ".$this->datas[$i]['inertia']." cciaTRadj ".$this->datas[$i]['cciaTRadj']." OSB ".$this->datas[$i]['osb'];
        }
        else
        {
            if (($this->datas[$i]['inertia'] > 5 && $this->datas[$i]['cciaTRadj'] < $this->datas[$i]['obb']) || $this->datas[$i]['inertia'] < 5)
                $this->flInertia = false;

            $this->datas[$i]['entrada'] = " Filtros volatilidad ".$op." Reg.Vol. ".$this->datas[$i]['regimenVolatilidad']." Inertia: ".$this->datas[$i]['inertia']." cciaTRadj ".$this->datas[$i]['cciaTRadj']." OBB ".$this->datas[$i]['obb'];
        }
    }

    private function cierraPosicion($i, $flAlcista, $flBajista, $offAbrePosicion, 
                                    $idSenial, $idTrade, $cantidadActivaContratos, $tipoOperacion, &$mpc, &$mpf)
    {
        //if ($i == 4767)
            //dd('Cierra pos'.$this->datas[$i]['evento']);
        if ($this->datas[$i]['evento'] == 'NM')
        {
            $precioCierre = $this->datas[$i]['open'];

            $this->armaTablaOperaciones($this->datas[$i]['fecha'],
                $idTrade, 
                '',
                $this->cantidadActivaContratos,  
                $this->datas[$i]['horainicio'], 
                '',
                '',
                $this->datas[$i]['t1'], $this->datas[$i]['t2'], $this->datas[$i]['t3'], $this->datas[$i]['t4'],
                '', '', '', '', '', '', '',
                $precioCierre, 
                0, 0, 'CIERRA NM', $i);
        }
        else
        {
            if ($this->datas[$i]['evento'] == 'SL')
                $precioCierre = $this->datas[$i]['stoploss'];
            else
                $precioCierre = round($this->datas[$i]['t1'] / $this->ticker, 0) * $this->ticker;
        }

        $plPuntos = ($this->datas[$i]['e'] - $precioCierre) * ($flBajista ? 1 : -1);
        $plTicks = $plPuntos / $this->ticker;
        $plPesos = $plTicks * $this->valorTicker;

		if ($this->datas[$i]['stoploss'] - $this->datas[$i]['e'] != 0)
        	$eficienciaEntrada = ($mpc - $this->datas[$i]['e']) / ($this->datas[$i]['stoploss'] - $this->datas[$i]['e']);
		else
        	$eficienciaEntrada = 0.;

		if ($this->datas[$i]['e']-$mpf != 0)
        	$eSalida = ($this->datas[$i]['e'] - $precioCierre) / ($this->datas[$i]['e']-$mpf);
		else
        	$eSalida = ($this->datas[$i]['e'] - $precioCierre);
        if ($eSalida < 0)
            $eficienciaSalida = 0;
        else
            $eficienciaSalida = $eSalida;
    }

    private function controlaCierreTgtSl($i, $flAlcista, $flBajista, &$flAbrePosicion, $idTrade, $mpc, $mpf)
    {
        $contratoActivo = $this->totalContratos - $this->cantidadActivaContratos + 1;

        for ($j = 0, $off = -1; $j < count($this->operaciones) && $off != $j; $j++)
        {
            if ($this->operaciones[$j]['idTrade'] == $idTrade)
                $off = $j;
        }
        // Configura direccion en funcion de como esta el trade actual
        $flAlcista = $flBajista = false;
        if ($this->operaciones[$off]['direccion'] == 1)
            $flAlcista = true;
        else    
            $flBajista = true;

        if ($flAlcista)
        {
            if ($this->datas[$i]['open'] >= $this->tgt[$contratoActivo] ||
                $this->datas[$i]['close'] >= $this->tgt[$contratoActivo] ||
                $this->datas[$i]['high'] >= $this->tgt[$contratoActivo] ||
                $this->datas[$i]['low'] >= $this->tgt[$contratoActivo])
            {
                $this->datas[$i]['evento'] = 'Tgt Hit '.$this->tgt[$contratoActivo].' Contrato activo '.$contratoActivo.'/'.
					$this->cantidadActivaContratos.' TOTAL '.$this->totalContratos;

                $this->armaTablaOperaciones($this->datas[$i]['fecha'],
                    $idTrade, 
                    '',
                    $this->cantidadActivaContratos,  
                    $this->datas[$i]['horainicio'], 
                    '',
                    '',
                    $this->datas[$i]['t1'], $this->datas[$i]['t2'], $this->datas[$i]['t3'], $this->datas[$i]['t4'],
                    '', '', '', '', '', '', '',
                    $this->tgt[$contratoActivo], 
                    $mpc, $mpf, 'CIERRA TGT', $i);
                
                $this->cantidadActivaContratos--;
                if ($this->cantidadActivaContratos == 0)
				{
                	$this->datas[$i]['p'] = '0';
                    $flAbrePosicion = false;
                	$this->acumFlCierraPorTiempo = false;
				}
            }
            // Chequea con SL
            if ($this->datas[$i]['open'] <= $this->datas[$i]['stoploss'] ||
                $this->datas[$i]['close'] <= $this->datas[$i]['stoploss'] ||
                $this->datas[$i]['high'] <= $this->datas[$i]['stoploss'] ||
                $this->datas[$i]['low'] <= $this->datas[$i]['stoploss'])
            {
                $this->armaTablaOperaciones($this->datas[$i]['fecha'],
                    $idTrade, 
                    '',
                    $this->cantidadActivaContratos,  
                    $this->datas[$i]['horainicio'], 
                    '',
                    '',
                    $this->datas[$i]['t1'], $this->datas[$i]['t2'], $this->datas[$i]['t3'], $this->datas[$i]['t4'],
                    '', '', '', '', '', '', '',
                    $this->datas[$i]['stoploss'], 
                    0, 0, 'CIERRA SL', $i);

                $this->datas[$i]['p'] = '0';
                $this->datas[$i]['evento'] = 'SL '.$this->datas[$i]['stoploss'].' A i'.$i;
                $flAbrePosicion = false;
                $this->acumFlCierraPorTiempo = false;
            }
        }
        if ($flBajista)
        {
            //if ($i == 4767)
                //echo ("SL ANT ".$this->datas[$i]['stoploss']);
            // Chequea Target 1
            if ($this->datas[$i]['open'] <= $this->tgt[$contratoActivo] ||
                $this->datas[$i]['close'] <= $this->tgt[$contratoActivo] ||
                $this->datas[$i]['high'] <= $this->tgt[$contratoActivo] ||
                $this->datas[$i]['low'] <= $this->tgt[$contratoActivo])
            {
                $this->datas[$i]['evento'] = 'Tgt Hit '.$this->tgt[$contratoActivo].' Contrato activo '.$contratoActivo.'/'.
					$this->cantidadActivaContratos.' TOTAL '.$this->totalContratos;

                $this->armaTablaOperaciones($this->datas[$i]['fecha'],
                    $idTrade, 
                    '',
                    $this->cantidadActivaContratos,  
                    $this->datas[$i]['horainicio'], 
                    '',
                    '',
                    $this->datas[$i]['t1'], $this->datas[$i]['t2'], $this->datas[$i]['t3'], $this->datas[$i]['t4'],
                    '', '', '', '', '', '', '',
                    $this->tgt[$contratoActivo], 
                    0, 0, 'CIERRA TGT', $i);
                
                $this->cantidadActivaContratos--;
                if ($this->cantidadActivaContratos == 0)
				{
                	$this->datas[$i]['p'] = '0';
                    $flAbrePosicion = false;
                	$this->acumFlCierraPorTiempo = false;
				}
            }
            //if ($i == 4767)
                //echo ("SLLLL".$this->datas[$i]['open'].' '.$this->datas[$i]['stoploss']);
            // Chequea con SL
            if ($this->datas[$i]['open'] >= $this->datas[$i]['stoploss'] ||
                $this->datas[$i]['close'] >= $this->datas[$i]['stoploss'] ||
                $this->datas[$i]['high'] >= $this->datas[$i]['stoploss'] ||
                $this->datas[$i]['low'] >= $this->datas[$i]['stoploss'])
            {
                $this->datas[$i]['p'] = '0';
                $this->datas[$i]['evento'] = 'SL '.$this->datas[$i]['stoploss'].' B i'.$i;
                $flAbrePosicion = false;
                $this->acumFlCierraPorTiempo = false;

                //if ($i == 4767)
                    //echo ("SL x".$this->datas[$i]['stoploss']);

                $this->armaTablaOperaciones($this->datas[$i]['fecha'],
                    $idTrade, 
                    '',
                    $this->cantidadActivaContratos,  
                    $this->datas[$i]['horainicio'], 
                    '',
                    '',
                    $this->datas[$i]['t1'], $this->datas[$i]['t2'], $this->datas[$i]['t3'], $this->datas[$i]['t4'],
                    '', '', '', '', '', '', '',
                    $this->datas[$i]['stoploss'], 
                    0, 0, 'CIERRA SL', $i);
            }
        }
    }

    private function buscaMinMaxAlcista($offset, &$off1oA, &$off0, &$stopLoss, &$maximo1oA)
    {
        for ($o = $offset; $o >= 0; $o--)
        {
            if ($this->datas[$o]['min'] != 0)
            {
                if ($off1oA != -1)
                {
                    $off0 = $o;
                    $stopLoss = $this->datas[$o]['low'];
                    if (!strstr($this->datas[$o]['entrada'], 'Stop Loss'))
                        $this->datas[$o]['entrada'] .= ' Nuevo STOP Loss Alcista '.$stopLoss;
                }
            }
            if ($this->datas[$o]['max'] != 0)
            {
                $off1oA = $o;
                $vela1oA = $this->datas[$o]['high'] - $this->datas[$o]['low'];
                $maximo1oA = $this->datas[$o]['high'];
                $this->datas[$o]['entrada'] .= ' Maximo Vela 1oA '.$vela1oA.' Max. 1oA '.$maximo1oA;
            }
            if ($off1oA != -1 && $off0 != -1)
                break;
        }
    }

    private function buscaMinMaxBajista($offset, &$offmax, &$offmin, &$stopLoss, &$minimo)
    {
        for ($o = $offset; $o >= 0; $o--)
        {
            if ($this->datas[$o]['max'] != 0)
            {
                if ($offmin != -1)
                {
                    $offmax = $o;
                    $stopLoss = $this->datas[$o]['high'];
                    $this->datas[$o]['entrada'] .= ' Nuevo Stop Loss '.$minimo.' offset '.$o;
                    if (!strstr($this->datas[$o]['entrada'], 'Stop Loss Bajista '))
                        $this->datas[$o]['entrada'] .= ' Nuevo STOP Loss Bajista '.$stopLoss;
                }
            }
            if ($this->datas[$o]['min'] != 0)
            {
                $offmin = $o;
                $minimo = $this->datas[$o]['min'];
                $this->datas[$o]['entrada'] .= ' Nuevo Minimo Bajista '.$minimo;
            }
            if ($offmax != -1 && $offmin != -1)
                break;
        }
    }

    private function buscaUltimoMinimo($offset, &$offmin, &$minimo)
    {
        $offmin = -1;
        for ($o = $offset; $o >= 0 && $offmin == -1; $o--)
        {
            if ($this->datas[$o]['min'] != 0)
            {
                $minimo = $this->datas[$o]['min'];
                $offmin = $o;
            }
        }
    }

    private function buscaUltimoMaximo($offset, &$offmax, &$maximo)
    {
        $offmax = -1;
        for ($o = $offset; $o >= 0 && $offmax == -1; $o--)
        {
            if ($this->datas[$o]['max'] != 0)
            {
                $maximo = $this->datas[$o]['max'];
                $offmax = $o;
            }
        }
    }

    private function calculaZona($valor, $offset)
    {
        $zona = 'NN ';
        if ($valor > $this->datas[$offset]['rfeInt'] &&
            $valor < $this->datas[$offset]['rfeExt'])
            $zona = 'RFE';
        if ($valor > $this->datas[$offset]['rfiInt'] &&
            $valor < $this->datas[$offset]['rfiExt'])
            $zona = 'RFI';
        if ($valor > $this->datas[$offset]['pp2'] &&
            $valor < $this->datas[$offset]['pp1'])
            $zona = 'PoC';
        if ($valor > $this->datas[$offset]['sfiExt'] &&
            $valor < $this->datas[$offset]['sfiInt'])
            $zona = 'SFI';
        if ($valor > $this->datas[$offset]['sfeExt'] &&
            $valor < $this->datas[$offset]['sfeInt'])
            $zona = 'SFE';

        return $zona;
    }

    private function calculaMpf($offAbrePosicion, $offCierraPosicion, $direccion, $precioCierre)
    {
        $mpf = ($direccion == 1 ? 0 : 9999999999);
        for ($i = $offAbrePosicion; $i <= $offCierraPosicion; $i++)   
        {
            // Calcula maximo de valores high mientras se tiene abierta la posicion
            if ($direccion == 1)
            {
                if ($this->datas[$i]['high'] > $mpf)
                    $mpf = $this->datas[$i]['high'];    
            }
            else
            {
                if ($this->datas[$i]['low'] < $mpf)
                    $mpf = $this->datas[$i]['low'];               
            }
        }
        return $mpf;
    }

    private function calculaMpc($offAbrePosicion, $offCierraPosicion, $direccion, $precioCierre)
    {
        $mpc = $direccion == 1 ? 99999999999 : 0;
        for ($i = $offAbrePosicion; $i <= $offCierraPosicion; $i++)   
        {
            // Calcula maximo de valores high mientras se tiene abierta la posicion
            if ($direccion == 1)
            {
                if ($this->datas[$i]['low'] < $mpc && $this->datas[$i]['low'] != 0)
                    $mpc = $this->datas[$i]['low'];
            }
            else
            {
                if ($this->datas[$i]['high'] > $mpc && $this->datas[$i]['high'] != 0)
                    $mpc = $this->datas[$i]['high'];
            }
        }
        return $mpc;
    }

    private function marcaSetup($offMax, $offMin, $setup)
    {   
        if ($setup == 'ALCISTA')
            $offset = $offMin;
        else    
            $offset = $offMax;

        // Calcula SP
        if (Self::calculaSp($offset, $setup, false))
            $this->datas[$offset]['senial'] = ($setup == 'ALCISTA' ? 'SPup' : 'Spdw');

        // Calcula AB=CD
        if (Self::calculaAbCd($offset, ($setup == 'ALCISTA' ? 'BAJISTA' : 'ALCISTA'), false))
            $this->datas[$offset]['senial'] = ($setup == 'ALCISTA' ? 'ABCDup' : 'ABCDdw');

        // Calcula W4 da vuelta el nombre porque W4 calcula como señal que anula que es la inversa
        if (Self::calculaW4($offset, ($setup == 'ALCISTA' ? 'BAJISTA' : 'ALCISTA'), false))
            $this->datas[$offset]['senial'] = ($setup == 'ALCISTA' ? 'W4up' : 'W4dw');       
        
        // Calcula W5
        if (Self::calculaW5($offset, ($setup == 'ALCISTA' ? 'BAJISTA' : 'ALCISTA')))
            $this->datas[$offset]['senial'] = ($setup == 'ALCISTA' ? 'W5up' : 'W5dw');  

        // Calcula ABC da vuelta el nombre porque ABC calcula como señal que anula que es la inversa
        if (Self::calculaAbc($offset, $setup, false))
            $this->datas[$offset]['senial'] = ($setup == 'ALCISTA' ? 'ABCup' : 'ABCdw');       
    }

    private function calculaAbc($offset, $setup, $flAvisos)
    {
        $offMin = 1;
        $offMax = 1;
        $C = $offset; $B = 1; $A = 1; $O = 0; $I = 0;
        $min = array();
        $max = array();
        for ($o = $offset-1; $o >= 0 && ($offMin >= 0 || $offMax >= 0); $o--)
        {
            if ($this->datas[$o]['min'] != 0 && $offMin >= 0)
                $min[$offMin--] = $o;

            if ($this->datas[$o]['max'] != 0 && $offMax >= 0)
                $max[$offMax--] = $o;
        }
        // Si encontro los minimos y maximos necesarios calcula condiciones de ABC
        $condicion0 = $condicion1 = $condicion2 = $condicion3 = $condicion4 = 0;
        $retroceso1 = $retroceso2 = 0;
        if ($flAvisos)
            $this->datas[$offset]['entrada'] .= " CONTROL ABC INICIAL ".$offMin." ".$offMax." ".$setup;
        if ($offMin == -1 && $offMax == -1)
        {
            if ($flAvisos)
            {
                if ($setup == 'BAJISTA')
                {
                    if ($this->datas[$C]['provMin'] < $this->datas[$min[$A]]['min'] && 
                        $this->datas[$A]['min'] > $this->datas[$min[$O]]['min'] &&
                        $this->datas[$C]['provMin'] > $this->datas[$min[$O]]['min'] &&
                        $this->datas[$max[$B]]['max'] < $this->datas[$max[$I]]['max'])
                        $condicion0 = true;

                    if ($condicion0)
                    {
                        $retroceso1 = abs($max[$I] - $C) / abs($max[$I] - $min[$O]);
                    }
                }
                else
                {
                    if ($this->datas[$C]['provMax'] > $this->datas[$max[$A]]['max'] && 
                        $this->datas[$min[$B]]['min'] > $this->datas[$min[$I]]['min'] &&
                        $this->datas[$C]['provMax'] < $this->datas[$max[$O]]['max'])
                        $condicion0 = true;

                    if ($condicion0)
                    {
                        $retroceso1 = abs($min[$I] - $C) / abs($min[$I] - $max[$O]);
                    }
                }

                // Arma condicion 1 2 y 3
                if ($retroceso1 >= 0.3 && $retroceso1 <= 1.05)
                    $condicion1 = true;

                $condicion2 = true;
                $condicion3 = true;
                $condicion4 = true;
            }
            else
            {
                if ($setup == 'BAJISTA')
                {
                    if ($this->datas[$C]['max'] < $this->datas[$max[$O]]['max'])
                        $condicion0 = true;

                    $x1 = abs($max[$O] - $min[$I]);
                    $x2 = abs($min[$I] - $max[$A]) + abs($max[$A] - $min[$B]) + abs($min[$B] - $C);

                    if ($x2 >= $x1 * 0.5 && $x2 <= $x1 * 1.618)
                        $condicion1 = true;

                    $retroceso1 = abs($this->datas[$min[$B]]['min'] - $this->datas[$C]['max']) / 
                                abs($this->datas[$max[$A]]['max'] - $this->datas[$min[$B]]['min']);

                    $retroceso2 = abs($this->datas[$max[$A]]['max'] - $this->datas[$min[$B]]['min']) /
                                abs($this->datas[$max[$A]]['max'] - $this->datas[$min[$I]]['min']);

                    if ($retroceso1 >= 0.786 && $retroceso1 <= 1.236)
                        $condicion2 = true;
        
                    if ($retroceso2 >= 0.786 && $retroceso2 <= 1.236)
                        $condicion3 = true;

                    $retroceso3 = abs($this->datas[$max[$A]]['max'] - $this->datas[$min[$B]]['min']) /
                                abs($this->datas[$max[$O]]['max'] - $this->datas[$min[$I]]['min']);

                    if ($retroceso3 <= 1)
                        $condicion4 = true;                        
                }
                else
                {
                    if ($this->datas[$C]['min'] > $this->datas[$min[$O]]['min'])
                        $condicion0 = true;

                    $x1 = abs($min[$O] - $max[$I]);
                    $x2 = abs($max[$I] - $min[$A]) + abs($min[$A] - $max[$B]) + abs($max[$B] - $C);

                    if ($x2 >= $x1 * 0.5 && $x2 <= $x1 * 1.618)
                        $condicion1 = true;

                    $retroceso1 = abs($this->datas[$max[$B]]['max'] - $this->datas[$C]['min']) / 
                                abs($this->datas[$min[$A]]['min'] - $this->datas[$max[$B]]['max']);

                    $retroceso2 = abs($this->datas[$min[$A]]['min'] - $this->datas[$max[$B]]['max']) /
                                abs($this->datas[$min[$A]]['min'] - $this->datas[$max[$I]]['max']);

                    if ($retroceso1 >= 0.786 && $retroceso1 <= 1.236)
                        $condicion2 = true;

                    if ($retroceso2 >= 0.786 && $retroceso2 <= 1.236)
                        $condicion3 = true;

                    $retroceso3 = abs($this->datas[$min[$A]]['min'] - $this->datas[$max[$B]]['max']) /
                                abs($this->datas[$min[$O]]['min'] - $this->datas[$max[$I]]['max']);

                    if ($retroceso3 <= 1)
                        $condicion4 = true;
                }
            }
        }
        if ($condicion0 && $condicion1 && $condicion2 && $condicion3 && $condicion4)
        {
            if ($flAvisos)
            {
                if ($setup == 'BAJISTA')
                    $this->datas[$offset]['entrada'] .= " Control ABC Alcista Min C=".$this->datas[$C]['provMin'].
                                                    " Max B=".$this->datas[$max[$B]]['max'].
                                                    " Min A=".$this->datas[$min[$A]]['min'].
                                                    " Max 1=".$this->datas[$max[$I]]['max'].
                                                    " Min O=".$this->datas[$min[$O]]['min'].
                                                    " Retroceso 1=".$retroceso1." ".
                                                    $condicion0." ".$condicion1;
                else
                    $this->datas[$offset]['entrada'] .= " Control ABC Bajista Max C=".$this->datas[$C]['provMax'].
                                                    " Min B=".$this->datas[$min[$B]]['min'].
                                                    " Max A=".$this->datas[$max[$A]]['max'].
                                                    " Min 1=".$this->datas[$min[$I]]['min'].
                                                    " Max O=".$this->datas[$max[$O]]['max'].
                                                    " Retroceso 1=".$retroceso1." ".
                                                    $condicion0." ".$condicion1;
            }

            // Asigna variables globales
            if ($setup == 'ALCISTA')
            {
                $this->offsetCAbc = $offset;
                $this->offsetBAbc = $min[$B];
                $this->offsetAAbc = $max[$A];
                $this->offsetU = $min[$I];
                $this->offsetO = $max[$O];
            }
            else
            {
                $this->offsetCAbc = $offset;
                $this->offsetBAbc = $max[$B];
                $this->offsetAAbc = $min[$A];
                $this->offsetU = $max[$I];
                $this->offsetO = $min[$O];
            }

            // Marca aviso de punto ABC
            if ($flAvisos)
                $this->datas[$offset]['entrada'] .= " CUMPLE ABC ".$setup." ";

            $this->offsetAbc = $offset;
            return true;
        }
        return false;
    }

    private function calculaAbCd($offset, $setup, $flAvisos)
    {
        if ($setup == 'BAJISTA')
        {
            $offMin = 0;
            $offMax = 1;
        }
        else
        {
            $offMin = 1;
            $offMax = 0;
        }
        $D = $offset; $C = 1; $B = 0; $A = 0;
        for ($o = $offset-1; $o >= 0 && ($offMin >= 0 || $offMax >= 0); $o--)
        {
            if ($this->datas[$o]['min'] != 0 && $offMin >= 0)
                $min[$offMin--] = $o;

            if ($this->datas[$o]['max'] != 0 && $offMax >= 0)
                $max[$offMax--] = $o;
        }
        // Si encontro los minimos y maximos necesarios calcula cibducuibes de ABC
        $condicion0 = $condicion1 = $condicion2 = $condicion3 = 0;
        $retroceso1 = $retroceso2 = $retroceso3 = 0;
        if ($flAvisos)
            $this->datas[$offset]['entrada'] .= "CONTROL AB=CD INICIAL ".$offMin." ".$offMax." ".$setup;
        if ($offMin == -1 && $offMax == -1)
        {
            if ($setup == 'BAJISTA')
            {
                if ($this->datas[$D]['provMin'] < $this->datas[$min[$B]]['min'] && 
                    $this->datas[$max[$C]]['max'] < $this->datas[$max[$A]]['max'])
                    $condicion0 = true;

                if ($condicion0)
                {
                    $retroceso1 = abs($this->datas[$min[$B]]['min'] - $this->datas[$max[$C]]['max']) / 
                                abs($this->datas[$max[$A]]['max'] - $this->datas[$min[$B]]['min']);

                    $retroceso2 = abs($this->datas[$max[$C]]['max'] - $this->datas[$D]['provMin']) / 
                                abs($this->datas[$min[$B]]['min'] - $this->datas[$max[$C]]['max']);

					$retroceso3 = abs($max[$C] - $D) / abs($max[$A] - $min[$B]);
                }
            }
            else
            {
                if ($this->datas[$D]['provMax'] > $this->datas[$max[$B]]['max'] && 
                    $this->datas[$min[$C]]['min'] > $this->datas[$min[$A]]['min'])
                    $condicion0 = true;

                if ($condicion0)
                {
                    $retroceso1 = abs($this->datas[$max[$B]]['max'] - $this->datas[$min[$C]]['min']) / 
                            abs($this->datas[$min[$A]]['min'] - $this->datas[$max[$B]]['max']);

                    $retroceso2 = abs($this->datas[$min[$C]]['min'] - $this->datas[$D]['provMax']) / 
                            abs($this->datas[$max[$B]]['max'] - $this->datas[$min[$C]]['min']);

					$retroceso3 = abs($min[$C] - $D) / abs($min[$A] - $max[$B]);
                }
            }

            // Arma condicion 1 2 y 3
            if ($retroceso1 >= 0.382 && $retroceso1 <= 0.886)
                $condicion1 = true;

            if ($flAvisos)
            {
                if ($retroceso2 >= 1.13 && $retroceso2 <= 2.618)
                    $condicion2 = true;
        
                if ($retroceso3 >= 0.618 && $retroceso3 <= 1.618)
                    $condicion3 = true;
            }
            else
            {
                if ($retroceso2 >= 1.272 && $retroceso2 <= 1.618)
                    $condicion2 = true; 
                
                $condicion3 = true;
            }

            if ($flAvisos)
            {
                if ($setup == 'BAJISTA')
                    $this->datas[$offset]['entrada'] .= " CONDICIONES AB=CD Min D=".$this->datas[$D]['provMin'].
                                                    " Max C=".$this->datas[$max[$C]]['max'].
                                                    " Min B=".$this->datas[$min[$B]]['min'].
                                                    " Max A=".$this->datas[$max[$A]]['max']. 
                                                    " Retroceso 1=".$retroceso1.
                                                    " Retroceso 2=".$retroceso2.
                                                    " Retroceso 3=".$retroceso3.
                                                    ' '.
                                                    $condicion0." ".$condicion1." ".$condicion2." ".$condicion3;
                else            
                    $this->datas[$offset]['entrada'] .= " CONDICIONES AB=CD Max D=".$this->datas[$D]['provMax'].
                                                " Min C=".$this->datas[$min[$C]]['min'].
                                                " Max B=".$this->datas[$max[$B]]['max'].
                                                " Min A=".$this->datas[$min[$A]]['min']. 
                                                " Retroceso 1=".$retroceso1.
                                                " Retroceso 2=".$retroceso2.
                                                " Retroceso 3=".$retroceso3.' '.
                                                $condicion0." ".$condicion1." ".$condicion2." ".$condicion3;
            }
        }
        if ($condicion0 && $condicion1 && $condicion2 && $condicion3)
        {
            // Asigna variables globales
            if ($setup == 'BAJISTA')
            {
                $this->offsetD = $D;
                $this->offsetCAbCd = $max[$C];
                $this->offsetBAbCd = $min[$B];
                $this->offsetAAbCd = $max[$A];
            }
            else
            {
                $this->offsetD = $D;
                $this->offsetCAbCd = $min[$C];
                $this->offsetBAbCd = $max[$B];
                $this->offsetAAbCd = $min[$A];
            }

            // Marca aviso de punto AB = CD
            if ($flAvisos)
                $this->datas[$offset]['entrada'] .= " CUMPLE AB = CD ".$setup." ";

            $this->offsetAbCd = $offset;
            return true;
        }
        return false;
    }

    private function calcula3Drives($offset, $setup)
    {
        $offMin = 2;
        $offMax = 2;
        $E = 2; $D = 2; $C = 1; $B = 1; $A = 0; $O = 0;
        $min = array();
        $max = array();
        for ($o = $offset; $o >= 0 && ($offMin >= 0 || $offMax >= 0); $o--)
        {
            if ($this->datas[$o]['min'] != 0 && $offMin >= 0)
                $min[$offMin--] = $o;

            if ($this->datas[$o]['max'] != 0 && $offMax >= 0)
                $max[$offMax--] = $o;
        }
        // Si encontro los minimos y maximos necesarios calcula cibducuibes de ABC
        $condicion0 = $condicion1 = $condicion2 = $condicion3 = $condicion4 = 0;
        $retroceso1 = $retroceso2 = $retroceso3 = $retroceso4 = 0;
        $this->datas[$offset]['entrada'] .= "CONTROL 3DRIVES INICIAL ".$offMin." ".$offMax." ".$setup;
        if ($offMin == -1 && $offMax == -1)
        {
            if ($setup == "BAJISTA")
            {
                if (abs($this->datas[$max[$C]]['max'] - $this->datas[$min[$D]]['min']) != 0)
                    $retroceso1 = abs($this->datas[$min[$D]]['min'] - $this->datas[$max[$E]]['max']) / 
                                abs($this->datas[$max[$C]]['max'] - $this->datas[$min[$D]]['min']);

                if (abs($this->datas[$min[$B]]['min'] - $this->datas[$max[$C]]['max']) != 0)
                    $retroceso2 = abs($this->datas[$max[$C]]['max'] - $this->datas[$min[$D]]['min']) / 
                            abs($this->datas[$min[$B]]['min'] - $this->datas[$max[$C]]['max']);

                if (abs($this->datas[$max[$A]]['max'] - $this->datas[$min[$B]]['min']) != 0)
                    $retroceso3 = abs($this->datas[$max[$C]]['max'] - $this->datas[$min[$D]]['min']) / 
                            abs($this->datas[$max[$A]]['max'] - $this->datas[$min[$B]]['min']);
        
                if (abs($this->datas[$min[$O]]['min'] - $this->datas[$max[$A]]['max']) != 0)
                    $retroceso4 = abs($this->datas[$max[$A]]['max'] - $this->datas[$min[$B]]['min']) / 
                            abs($this->datas[$min[$O]]['min'] - $this->datas[$max[$A]]['max']);

                $barras1 = $this->datas[$max[$A]]['swingBars'];
                $barras2 = $this->datas[$min[$B]]['swingBars'];
                $barras3 = $this->datas[$max[$C]]['swingBars'];
                $barras4 = $this->datas[$min[$D]]['swingBars'];
                $barras5 = $this->datas[$max[$E]]['swingBars'];
            }
            else
            {
                if (abs($this->datas[$min[$C]]['min'] - $this->datas[$max[$D]]['max']) != 0)
                    $retroceso1 = abs($this->datas[$max[$D]]['max'] - $this->datas[$min[$E]]['min']) / 
                                abs($this->datas[$min[$C]]['min'] - $this->datas[$max[$D]]['max']);

                if (abs($this->datas[$max[$B]]['max'] - $this->datas[$min[$C]]['min']) != 0)
                    $retroceso2 = abs($this->datas[$min[$C]]['min'] - $this->datas[$max[$D]]['max']) / 
                            abs($this->datas[$max[$B]]['max'] - $this->datas[$min[$C]]['min']);

                if (abs($this->datas[$min[$A]]['min'] - $this->datas[$max[$B]]['max']) != 0)
                    $retroceso3 = abs($this->datas[$min[$C]]['min'] - $this->datas[$max[$D]]['max']) / 
                            abs($this->datas[$min[$A]]['min'] - $this->datas[$max[$B]]['max']);
        
                if (abs($this->datas[$max[$O]]['max'] - $this->datas[$min[$A]]['min']) != 0)
                    $retroceso4 = abs($this->datas[$min[$A]]['min'] - $this->datas[$max[$B]]['max']) / 
                            abs($this->datas[$max[$O]]['max'] - $this->datas[$min[$A]]['min']);
            
                $barras1 = $this->datas[$min[$A]]['swingBars'];
                $barras2 = $this->datas[$max[$B]]['swingBars'];
                $barras3 = $this->datas[$min[$C]]['swingBars'];
                $barras4 = $this->datas[$max[$D]]['swingBars'];
                $barras5 = $this->datas[$min[$E]]['swingBars'];
            }

            // Arma condicion 1 2 3 4 y 5
            if ($retroceso1 >= 1.1 && $retroceso1 <= 2)
                $condicion0 = true;
                
            if ($retroceso2 >= 0.382 && $retroceso2 <= 0.886)
                $condicion1 = true;

            if ($retroceso3 >= 1.1 && $retroceso3 <= 2)
                $condicion2 = true;

            if ($retroceso4 >= 0.382 && $retroceso4 <= 0.886)
                $condicion3 = true;

            if ($setup == 'BAJISTA')
            {
                if ($this->datas[$max[$A]]['max'] < $this->datas[$min[$D]]['min'] && 
                    $this->datas[$min[$D]]['min'] < $this->datas[$min[$B]]['min'] &&
                    $this->datas[$max[$B]]['max'] < $this->datas[$min[$O]]['min'])
                  $condicion4 = true;

                $this->datas[$offset]['entrada'] .= " Control 3Drives Max E=".$this->datas[$max[$E]]['max']." Min D=".$this->datas[$min[$D]]['min']." Max C=". 
                                            $this->datas[$max[$C]]['max']." Min B=".$this->datas[$min[$B]]['min'].
                                            " Max A=".$this->datas[$max[$A]]['max']." Min O=".$this->datas[$min[$O]]['min'].
                                            " Retroceso 1=".$retroceso1.
                                            " Retroceso 2=".$retroceso2." Barras 0A ".$barras1." Barras AB ".$barras2.
                                            " Barras BC ".$barras3." Barras CD ".$barras4." Barras DE ".$barras5." ".
                                            $condicion0." ".$condicion1." ".$condicion2." ".$condicion3." ".$condicion4;
            }
            else
            {
                if ($this->datas[$min[$A]]['min'] < $this->datas[$max[$D]]['max'] && 
                    $this->datas[$max[$D]]['max'] < $this->datas[$max[$B]]['max'] &&
                    $this->datas[$min[$B]]['min'] < $this->datas[$max[$O]]['max'])
                    $condicion4 = true;

                $this->datas[$offset]['entrada'] .= " Control 3Drives Min E=".$this->datas[$min[$E]]['min']." Max D=".$this->datas[$max[$D]]['max']." Min C=". 
												$this->datas[$min[$C]]['min']." Max B=".$this->datas[$max[$B]]['max'].
												" Min A=".$this->datas[$min[$A]]['min']." Max O=".$this->datas[$max[$O]]['max'].
                                                " Retroceso 1=".$retroceso1.
												" Retroceso 2=".$retroceso2." Barras 0A ".$barras1." Barras AB ".$barras2.
                                                " Barras BC ".$barras3." Barras CD ".$barras4." Barras DE ".$barras5." ".
                                                $condicion0." ".$condicion1." ".$condicion2." ".$condicion3." ".$condicion4;
            }
        }
        if ($condicion0 && $condicion1 && $condicion2 && $condicion3 && $condicion4)
        {
            // Asigna variables globales
            if ($setup == 'BAJISTA')
            {
                $this->offsetMaximoE = $max[$E];
                $this->offsetMinimoD = $min[$D];
                $this->offsetMaximoC = $max[$C];
                $this->offsetMinimoB = $min[$B];
                $this->offsetMaximoA = $max[$A];
            }
            else
            {
                $this->offsetMinimoE = $min[$E];
                $this->offsetMaximoD = $max[$D];
                $this->offsetMinimoC = $min[$C];
                $this->offsetMaximoB = $max[$B];
                $this->offsetMinimoA = $min[$A];
            }

            // Marca aviso de punto ABC
            $this->datas[$offset]['entrada'] .= " CUMPLE 3Drives ".$setup." ";

            $this->offset3Drives = $offset;
            return true;
        }
        return false;
    }

    private function calculaShark($offset, $setup)
    {
        if ($setup == 'BAJISTA')
        {
            $offMin = 1;
            $offMax = 2;
        }
        else
        {
            $offMin = 2;
            $offMax = 1;
        }
        $D = 2; $C = 1; $B = 1; $A = 0; $O = 0;
        $min = array();
        $max = array();
        for ($o = $offset; $o >= 0 && ($offMin >= 0 || $offMax >= 0); $o--)
        {
            if ($this->datas[$o]['min'] != 0 && $offMin >= 0)
                $min[$offMin--] = $o;

            if ($this->datas[$o]['max'] != 0 && $offMax >= 0)
                $max[$offMax--] = $o;
        }
        // Si encontro los minimos y maximos necesarios calcula cibducuibes de ABC
        $condicion0 = $condicion1 = $condicion2 = $condicion3 = $condicion4 = 0;
        $retroceso1 = $retroceso2 = 0;
        $this->datas[$offset]['entrada'] .= "CONTROL 3DRIVES INICIAL ".$offMin." ".$offMax." ".$setup;
        if ($offMin == -1 && $offMax == -1)
        {
            if ($setup == 'BAJISTA')
            {
                if (abs($this->datas[$max[$B]]['max'] - $this->datas[$min[$C]]['min']) != 0)
                    $retroceso1 = abs($this->datas[$min[$C]]['min'] - $this->datas[$max[$D]]['max']) / 
                                abs($this->datas[$max[$B]]['max'] - $this->datas[$min[$C]]['min']);

                $topeInferior = ($this->datas[$max[$O]]['max']-
                                abs($this->datas[$max[$O]]['max']-$this->datas[$min[$A]]['min']))*0.13;

                $topeSuperior = ($this->datas[$max[$O]]['max']+
                                abs($this->datas[$max[$O]]['max']-$this->datas[$min[$A]]['min']))*0.114;

                if (abs($this->datas[$min[$A]]['min'] - $this->datas[$max[$B]]['max']) != 0)
                    $retroceso3 = abs($this->datas[$max[$B]]['max'] - $this->datas[$min[$C]]['min']) / 
                            abs($this->datas[$min[$A]]['min'] - $this->datas[$max[$B]]['max']);

                $barras1 = $this->datas[$min[$A]]['swingBars'];
                $barras2 = $this->datas[$max[$B]]['swingBars'];
                $barras3 = $this->datas[$min[$C]]['swingBars'];
                $barras4 = $this->datas[$max[$D]]['swingBars'];

                // Arma condicion 1 2 3 4 y 5
                if ($retroceso1 >= 1.618 && $retroceso1 <= 2.24)
                    $condicion0 = true;
                    
                if ($this->datas[$max[$D]]['max'] > $topeInferior &&
                    $this->datas[$max[$D]]['max'] < $topeSuperior)
                    $condicion1 = true;

                if ($retroceso3 >= 1.001 && $retroceso3 <= 1.618)
                    $condicion2 = true;

                if ($this->datas[$max[$B]]['max'] > $this->datas[$max[$O]]['max'])
                    $condicion3 = true;
            }
            else
            {
                if (abs($this->datas[$min[$B]]['min'] - $this->datas[$max[$C]]['max']) != 0)
                    $retroceso1 = abs($this->datas[$max[$C]]['max'] - $this->datas[$min[$D]]['min']) / 
                                abs($this->datas[$min[$B]]['min'] - $this->datas[$max[$C]]['max']);

                $topeInferior = ($this->datas[$min[$O]]['min']-
                                abs($this->datas[$min[$O]]['min']-$this->datas[$max[$A]]['max']))*0.13;

                $topeSuperior = ($this->datas[$min[$O]]['min']+
                                abs($this->datas[$min[$O]]['min']-$this->datas[$max[$A]]['max']))*0.114;

                if (abs($this->datas[$max[$A]]['max'] - $this->datas[$min[$B]]['min']) != 0)
                    $retroceso3 = abs($this->datas[$min[$B]]['min'] - $this->datas[$max[$C]]['max']) / 
                            abs($this->datas[$max[$A]]['max'] - $this->datas[$min[$B]]['min']);

                $barras1 = $this->datas[$max[$A]]['swingBars'];
                $barras2 = $this->datas[$min[$B]]['swingBars'];
                $barras3 = $this->datas[$max[$C]]['swingBars'];
                $barras4 = $this->datas[$min[$D]]['swingBars'];

                // Arma condicion 1 2 3 4 y 5
                if ($retroceso1 >= 1.618 && $retroceso1 <= 2.24)
                    $condicion0 = true;
                    
                if ($this->datas[$min[$D]]['min'] > $topeInferior &&
                    $this->datas[$min[$D]]['min'] < $topeSuperior)
                    $condicion1 = true;

                if ($retroceso3 >= 1.001 && $retroceso3 <= 1.618)
                    $condicion2 = true;

                if ($this->datas[$min[$B]]['min'] > $this->datas[$min[$O]]['min'])
                    $condicion3 = true;
            }

            if ($barras4 <= (0.618 * ($barras1+$barras2+$barras3)))
                $condicion4 = true;

            if ($setup == 'BAJISTA')
                $this->datas[$offset]['entrada'] .= " Control Shark Max D=".$this->datas[$max[$D]]['max']." Min C=". 
                                                $this->datas[$min[$C]]['min']." Max B=".$this->datas[$max[$B]]['max'].
                                                " Min A=".$this->datas[$min[$A]]['min']." Max O=".$this->datas[$max[$O]]['max'].
                                                " Retroceso 1=".$retroceso1.
                                                " Retroceso 3=".$retroceso3." Barras 0A ".$barras1." Barras AB ".$barras2.
                                                " Barras BC ".$barras3." Barras CD ".$barras4." ".
                                                $condicion0." ".$condicion1." ".$condicion2." ".$condicion3." ".$condicion4;
            else
                $this->datas[$offset]['entrada'] .= " Control Shark Min D=".$this->datas[$min[$D]]['min']." Max C=". 
												$this->datas[$max[$C]]['max']." Min B=".$this->datas[$min[$B]]['min'].
												" Max A=".$this->datas[$max[$A]]['max']." Min O=".$this->datas[$min[$O]]['min'].
                                                " Retroceso 1=".$retroceso1.
												" Retroceso 3=".$retroceso3." Barras 0A ".$barras1." Barras AB ".$barras2.
                                                " Barras BC ".$barras3." Barras CD ".$barras4." ".
                                                $condicion0." ".$condicion1." ".$condicion2." ".$condicion3." ".$condicion4;
        }
        if ($condicion0 && $condicion1 && $condicion2 && $condicion3 && $condicion4)
        {
            // Asigna variables globales
            if ($setup == 'BAJISTA')
            {
                $this->offsetMaximoD = $max[$D];
                $this->offsetMinimoC = $min[$C];
                $this->offsetMaximoB = $max[$B];
                $this->offsetMinimoA = $min[$A];
            }
            else
            {
                $this->offsetMinimoD = $min[$D];
                $this->offsetMaximoC = $max[$C];
                $this->offsetMinimoB = $min[$B];
                $this->offsetMaximoA = $max[$A];
            }

            // Marca aviso de punto ABC
            $this->datas[$offset]['entrada'] .= " CUMPLE Shark ".$setup." ";

            $this->offsetShark = $offset;
            return true;
        }
        return false;
    }

    private function calculaW4($offset, $setup, $flAvisos)
    {
        $offMax = 1;
        $offMin = 1;
        $O = 0; $U = 0; $D = 1; $T = 1; $C = $offset;
        $min = array();
        $max = array();
        for ($o = $offset-1; $o >= 0 && ($offMin >= 0 || $offMax >= 0); $o--)
        {
            if ($this->datas[$o]['min'] != 0 && $offMin >= 0)
                $min[$offMin--] = $o;

            if ($this->datas[$o]['max'] != 0 && $offMax >= 0)
                $max[$offMax--] = $o;
        }
        // Si encontro los minimos y maximos necesarios calcula condiciones
        $condicion0 = $condicion1 = $condicion2 = $condicion3 = 0;
        $condicion4 = 0;

        if ($flAvisos)
            $this->datas[$offset]['entrada'] .= "CONTROL W4 INICIAL ".$offMin." ".$offMax." ".$setup;

        if ($offMin == -1 && $offMax == -1)
        {
            if ($setup == 'ALCISTA')
            {
                // Arma condicion 1 2 3 4 y 5
                if ($this->datas[$C]['provMax'] < $this->datas[$min[$U]]['min'])
                    $condicion0 = true;
                
                if ($this->datas[$max[$D]]['max'] <= $this->datas[$max[$O]]['max'])
                    $condicion1 = true;

                if ($flAvisos)
                {
                    if (abs($C - $min[$T]) <= abs($max[$O] - $min[$U]) * 1.5)
                        $condicion2 = true;

                    if (abs($min[$U] - $max[$D]) * 0.6 <= abs($min[$T] - $C) && 
                        abs($min[$T] - $C) <= abs($min[$U] - $max[$D]) * 2)
                        $condicion3 = true;
                }
                else
                {
                    if ($this->datas[$C]['max'] < $this->datas[$max[$D]]['max'])
                        $condicion2 = true;                    

                    if ($this->datas[$min[$T]]['min'] <= $this->datas[$min[$U]]['min'])
                        $condicion3 = true;          
                
                    if (abs($C - $min[$T]) <= abs($min[$U] - $max[$D]) * 3)
                        $condicion4 = true;                       
                }
            }
            else
            {
                // Arma condicion 1 2 3 4 y 5
                if ($this->datas[$C]['provMin'] > $this->datas[$max[$U]]['max'])
                    $condicion0 = true;
                
                if ($this->datas[$min[$D]]['min'] >= $this->datas[$min[$O]]['min'])
                    $condicion1 = true;

                if ($flAvisos)
                {
                    if (abs($C - $max[$T]) <= abs($min[$O] - $max[$U]) * 1.5)
                        $condicion2 = true;

                    if (abs($max[$U] - $min[$D]) * 0.6 <= abs($max[$T] - $C) && 
                        abs($max[$T] - $C) <= abs($max[$U] - $min[$D]) * 2)
                        $condicion3 = true;
                }
                else
                {
                    if ($this->datas[$C]['min'] > $this->datas[$min[$D]]['min'])
                        $condicion2 = true;                    

                    if ($this->datas[$max[$T]]['max'] > $this->datas[$max[$U]]['max'])
                        $condicion3 = true;          
                    
                    if (abs($C - $max[$T]) <= abs($max[$U] - $min[$D]) * 3)
                        $condicion4 = true;                        
                }

            }
            if ($flAvisos)
            {
                if ($this->datas[$offset]['ewo'] < $this->datas[$offset]['w4Dw2'] &&
                    $this->datas[$offset]['ewo'] > $this->datas[$offset]['w4Dw1'])
                    $condicion4 = true;

                if ($setup == 'ALCISTA')
                    $this->datas[$offset]['entrada'] .= " Control W4 Max 4=".$this->datas[$C]['provMax'].
                                                    " Min 3=".$this->datas[$min[$T]]['min'].
                                                    " Max 2=".$this->datas[$max[$D]]['max'].
                                                    " Min 1=".$this->datas[$min[$U]]['min'].
                                                    " Max O=".$this->datas[$max[$O]]['max'].
                                                    " EWO=".$this->datas[$offset]['ewo'].
                                                    " W4dw1=".$this->datas[$offset]['w4Dw1'].
                                                    " W4dw2=".$this->datas[$offset]['w4Dw2']." ".
                                                    $condicion0." ".$condicion1." ".$condicion2." ".
                                                    $condicion3." ".
                                                    $condicion4;
                else
                    $this->datas[$offset]['entrada'] .= " Control W4 Min 4=".$this->datas[$C]['provMin'].
                                                    " Max 3=".$this->datas[$max[$T]]['max'].
                                                    " Min 2=".$this->datas[$min[$D]]['min'].
                                                    " Max 1=".$this->datas[$max[$U]]['max'].
                                                    " Min O=".$this->datas[$min[$O]]['min'].
                                                    " EWO=".$this->datas[$offset]['ewo'].
                                                    " W4dw1=".$this->datas[$offset]['w4Dw1'].
                                                    " W4dw2=".$this->datas[$offset]['w4Dw2'].
                                                    $condicion0." ".$condicion1." ".$condicion2." ".$condicion3." ".
                                                    $condicion4;
            }
        }
        if ($condicion0 && $condicion1 && $condicion2 && $condicion3 && $condicion4)
        {
            // Asigna variables globales
            if ($setup == 'ALCISTA')
            {
                $this->offsetMaximoCW4 = $C;
                $this->offsetMinimoTW4 = $min[$T];
                $this->offsetMaximoDW4 = $max[$D];
                $this->offsetMinimoUW4 = $min[$U];
                $this->offsetMaximoOW4 = $max[$O];
            }
            else
            {
                $this->offsetMinimoCW4 = $C;
                $this->offsetMaximoTW4 = $max[$T];
                $this->offsetMinimoDW4 = $min[$D];
                $this->offsetMaximoUW4 = $max[$U];
                $this->offsetMinimoOW4 = $min[$O];
            }            

            // Marca aviso de punto 
            if ($flAvisos)
                $this->datas[$offset]['entrada'] .= " CUMPLE W4 ".$setup." ";

            $this->offsetW4 = $offset;
            return true;
        }
        return false;
    }

    private function calculaW5($offset, $setup)
    {
        $offMax = 2;
        $offMin = 2;
        $O = 0; $U = 0; $D = 1; $T = 1; $C = 2; $I = 2;
        $min = array();
        $max = array();
        for ($o = $offset; $o >= 0 && ($offMin >= 0 || $offMax >= 0); $o--)
        {
            if ($this->datas[$o]['min'] != 0 && $offMin >= 0)
                $min[$offMin--] = $o;

            if ($this->datas[$o]['max'] != 0 && $offMax >= 0)
                $max[$offMax--] = $o;
        }
        // Si encontro los minimos y maximos necesarios calcula condiciones
        $condicion0 = $condicion1 = $condicion2 = $condicion3 = 0;
        $condicion4 = $condicion5 = 0;

        if ($offMin == -1 && $offMax == -1)
        {
            if ($setup == 'ALCISTA')
            {
                // Arma condicion 1 2 3 4 y 5
                if ($this->datas[$max[$I]]['max'] >= $this->datas[$max[$T]]['max'])
                    $condicion0 = true;
                
                if ($this->datas[$max[$T]]['max'] > $this->datas[$max[$U]]['max'])
                    $condicion1 = true;

                if ($this->datas[$min[$C]]['min'] > $this->datas[$min[$D]]['min'])
                    $condicion2 = true;                    

                if ($this->datas[$min[$D]]['min'] >= $this->datas[$min[$O]]['min'])
                    $condicion3 = true;          

                if ($this->datas[$min[$C]]['min'] > $this->datas[$max[$U]]['max'])
                    $condicion4 = true;                    
            }
            else
            {
                // Arma condicion 1 2 3 4 y 5
                if ($this->datas[$min[$I]]['min'] <= $this->datas[$min[$T]]['min'])
                    $condicion0 = true;
                
                if ($this->datas[$min[$T]]['min'] < $this->datas[$min[$U]]['min'])
                    $condicion1 = true;

                if ($this->datas[$max[$C]]['max'] < $this->datas[$max[$D]]['max'])
                    $condicion2 = true;                    

                if ($this->datas[$max[$D]]['max'] <= $this->datas[$max[$O]]['max'])
                    $condicion3 = true;          

                if ($this->datas[$max[$C]]['max'] < $this->datas[$min[$U]]['min'])
                    $condicion4 = true;                    
            }
        }
        if ($condicion0 && $condicion1 && $condicion2 && $condicion3 && $condicion4)
        {
            if ($setup == 'ALCISTA')
                $this->datas[$offset]['entrada'] = "W5dw H0=".$this->datas[$max[$I]]['max'].
                                                        "L0=".$this->datas[$min[$C]]['min'].
                                                        "H1=".$this->datas[$max[$T]]['max'].
                                                        "L1=".$this->datas[$min[$D]]['min'].
                                                        "H2=".$this->datas[$max[$U]]['max'].
                                                        "L2=".$this->datas[$min[$O]]['min'];
            else
                $this->datas[$offset]['entrada'] = "W5up L0=".$this->datas[$min[$I]]['min'].
                                                        "H0=".$this->datas[$max[$C]]['max'].
                                                        "L1=".$this->datas[$min[$T]]['min'].
                                                        "H1=".$this->datas[$max[$D]]['max'].
                                                        "L2=".$this->datas[$min[$U]]['min'].
                                                        "H2=".$this->datas[$max[$O]]['max'];

            return true;
        }
        return false;
    }

    private function calculaSp($offset, $setup, $flAvisos)
    {
        if ($setup == 'ALCISTA')
        {
            $offMin = 1;
            $offMax = 0;
        }
        else
        {
            $offMin = 0;
            $offMax = 1;
        }
        $B = 1; $A = 0; $O = 0;
        $min = array();
        $max = array();
        for ($o = $offset; $o >= 0 && ($offMin >= 0 || $offMax >= 0); $o--)
        {
            if ($this->datas[$o]['min'] != 0 && $offMin >= 0)
                $min[$offMin--] = $o;

            if ($this->datas[$o]['max'] != 0 && $offMax >= 0)
                $max[$offMax--] = $o;
        }
        // Si encontro los minimos y maximos necesarios calcula cibducuibes de ABC
        $condicion0 = 0;
        if ($flAvisos)
            $this->datas[$offset]['entrada'] .= "CONTROL SP INICIAL ".$offMin." ".$offMax." ".$setup;
        if ($offMin == -1 && $offMax == -1)
        {
            if ($setup == 'ALCISTA')
            {
                if ($this->datas[$min[$B]]['min'] >= $this->datas[$min[$O]]['min'])
                    $condicion0 = true;
            }
            else
            {
                if ($this->datas[$max[$B]]['max'] <= $this->datas[$max[$O]]['max'])
                    $condicion0 = true;
            }

            if ($flAvisos)
            {
                if ($setup == 'ALCISTA')
                    $this->datas[$offset]['entrada'] .= " Control SP ".$setup." Min B=".$this->datas[$min[$B]]['min'].
                                                    " Max A=".$this->datas[$max[$A]]['max']." Min O=".$this->datas[$min[$O]]['min'].
                                                    $condicion0;
                else
                    $this->datas[$offset]['entrada'] .= " Control SP ".$setup." Max B=".$this->datas[$max[$B]]['max'].
                                                    " Min A=".$this->datas[$min[$A]]['min']." Max O=".$this->datas[$max[$O]]['max'].
                                                    $condicion0;
            }
        }
        if ($condicion0)
        {
            // Asigna variables globales
            if ($setup == 'ALCISTA')
            {
                $this->offsetMinimoB = $min[$B];
                $this->offsetMaximoA = $max[$A];
            }
            else
            {
                $this->offsetMaximoB = $max[$B];
                $this->offsetMinimoA = $min[$A];
            }

            // Marca aviso de punto ABC
            if ($flAvisos)
                $this->datas[$offset]['entrada'] .= " CUMPLE SP ".$setup." ";

            $this->offsetSP = $offset;
            return true;
        }
        return false;
    }

    private function verificaAnulacionActivaAbc($offset, $setup)
    {
        $flSigueAnulacion = true;
        if ($setup == 'BAJISTA')
        {
            if ($this->datas[$offset]['low'] < $this->datas[$this->offsetCAbc]['provMin'])
            {
                $this->datas[$offset]['entrada'] .= " Desactiva anulacion ABC por low menor a minimo C ";
                $flSigueAnulacion = false;
            }
            if ($this->datas[$offset]['high'] > $this->datas[$this->offsetBAbc]['max'])
            {
                $this->datas[$offset]['entrada'] .= " Desactiva anulacion ABC por high mayor a maximo B ";
                $flSigueAnulacion = false;
            }
        }
        else
        {
            if ($this->datas[$offset]['high'] > $this->datas[$this->offsetCAbc]['provMax'])
            {
                $this->datas[$offset]['entrada'] .= " Desactiva anulacion ABC por high mayor a maximo C ";
                $flSigueAnulacion = false;
            }
            if ($this->datas[$offset]['low'] < $this->datas[$this->offsetBAbc]['min'])
            {
                $this->datas[$offset]['entrada'] .= " Desactiva anulacion ABC por low menor a minimo B ";
                $flSigueAnulacion = false;
            }
        }
        $barras = abs($this->offsetO-$this->offsetU) * 1.05;

        //if ($offset == 3169)
            //dd($barras.' '.$this->offsetO.' '.$this->offsetU.' '.$this->offsetAbc);

        // Chequea ultima condicion para levantar anulacion activa ABC
        if ($flSigueAnulacion)
        {
            if ($offset >= $barras + $this->offsetAbc + 1)
            {
                $this->datas[$offset]['entrada'] .= " Desactiva anulacion ABC por tiempo ".$barras;
                $flSigueAnulacion = false;
            }
        }
        return $flSigueAnulacion;
    }

    private function verificaAnulacionActivaAbCd($offset, $setup)
    {
        $flSigueAnulacion = true;
        if ($setup == 'BAJISTA')
        {
            if ($this->datas[$offset]['low'] < $this->datas[$this->offsetD]['provMin'])
            {
                $this->datas[$offset]['entrada'] .= " Desactiva anulacion ABCD por low menor a minimo D ";
                $flSigueAnulacion = false;
            }
            if ($this->datas[$offset]['high'] > $this->datas[$this->offsetCAbCd]['max'])
            {
                $this->datas[$offset]['entrada'] .= " Desactiva anulacion ABCD por high mayor a maximo C ";
                $flSigueAnulacion = false;
            }

            if ($this->datas[$offset]['provMin'] > 0)
            {
                $retroceso2 = abs($this->datas[$this->offsetCAbCd]['max'] - $this->datas[$offset]['provMin']) / 
                            abs($this->datas[$this->offsetBAbCd]['min'] - $this->datas[$this->offsetCAbCd]['max']);
    
                if ($retroceso2 > 2.618)
                {
                    $this->datas[$offset]['entrada'] .= " Desactiva anulacion ABCD por cd/bc mayor a 2.618";
                    $flSigueAnulacion = false;
                }
            }
        }
        else
        {
            if ($this->datas[$offset]['low'] < $this->datas[$this->offsetCAbCd]['min'])
            {
                $this->datas[$offset]['entrada'] .= " Desactiva anulacion ABCD por low menor a minimo C ";
                $flSigueAnulacion = false;
            }
            if ($this->datas[$offset]['high'] > $this->datas[$this->offsetD]['provMax'])
            {
                $this->datas[$offset]['entrada'] .= " Desactiva anulacion ABCD por low menor a maximo D ";
                $flSigueAnulacion = false;
            }

            if ($this->datas[$offset]['provMax'] > 0)
            {
				if (abs($this->datas[$this->offsetBAbCd]['max'] -
				        $this->datas[$this->offsetCAbCd]['min']) != 0)
                	$retroceso2 = abs($this->datas[$this->offsetCAbCd]['min'] - 
                                $this->datas[$offset]['provMax']) / 
                                abs($this->datas[$this->offsetBAbCd]['max'] - 
                                $this->datas[$this->offsetCAbCd]['min']);
				else
					$retroceso2 = 0.;
    
                if ($retroceso2 > 2.618)
                {
                    $this->datas[$offset]['entrada'] .= " Desactiva anulacion ABCD por cd/bc mayor a 2.618";
                    $flSigueAnulacion = false;
                }
            }
        }
        $barras = abs($this->offsetAAbCd-$this->offsetBAbCd) + abs($this->offsetBAbCd-$this->offsetCAbCd) + 
                    abs($this->offsetCAbCd-$this->offsetD);

        // Chequea ultima condicion para levantar anulacion activa ABC\d
        if ($flSigueAnulacion)
        {
            if ($offset >= $barras + $this->offsetAbCd + 1)
            {
                $this->datas[$offset]['entrada'] .= " Desactiva anulacion ABCD por tiempo ".$barras;
                $flSigueAnulacion = false;
            }
        }

        return $flSigueAnulacion;
    }

    private function verificaAnulacionActiva3Drives($offset, $setup)
    {
        $flSigueAnulacion = true;
        if ($setup == 'BAJISTA')
        {
        }
        else
        {
            $this->datas[$offset]['entrada'] .= " Controla anulacion candidatos bajistas minimo E ".
                                            $this->datas[$this->offsetMinimoE]['low']." maximo D ".
                                            $this->datas[$this->offsetMaximoD]['high'];
            if ($this->datas[$offset]['low'] < $this->datas[$this->offsetMinimoE]['low'])
            {
                $this->datas[$offset]['entrada'] .= " Desactiva anulacion minimo E ";
                $flSigueAnulacion = false;
            }

            if ($this->datas[$offset]['high'] > $this->datas[$this->offsetMaximoD]['high'])
            {
                $this->datas[$offset]['entrada'] .= " Desactiva anulacion maximo D ";                                        
                $flSigueAnulacion = false;
            }
        }

        // Chequea ultima condicion para activar la anulacion
        if ($flSigueAnulacion)
        {
            // Calcula tiempo
            $tiempoSwing = ($this->datas[$this->offsetMinimoA]['swingBars'] +
                            $this->datas[$this->offsetMaximoB]['swingBars'] +
                            $this->datas[$this->offsetMinimoC]['swingBars'] +
                            $this->datas[$this->offsetMaximoD]['swingBars'] +
                            $this->datas[$this->offsetMinimoE]['swingBars']);

            $barrasDesde3Drives = $this->offset3Drives + $tiempoSwing;

            $this->datas[$offset]['entrada'] .= " tiempo ".$tiempoSwing." offset ".$offset." Barras ".$barrasDesde3Drives;
            if ($offset > $barrasDesde3Drives)
            {
                $this->datas[$offset]['entrada'] .= " Desactiva anulacion tiempo ".$barrasDesde3Drives.' '.$tiempoSwing." ";
                $flSigueAnulacion = false;
            }
        }
        return $flSigueAnulacion;
    }

    private function verificaAnulacionActivaShark($offset, $setup)
    {
        $flSigueAnulacion = true;
        if ($setup == 'BAJISTA')
        {
        }
        else
        {
            $this->datas[$offset]['entrada'] .= " Controla anulacion candidatos bajistas minimo D ".
                                            $this->datas[$this->offsetMinimoD]['low']." maximo B ".
                                            $this->datas[$this->offsetMaximoB]['high'];
            if ($this->datas[$offset]['low'] < $this->datas[$this->offsetMinimoD]['low'])
            {
                $this->datas[$offset]['entrada'] .= " Desactiva anulacion minimo D ";
                $flSigueAnulacion = false;
            }

            if ($this->datas[$offset]['high'] > $this->datas[$this->offsetMaximoB]['high'])
            {
                $this->datas[$offset]['entrada'] .= " Desactiva anulacion maximo B ";                                        
                $flSigueAnulacion = false;
            }
        }

        // Chequea ultima condicion para activar la anulacion
        if ($flSigueAnulacion)
        {
            // Calcula tiempo
            $tiempoSwing = ($this->datas[$this->offsetMinimoA]['swingBars'] * 2);

            $barrasDesdeShark = $this->offsetShark + $tiempoSwing;

            $this->datas[$offset]['entrada'] .= " tiempo ".$tiempoSwing." offset ".$offset.
                                                " Barras ".$barrasDesdeShark;
            if ($offset > $barrasDesdeShark)
            {
                $this->datas[$offset]['entrada'] .= " Desactiva anulacion tiempo ".$barrasDesdeShark.' '.$tiempoSwing." ";
                $flSigueAnulacion = false;
            }
        }
        return $flSigueAnulacion;
    }

    private function verificaAnulacionActivaW4($offset, $setup)
    {
        $flSigueAnulacion = true;
        if ($setup == 'ALCISTA')
        {
            if ($this->datas[$offset]['low'] < $this->datas[$this->offsetMinimoTW4]['provMin'])
            {
                $this->datas[$offset]['entrada'] .= " Desactiva anulacion minimo 3 ";
                $flSigueAnulacion = false;
            }

            if ($this->datas[$offset]['high'] > $this->datas[$this->offsetMaximoCW4]['provMax'])
            {
                $this->datas[$offset]['entrada'] .= " Desactiva anulacion maximo 4 ". 
                $this->datas[$offset]['high'].' '.$this->datas[$this->offsetMaximoCW4]['max'];                                        
                $flSigueAnulacion = false;
            }
        }
        else
        {
            if ($this->datas[$offset]['low'] < $this->datas[$this->offsetMinimoCW4]['provMin'])
            {
                $this->datas[$offset]['entrada'] .= " Desactiva anulacion minimo 4 ";
                $flSigueAnulacion = false;
            }

            if ($this->datas[$offset]['high'] > $this->datas[$this->offsetMaximoTW4]['provMax'])
            {
                $this->datas[$offset]['entrada'] .= " Desactiva anulacion maximo 3 ";                                        
                $flSigueAnulacion = false;
            }
        }

        // Chequea ultima condicion para activar la anulacion
        if ($flSigueAnulacion)
        {
            // Calcula tiempo
            $barrasOU = abs($this->offsetMaximoOW4 - $this->offsetMinimoUW4) * 2;

            $barrasDesdeW4 = abs($this->offsetW4 - $offset);

            $this->datas[$offset]['entrada'] .= " tiempo 01 offset ".$offset.
                                                " Barras ".$barrasDesdeW4;
            if ($barrasDesdeW4 > $barrasOU)
            {
                $this->datas[$offset]['entrada'] .= " Desactiva anulacion tiempo desde W4 ".$barrasDesdeW4.
                                                    " Barras 01 * 2".$barrasOU;
                $flSigueAnulacion = false;
            }
        }
        return $flSigueAnulacion;
    }

    private function verificaAnulacionActivaSP($offset, $setup)
    {
        $flSigueAnulacion = true;
        if ($setup == 'ALCISTA')
        {
            $this->datas[$offset]['entrada'] .= " Controla anulacion candidatos bajistas minimo B ".
                                            $this->datas[$this->offsetMinimoB]['low']." maximo A ".
                                            $this->datas[$this->offsetMaximoA]['high'];
            if ($this->datas[$offset]['low'] < $this->datas[$this->offsetMinimoB]['low'])
            {
                $this->datas[$offset]['entrada'] .= " Desactiva anulacion minimo B ";
                $flSigueAnulacion = false;
            }

            if ($this->datas[$offset]['high'] > $this->datas[$this->offsetMaximoA]['high'])
            {
                $this->datas[$offset]['entrada'] .= " Desactiva anulacion maximo A ";                                        
                $flSigueAnulacion = false;
            }
        }
        else
        {
            $this->datas[$offset]['entrada'] .= " Controla anulacion candidatos alcistas maximo B ".
                                            $this->datas[$this->offsetMaximoB]['high']." minimo A ".
                                            $this->datas[$this->offsetMinimoA]['low'];
            if ($this->datas[$offset]['low'] < $this->datas[$this->offsetMinimoA]['low'])
            {
                $this->datas[$offset]['entrada'] .= " Desactiva anulacion minimo A ";
                $flSigueAnulacion = false;
            }

            if ($this->datas[$offset]['high'] > $this->datas[$this->offsetMaximoB]['high'])
            {
                $this->datas[$offset]['entrada'] .= " Desactiva anulacion maximo B ";                                        
                $flSigueAnulacion = false;
            }
        }

        // Chequea ultima condicion para activar la anulacion
        if ($flSigueAnulacion)
        {
            // Calcula tiempo
            $barrasDesdeSp = $this->offsetSp + 64;

            $this->datas[$offset]['entrada'] .= " tiempo 64 offset ".$offset.
                                                " Barras ".$barrasDesdeSp;
            if ($offset > $barrasDesdeSp)
            {
                $this->datas[$offset]['entrada'] .= " Desactiva anulacion tiempo ".$barrasDesdeSp;
                $flSigueAnulacion = false;
            }
        }
        return $flSigueAnulacion;
    }

    private function lindev($item, $precioTipico, $largo, &$retorno)
    {
        for ($ii = $item - $largo, $cant = 0, $x = 0; $ii < $item - 1; $ii++)
        {
            $x += $this->datas[$ii]['precioTipico'];
            $cant++;
        }
        $x += $precioTipico;
        $cant++;
        $x /= ($cant > 0 ? $cant : 1);
        
        for ($ii = $item - $largo, $y = 0; $ii < $item-1; $ii++)
            $y += ABS($x - $this->datas[$ii]['precioTipico']);
        $y += ABS($x - $precioTipico);
        
        $retorno = $y / ($cant > 0 ? $cant : 1);
    }

    private function promedio($base, $item, $indice, $mm)
    {
        for ($ii = $item - $mm, $cant = 0, $acum = 0; $ii < $item - 1; $ii++)
        {
            $acum += $this->datas[$ii][$indice];
            $cant++;
        }
		// Agrega el item actual
        $acum += $base;
		$cant++;

        return $acum / ($cant > 0 ? $cant : 1);
    }

    private function acumulado($base, $item, $indice, $mm)
    {
        for ($ii = $item - $mm, $acum = 0; $ii < $item - 1; $ii++)
            $acum += $this->datas[$ii][$indice];

        // Agrega el item actual
        $acum += $base;

        return $acum;
    }

    private function calculaDatosDiaAnterior($fecha, &$open, &$close, &$low, &$high)
    {
        if ($fecha != $this->fechaUltimaLectura)
        {
            $count = 0;
            $fechaAnt = date("d-m-Y",strtotime($fecha."- 2 days"));    
            $fechaAct = date("d-m-Y",strtotime($fecha."- 1 days"));    
            $desde_fecha = strtotime($fechaAnt.' '.'19:00')*1000;
            $hasta_fecha = strtotime($fechaAct.' '.'18:00')*1000;

            do
            {
                $count++;

                $this->dataAnterior = DB::connection('trade')->table('trade.lecturas')
                    ->select('fechaChar as fechastr',
                            'chartTime as fecha',
                            'openPrice as open',
                            'highPrice as high',
                            'lowPrice as low',
                            'closePrice as close',
                            'volume')
                    ->where('especie', $this->especie)
                    ->whereBetween('chartTime', [$desde_fecha, $hasta_fecha])
                    ->get();

                if (count($this->dataAnterior) == 0)
                {
                    $fechaAnt = date("d-m-Y",strtotime($fechaAnt."- 1 days"));    
                    $fechaAct = date("d-m-Y",strtotime($fechaAct."- 1 days"));    
                    $desde_fecha = strtotime($fechaAnt.' '.'19:00')*1000;
                    $hasta_fecha = strtotime($fechaAct.' '.'18:00')*1000;
                }
            } while (count($this->dataAnterior) == 0 && $count < 10);
            $this->fechaUltimaLectura = $fecha;
        }
     
        $open = $close = $low = $high = 0;
        foreach ($this->dataAnterior as $lectura)
        {
            if ($open == 0)
            {
                $open = $lectura->open;
                $low = $lectura->low;
                $high = $lectura->high;
            }

            $close = $lectura->close;

            if ($lectura->low < $low)
                $low = $lectura->low;

            if ($lectura->high > $high)
                $high = $lectura->high;
        }
    }

    // Arma tabla para envio a impresion
    private function armaTabla($fechaStr, $fecha, $horaInicio, $open, $close, $low, $high, $totVolume, $ewo,
                                $bandaSup, $bandaInf, $w4Up1, $w4Up2, $w4Dw1, $w4Dw2,
                                $rfLim, $rfeExt, $rfeInt, $rfiExt, $rfiInt, $pp1, $poc, $pp2,
                                $sfiInt, $sfiExt, $sfeInt, $sfeExt, $sfLim, $base, $smac, $smal, $tmp1, $tmp2, $d1, $d2,
                                $condicional, $d3, $k, $VMA, 
                                $trueRange, $averageTrueRange, $cciaTRadj, $obb, $osb, $atr21, $atrmstdev,
                                $regimenVolatilidad, $stdevHi, $stdevLo, $stdevH1, $h1, $h1Exp, $stdevH2,
                                $h2, $h2Exp, $stdevL1, $l1, $l1Exp, $stdevL2, $l2, $l2Exp, $rvih0, $rvih, $rbil0,
                                $rvil, $rviSimple, $rviExp, $x, $xCuadrado, $a, $b, $yaxb, $inertia,
                                $precioTipico, $SMACCI, $auxCCI, $blanco1, $blanco2,
                                $CCI, $SMAXTL, $auxXTL, $CCIXTL, $estado, $rango, $TQRVerde, $stopTQRVerde, $tgtTQRVerde,
                                $TQRRojo, $stopTQRRojo, $tgtTQRRojo)
    {
        $this->datas[] = ['fechastr'=>$fechaStr, 'fecha'=>$fecha, 'horainicio'=>$horaInicio,
            'open'=>$open, 'close'=>$close,
            'low'=>$low,'high'=>$high,'volume'=>$totVolume,
            'ewo'=>$ewo,
            'bandaSup'=>$bandaSup,
            'bandaInf'=>$bandaInf,
            'w4Up1'=>$w4Up1,
            'w4Up2'=>$w4Up2,
            'w4Dw1'=>$w4Dw1,
            'w4Dw2'=>$w4Dw2,
            'rfLim'=>$rfLim,
            'rfeExt'=>$rfeExt,
            'rfeInt'=>$rfeInt,
            'rfiExt'=>$rfiExt,
            'rfiInt'=>$rfiInt,
            'pp1'=>$pp1,
            'poc'=>$poc,
            'pp2'=>$pp2,
            'sfiInt'=>$sfiInt,
            'sfiExt'=>$sfiExt,
            'sfeInt'=>$sfeInt,
            'sfeExt'=>$sfeExt,
            'sfLim'=>$sfLim,
            'base'=>$base,
            'smac'=>$smac,
            'smal'=>$smal,
            'tmp1'=>$tmp1,
            'tmp2'=>$tmp2,
            'd1'=>$d1,
            'd2'=>$d2,
            'condicional'=>$condicional,
            'd3'=>$d3,
            'k'=>$k,
            'VMA'=>$VMA,
            'trueRange'=>$trueRange,
            'averageTrueRange'=>$averageTrueRange,
            'cciaTRadj'=>$cciaTRadj,
            'obb'=>$obb,
            'osb'=>$osb,
            'atr21'=>$atr21,
            'atrmstdev'=>$atrmstdev,
            'regimenVolatilidad'=>$regimenVolatilidad,
            'stdevHi'=>$stdevHi,
            'stdevLo'=>$stdevLo,
            'stdevH1'=>$stdevH1,
            'h1'=>$h1,
            'h1Exp'=>$h1Exp,
            'stdevH2'=>$stdevH2,
            'h2'=>$h2,
            'h2Exp'=>$h2Exp,
            'stdevL1'=>$stdevL1,
            'l1'=>$l1,
            'l1Exp'=>$l1Exp,
            'stdevL2'=>$stdevL2,
            'l2'=>$l2,
            'l2Exp'=>$l2Exp,
            'rvih0'=>$rvih0,
            'rvih'=>$rvih,
            'rvil0'=>$rbil0,
            'rvil'=>$rvil,
            'rviSimple'=>$rviSimple,
            'rviExp'=>$rviExp,
            'x'=>$x,
            'xCuadrado'=>$xCuadrado,
            'a'=>$a,
            'b'=>$b,
            'yaxb'=>$yaxb,
            'inertia'=>$inertia,
            'precioTipico'=>$precioTipico,
            'SMACCI'=>$SMACCI,
            'auxCCI'=>$auxCCI,
            'blanco1'=>$blanco1,
            'blanco2'=>$blanco2,
            'CCI'=>$CCI,
            'SMAXTL'=>$SMAXTL,
            'auxXTL'=>$auxXTL,
            'CCIXTL'=>$CCIXTL,
            'estado'=>$estado,
            'rango'=>$rango,
            'TQRVerde'=>$TQRVerde,
            'stopTQRVerde'=>$stopTQRVerde,
            'tgtTQRVerde'=>$tgtTQRVerde,
            'TQRRojo'=>$TQRRojo,
            'stopTQRRojo'=>$stopTQRRojo,
            'tgtTQRRojo'=>$tgtTQRRojo,
            'provMin'=>0,
            'provMax'=>0,
            'provRet'=>0,
            'barras'=>0,
            'min'=>0,
            'max'=>0,
            'tendencia'=>0,
            'trendBars'=>0,
            'swingBars'=>0,
            'swingBarsPrev'=>0,
            'pivot0'=>0,
            'pivot1'=>0,
            'pivot2'=>0,
            'pivot3'=>0,
            'pivot4'=>0,
            'retroceso'=>0,
            'extT1'=>0,
            'extT2'=>0,
            'extT3'=>0,
            'extT4'=>0,
            'volumen'=>0,
            'volumenPorSwing'=>0,
            'setup'=>'',
            't1Hit'=>0,
            't2Hit'=>0,
            't3Hit'=>0,
            't4Hit'=>0,
            'filtroActivo'=>'',
            'entrada'=>'',
            'e' => '',
            'stoploss' => '',
            't1' => '',
            't2' => '',
            't3' => '',
            't4' => '',
            'p' => '',
            'evento' => '',
            'zona' => '',
            'senial' => '',
            'outbound' => false,
            'nuevo' => true
        ];
    }

    private function armaTablaOperaciones($fecha, $idTrade, $direccion, $numeroContratos, $desdeHora,
                                        $valorEntrada, $stopLoss, $t1, $t2, $t3, $t4, 
                                        $rrr, $swingBars, $contraSwingBars,
                                        $rv, $retroceso, $riesgoTicks, $retornoTicks, $precioCierre, 
                                        $mpc, $mpf, $operacion, $i)
    {
        //if ($i == 4767)
            //dd($operacion);
        if ($operacion == 'CIERRA SL' || $operacion == 'CIERRA TGT' || $operacion == 'CIERRA NM')
        {
            for ($j = 0, $off = -1; $j < count($this->operaciones) && $off != $j; $j++)
            {
                if ($this->operaciones[$j]['idTrade'] == $idTrade)
                    $off = $j;
            }
            if ($off == -1)
                return;
            $totalTicks = $this->operaciones[$off]['acumTicks'];
            $plPesos = 0;

            if ($this->operaciones[$off]['operacion'] == ' ')
                $this->operaciones[$off]['operacion'] = $operacion;

            switch($operacion)
            {
            case 'CIERRA SL':
            case 'CIERRA NM':
                for ($contrato = $this->totalContratos - $numeroContratos + 1; $contrato <= $this->totalContratos; $contrato++)
                {
                    $lblPrecio = 'precioCierre'.$contrato;
                    $lblHora = 'horaCierre'.$contrato;
                    $this->operaciones[$off][$lblPrecio] = $precioCierre;
                    $this->operaciones[$off][$lblHora] = $desdeHora;
                    $totalTicks += ($this->operaciones[$off][$lblPrecio] - $this->operaciones[$off]['valorEntrada']);
                }
                break;
            case 'CIERRA TGT':
                for ($contrato = 1; $contrato <= 4; $contrato++)
                {
                    $lblPrecio = 'precioCierre'.$contrato;
                    $lblHora = 'horaCierre'.$contrato;

                    if ($this->operaciones[$off][$lblHora] == ' ')
                    {
                        $this->operaciones[$off][$lblPrecio] = $precioCierre;
                        $this->operaciones[$off][$lblHora] = $desdeHora;
                        $totalTicks += ($this->operaciones[$off][$lblPrecio] - $this->operaciones[$off]['valorEntrada']);  
                        break;
                    }
                }
                break;
            }

            $this->operaciones[$off]['acumTicks'] = $totalTicks;

            if ($this->operaciones[$off]['direccion'] == -1)
                $this->operaciones[$off]['totalTicks'] = $totalTicks / $this->ticker * -1;
            else
                $this->operaciones[$off]['totalTicks'] = $totalTicks / $this->ticker;
            $this->operaciones[$off]['plPesos'] = ($this->operaciones[$off]['totalTicks'] * $this->valorTicker) - 
                                                ($this->totalContratos * $this->comision * 2);

            if ($operacion == 'CIERRA SL')
                $evento = 'SL';
            else    
                $evento = 'TGT';
            $mpc = $this->calculaMpc($this->operaciones[$off]['i'], $i, $this->operaciones[$off]['direccion'], 
                $precioCierre);
            $mpf = $this->calculaMpf($this->operaciones[$off]['i'], $i, $this->operaciones[$off]['direccion'], 
                $precioCierre);

            if ($mpc > $this->operaciones[$off]['stopLoss'])
                $mpc = $this->operaciones[$off]['stopLoss'];

            $this->operaciones[$off]['mpc'] = $mpc;
            $this->operaciones[$off]['mpf'] = $mpf;

            // Si es batch envia correo de apertura de posicion
            if ($this->flBatch)
            {
                //$receivers = "sergiogranucci@gmail.com";

                //Mail::to($receivers)->send(new Trade($this->operaciones[$off]));
                Log::info($this->operaciones[$off]);
            }
        }
        else
        {
            // Controla trade con minimo y maximo repetido
            $this->buscaUltimoMaximo($i, $offMax, $maximo);
            $this->buscaUltimoMinimo($i, $offMin, $minimo);

            if ($idTrade > 1)
            {
                if ($minimo == $this->operaciones[$idTrade-2]['min'] &&
                    $maximo == $this->operaciones[$idTrade-2]['max'] &&
                    $offMin == $this->operaciones[$idTrade-2]['offmin'] &&
                    $offMax == $this->operaciones[$idTrade-2]['offmax'])
                    return 'Error';
            }

            $fechaLectura = date('Y-m-d', ceil($fecha/1000));
            $dataOperacion = [
                'i' => $i,
                'fecha' => $fechaLectura,
                'idTrade' => $idTrade,
                'direccion' => intval($direccion),
                'numeroContratos' => $numeroContratos,
                'desdeHora' => $desdeHora,
                'valorEntrada' => $valorEntrada,
                'stopLoss' => $stopLoss,
                't1' => $t1,
                't2' => $t2,
                't3' => $t3,
                't4' => $t4,
                'rrr' => $rrr,
                'swingBars' => $swingBars,
                'contraSwingBars' => $contraSwingBars,
                'rv' => $rv,
                'retroceso' => $retroceso,
                'riesgoTicks' => $riesgoTicks,
                'retornoTicks' => $retornoTicks,
                'precioCierre1' => 0,
                'horaCierre1' => ' ',
                'precioCierre2' => 0,
                'horaCierre2' => ' ',
                'precioCierre3' => 0,
                'horaCierre3' => ' ',
                'precioCierre4' => 0,
                'horaCierre4' => ' ',
                'totalTicks' => 0,
                'acumTicks' => 0,
                'plPesos' => 0,
                'operacion' => ' ',
                'mpc' => $mpc,
                'mpf' => $mpf,
                'min' => $minimo,
                'max' => $maximo,
                'offmin' => $offMin,
                'offmax' => $offMax
            ];
            $this->operaciones[] = $dataOperacion;

            //if ($idTrade == 7)
              //  dd($this->operaciones);

            // Si es batch envia correo de apertura de posicion
            if ($this->flBatch)
            {
                //$receivers = "sergiogranucci@gmail.com";

                //Mail::to($receivers)->send(new Trade($dataOperacion));
                Log::info($dataOperacion);
            }
        }
        return 0;
    }

    private function calculaProfitAndLoss($idTrade, $numeroContratos, $precioCierre)
    {
        for ($j = 0, $off = -1; $j < count($this->operaciones) && $off != $j; $j++)
        {
            if ($this->operaciones[$j]['idTrade'] == $idTrade)
                $off = $j;
        }
        if ($off == -1)
            return 0;
        
        $plPesos = 0;

        $numeroPrecio = $this->totalContratos - $numeroContratos + 1;
        $label = 'precioCierre'.$numeroPrecio;
        $this->operaciones[$off][$label] = $precioCierre;
        
        $totalTicks = 0;
        for ($i = 1; $i <= 4; $i++)
        {
            $label = 'precioCierre'.$i;

            if ($this->operaciones[$off][$label] != 0)
            {
                $totalTicks += ($this->operaciones[$off][$label] - $this->operaciones[$off]['valorEntrada']);
            }
        }
        
        if ($this->operaciones[$off]['direccion'] == -1)
            $this->operaciones[$off]['totalTicks'] = $totalTicks / $this->ticker * -1;
        else
            $this->operaciones[$off]['totalTicks'] = $totalTicks / $this->ticker;

        $plPesos = $this->operaciones[$off]['totalTicks'] * $this->valorTicker;

        return $plPesos;
    }

	public function parametros($desdefecha, $hastafecha, $desdehora, $hastahora, $especie, $calculobase, 
                                $mmcorta, $mmlarga, $compresion, $largovma, $largocci, $largoxtl,
							    $umbralxtl, $calculobase_enum, $swingSize)
	{
        $this->desdeFecha = $desdefecha;
		$this->hastaFecha = $hastafecha;
		$this->desdeHora = $desdehora;
		$this->hastaHora = $hastahora;
        $this->especie = $especie;
		$this->compresion = $compresion;
        $this->calculoBase = $calculobase;
        $this->calculoBase_enum = $calculobase_enum;
        $this->mmCorta = $mmcorta;
        $this->mmLarga = $mmlarga;
        $this->largoVMA = $largovma;
        $this->largoCCI = $largocci;
        $this->largoXTL = $largoxtl;
        $this->umbralXTL = $umbralxtl;
        $this->swingSize = $swingSize;

        switch($compresion)
        {
        case 1:
            $this->compresiontxt = "1 minuto";
            $this->factorCompresion = 1;
            break;
        case 2:
            $this->compresiontxt = "5 minutos";
            $this->factorCompresion = 5;
            break;
        case 3:
            $this->compresiontxt = "15 minutos";
            $this->factorCompresion = 15;
            break;
        case 4:
            $this->compresiontxt = "1 hora";
            $this->factorCompresion = 60;
            break;
        case 5:
            $this->compresiontxt = "1 día";
            $this->factorCompresion = 3600;
            break;
        }

		return $this;
	}

    // Procesa datos para generar ordenes on-line

	public function generaDatosOrdenes($data, $especie, $calculobase, 
                        $mmcorta, $mmlarga, $compresion, $largovma, $largocci, 
                        $largoxtl, $umbralxtl, $swingSize, $filtroSetup, $factorCompresion)
	{
        $this->especie = $especie;
        $this->calculoBase = $calculobase;
        $this->mmCorta = $mmcorta;
        $this->mmLarga = $mmlarga;
        $this->compresion = $compresion;
        $this->largoVMA = $largovma;
        $this->largoCCI = $largocci;
        $this->largoXTL = $largoxtl;
        $this->umbralXTL = $umbralxtl;
        $this->swingSize = $swingSize;
        $this->filtroSetup = $filtroSetup;
        $this->factorCompresion = $factorCompresion;
        $this->totalContratos = 4;
        $this->ticker = 0.25;
        $this->valorTicker = 12.5;
        $this->administracionPosicion = 'B';
        $this->tiempo = 30;
        $this->k2 = 2 / ($this->mmCorta + $this->mmLarga);
        $this->k1 = 1 - $this->k2;
        $this->flBatch = true;
        $this->flSinFiltros = false;
        // Saltea fechas repetidas
        if ($this->acumFechaLectura != "01-01-2001")
        {
            if ($data->fechalectura == $this->acumFechaLectura)
                return;
        }
        $this->acumFechaLectura = $data->fechalectura;
        $auxFecha = Carbon::parse($data->fechalectura);
        $this->acumMinutoLectura = $auxFecha->minute;
        // Verifica arrancar en divisor del factor de compresion
        if ($this->factorCompresion > 1 && !$this->acumFlEmpezoRango)
        {
            if ($this->acumMinutoLectura % $this->factorCompresion == 0)
                $this->acumFlEmpezoRango = true;
        }
        if ($this->acumFlEmpezoRango)
        {
            // Corte Si es por dia
            $flCorte = false;
            if ($this->factorCompresion == 3600)
            {
                $horaLect = date('H:i', ceil($data->fecha/1000));
                // Corta el dia a las 17:59
                if ($horaLect >= '17:59' && $horaLect < '19:00')
                    $flCorte = true;
            }
            else // Corte si es por minutos
            {
                if ($this->acumMinutoLectura % $this->factorCompresion == 0 && $this->acumFecha != "01-01-2001")
                    $flCorte = true;
            }
            if ($flCorte)
            {
                switch($this->calculoBase)
                {
                    case 1: // HL2
                        $base = ($this->acumHigh + $this->acumLow) / 2;
                        break;
                    case 2: // HLC3
                        $base = ($this->acumHigh + $this->acumLow + $this->acumClose) / 3;
                        break;
                    case 3: // OHLC4
                        $base = ($this->acumOpen + $this->acumHigh + $this->acumLow + $this->acumClose) / 4;
                        break;
                }

                $this->acumItem++;
                $ewo = $bandaSup = $bandaInf = 0;
                $smac = $smal = 0;
                $w4Up1 = $w4Up2 = $w4Dw1 = $w4Dw2 = 0;   
                $rfLim = $rfeExt = $rfeInt = $rfiExt = $rfiInt = $pp1 = $poc = $pp2 = 0;
                $sfiInt = $sfiExt = $sfeInt = $sfeExt = $sfLim = $smac = $smal = $tmp1 = $tmp2 = 0;
                $d1 = $d2 = 0;
                $condicional = $d3 = $k = $VMA = $precioTipico = $SMACCI = $auxCCI = $blanco1 = $blanco2 = 0;
                $CCI = $SMAXTL = $auxXTL = $CCIXTL = $estado = $rango = $TQRVerde = $stopTQRVerde = 0;
                $tgtTQRVerde = $TQRRojo = $stopTQRRojo = $tgtTQRRojo = 0;
                $trueRange = $averageTrueRange = $cciaTRadj = $obb = $osb = $atr21 = $atrmstdev = 0;
                $regimenVolatilidad = $stdevHi = $stdevLo = $stdevH1 = $h1 = $h1Exp = $stdevH2 = 0;
                $h2 = $h2Exp = $stdevL1 = $l1 = $liExp = $stdevL2 = $l2 = $l2Exp = $rvih0 = $rvih = $rbil0 = 0;
                $rvil = $rviSimple = $rviExp = $x = $xCuadrado = $a = $b = $yaxb = $inertia = 0;                     

                if ($this->acumItem > 1)
                {
                    if ($this->datas[$this->acumItem-2]['nuevo'])
                    {
                        if ($this->acumItem >= $this->mmLarga + 1)
                            $this->calculaEWO($this->acumItem, $base, $smac, $smal, $ewo, $bandaSup, $bandaInf,
                                $w4Up1, $w4Up2, $w4Dw1, $w4Dw2);

                        // Calcula pivot de fibonacci
                        $this->calculaFibonacci($this->acumFechaInicioRango, $rfLim, $rfeExt, $rfeInt, $rfiExt, $rfiInt, $pp1,
                            $poc, $pp2, $sfiInt, $sfiExt, $sfeInt, $sfeExt, $sfLim, $base);

                        // Calcula CCI
                        $this->CalculaCCI($this->acumItem, $this->acumHigh, $this->acumLow, $this->acumClose, $precioTipico, 
                            $SMACCI, $auxCCI, $blanco1, $blanco2, $CCI);
                    }
                }
                if ($this->acumItem > 2 && $this->datas[$this->acumItem-2]['nuevo'] && $this->datas[$this->acumItem-3]['nuevo'])
                    $this->calculaNuevosIndicadores($this->acumItem-1);

                // Arma tabla 
                $this->armaTabla($this->acumFechaLectura, $this->acumFecha, $this->acumHoraInicio, 
                                $this->acumOpen, $this->acumClose, $this->acumLow, $this->acumHigh, 
                                $this->acumTotVolume, $ewo,
                                $bandaSup, $bandaInf, $w4Up1, $w4Up2, $w4Dw1, $w4Dw2,
                                $rfLim, $rfeExt, $rfeInt, $rfiExt, $rfiInt, $pp1, $poc, $pp2,
                                $sfiInt, $sfiExt, $sfeInt, $sfeExt, $sfLim, $base, $smac, $smal, $tmp1, $tmp2, 
                                $d1, $d2,
                                $condicional, $d3, $k, $VMA, 
                                $trueRange, $averageTrueRange, $cciaTRadj, $obb, $osb, $atr21, $atrmstdev,
                                $regimenVolatilidad, $stdevHi, $stdevLo, $stdevH1, $h1, $h1Exp, $stdevH2,
                                $h2, $h2Exp, $stdevL1, $l1, $liExp, $stdevL2, $l2, $l2Exp, $rvih0, $rvih, $rbil0,
                                $rvil, $rviSimple, $rviExp, $x, $xCuadrado, $a, $b, $yaxb, $inertia,
                                $precioTipico, $SMACCI, $auxCCI, $blanco1, $blanco2,
                                $CCI, $SMAXTL, $auxXTL, $CCIXTL, $estado, $rango, $TQRVerde, $stopTQRVerde, 
                                $tgtTQRVerde, $TQRRojo, $stopTQRRojo, $tgtTQRRojo);

                if ($this->acumItem > ($this->swingSize * 2))
                {
                    //$this->calculaPivot();
                    
                    // Calcula volumen por swing y Tgt hit
                    $this->calculaSwingTgtBatch($this->acumItem-2);       
                }    

                // Graba tabla 
                if ($this->acumItem > 500)
                {
                    $this->grabaTablaIndicadores();

                    // Trae ultimos 100 registros
                    $this->cargaIndicadores();
                }
                $this->acumCantLectura = 0;
                $this->acumLow = $this->acumHigh = $this->acumTotVolume = $this->acumOpen = $this->acumClose = 0;
            }

            // Procesa cada registro
            $this->acumFecha = $data->fecha;
            $this->acumTotVolume += $data->volume;
            $this->acumCantLectura++;

            // Si es primer lectura del rango inicia variables
            if ($this->acumCantLectura == 1)
            {
                $this->acumFechaInicioRango = date('Y-m-d H:i', ceil($data->fecha/1000));
                $this->acumHoraInicio = date('H:i:s', ceil($data->fecha/1000));
                $this->acumOpen = $data->open;
                $this->acumLow = $data->low;
                $this->acumHigh = $data->high;
            }
            else
            {
                if ($data->low < $this->acumLow)
                    $this->acumLow = $data->low;
                if ($data->high > $this->acumHigh)
                    $this->acumHigh = $data->high;
            }  
            $this->acumClose = $data->close;
        }
    }

    public function grabaTablaIndicadores()
    {
        for ($i = 0; $i < count($this->datas); $i++)
        {
            if ($this->datas[$i]['nuevo'])
                $this->grabaIndicadores($this->datas[$i]['fechastr'], $this->datas[$i]['horainicio'], 
                                $this->especie, 
                                $this->datas[$i]['fecha'], $this->datas[$i]['open'], $this->datas[$i]['close'], 
                                $this->datas[$i]['low'], $this->datas[$i]['high'], $this->datas[$i]['volume'],
                                $this->datas[$i]['ewo'], $this->datas[$i]['bandaSup'], $this->datas[$i]['bandaInf'], 
                                $this->datas[$i]['w4Up1'], $this->datas[$i]['w4Up2'], $this->datas[$i]['w4Dw1'], $this->datas[$i]['w4Dw2'],
                                $this->datas[$i]['provMin'], $this->datas[$i]['provMax'],
                                $this->datas[$i]['provRet'], $this->datas[$i]['max'], $this->datas[$i]['min'], $this->datas[$i]['swingBarsPrev'], 
                                $this->datas[$i]['barras'],
                                $this->datas[$i]['extT1'], $this->datas[$i]['extT2'], $this->datas[$i]['extT3'], $this->datas[$i]['extT4'], 
                                $this->datas[$i]['setup'], $this->datas[$i]['t1Hit'], $this->datas[$i]['t2Hit'], $this->datas[$i]['t3Hit'], 
                                $this->datas[$i]['t4Hit'],
                                $this->datas[$i]['rfeInt'], $this->datas[$i]['rfeExt'], $this->datas[$i]['rfiInt'], $this->datas[$i]['rfiExt'], 
                                $this->datas[$i]['pp1'], $this->datas[$i]['pp2'], $this->datas[$i]['sfiExt'], $this->datas[$i]['sfiInt'], 
                                $this->datas[$i]['sfeExt'], $this->datas[$i]['sfeInt'], 
                                $this->datas[$i]['swingBars'], $this->datas[$i]['precioTipico'], $this->datas[$i]['p'], $this->datas[$i]['evento'], 
                                $this->datas[$i]['t1'], $this->datas[$i]['t2'], $this->datas[$i]['t3'], $this->datas[$i]['t4'],
                                $this->datas[$i]['stoploss'], $this->datas[$i]['entrada'], $this->datas[$i]['e']);
        }
    }

    private function grabaIndicadores($fecha, $hora, $especie, $fechastr, $open, $close, $low, $high, $volume,
                                        $ewo, $bandasup, $bandainf, $w4up1, $w4up2, $w4dw1, $w4dw2, 
                                        $provmin, $provmax,
                        
                                        $provret, $max, $min, $swingbarsprev, $barras,
                                        $extt1, $extt2, $extt3, $extt4, $setup, $t1hit, $t2hit, $t3hit, $t4hit,
                                        $rfeint, $rfeext, $rfiint, $rfiext, $pp1, $pp2, $sfiext, $sfiint, $sfeext, $sfeint, 
                                        $swingbars, $preciotipico, $p, $evento, $t1, $t2, $t3, $t4, $stoploss, $entrada, $e)
    {
        $datas[] = ['fecha' => $fecha, 
                    'hora' => $hora, 
                    'especie' => $especie, 
                    'chartTime' => $fechastr,
                    'open' => $open, 
                    'high' => $high, 
                    'low' => $low, 
                    'close' => $close, 
                    'volume' => $volume,
                    'ewo' => $ewo, 
                    'bandasup' => $bandasup, 
                    'bandainf' => $bandainf, 
                    'w4up1' => $w4up1, 
                    'w4up2' => $w4up2, 
                    'w4dw1' => $w4dw1, 
                    'w4dw2' => $w4dw2,
                    'provmin' => $provmin,
                    'provmax' => $provmax,
                    'provret' => $provret, 
                    'max' => $max, 
                    'min' => $min, 
                    'swingbarsprev' => $swingbarsprev, 
                    'barras' => $barras,
                    'extt1' => $extt1, 
                    'extt2' => $extt2, 
                    'extt3' => $extt3, 
                    'extt4' => $extt4, 
                    'setup' => $setup, 
                    't1hit' => $t1hit, 
                    't2hit' => $t2hit, 
                    't3hit' => $t3hit, 
                    't4hit' => $t4hit,
                    'rfeint' => $rfeint, 
                    'rfeext' => $rfeext, 
                    'rfiint' => $rfiint, 
                    'rfiext' => $rfiext, 
                    'pp1' => $pp1, 
                    'pp2' => $pp2, 
                    'sfiext' => $sfiext, 
                    'sfiint' => $sfiint, 
                    'sfeext' => $sfeext, 
                    'sfeint' => $sfeint, 
                    'swingbars' => $swingbars, 
                    'preciotipico' => $preciotipico, 
                    'p' => $p, 
                    'evento' => $evento, 
                    't1' => $t1,
                    't2' => $t2,
                    't3' => $t3,
                    't4' => $t4,
                    'stoploss' => $stoploss,
                    'entrada' => $entrada,
                    'e' => $e];

        $data = DB::connection('trade')->table('trade.indicadores')->insert($datas);
            
        return $data;
    }

	private function cargaIndicadores()
	{
		$data = DB::connection('trade')->table('trade.indicadores')
				->select('id',
                         'fecha',
                         'especie',
                         'chartTime',
                         'open', 
                         'high', 
                         'low', 
                         'close', 
                         'volume',
                         'ewo', 
                         'bandasup', 
                         'bandainf', 
                         'w4up1', 
                         'w4up2', 
                         'w4dw1', 
                         'w4dw2',
                         'provret', 
                         'provmin',
                         'provmax',
                         'max',
                         'min', 
                         'swingbarsprev', 
                         'barras',
                         'extt1',
                         'extt2', 
                         'extt3', 
                         'extt4', 
                         'setup', 
                         't1hit', 
                         't2hit', 
                         't3hit', 
                         't4hit',
                         'rfeint', 
                         'rfeext', 
                         'rfiint', 
                         'rfiext', 
                         'pp1', 
                         'pp2', 
                         'sfiext', 
                         'sfiint', 
                         'sfeext', 
                         'sfeint', 
                         'swingbars', 
                         'preciotipico',
                         'p', 
                         'evento', 
                         't1',
                         't2',
                         't3',
                         't4',
                         'stoploss',
                         'entrada',
                         'e')
                ->where('especie', $this->especie)
				->orderBy('id', 'desc')
				->take(100)
				->get();

        $this->datas = [];
        $this->acumItem = 0;
		foreach ($data as $indicador)
		{
            $this->acumItem++;
            $horaInicio = date("H:i:s", strtotime($indicador->fecha));

            $this->datas[] = ['fechastr'=>$indicador->fecha, 
                            'fecha'=>$indicador->chartTime, 
                            'horainicio'=>$horaInicio,
                            'open'=>$indicador->open, 'close'=>$indicador->close,
                            'low'=>$indicador->low,'high'=>$indicador->high,'volume'=>$indicador->volume,
                            'ewo'=>$indicador->ewo,
                            'bandaSup'=>$indicador->bandasup,
                            'bandaInf'=>$indicador->bandainf,
                            'w4Up1'=>$indicador->w4up1,
                            'w4Up2'=>$indicador->w4up2,
                            'w4Dw1'=>$indicador->w4dw1,
                            'w4Dw2'=>$indicador->w4dw2,
                            'rfeExt'=>$indicador->rfeext,
                            'rfeInt'=>$indicador->rfeint,
                            'rfiExt'=>$indicador->rfiext,
                            'rfiInt'=>$indicador->rfiint,
                            'pp1'=>$indicador->pp1,
                            'pp2'=>$indicador->pp2,
                            'sfiInt'=>$indicador->sfiint,
                            'sfiExt'=>$indicador->sfiext,
                            'sfeInt'=>$indicador->sfeint,
                            'sfeExt'=>$indicador->sfeext,
                            'sfLim'=>0,
                            'base'=>0,
                            'smac'=>0,
                            'smal'=>0,
                            'tmp1'=>0,
                            'tmp2'=>0,
                            'd1'=>0,
                            'd2'=>0,
                            'condicional'=>0,
                            'd3'=>0,
                            'k'=>0,
                            'VMA'=>0,
                            'precioTipico'=>$indicador->preciotipico,
                            'SMACCI'=>0,
                            'auxCCI'=>0,
                            'blanco1'=>0,
                            'blanco2'=>0,
                            'CCI'=>0,
                            'SMAXTL'=>0,
                            'auxXTL'=>0,
                            'CCIXTL'=>0,
                            'estado'=>0,
                            'rango'=>0,
                            'TQRVerde'=>0,
                            'stopTQRVerde'=>0,
                            'tgtTQRVerde'=>0,
                            'TQRRojo'=>0,
                            'stopTQRRojo'=>0,
                            'tgtTQRRojo'=>0,
                            'provMin'=>$indicador->provmin,
                            'provMax'=>$indicador->provmax,
                            'provRet'=>$indicador->provret,
                            'barras'=>$indicador->barras,
                            'min'=>$indicador->min,
                            'max'=>$indicador->max,
                            'tendencia'=>0,
                            'trendBars'=>0,
                            'swingBars'=>$indicador->swingbars,
                            'swingBarsPrev'=>$indicador->swingbarsprev,
                            'pivot0'=>0,
                            'pivot1'=>0,
                            'pivot2'=>0,
                            'pivot3'=>0,
                            'pivot4'=>0,
                            'retroceso'=>0,
                            'extT1'=>$indicador->extt1,
                            'extT2'=>$indicador->extt2,
                            'extT3'=>$indicador->extt3,
                            'extT4'=>$indicador->extt4,
                            'volumen'=>0,
                            'volumenPorSwing'=>0,
                            'setup'=>$indicador->setup,
                            't1Hit'=>$indicador->t1hit,
                            't2Hit'=>$indicador->t2hit,
                            't3Hit'=>$indicador->t3hit,
                            't4Hit'=>$indicador->t4hit,
                            'entrada'=>$indicador->entrada,
                            'e' => $indicador->e,
                            'stoploss' => $indicador->stoploss,
                            't1' => $indicador->t1,
                            't2' => $indicador->t2,
                            't3' => $indicador->t3,
                            't4' => $indicador->t4,
                            'p' => $indicador->p,
                            'evento' => $indicador->evento,
                            'zona' => '',
                            'senial' => '',
                            'nuevo' => false
                        ];
		}
	}

	private function buscaUltimoPivot($offset)
	{
    	$this->tgt = [];
    	$this->tgt[1] = $this->datas[$offset]['t1'];
    	$this->tgt[2] = $this->datas[$offset]['t2'];
    	$this->tgt[3] = $this->datas[$offset]['t3'];
    	$this->tgt[4] = $this->datas[$offset]['t4'];
	}

    private function calculaNuevosIndicadores($offset)
    {
        // Calcula nuevos indicadores
        $this->datas[$offset-1]['trueRange'] = max(($this->datas[$offset-1]['high']-$this->datas[$offset-1]['low']), 
                        abs($this->datas[$offset-1]['high']-$this->datas[$offset-2]['close']),
                        abs($this->datas[$offset-1]['low']-$this->datas[$offset-2]['close']));

        $acum7 = $acum21 = 0;
        if ($offset >= 8)
        {
            for ($i = $offset-1; $i >= $offset-7; $i--)
            {
                if (isset($this->datas[$i]['trueRange']))
                    $acum7 += $this->datas[$i]['trueRange'];
            }
        }
        if ($offset >= 22)
        {
            for ($i = $offset-1; $i >= $offset-21; $i--)
            {
                if (isset($this->datas[$i]['trueRange']))
                    $acum21 += $this->datas[$i]['trueRange'];
            }
        }

        $this->datas[$offset-1]['averageTrueRange'] = ceil($acum7 / 7 / $this->ticker);
        $this->datas[$offset-1]['cciaTRadj'] = $this->datas[$offset-1]['CCI']*$this->datas[$offset-1]['averageTrueRange']/100;
        $this->datas[$offset-1]['obb'] = ceil($this->datas[$offset-1]['averageTrueRange'] * 1.272);
        $this->datas[$offset-1]['osb'] = -ceil($this->datas[$offset-1]['averageTrueRange'] * 1.272);
        $this->datas[$offset-1]['atr21'] = $acum21 / 21 / $this->datas[$offset-1]['close'] * 100;
        
        // Calcula Desvio Standard de 24 items de atr21
        $acumAtr = 0;
        $suma = $sumaHigh = $sumaLow = 0;
        if ($offset >= 25)
        {
            for ($i = $offset-1; $i >= $offset-24; $i--)
            {
                if (isset($this->datas[$i]['atr21']))
                    $acumAtr += $this->datas[$i]['atr21'];
            }
            $media = $acumAtr / 24;
            for ($i = $offset-1; $i >= $offset-24; $i--)
            {
                if (isset($this->datas[$i]['atr21']))
                {
                    $suma += ($this->datas[$i]['atr21'] - $media) * ($this->datas[$i]['atr21'] - $media);

                    if ($i >= $offset-20)
                    {
                        $sumaHigh += $this->datas[$i]['high'];
                        $sumaLow += $this->datas[$i]['low'];
                    }
                }
            }
        }
        $varianza = $suma / 23;
        $desvioStandard = sqrt($varianza);

        $this->datas[$offset-1]['atrmstdev'] = ($acumAtr / 24) - $desvioStandard;

        if ($this->datas[$offset-1]['atr21'] > $this->datas[$offset-1]['atrmstdev'])
            $this->datas[$offset-1]['regimenVolatilidad'] = 1;
        else
            $this->datas[$offset-1]['regimenVolatilidad'] = 0;
        
        if ($offset >= 25)
        {
            $mediaH = $sumaHigh / 20;
            $mediaL = $sumaLow / 20;
            for ($i = $offset-1, $sumaH = 0, $sumaL = 0; $i >= $offset-20; $i--)
            {
                $sumaH += ($this->datas[$i]['high'] - $mediaH) * ($this->datas[$i]['high'] - $mediaH);
                $sumaL += ($this->datas[$i]['low'] - $mediaL) * ($this->datas[$i]['low'] - $mediaL);
            }
            $varianza = $sumaH / 19;
            $desvioStandard = sqrt($varianza);
            $this->datas[$offset-1]['stdevHi'] = $desvioStandard;

            $varianza = $sumaL / 19;
            $desvioStandard = sqrt($varianza);
            $this->datas[$offset-1]['stdevLo'] = $desvioStandard;
        }
        
        if ($this->datas[$offset-1]['high'] > $this->datas[$offset-2]['high'])
            $this->datas[$offset-1]['stdevH1'] = $this->datas[$offset-1]['stdevHi'];
        else
            $this->datas[$offset-1]['stdevH1'] = 0;

        if ($offset > 20)
        {
            for ($i = $offset-1, $suma = 0; $i >= $offset-20; $i--)
            {
                if (isset($this->datas[$i]['stdevH1']))
                    $suma += $this->datas[$i]['stdevH1'];
            }
            $this->datas[$offset-1]['h1'] = $suma / 20;
        }

        if ($offset > 2)
        {
            if ($offset - 1 == 44)
                $this->datas[$offset-1]['h1Exp'] = $this->datas[$offset-1]['stdevH1'];
            else
                $this->datas[$offset-1]['h1Exp'] = 2 / 21 * $this->datas[$offset-1]['stdevH1'] + (1-2/21) * $this->datas[$offset-2]['h1Exp'];
        }
        else
            $this->datas[$offset-1]['h1Exp'] = 0;

        if ($this->datas[$offset-1]['high'] < $this->datas[$offset-2]['high'])
            $this->datas[$offset-1]['stdevH2'] = $this->datas[$offset-1]['stdevHi'];
        else
            $this->datas[$offset-1]['stdevH2'] = 0;
        if ($offset > 20)
        {
            for ($i = $offset-1, $suma = 0; $i >= $offset-20; $i--)
            {
                if (isset($this->datas[$i]['stdevH2']))
                    $suma += $this->datas[$i]['stdevH2'];
            }
            $this->datas[$offset-1]['h2'] = $suma / 20;
        }

        if ($offset > 2)
        {
            if ($offset - 1 == 44)
                $this->datas[$offset-1]['h2Exp'] = $this->datas[$offset-1]['stdevH2'];
            else
                $this->datas[$offset-1]['h2Exp'] = 2 / 21 * $this->datas[$offset-1]['stdevH2'] + (1-2/21) * $this->datas[$offset-2]['h2Exp'];
        }
        else
            $this->datas[$offset-1]['h2Exp'] = 0;

        if ($this->datas[$offset-1]['low'] > $this->datas[$offset-2]['low'])
            $this->datas[$offset-1]['stdevL1'] = $this->datas[$offset-1]['stdevLo'];
        else
            $this->datas[$offset-1]['stdevL1'] = 0;

        $suma = 0;
        if ($offset >= 21)
        {
            for ($i = $offset-1; $i >= $offset-20; $i--)
            {
                if (isset($this->datas[$i]['stdevL1']))
                    $suma += $this->datas[$i]['stdevL1'];
            }
        }
        $this->datas[$offset-1]['l1'] = $suma / 20;

        if ($offset > 2)
        {
            if ($offset - 1 == 44)
                $this->datas[$offset-1]['l1Exp'] = $this->datas[$offset-1]['stdevL1'];
            else
                $this->datas[$offset-1]['l1Exp'] = 2 / 21 * $this->datas[$offset-1]['stdevL1'] + (1-2/21) * $this->datas[$offset-2]['l1Exp'];
        }
        else
            $this->datas[$offset-1]['l1Exp'] = 0;

        if ($this->datas[$offset-1]['low'] < $this->datas[$offset-2]['low'])
            $this->datas[$offset-1]['stdevL2'] = $this->datas[$offset-1]['stdevLo'];
        else
            $this->datas[$offset-1]['stdevL2'] = 0;

        $suma = 0;
        if ($offset >= 21)
        {
            for ($i = $offset-1; $i >= $offset-20; $i--)
            {
                if (isset($this->datas[$i]['stdevL2']))
                    $suma += $this->datas[$i]['stdevL2'];
            }
        }
        $this->datas[$offset-1]['l2'] = $suma / 20;

        if ($offset > 2)
        {
            if ($offset - 1 == 44)
                $this->datas[$offset-1]['l2Exp'] = $this->datas[$offset-1]['stdevL2'];
            else
                $this->datas[$offset-1]['l2Exp'] = 2 / 21 * $this->datas[$offset-1]['stdevL2'] + (1-2/21) * $this->datas[$offset-2]['l2Exp'];
        }
        else
            $this->datas[$offset-1]['l2Exp'] = 0;
       
        if ($this->datas[$offset-1]['h1'] + $this->datas[$offset-1]['h2'] == 0)
            $this->datas[$offset-1]['rvih0'] = 50;
        else
            $this->datas[$offset-1]['rvih0'] = 100 * $this->datas[$offset-1]['h1'] / ($this->datas[$offset-1]['h1'] + $this->datas[$offset-1]['h2']);

        if ($this->datas[$offset-1]['h1Exp'] + $this->datas[$offset-1]['h2Exp'] == 0)
            $this->datas[$offset-1]['rvih'] = 50;
        else
            $this->datas[$offset-1]['rvih'] = 100 * $this->datas[$offset-1]['h1Exp'] / ($this->datas[$offset-1]['h1Exp'] + $this->datas[$offset-1]['h2Exp']);

        if ($this->datas[$offset-1]['l1'] + $this->datas[$offset-1]['l2'] == 0)
            $this->datas[$offset-1]['rvil0'] = 50;
        else
            $this->datas[$offset-1]['rvil0'] = 100 * $this->datas[$offset-1]['l1']/($this->datas[$offset-1]['l1']+$this->datas[$offset-1]['l2']);

        if ($this->datas[$offset-1]['l1Exp'] + $this->datas[$offset-1]['l2Exp'] == 0)
            $this->datas[$offset-1]['rvil'] = 50;
        else
            $this->datas[$offset-1]['rvil'] = 100 * $this->datas[$offset-1]['l1Exp']/($this->datas[$offset-1]['l1Exp']+$this->datas[$offset-1]['l2Exp']);

        $this->datas[$offset-1]['rviSimple'] = ($this->datas[$offset-1]['rvih0'] + $this->datas[$offset-1]['rvil0']) / 2;
        $this->datas[$offset-1]['rviExp'] = ($this->datas[$offset-1]['rvih'] + $this->datas[$offset-1]['rvil']) / 2;

        $this->datas[$offset-1]['x'] = $offset;

        $this->datas[$offset-1]['xCuadrado'] = $this->datas[$offset-1]['x'] * $this->datas[$offset-1]['x'];
            
        if ($offset > 20)
        {
            for ($i = $offset-1, $suma = $sumaX = $sumaProducto = $sumaExp = $sumaX2 = 0; $i >= $offset-20; $i--)
            {
                if (isset($this->datas[$i]['rviExp']))
                {
                    $sumaProducto += ($this->datas[$i]['rviExp'] * $this->datas[$i]['x']);
                    $sumaX += $this->datas[$i]['x'];
                    $sumaExp += $this->datas[$i]['rviExp'];
                    $sumaX2 += $this->datas[$i]['xCuadrado'];
                }
            }

            $this->datas[$offset-1]['a'] = (20 * $sumaProducto - $sumaX * $sumaExp) / ((20 * $sumaX2)-($sumaX * $sumaX));
            $this->datas[$offset-1]['b'] = ($sumaX2*$sumaExp-$sumaX*$sumaProducto) / ((20*$sumaX2)-($sumaX*$sumaX));
        }

        $this->datas[$offset-1]['yaxb'] = $this->datas[$offset-1]['a'] * $this->datas[$offset-1]['x'] + $this->datas[$offset-1]['b'];

        $this->datas[$offset-1]['inertia'] = $this->datas[$offset-1]['yaxb'] - 50;
    }
}

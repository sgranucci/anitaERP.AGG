<?php

namespace App\Exports\Graficos;

use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithHeadings;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Carbon\Carbon;
use DB;

class IndicadoresExport implements FromView, WithColumnFormatting, WithMapping, ShouldAutoSize, WithStyles, WithColumnWidths, WithEvents, WithTitle
{
	use Exportable;
	private $desdeFecha, $hastaFecha;
    private $desdeHora, $hastaHora;
    private $especie;
    private $compresion, $compresiontxt, $factorCompresion;
    private $calculoBase, $mmCorta, $mmLarga, $calculoBase_enum;
    private $largoVMA, $largoCCI, $largoXTL, $umbralXTL;
	private $k1, $k2;
    private $dataAnterior = [];
    private $fechaUltimaLectura;
    private $swingSize;
    private $datas = [];
    private $operaciones = [];
	protected $dates = ['fecha'];
    
	public function view(): View
	{
        ini_set('memory_limit', '512M');
        ini_set('max_execution_time', '2400');

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

            // Verifica arrancar en divisor del factor del compresion
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
                        
                    $difMinutos = \Carbon\Carbon::parse($fechaInicioRango)->diffInMinutes($fechaLectura);
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
                    $this->calculaEWO($item, $base, $smac, $smal, $ewo, $bandaSup, $bandaInf);
                    
                    // Calcula pivot de fibonacci
                    $this->calculaFibonacci($fechaInicioRango, $rfLim, $rfeExt, $rfeInt, $rfiExt, $rfiInt, $pp1,
                                            $poc, $pp2, $sfiInt, $sfiExt, $sfeInt, $sfeExt, $sfLim, $base);

                    $tmp1 = $tmp2 = $d1 = $d2 = $condicional = $d3 = $k = $VMA = $precioTipico = 0;
                    $SMACCI = $auxCCI = $blanco1 = $blanco2 = $CCI = $SMAXTL = $auxXTL = $CCIXTL = 0;
                    $estado = $rango = $TQRVerde = $stopTQRVerde = $tgtTQRVerde = $TQRRojo = 0;
                    $stopTQRRojo = $tgtTQRRojo = 0;
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

                    // Arma tabla 
                    $this->armaTabla($fechaStr, $fecha, $horaInicio, $open, $close, $low, $high, $totVolume, $ewo,
                                    $bandaSup, $bandaInf, $rfLim, $rfeExt, $rfeInt, $rfiExt, $rfiInt, $pp1, $poc, $pp2,
                                    $sfiInt, $sfiExt, $sfeInt, $sfeExt, $sfLim, $base, $smac, $smal, $tmp1, $tmp2, $d1, $d2,
                                    $condicional, $d3, $k, $VMA, $precioTipico, $SMACCI, $auxCCI, $blanco1, $blanco2,
                                    $CCI, $SMAXTL, $auxXTL, $CCIXTL, $estado, $rango, $TQRVerde, $stopTQRVerde, $tgtTQRVerde,
                                    $TQRRojo, $stopTQRRojo, $tgtTQRRojo);
                                    
                    $cantLectura = 0;
                    $low = $high = $totVolume = $open = $close = 0;
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
                                $bandaSup, $bandaInf, $rfLim, $rfeExt, $rfeInt, $rfiExt, $rfiInt, $pp1, $poc, $pp2,
                                $sfiInt, $sfiExt, $sfeInt, $sfeExt, $sfLim, $base, $smac, $smal, $tmp1, $tmp2, $d1, $d2,
                                $condicional, $d3, $k, $VMA, $precioTipico, $SMACCI, $auxCCI, $blanco1, $blanco2,
                                $CCI, $SMAXTL, $auxXTL, $CCIXTL, $estado, $rango, $TQRVerde, $stopTQRVerde, $tgtTQRVerde,
                                $TQRRojo, $stopTQRRojo, $tgtTQRRojo);
                                
        }

        // Calcula pivots
        $this->calculaPivot();

        // Calcula volumen por swing y Tgt hit
        $this->calculaSwingTgt();
       
        $v = view('exports.graficos.indicadores', ['comprobantes' => $this->datas,
                'desdefecha' => $desde_fecha, 'hastafecha' => $hasta_fecha, 
                'desdehora' => $this->desdeHora, 'hastahora' => $this->hastaHora, 
                'compresiontxt' => $this->compresiontxt,
                'mmcorta' => $this->mmCorta,
                'mmlarga' => $this->mmLarga,
                'largovma' => $this->largoVMA,
                'largocci' => $this->largoCCI,
                'largoxtl' => $this->largoXTL,
                'umbralxtl' => $this->umbralXTL,
                'calculobasetxt' => $calculoBaseTxt,
                'swingsize' => $this->swingSize,
                'especie' => $this->especie]);

        return ['indicadores' => $v, 'operaciones' => $this->operaciones];
	}

    private function calculaEWO($item, $base, &$smac, &$smal, &$ewo, &$bandaSup, &$bandaInf)
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
    
    private function calculaPivot()
    {
        $trend = 0;
        $maxRango = 0;
        $minRango = 99999999999;
        $nroMin = $nroMax = 0;
        $bnMin = $bnMax = 0;
        $pivot3 = $pivot2 = $pivot1 = 0;
        $cswing = 1;
        $n = $this->swingSize;
        for ($i = $n - 1; $i < count($this->datas); $i++)
        {
            $maxRango = 0;
            $minRango = 999999999999;
            for ($j = $i - $n + 1; $j <= $i; $j++)
            {
                if ($this->datas[$j]['high'] > $maxRango)
                    $maxRango = $this->datas[$j]['high'];
                if ($this->datas[$j]['low'] < $minRango)
                    $minRango = $this->datas[$j]['low'];
            }

            $low = $this->datas[$i]['low'];
            $high = $this->datas[$i]['high'];
            $volumen = $this->datas[$i]['volume'];

            // Cuenta los high y low del rango
            if ($i > $n)
            {
                for ($j = $i; $j >= $i - $n + 1; $j--)
                {
                    if ($this->datas[$j]['high'] == $maxRango)
                        $nroMax++;                  
                    if ($this->datas[$j]['low'] == $minRango)
                        $nroMin++;
                }
            }
        
            switch($trend)
            {
            case 0:
                if ($low == $minRango)
                {
                    $minimo = $low;
                    $bnMin = $i;
                    $trend = -1;
                    $pivot0 = $minimo;
                    $barras = 1;
                    
                    // Asigna valores
                    $this->datas[$i]['provMin'] = $minimo;
                    $this->datas[$i]['min'] = $minimo;
                    $this->datas[$i]['tendencia'] = $trend;
                    $this->datas[$i]['pivot0'] = $pivot0;
                    $this->datas[$i]['trendBars'] = $barras;
                    $this->datas[$i]['volumen'] = $volumen;
                }
                elseif ($high == $maxRango)
                {
                    $maximo = $high;
                    $bnMax = $i;
                    $pivot0 = $maximo;
                    $trend = 1;
                    $barras = 1;

                    // Asigna valores
                    $this->datas[$i]['provMax'] = $maximo;
                    $this->datas[$i]['max'] = $maximo;
                    $this->datas[$i]['tendencia'] = $trend;
                    $this->datas[$i]['pivot0'] = $pivot0;
                    $this->datas[$i]['trendBars'] = $barras;
                    $this->datas[$i]['volumen'] = $volumen;
                }
                break;
            case 1:
                if ($low == $minRango && $nroMin == 1)
                {
                    $minimo = $low;

                    // Asigna valores
                    $this->datas[$i]['volumen'] = $volumen;
                    $this->datas[$i]['provMin'] = $minimo;
                    $this->datas[$i]['min'] = $minimo;
                    $trend = -1;
                    $this->datas[$i]['tendencia'] = $trend;

                    if ($bnMin != 0 && $bnMax != 0)
                    {
                        $swingBars = ABS($bnMin - $bnMax);
                        $this->datas[$bnMax]['swingBars'] = $swingBars;
                        $this->datas[$bnMax]['swingBarsPrev'] = $this->datas[$bnMin]['swingBars'];

                        $pivot4 = $pivot3;
                        $pivot3 = $pivot2;
                        $pivot2 = $pivot1;
                        $pivot1 = $pivot0;
                        $pivot0 = $maximo;

                        $this->datas[$bnMax]['pivot0'] = $pivot0;
                        $this->datas[$bnMax]['pivot1'] = $pivot1;
                        $this->datas[$bnMax]['pivot2'] = $pivot2;
                        $this->datas[$bnMax]['pivot3'] = $pivot3;
                        $this->datas[$bnMax]['pivot4'] = $pivot4;
                    
                        if (isset($pivot0) && isset($pivot1) and isset($pivot2))
                        {
                            if ($pivot0 != 0 && $pivot1 != 0 && $pivot2 != 0)
                            {
                                $cswingProv = $pivot0 - $this->datas[$i]['provMin'];
                                $swing = $pivot1 - $pivot2;
                                $cswing = $pivot1 - $pivot0;
                                $retrocesoProv = ABS($cswingProv / $cswing);
                                if ($swing != 0)
                                    $retroceso = $cswing / $swing;
                                else    
                                    $retroceso = 0;

                                // Asigna datos
                                $this->datas[$i]['provRet'] = $retrocesoProv;
                                $this->datas[$bnMax]['retroceso'] = $retroceso;

                                if ($pivot1 > $pivot2)
                                {
                                    $this->datas[$bnMax]['extT1'] = $pivot0 + ABS($swing) * 0.618;
                                    $this->datas[$bnMax]['extT2'] = $pivot0 + ABS($swing) * 1;
                                    $this->datas[$bnMax]['extT3'] = $pivot0 + ABS($swing) * 1.618;
                                    $this->datas[$bnMax]['extT4'] = $pivot0 + ABS($swing) * 2.618;

                                    if ($pivot0 > $pivot2)
                                        $this->datas[$bnMax]['setup'] = 'HL';
                                    elseif ($pivot0 < $pivot2)
                                        $this->datas[$bnMax]['setup'] = 'LL';
                                    elseif ($pivot0 == $pivot2)
                                        $this->datas[$bnMax]['setup'] = 'DB';
                                }
                                elseif ($pivot1 < $pivot2)
                                {
                                    $this->datas[$bnMax]['extT1'] = $pivot0 - ABS($swing) * 0.618;
                                    $this->datas[$bnMax]['extT2'] = $pivot0 - ABS($swing) * 1;
                                    $this->datas[$bnMax]['extT3'] = $pivot0 - ABS($swing) * 1.618;
                                    $this->datas[$bnMax]['extT4'] = $pivot0 - ABS($swing) * 2.618;

                                    if ($pivot0 < $pivot2)
                                        $this->datas[$bnMax]['setup'] = 'LH';
                                    elseif ($pivot0 > $pivot2)
                                        $this->datas[$bnMax]['setup'] = 'HH';
                                    elseif ($pivot0 == $pivot2)
                                        $this->datas[$bnMax]['setup'] = 'DT';
                                }
                            }
                        }
                    }
                    $bnMin = $i;
                    $barras = 1;
                    $this->datas[$i]['trendBars'] = $barras;
                }

                if ($high >= $maximo && $trend == $this->datas[$i-1]['tendencia'])
                {
                    $this->datas[$bnMax]['max'] = 0;
                    $this->datas[$i]['volumen'] = $volumen + $this->datas[$i-1]['volumen'];
                    $maximo = $high;
                    $bnMax = $i;

                    // Asigna valores
                    $this->datas[$i]['provMax'] = $maximo;
                    //$this->datas[$i]['barras'] = ABS($bnMin - $bnMax);
                    $this->datas[$i]['max'] = $maximo;
                    $this->datas[$i]['tendencia'] = $trend;
                    $barras++;
                    $this->datas[$i]['trendBars'] = $barras;

                    if (isset($pivot1) && isset($pivot1) && isset($pivot2))
                    {
                        if ($pivot1 != 0 && $pivot1 != 0 && $pivot2 != 0)
                        {
                            $cswingProv = $pivot0 - $this->datas[$i]['provMax'];
                            $swing = $pivot1 - $pivot2;
                            if ($cswing != 0)
                                $retrocesoProv = ABS($cswingProv / $cswing);
                            else    
                                $retrocesoProv = 0;
                            $this->datas[$i]['provRet'] = $retrocesoProv;
                        }
                    }
                }
                else
                {
                    if ($trend == $this->datas[$i-1]['tendencia'])
                    {
                        $barras++;
                        $this->datas[$i]['trendBars'] = $barras;
                        $this->datas[$i]['volumen'] = $volumen + $this->datas[$i-1]['volumen'];
                    }
                    $this->datas[$i]['tendencia'] = $trend;
                }
                break;
            case -1:
                if ($high == $maxRango && $nroMax == 1)
                {
                    $maximo = $high;
                    $this->datas[$i]['volumen'] = $volumen;
                    $this->datas[$i]['max'] = $maximo;
                    $this->datas[$i]['provMax'] = $maximo;
                    $trend = 1;
                    $this->datas[$i]['tendencia'] = $trend;
                    if ($bnMin != 0 && $bnMax != 0)
                    {
                        //$this->datas[$i]['barras'] = ABS($bnMin - $i);
                        $swingBars = ABS($bnMin - $bnMax);
                        $this->datas[$bnMin]['swingBars'] = $swingBars;
                        $this->datas[$bnMin]['swingBarsPrev'] = $this->datas[$bnMax]['swingBars'];
                      
                        $pivot4 = $pivot3;
                        $pivot3 = $pivot2;
                        $pivot2 = $pivot1;
                        $pivot1 = $pivot0;
                        $pivot0 = $minimo;
                    
                        $this->datas[$bnMin]['pivot0'] = $pivot0;
                        $this->datas[$bnMin]['pivot1'] = $pivot1;
                        $this->datas[$bnMin]['pivot2'] = $pivot2;
                        $this->datas[$bnMin]['pivot3'] = $pivot3;
                        $this->datas[$bnMin]['pivot4'] = $pivot4;
                    
                        if (isset($pivot0) && isset($pivot1) && isset($pivot2))
                        {
                            if ($pivot0 != 0 && $pivot1 != 0 && $pivot2 != 0)
                            {
                                $cswingProv = $pivot0 - $this->datas[$i]['provMax'];
                                $swing = $pivot1 - $pivot2;
                                $cswing = $pivot1 - $pivot0;
                                $retroceso = $cswing / $swing;
                                if ($cswing != 0)
                                    $retrocesoProv = ABS($cswingProv / $cswing);
                                else
                                    $retrocesoProv = 0;
                                $this->datas[$i]['provRet'] = $retrocesoProv;
                                $this->datas[$bnMin]['retroceso'] = $retroceso;

                                if ($pivot1 > $pivot2)
                                {
                                    $this->datas[$bnMin]['extT1'] = $pivot0 + ABS($swing) * 0.618;
                                    $this->datas[$bnMin]['extT2'] = $pivot0 + ABS($swing) * 1;
                                    $this->datas[$bnMin]['extT3'] = $pivot0 + ABS($swing) * 1.618;
                                    $this->datas[$bnMin]['extT4'] = $pivot0 + ABS($swing) * 2.618;

                                    if ($pivot0 > $pivot2)
                                        $this->datas[$bnMin]['setup'] = 'HL';
                                    elseif ($pivot0 < $pivot2)
                                        $this->datas[$bnMin]['setup'] = 'LL';
                                    else    
                                        $this->datas[$bnMin]['setup'] = 'DB';
                                }
                                elseif ($pivot1 < $pivot2)
                                {
                                    $this->datas[$bnMin]['extT1'] = $pivot0 - ABS($swing) * 0.618;
                                    $this->datas[$bnMin]['extT2'] = $pivot0 - ABS($swing) * 1;
                                    $this->datas[$bnMin]['extT3'] = $pivot0 - ABS($swing) * 1.618;
                                    $this->datas[$bnMin]['extT4'] = $pivot0 - ABS($swing) * 2.618;

                                    if ($pivot0 > $pivot2)
                                        $this->datas[$bnMin]['setup'] = 'HL';
                                    elseif ($pivot0 < $pivot2)
                                        $this->datas[$bnMin]['setup'] = 'LH';
                                    elseif ($pivot0 == $pivot2)
                                        $this->datas[$bnMin]['setup'] = 'DT';
                                }
                            }
                        }
                    }
                    $bnMax = $i;
                    $barras = 1;
                    $this->datas[$i]['trendBars'] = $barras;
                }
                
                if ($low <= $minimo && $trend == $this->datas[$i-1]['tendencia'])
                {
                    $this->datas[$i]['volumen'] = $volumen + $this->datas[$i-1]['volumen'];
                    $this->datas[$bnMin]['min'] = 0;
                    $bnMin = $i;
                    $minimo = $low;
                    $this->datas[$i]['provMin'] = $minimo;
                    //$this->datas[$i]['barras'] = ABS($bnMin - $bnMax);
                    $this->datas[$i]['min'] = $minimo;
                    $this->datas[$i]['tendencia'] = $trend;
                    $barras++;
                    $this->datas[$i]['trendBars'] = $barras;

                    if (isset($pivot0) && isset($pivot1) && isset($pivot2))
                    {
                        if ($pivot0 != 0 && $pivot1 != 0 && $pivot2 != 0)
                        {
                            $cswingProv = $pivot0 - $this->datas[$i]['provMin'];
                            $swing = $pivot1 - $pivot2;
                            $retrocesoProv = ABS($cswingProv / $cswing);
                            $this->datas[$i]['provRet'] = $retrocesoProv;
                        }
                    }
                }
                else
                {
                    if ($trend == $this->datas[$i-1]['tendencia'])
                    {
                        $barras++;
                        $this->datas[$i]['trendBars'] = $barras;
                        $this->datas[$i]['volumen'] = $volumen + $this->datas[$i-1]['volumen'];
                    }
                    $this->datas[$i]['tendencia'] = $trend;
                }
                break;
            }
            $nroMax = 0;
            $nroMin = 0;
        }
        for ($i = 1; $i < count($this->datas); $i++)
        {
            // Calcula las barras
            if ($this->datas[$i]['swingBars'] != 0)
                $this->datas[$i]['barras'] = $this->datas[$i]['swingBars'];
            else
            {
                if ($this->datas[$i-1]['swingBars'] != 0)
                    $this->datas[$i]['barras'] = 1;
                else
                    $this->datas[$i]['barras'] = $this->datas[$i-1]['barras'] + 1;
            }

        }
    }

    private function calculaSwingTgt()
    {
        $n = $this->swingSize;
        $flBuscaEntrada = $flAbrePosicion = false;
        $off0 = $off1oA = -1;
        $ticker = 0.25;
        $valorTicker = 12.5;
        $flAlcista = false;
        $flBajista = false;
        $idSenial = $idTrade = 0;
        $numeroContratos = 1;
        for ($i = $n - 1; $i < count($this->datas); $i++)
        {
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

            // Si tiene posicion abierta chequea contra ordenes hijas SL y PT
            if ($flAbrePosicion)
            {
                $this->datas[$i]['e'] = $open;
                $this->datas[$i]['t1'] = $this->datas[$offAbrePosicion]['t1'];
                $this->datas[$i]['p'] = '1';
                $this->datas[$i]['evento'] = ($flAlcista ? 'Compra' : 'Vende');

                // Chequea con TGT Hit
                if ($flAlcista)
                {
                    $this->datas[$i]['stoploss'] = $stopLoss - $ticker;

                    if ($this->datas[$i]['open'] >= $this->datas[$i]['t1'] ||
                        $this->datas[$i]['close'] >= $this->datas[$i]['t1'] ||
                        $this->datas[$i]['high'] >= $this->datas[$i]['t1'] ||
                        $this->datas[$i]['low'] >= $this->datas[$i]['t1'])
                    {
                        $this->datas[$i]['p'] = '0';
                        $this->datas[$i]['evento'] = 'Tgt Hit';
                        $flAbrePosicion = false;
                    }
                    // Chequea con SL
                    if ($this->datas[$i]['open'] <= $this->datas[$i]['stoploss'] ||
                        $this->datas[$i]['close'] <= $this->datas[$i]['stoploss'] ||
                        $this->datas[$i]['high'] <= $this->datas[$i]['stoploss'] ||
                        $this->datas[$i]['low'] <= $this->datas[$i]['stoploss'])
                    {
                        $this->datas[$i]['p'] = '0';
                        $this->datas[$i]['evento'] = 'SL';
                        $flAbrePosicion = false;
                    }
                }
                if ($flBajista)
                {
                    $this->datas[$i]['stoploss'] = $stopLoss + $ticker;

                    // Chequea Target 1
                    if ($this->datas[$i]['open'] <= $this->datas[$i]['t1'] ||
                        $this->datas[$i]['close'] <= $this->datas[$i]['t1'] ||
                        $this->datas[$i]['high'] <= $this->datas[$i]['t1'] ||
                        $this->datas[$i]['low'] <= $this->datas[$i]['t1'])
                    {
                        $this->datas[$i]['p'] = '0';
                        $this->datas[$i]['evento'] = 'Tgt Hit';
                        $flAbrePosicion = false;
                    }
                    // Chequea con SL
                    if ($this->datas[$i]['open'] >= $this->datas[$i]['stoploss'] ||
                        $this->datas[$i]['close'] >= $this->datas[$i]['stoploss'] ||
                        $this->datas[$i]['high'] >= $this->datas[$i]['stoploss'] ||
                        $this->datas[$i]['low'] >= $this->datas[$i]['stoploss'])
                    {
                        $this->datas[$i]['p'] = '0';
                        $this->datas[$i]['evento'] = 'SL';
                        $flAbrePosicion = false;
                    }
                }

                // Chequea para cerrar swing
                if (!$flAbrePosicion)
                {
                    $off1oA = -1;
                    $flAbrePosicion = false;

                    $precioCierre = round($this->datas[$i]['t1'] / $ticker, 0) * $ticker;
                    $plPuntos = $this->datas[$i]['e'] - $precioCierre;
                    $plTicks = $plPuntos / $ticker;
                    $plPesos = $plTicks * $valorTicker;
                    $mpc = $this->calculaMpc($offAbrePosicion, $i);
                    $mpf = $this->calculaMpf($offAbrePosicion, $i, $precioCierre);
                    $eficienciaEntrada = ($mpc - $this->datas[$i]['e']) / ($this->datas[$i]['stoploss'] - $this->datas[$i]['e']);

                    $eSalida = ($this->datas[$i]['e'] - $precioCierre) / ($this->datas[$i]['e']-$mpf);
                    if ($eSalida < 0)
                        $eficienciaSalida = 0;
                    else
                        $eficienciaSalida = $eSalida;
                    
                    $tipoOperacion = ($flAlcista ? "Sell to Close" : "Buy to Close");
                    $this->armaTablaOperaciones($idSenial, $idTrade, $numeroContratos, $this->datas[$i]['evento'], 
                        $tipoOperacion,
                        $this->datas[$i]['fechastr'], $this->datas[$i]['horainicio'], 
                        $this->datas[$i]['fecha'],
                        '', '', '', '', '', '', '',
                        '', '', '', '', '', '', '',
                        '', '', '', '', '', '', '',
                        $precioCierre,
                        $plPuntos, $plTicks, $plPesos, $mpc, $mpf, $eficienciaEntrada,
                        $eficienciaSalida);
                }
            }

            // Sigue una vela mas con la entrada habilitada para disparar gatillo 
            if ($flBuscaEntrada)
            {
                // calcula riesgo retorno
                if ($flAlcista)
                {
                    $retorno = $t1 - $this->datas[$i]['open'];
                    $riesgo = $this->datas[$i]['open'] - $stopLoss;
                    $rrr = $retorno / $riesgo;
                }
                else
                {
                    if ($flBajista)
                    {
                        $retorno = $this->datas[$i]['open'] - $t1;
                        $riesgo = $stopLoss - $this->datas[$i]['open'];
                        $rrr = abs($retorno) / abs($riesgo);
                    }
                }

                $this->datas[$i]['entrada'] = ' Retorno '.$retorno.' Riesgo '.$riesgo.' RRR '.$rrr.' SL '.$stopLoss;
                if ($rrr >= 1.5)
                {
                    // Redondea T1
                    //$t1 = redondear($t1, 2, $ticker*100);

                    $open = $this->datas[$i]['open'];

                    $flAbrePosicion = true;
                    $this->datas[$i]['e'] = $open;
                    $this->datas[$i]['stoploss'] = ($flAlcista ? $stopLoss - $ticker : $stopLoss + $ticker);
                    $this->datas[$i]['t1'] = $t1;
                    $this->datas[$i]['p'] = '1';
                    $this->datas[$i]['evento'] = ($flAlcista ? 'Compra' : 'Vende');
                    $offAbrePosicion = $i;

                    // Arma tabla de operaciones
                    $riesgoPuntos = abs($open-($flAlcista ? $stopLoss - $ticker : $stopLoss + $ticker));
                    $riesgoTicks = round($riesgoPuntos/$ticker, 0);
                    $riesgoPesos = $riesgoTicks * $valorTicker;
                    $retornoPuntos = abs($open-$t1);
                    $retornoTicks = round($retornoPuntos/$ticker, 0);
                    $retornoPesos = $retornoTicks * $valorTicker;
                    $rrr = $retornoTicks / $riesgoTicks;

                    $tipoOperacion = ($flAlcista ? "Buy to Open" : "Sell to Open");
                    $this->armaTablaOperaciones($idSenial, ++$idTrade, $numeroContratos, "Gatillo operación", 
                                                $tipoOperacion,
                                                $this->datas[$i]['fechastr'], $this->datas[$i]['horainicio'], 
                                                $this->datas[$i]['fecha'],
                                                '', '', '', '', '', '', '',
                                                $open, 
                                                '', '', '', '', 
                                                '', '',
                                                $riesgoPuntos, $riesgoTicks, $riesgoPesos,
                                                $retornoPuntos, $retornoTicks, $retornoPesos, $rrr, 
                                                '', '', '', '', '', '', '', '');
                }
                else
                {
                    // Si es un pivot analiza nuevamente
                    if ($this->datas[$i]['provRet'] >= 0.5 && $this->datas[$i]['provRet'] <= 1)
                    {
                        $flAbrePosicion = false;
                        $this->datas[$i]['entrada'] .= ' Encuentra un nuevo pivot';
                    }
                    else
                    {
                        $flAbrePosicion = false;
                    }
                    $this->armaTablaOperaciones($idSenial, ++$idTrade, $numeroContratos, "Filtrado por RRR", "",
                                                $this->datas[$i]['fechastr'], $this->datas[$i]['horainicio'], 
                                                $this->datas[$i]['fecha'],
                                                '', '', '', '', '', '', '',
                                                '', 
                                                $this->datas[$i]['stoploss'], $this->datas[$i]['t1'], 
                                                '', '', 
                                                '', '',
                                                '', '', '',
                                                '', '', '', '', 
                                                '', '', '', '', '', '', '', '');
                }

                $flBuscaEntrada = false;
            }

            // Si no esta con posicion abierta va chequeando cada vela para ver si cumple gatillo pero 
            // solo de los puntos candidatos
            if (!$flAbrePosicion &&
                $this->datas[$i]['provRet'] >= 0.5 && $this->datas[$i]['provRet'] <= 1)
            {
                $minimoActual = $this->datas[$i]['low'];
                $maximoActual = $this->datas[$i]['high'];

                // Define si el candidato es alcista o bajista 
                $off0 = $off1oA = -1;
                $flBajista = $flAlcista = false;
                $retroceso = $relacionVelas = 0;
                //if ($minimoActual == $this->datas[$i]['provMin']) // Alcista
                if (false)
                {
                    self::buscaMinMaxAlcista($i, $off1oA, $off0, $stopLoss, $maximo1oA);
                    $flAlcista = true;

                    // Si obtiene maximo y minimo calcula valores para verificar gatillo
                    if ($off1oA != -1 && $off0 != -1)
                    {
                        $recorrido1oA = $maximo1oA - $stopLoss;
                        $recorrido2oB = $maximo1oA - $minimoActual;
                        $retroceso = $recorrido2oB / $recorrido1oA;

                        $barras1oA = $off1oA - $off0;
                        $barras2oB = $i - $off1oA;
                        $relacionVelas = $barras2oB / $barras1oA;

                        $t1 = ($recorrido1oA * 0.618) + $minimoActual;
                    }
                }
                if ($maximoActual == $this->datas[$i]['provMax']) // Bajista
                {
                    self::buscaMinMaxBajista($i, $off1oA, $off0, $stopLoss, $minimo);
                    $flBajista = true;

                    // Si obtiene maximo y minimo calcula valores para verificar gatillo
                    if ($off1oA != -1 && $off0 != -1)
                    {
                        $recorrido1oA = $stopLoss - $minimo;
                        $recorrido2oB = $maximoActual - $minimo;
                        $retroceso = $recorrido2oB / $recorrido1oA;

                        $barras1oA = $off0 - $off1oA;
                        $barras2oB = $i - $off0;
                        $relacionVelas = $barras2oB / $barras1oA;

                        $t1 = $maximoActual - ($recorrido1oA * 0.618);
                    }
                }
                
                // Si obtiene maximo y minimo verifica gatillo
                if ($off1oA != -1 && $off0 != -1)
                {
                    if ($retroceso >= 0.50 && $relacionVelas <= 1)
                    {
                        $flBuscaEntrada = true;

                        $this->datas[$i]['entrada'] .= ' Retroceso '.$retroceso.' RV '.$relacionVelas.' T1 '.$t1;

                        $zonaOpen = $this->calculaZona($this->datas[$i]['open'], $i);
                        $zonaHigh = $this->calculaZona($this->datas[$i]['high'], $i);
                        $zonaLow = $this->calculaZona($this->datas[$i]['low'], $i);
                        $zonaClose = $this->calculaZona($this->datas[$i]['close'], $i);

                        $this->armaTablaOperaciones(++$idSenial, '', $numeroContratos, "Señal de venta", "",
                                                    $this->datas[$i]['fechastr'], $this->datas[$i]['horainicio'], 
                                                    $this->datas[$i]['fecha'],
                                                    $zonaOpen, $zonaHigh, $zonaLow, $zonaClose, 
                                                    $this->datas[$i]['ewo'], $this->datas[$i]['bandaSup'],
                                                    $this->datas[$i]['bandaInf'], 0, 
                                                    ($flAlcista ? $stopLoss - $ticker : $stopLoss + $ticker),
                                                    $t1, 
                                                    $this->datas[$i]['swingBars'], 
                                                    $this->datas[$i]['barras'], 
                                                    $relacionVelas, $retroceso, 
                                                    '', '', '', '', '', '', '',
                                                    '', '', '', '', '', '', '', '');
                    }
                }
            }

            $sp = $this->datas[$i]['setup'];
            if ($sp != '')
            {
                $t1 = $this->datas[$i]['extT1'];
                $t2 = $this->datas[$i]['extT2'];
                $t3 = $this->datas[$i]['extT3'];
                $t4 = $this->datas[$i]['extT4'];
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

                for ($j = $i + 1; $j < count($this->datas); $j++)
                {
                    $spCtrl = $this->datas[$j]['setup'];
                    $extremo = $this->datas[$j][$columna];
                    if ($columna == 'high')
                    {
                        if ($t1Hit == 0 && $t1 <= $extremo)
                        {
                            $t1Hit = 1;
                            $this->datas[$i]['t1Hit'] = $j - $i;
                        }
                        elseif ($t2Hit == 0 && $t2 <= $extremo)
                        {
                            $t2Hit = 1;
                            $this->datas[$i]['t2Hit'] = $j - $i;
                        }
                        elseif ($t3Hit == 0 && $t3 <= $extremo)
                        {
                            $t3Hit = 1;
                            $this->datas[$i]['t3Hit'] = $j - $i;
                        }
                        elseif ($t4Hit == 0 && $t4 <= $extremo)
                        {
                            $t4Hit = 1;
                            $this->datas[$i]['t4Hit'] = $j - $i;
                        }
                    }
                    else
                    {
                        if ($columna == 'low')
                        {
                            if ($t1Hit == 0 && $t1 >= $extremo)
                            {
                                $t1Hit = 1;
                                $this->datas[$i]['t1Hit'] = $j - $i;
                            }
                            elseif ($t2Hit == 0 && $t2 >= $extremo)
                            {
                                $t2Hit = 1;
                                $this->datas[$i]['t2Hit'] = $j - $i;
                            }
                            elseif ($t3Hit == 0 && $t3 >= $extremo)
                            {
                                $t3Hit = 1;
                                $this->datas[$i]['t3Hit'] = $j - $i;
                            }
                            elseif ($t4Hit == 0 && $t4 >= $extremo)
                            {
                                $t4Hit = 1;
                                $this->datas[$i]['t4Hit'] = $j - $i;
                            }
                        }
                    }
                    if ($spCtrl != '')
                        break;
                }
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
                        $this->datas[$o]['entrada'] .= ' Nuevo Stop Loss Alcista '.$stopLoss;
                }
            }
            if ($this->datas[$o]['max'] != 0)
            {
                $off1oA = $o;
                $vela1oA = $this->datas[$o]['high'] - $this->datas[$o]['low'];
                $maximo1oA = $this->datas[$o]['high'];
                $this->datas[$o]['entrada'] = ' Maximo Vela 1oA '.$vela1oA.' Max. 1oA '.$maximo1oA;
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
                    if (!strstr($this->datas[$o]['entrada'], 'Stop Loss Bajista '))
                        $this->datas[$o]['entrada'] .= ' Nuevo Stop Loss Bajista '.$stopLoss;
                }
            }
            if ($this->datas[$o]['min'] != 0)
            {
                $offmin = $o;
                $minimo = $this->datas[$o]['min'];
                $this->datas[$o]['entrada'] = ' Nuevo Minimo Bajista '.$minimo;
            }
            if ($offmax != -1 && $offmin != -1)
                break;
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

    private function calculaMpc($offAbrePosicion, $offCierraPosicion)
    {
        $mpc = 0;
        for ($i = $offAbrePosicion; $i <= $offCierraPosicion; $i++)   
        {
            // Calcula maximo de valores high mientras se tiene abierta la posicion
            if ($this->datas[$i]['high'] > $mpc)
                $mpc = $this->datas[$i]['high'];
        }
        return $mpc;
    }

    private function calculaMpf($offAbrePosicion, $offCierraPosicion, $precioCierre)
    {
        $mpf = 0;
        $minimo = 99999999999;
        for ($i = $offAbrePosicion; $i <= $offCierraPosicion; $i++)   
        {
            // Calcula maximo de valores high mientras se tiene abierta la posicion
            if ($this->datas[$i]['low'] < $minimo)
                $minimo = $this->datas[$i]['low'];
        }
        if ($minimo > $precioCierre)
            $mpf = $minimo;
        else    
            $mpf = $precioCierre;
        return $mpf;
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
                                $bandaSup, $bandaInf, $rfLim, $rfeExt, $rfeInt, $rfiExt, $rfiInt, $pp1, $poc, $pp2,
                                $sfiInt, $sfiExt, $sfeInt, $sfeExt, $sfLim, $base, $smac, $smal, $tmp1, $tmp2, $d1, $d2,
                                $condicional, $d3, $k, $VMA, $precioTipico, $SMACCI, $auxCCI, $blanco1, $blanco2,
                                $CCI, $SMAXTL, $auxXTL, $CCIXTL, $estado, $rango, $TQRVerde, $stopTQRVerde, $tgtTQRVerde,
                                $TQRRojo, $stopTQRRojo, $tgtTQRRojo)
    {
        $this->datas[] = ['fechastr'=>$fechaStr, 'fecha'=>$fecha, 'horainicio'=>$horaInicio,
            'open'=>$open, 'close'=>$close,
            'low'=>$low,'high'=>$high,'volume'=>$totVolume,
            'ewo'=>$ewo,
            'bandaSup'=>$bandaSup,
            'bandaInf'=>$bandaInf,
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
            'entrada'=>'',
            'e' => '',
            'stoploss' => '',
            't1' => '',
            'p' => '',
            'evento' => '',
            'zona' => ''
        ];
    }

    private function armaTablaOperaciones($idSenial, $idTrade, $numeroContratos, $evento, $tipoOperacion, $fechaStr, $desdeHora, $fecha,
                                        $zonaOpen, $zonaHigh, $zonaLow, $zonaClose, $ewo, $bandaSup, $bandaInf,
                                        $precioEntrada, $stopLoss, $target, $swingBars, $contraSwingBars,
                                        $rv, $retroceso, $riesgoPuntos, $riesgoTicks, $riesgoPesos,
                                        $retornoPuntos, $retornoTicks, $retornoPesos, $rrr, $precioCierre,
                                        $plPuntos, $plTicks, $plPesos, $mpc, $mpf, $eficienciaEntrada,
                                        $eficienciaSalida)
    {
        $this->operaciones[] = [
            'idSenial' => $idSenial,
            'idTrade' => $idTrade,
            'numeroContratos' => $numeroContratos,
            'evento' => $evento,
            'tipoOperacion' => $tipoOperacion,
            'fechastr' => $fechaStr,
            'desdeHora' => $desdeHora,
            'fecha' => $fecha,
            'zonaOpen' => $zonaOpen,
            'zonaHigh' => $zonaHigh,
            'zonaLow' => $zonaLow,
            'zonaClose' => $zonaClose,
            'ewo' => $ewo,
            'bandaSup' => $bandaSup,
            'bandaInf' => $bandaInf,
            'precioEntrada' => $precioEntrada,
            'stopLoss' => $stopLoss,
            'target' => $target,
            'swingBars' => $swingBars,
            'contraSwingBars' => $contraSwingBars,
            'rv' => $rv,
            'retroceso' => $retroceso,
            'riesgoPuntos' => $riesgoPuntos,
            'riesgoTicks' => $riesgoTicks,
            'riesgoPesos' => $riesgoPesos,
            'retornoPuntos' => $retornoPuntos,
            'retornoTicks' => $retornoTicks,
            'retornoPesos' => $retornoPesos,
            'rrr' => $rrr,
            'precioCierre' => $precioCierre,
            'plPuntos' => $plPuntos,
            'plTicks' => $plTicks,
            'plPesos' => $plPesos,
            'mpc' => $mpc,
            'mpf' => $mpf,
            'eficienciaEntrada' => $eficienciaEntrada,
            'eficienciaSalida' => $eficienciaSalida
        ];
    }

	public function columnFormats(): array
    {
        return [
            'A' => NumberFormat::FORMAT_TEXT,
            'B' => NumberFormat::FORMAT_TEXT,
            'C' => NumberFormat::FORMAT_TEXT,
            'D' => NumberFormat::FORMAT_NUMBER,
            'E' => NumberFormat::FORMAT_NUMBER,
            'F' => NumberFormat::FORMAT_NUMBER,
            'G' => NumberFormat::FORMAT_NUMBER,
            'H' => NumberFormat::FORMAT_NUMBER,
        ];
    }

	public function map($row): array
    {
        return [
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            4   => ['font' => ['bold' => true,
        						'color' => array('rgb' => '17202A'),
        						'size'  => 12,
        						'name'  => 'Arial'
								],
					'fill' => [
                    			'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
        						'color' => array('rgb' => '85C1E9'),
					]
					],
            'B' => ['font' => ['bold' => true]],
            'C' => ['font' => ['bold' => true]],
        ];
    }

	public function columnWidths(): array
    {
        return [
            'A' => 40,
            'BJ' => 80,
        ];
    }

	public function registerEvents(): array
    {
        return [
            AfterSheet::class    => function(AfterSheet $event) {

                $event->sheet->getDelegate()->freezePane('A5');

            },
        ];
    }

	public function title(): string
    {
        return 'Lecturas';
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
}

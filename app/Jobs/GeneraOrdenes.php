<?php

namespace App\Jobs;

use App\Services\Graficos\IndicadoresService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use DB;

class GeneraOrdenes implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    //public $tries = 5;
    //public $backoff = 1;
    private $especie;
    private $calculoBase;
    private $mmCorta;
    private $mmLarga;
    private $compresion;
    private $largoVMA;
    private $largoCCI;
    private $largoXTL;
    private $umbralXTL;
    private $swingSize;
    private $filtroSetup;
    private $factorCompresion;

    public $timeout = 36000;

    public $failOnTimeout = false;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($especie, $calculobase, 
                                $mmcorta, $mmlarga, $compresion, $largovma, $largocci, $largoxtl,
                                $umbralxtl, $swingSize, $filtroSetup)
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
        $this->factorCompresion = 5;
        switch($compresion)
        {
        case 1:
            $this->factorCompresion = 1;
            break;
        case 2:
            $this->factorCompresion = 5;
            break;
        case 3:
            $this->factorCompresion = 15;
            break;
        case 4:
            $this->factorCompresion = 60;
            break;
        case 5:
            $this->factorCompresion = 3600;
            break;
        }
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {        
        $indicadoresService = new IndicadoresService;

        $currId = 0;
        $q = 0;

        // Procesa compresion
        $indicadoresService->acumOpen = 0;
        $indicadoresService->acumClose = $indicadoresService->acumLow = $indicadoresService->acumHigh = 0;
        $indicadoresService->acumVolume = 0;
        $indicadoresService->acumCantLectura = 0;
        $indicadoresService->acumFlEmpezoRango = false;
        $indicadoresService->acumItem = 0;
        $indicadoresService->acumFecha = "01-01-2001";
        $indicadoresService->acumFechaInicioRango = "01-01-2001";
        $indicadoresService->acumFechaStr = "01-01-2001";
        $indicadoresService->acumFechaLectura = "01-01-2001";
        $indicadoresService->cantLectura = 0;
        $indicadoresService->comision = 2.25;

        $indicadoresService->acumFlBuscaEntrada = $indicadoresService->acumFlAbrePosicion = false;
        $indicadoresService->acumOff0 = $indicadoresService->acumOff1oA = -1;
        $indicadoresService->acumFlAcista = false;
        $indicadoresService->acumFlBajista = false;
        $indicadoresService->flAbc = false;
        $indicadoresService->flAbCd = false;
        $indicadoresService->fl3Drives = false;
        $indicadoresService->flShark = false;
        $indicadoresService->flW4 = false;
        $indicadoresService->flSp = false;
        $indicadoresService->flInertia = false;
        $indicadoresService->flVolatilidad = false;

        $indicadoresService->acumFlAnulacionAbcAlcistaActiva = false;
        $indicadoresService->acumFlAnulacionAbcBajistaActiva = false;
        $indicadoresService->acumFlAnulacionAbCdAlcistaActiva = false;
        $indicadoresService->acumFlAnulacionAbCdBajistaActiva = false;

        $indicadoresService->acumIdSenial = $indicadoresService->acumIdTrade = 0;
        $indicadoresService->cantidadActivaContratos = $indicadoresService->totalContratos;
        $indicadoresService->acumFlCerroPorTiempoAlcista = false;
        $indicadoresService->acumFlCerroPorTiempoBajista = false;
        $indicadoresService->acumFlCierraPorTiempo = false;
        $indicadoresService->acumProfitAndLoss = 0;
        $indicadoresService->pivotes = [];
        $indicadoresService->flSpAlcista = false;
        $indicadoresService->tgtSpAlcista1 = 0;
        $indicadoresService->ventanaSpAlcista = 0;

        $indicadoresService->flSpBajista = false;
        $indicadoresService->tgtSpBajista1 = 0;
        $indicadoresService->ventanaSpBajista = 0;
        $indicadoresService->flEmpiezaOperacion = false;
        
        // Variables de calculo de swing
        $indicadoresService->acumTendencia = 'Indefinida';
        $indicadoresService->acumBnMinActual = $indicadoresService->acumBnMaxActual = $indicadoresService->acumMaximoActual = 0;
        $indicadoresService->acumMinimoActual = 0;
        $indicadoresService->acumBnMaximo = $indicadoresService->acumBnMinimo = $indicadoresService->acumBnMaximoAnterior = 0;
        $indicadoresService->acumBnMinimoAnterior = 0;

        $indicadoresService->ultimoMaximoBajista = 0;
        $indicadoresService->ultimoMinimoBajista = 0;
        $indicadoresService->ultimoMaximoAlcista = 0;
        $indicadoresService->ultimoMinimoAlcista = 0;
        $indicadoresService->offsetD = 0;
        $indicadoresService->offsetC = 0;
        $indicadoresService->offsetB = 0;
        $indicadoresService->offsetA = 0;
        $indicadoresService->offsetU = 0;
        $indicadoresService->offsetO = 0;
        $indicadoresService->offsetMaximoCW4 = 0;
        $indicadoresService->offsetMinimoTW4 = 0;
        $indicadoresService->offsetMaximoDW4 = 0;
        $indicadoresService->offsetMinimoUW4 = 0;
        $indicadoresService->offsetMaximoOW4 = 0;
        $indicadoresService->offsetMinimoCW4 = 0;
        $indicadoresService->offsetMaximoTW4 = 0;
        $indicadoresService->offsetMinimoDW4 = 0;
        $indicadoresService->offsetMaximoUW4 = 0;
        $indicadoresService->offsetMinimoOW4 = 0;
        $indicadoresService->acumFlAnulacionW4AlcistaActiva = false;
        $indicadoresService->acumFlAnulacionW4BajistaActiva = false;

        // Por ahora no utiliza filtros para anular candidatos
        $indicadoresService->acumconFiltrosCandidato = true;

        // Filtro outbound
        $indicadoresService->acumFlFiltroOutBound = false;

        $dataPrueba = DB::connection('trade')->table('trade.lecturas')
            ->select('id',
                    'fechalectura',
                    'chartTime as fecha',
                    'openPrice as open',
                    'highPrice as high',
                    'lowPrice as low',
                    'closePrice as close',
                    'volume')
            ->where('especie', $this->especie)
            ->where('fecha','>','2023-09-04')
            ->where('fecha','<','2023-09-09')
            ->get();
        do
        {
            $cantidadLeidos = 0;
            foreach($dataPrueba as $data)
            {
                $cantidadLeidos++;
            //$data = DB::connection('trade')->table('trade.lecturas')
            //    ->select('id',
            //        'fechalectura',
            //        'chartTime as fecha',
            //        'openPrice as open',
            //        'highPrice as high',
            //        'lowPrice as low',
            //        'closePrice as close',
            //        'volume')
            //->where('especie', $this->especie)
            //->orderBy('id', 'DESC')
            //->first();
                if ($data->id > $currId)
                    break;
            }
            if ($data->id != $currId)
            {
                $currId = $data->id;

                //Log::info($data->fechalectura.' '.$data->open." ".$data->high);

                $indicadoresService->generaDatosOrdenes($data, $this->especie, $this->calculoBase, 
                        $this->mmCorta, $this->mmLarga, $this->compresion, $this->largoVMA, $this->largoCCI, 
                        $this->largoXTL, $this->umbralXTL, $this->swingSize, $this->filtroSetup,
                        $this->factorCompresion);
            }
        } while ($currId == $data->id && $cantidadLeidos < count($dataPrueba));

        // Graba los que faltan
        if ($indicadoresService->acumItem <= 500)
            $indicadoresService->grabaTablaIndicadores();

        Log::info("Finalizo correctamente");
    }

    public function retryUntil()
    {
        return now()->addSeconds(10);
    }
}

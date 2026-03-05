<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Bus\Batchable;
use Illuminate\Support\Facades\App;
use DB;
use DateTime;

class Padron_Iibb implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, Batchable;

    public $filePath;
    public $jurisdiccion;
    public $provincia_id;
    public $tipopadron;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($filePath, $jurisdiccion, $provincia_id, $tipopadron = null)
    {
        $this->filePath = $filePath;
        $this->jurisdiccion = $jurisdiccion;
        $this->provincia_id = $provincia_id;

        if (isset($tipopadron))
            $this->tipopadron = $tipopadron;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $this->padron_iibbRepository = App::make(\App\Repositories\Configuracion\Padron_IibbRepositoryInterface::class);
        $this->padron_iibb_tasaRepository = App::make(\App\Repositories\Configuracion\Padron_Iibb_TasaRepositoryInterface::class);
        $this->padron_coeficiente_tucumanRepository = App::make(\App\Repositories\Configuracion\Padron_Coeficiente_TucumanRepositoryInterface::class);

        if (($handle = fopen($this->filePath, 'r')) !== false) 
        {
            try
            {
                set_time_limit(0);

                DB::beginTransaction();

                while (($columnas = fgetcsv($handle, 1000, ';')) !== false)
                {
                    switch($this->jurisdiccion)
                    {
                    case 902: // ARBA
                        $desdeFecha = DateTime::createFromFormat('dmY', $columnas[2]);
                        $hastaFecha = DateTime::createFromFormat('dmY', $columnas[3]);

                        $arrayPadron_Iibb = [
                            'cuit' => $columnas[4]
                        ];

                        //$padron_iibb = DB:$this->padron_iibbRepository->findPorCuit($columnas[4]);
                        $padron_iibb = DB::table('padron_iibb')->select('id', 'cuit')->where('cuit', $columnas[4])->first();

                        if ($padron_iibb)
                        {
                            $padron_iibb_id = $padron_iibb->id;
                            //DB::table('padron_iibb')->where('id', $padron_iibb->id)->update($arrayPadron_Iibb);
                            $this->padron_iibbRepository->update($arrayPadron_Iibb, $padron_iibb->id);
                        }
                        else
                        {
                            $padron_iibb = $this->padron_iibbRepository->create($arrayPadron_Iibb);
                            //$padron_iibb_id = DB::table('padron_iibb')->insertGetId($arrayPadron_Iibb);

                            $padron_iibb_id = $padron_iibb->id;
                        }

                        if ($columnas[0] == 'P')
                            $arrayPadron_Iibb_Tasa = [
                                'padron_iibb_id' => $padron_iibb_id,
                                'provincia_id' => $this->provincia_id,
                                'desdefecha' => $desdeFecha->format('Y-m-d'),
                                'hastafecha' => $hastaFecha->format('Y-m-d'),
                                'tasapercepcion' => $columnas[8],
                                'tasapercepciondiferencial' => null,
                                'tasaretenciondiferencial' => null,
                                'coeficiente' => null,
                                'riesgofiscal' => null,
                                'tipocontribuyente' => $columnas[5],
                                'excluido' => null
                            ];
                        else
                            $arrayPadron_Iibb_Tasa = [
                                'padron_iibb_id' => $padron_iibb_id,
                                'provincia_id' => $this->provincia_id,
                                'desdefecha' => $desdeFecha->format('Y-m-d'),
                                'hastafecha' => $hastaFecha->format('Y-m-d'),
                                'tasaretencion' => $columnas[8],
                                'tasapercepciondiferencial' => null,
                                'tasaretenciondiferencial' => null,
                                'coeficiente' => null,
                                'riesgofiscal' => null,
                                'tipocontribuyente' => $columnas[5],
                                'excluido' => null
                            ];

                        //$padron_iibb_tasa = $this->padron_iibb_tasaRepository->create($arrayPadron_Iibb_Tasa);
                        //$padron_tasa = DB::table('padron_iibb_tasa')->insert($arrayPadron_Iibb_Tasa);
                        $padron_iibb_tasa = $this->padron_iibb_tasaRepository->create($arrayPadron_Iibb_Tasa);
                        break;

                    case 904: // Cordoba
                        $desdeFecha = DateTime::createFromFormat('dmY', $columnas[2]);
                        $hastaFecha = DateTime::createFromFormat('dmY', $columnas[3]);

                        $arrayPadron_Iibb = [
                            'cuit' => $columnas[4]
                        ];

                        $padron_iibb = $this->padron_iibbRepository->findPorCuit($columnas[4]);

                        if ($padron_iibb)
                            $this->padron_iibbRepository->update($arrayPadron_Iibb, $padron_iibb->id);
                        else
                            $padron_iibb = $this->padron_iibbRepository->create($arrayPadron_Iibb);

                        // Busca registro de tasas
                        $padron_iibb_tasa = $this->padron_iibb_tasaRepository->findPorIdProvincia($padron_iibb->id, $this->provincia_id);

                        if ($columnas[0] == 'P')
                        {
                            $tasaPercepcion = str_replace(',', '.', $columnas[8]);

                            $arrayPadron_Iibb_Tasa = [
                                'padron_iibb_id' => $padron_iibb->id,
                                'provincia_id' => $this->provincia_id,
                                'desdefecha' => $desdeFecha->format('Y-m-d'),
                                'hastafecha' => $hastaFecha->format('Y-m-d'),
                                'tasapercepcion' => $tasaPercepcion,
                                'tasapercepciondiferencial' => null,
                                'tasaretenciondiferencial' => null,
                                'coeficiente' => null,
                                'riesgofiscal' => null,
                                'tipocontribuyente' => $columnas[5],
                                'excluido' => null
                            ];
                        }
                        else
                        {
                            $tasaRetencion = str_replace(',', '.', $columnas[8]);

                            $arrayPadron_Iibb_Tasa = [
                                'padron_iibb_id' => $padron_iibb->id,
                                'provincia_id' => $this->provincia_id,
                                'desdefecha' => $desdeFecha->format('Y-m-d'),
                                'hastafecha' => $hastaFecha->format('Y-m-d'),
                                'tasaretencion' => $tasaRetencion,
                                'tasapercepciondiferencial' => null,
                                'tasaretenciondiferencial' => null,
                                'coeficiente' => null,
                                'riesgofiscal' => null,
                                'tipocontribuyente' => $columnas[5],
                                'excluido' => null
                            ];                           
                        }

                        if ($padron_iibb_tasa)
                            $padron_iibb_tasa = $this->padron_iibb_tasaRepository->update($arrayPadron_Iibb_Tasa, $padron_iibb_tasa->id);
                        else
                            $padron_iibb_tasa = $this->padron_iibb_tasaRepository->create($arrayPadron_Iibb_Tasa);
                        break;

                    case 908: // Entre Rios
                        if (is_numeric(substr($columnas[0], 0, 1)))
                        {
                            $desdeFecha = DateTime::createFromFormat('dmY', $columnas[1]);
                            $hastaFecha = DateTime::createFromFormat('dmY', $columnas[2]);

                            $arrayPadron_Iibb = [
                                'cuit' => $columnas[3]
                            ];

                            $padron_iibb = $this->padron_iibbRepository->findPorCuit($columnas[3]);

                            if ($padron_iibb)
                                $this->padron_iibbRepository->update($arrayPadron_Iibb, $padron_iibb->id);
                            else
                                $padron_iibb = $this->padron_iibbRepository->create($arrayPadron_Iibb);

                            $tasaPercepcion = str_replace(',', '.', $columnas[7]);
                            $tasaRetencion = str_replace(',', '.', $columnas[8]);

                            // Busca registro de tasas
                            $padron_iibb_tasa = $this->padron_iibb_tasaRepository->findPorIdProvincia($padron_iibb->id, $this->provincia_id);

                            $arrayPadron_Iibb_Tasa = [
                                'padron_iibb_id' => $padron_iibb->id,
                                'provincia_id' => $this->provincia_id,
                                'desdefecha' => $desdeFecha->format('Y-m-d'),
                                'hastafecha' => $hastaFecha->format('Y-m-d'),
                                'tasapercepcion' => $tasaPercepcion,
                                'tasaretencion' => $tasaRetencion,
                                'tasapercepciondiferencial' => null,
                                'tasaretenciondiferencial' => null,
                                'coeficiente' => null,
                                'riesgofiscal' => null,
                                'tipocontribuyente' => $columnas[4],
                                'excluido' => null
                            ];

                            if ($padron_iibb_tasa)
                                $padron_iibb_tasa = $this->padron_iibb_tasaRepository->update($arrayPadron_Iibb_Tasa, $padron_iibb_tasa->id);
                            else
                                $padron_iibb_tasa = $this->padron_iibb_tasaRepository->create($arrayPadron_Iibb_Tasa);
                        }
                        break;                        

                    case 914: // Misiones
                        if (is_numeric(substr($columnas[0], 0, 1)))
                        {
                            $fecha = DateTime::createFromFormat('Ymd', $columnas[0]."01");
                            $desdeFecha = $fecha;

                            $fecha = DateTime::createFromFormat('Ymd', $columnas[0]."01");
                            $hastaFecha = $fecha->modify('last day of this month');

                            $arrayPadron_Iibb = [
                                'cuit' => $columnas[2]
                            ];

                            $padron_iibb = $this->padron_iibbRepository->findPorCuit($columnas[2]);

                            if ($padron_iibb)
                                $this->padron_iibbRepository->update($arrayPadron_Iibb, $padron_iibb->id);
                            else
                                $padron_iibb = $this->padron_iibbRepository->create($arrayPadron_Iibb);

                            $arrayPadron_Iibb_Tasa = [
                                    'padron_iibb_id' => $padron_iibb->id,
                                    'provincia_id' => $this->provincia_id,
                                    'desdefecha' => $desdeFecha->format('Y-m-d'),
                                    'hastafecha' => $hastaFecha->format('Y-m-d'),
                                    'tasapercepcion' => $columnas[4],
                                    'tasaretencion' => $columnas[4],
                                    'tasapercepciondiferencial' => null,
                                    'tasaretenciondiferencial' => null,
                                    'coeficiente' => null,
                                    'riesgofiscal' => null,
                                    'tipocontribuyente' => $columnas[6],
                                    'excluido' => null
                                ];

                            $padron_iibb_tasa = $this->padron_iibb_tasaRepository->create($arrayPadron_Iibb_Tasa);
                        }
                        break;

                    case 924: // Tucuman
                        if (is_numeric(substr($columnas[0], 0, 1)))
                        {
                            if ($this->tipopadron == 'T') // Tasas
                            {
                                $cuit = substr($columnas[0],0,11);
                                $excluido = substr($columnas[0],13,1);
                                $coeficiente = (float) substr($columnas[0],191,6);

                                if (substr($columnas[0],16,2) == 'CL')
                                    $tipoContribuyente = 'L';
                                else
                                    $tipoContribuyente = 'C';

                                $nombre = substr($columnas[0],40,60);

                                $fecha = DateTime::createFromFormat('Ymd', substr($columnas[0],20,8));
                                $desdeFecha = $fecha;

                                $fecha = DateTime::createFromFormat('Ymd', substr($columnas[0],30,8));
                                $hastaFecha = $fecha;

                                $arrayPadron_Iibb = [
                                    'cuit' => $cuit,
                                    'nombre' => $nombre
                                ];

                                $padron_iibb = $this->padron_iibbRepository->findPorCuit($cuit);

                                if ($padron_iibb)
                                    $this->padron_iibbRepository->update($arrayPadron_Iibb, $padron_iibb->id);
                                else
                                    $padron_iibb = $this->padron_iibbRepository->create($arrayPadron_Iibb);

                                // Busca registro de tasas
                                $padron_iibb_tasa = $this->padron_iibb_tasaRepository->findPorIdProvincia($padron_iibb->id, $this->provincia_id);

                                $arrayPadron_Iibb_Tasa = [
                                        'padron_iibb_id' => $padron_iibb->id,
                                        'provincia_id' => $this->provincia_id,
                                        'nombre' => $nombre,
                                        'desdefecha' => $desdeFecha->format('Y-m-d'),
                                        'hastafecha' => $hastaFecha->format('Y-m-d'),
                                        'tasapercepcion' => null,
                                        'tasaretencion' => null,
                                        'tasapercepciondiferencial' => null,
                                        'tasaretenciondiferencial' => null,
                                        'coeficiente' => $coeficiente,
                                        'riesgofiscal' => null,
                                        'tipocontribuyente' => $tipoContribuyente,
                                        'excluido' => $excluido
                                    ];

                                if ($padron_iibb_tasa)
                                    $padron_iibb_tasa = $this->padron_iibb_tasaRepository->update($arrayPadron_Iibb_Tasa, $padron_iibb_tasa->id);
                                else
                                    $padron_iibb_tasa = $this->padron_iibb_tasaRepository->create($arrayPadron_Iibb_Tasa);                                
                            }
                            else
                            {
                                $cuit = substr($columnas[0],0,11);
                                $excluido = substr($columnas[0],13,1);
                                $coeficiente = (float) substr($columnas[0],16,6);
                                $coeficienteFinal = (float) substr($columnas[0],184,6);

                                $tipoContribuyente = 'C';

                                $nombre = substr($columnas[0],32,60);

                                $fecha = DateTime::createFromFormat('Ymd', substr($columnas[0],24,6)."01");
                                $desdeFecha = $fecha;

                                $fecha = DateTime::createFromFormat('Ymd', substr($columnas[0],24,6)."01");
                                $hastaFecha = $fecha->modify('last day of this month');

                                // Busca registro de tasas
                                $padron_iibb_tasa = $this->padron_coeficiente_tucumanRepository->findPorCuit($cuit);

                                $arrayPadron_Coeficiente_Tucuman = [
                                        'cuit' => $cuit,
                                        'nombre' => $nombre,
                                        'desdefecha' => $desdeFecha->format('Y-m-d'),
                                        'hastafecha' => $hastaFecha->format('Y-m-d'),
                                        'coeficiente' => $coeficiente,
                                        'coeficientefinal' => $coeficienteFinal,
                                        'tipocontribuyente' => $tipoContribuyente,
                                        'excluido' => $excluido
                                    ];

                                if ($padron_iibb_tasa)
                                    $this->padron_coeficiente_tucumanRepository->update($arrayPadron_Coeficiente_Tucuman, $padron_iibb_tasa->id);
                                else
                                    $this->padron_coeficiente_tucumanRepository->create($arrayPadron_Coeficiente_Tucuman);
                            }
                        }
                        break;
                    }
                }
                DB::commit();
                
            } catch (\Exception $exception) {            
                DB::rollBack();
                
                return back()
                    ->with('mensaje', $exception->getMessage());
            }        
            fclose($handle);
        }
    }
}

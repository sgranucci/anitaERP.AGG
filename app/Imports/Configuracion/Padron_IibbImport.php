<?php

namespace App\Imports\Configuracion;

use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Row;
use Illuminate\Support\Facades\App;
use Auth;
use Carbon\Carbon;
use DB;
use DateTime;

class Padron_IibbImport implements ToModel, WithChunkReading
{
    private $padron_iibbRepository;
    private $padron_iibb_tasaRepository;
    private $provinciaRepository;
    private $provincia_id;
    private $jurisdiccion;
    private $id;

    public function  __construct(string $provincia_id, string $jurisdiccion)
    {
        $this->provincia_id = $provincia_id;
        $this->jurisdiccion = $jurisdiccion;
        $this->id = 1;
    }

    /**
    * @param array $row
    *
    * @return \Illuminate\Database\Eloquent\Model|null
    */
    public function model(array $row)
    {
        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $this->padron_iibbRepository = App::make(\App\Repositories\Configuracion\Padron_IibbRepositoryInterface::class);
        $this->padron_iibb_tasaRepository = App::make(\App\Repositories\Configuracion\Padron_Iibb_TasaRepositoryInterface::class);

        try {
            set_time_limit(0);

            DB::beginTransaction();

            switch($this->jurisdiccion)
            {
            case 902: // ARBA
                $columnas = explode(';', $row[0]);

                $desdeFecha = DateTime::createFromFormat('dmY', $columnas[2]);
                $hastaFecha = DateTime::createFromFormat('dmY', $columnas[3]);

                $arrayPadron_Iibb = [
                    'id' => $this->id++,
                    'cuit' => $columnas[4]
                ];

                $padron_iibb = $this->padron_iibbRepository->findPorCuit($columnas[4]);

                if ($padron_iibb)
                {
                    $operacion = 'update';
                    $this->padron_iibbRepository->update($arrayPadron_Iibb, $padron_iibb->id);
                }
                else
                {
                    $operacion = 'insert';
                    $padron_iibb = $this->padron_iibbRepository->create($arrayPadron_Iibb);
                }

                if ($columnas[0] == 'P')
                    $arrayPadron_Iibb_Tasa = [
                        'padron_iibb_id' => $padron_iibb->id,
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
                        'padron_iibb_id' => $padron_iibb->id,
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

                    if ($operacion == 'update')
                    {
                        foreach($padron_iibb as $tasa)
                        {
                            // Si ya esta cargado 
                            if ($tasa->provincia_id == $this->provincia_id &&
                                $tasa->desdefecha == $desdeFecha &&
                                $tasa->hastafecha == $hastaFecha)
                            {
                                $padron_iibb_tasa = $this->padron_iibb_tasaRepository->update($arrayPadron_Iibb_Tasa, $tasa->id);
                            }
                        }                
                    }
                    else
                        $padron_iibb_tasa = $this->padron_iibb_tasaRepository->create($arrayPadron_Iibb_Tasa);

                break;

            case 914: // Misiones
                $columnas = explode(';', $row[0]);

                $desdeFecha = DateTime::createFromFormat('dmY', $columnas[1]."01");
                $hastaFecha = $desdeFecha->modify('last day of this month');

                $arrayPadron_Iibb = [
                    'id' => $this->id++,
                    'cuit' => $columnas[3]
                ];

                $padron_iibb = $this->padron_iibbRepository->findPorCuit($columnas[3]);

                if ($padron_iibb)
                {
                    $operacion = 'update';
                    $this->padron_iibbRepository->update($arrayPadron_Iibb, $padron_iibb->id);
                }
                else
                {
                    $operacion = 'insert';
                    $padron_iibb = $this->padron_iibbRepository->create($arrayPadron_Iibb);
                }

                $arrayPadron_Iibb_Tasa = [
                        'padron_iibb_id' => $padron_iibb->id,
                        'provincia_id' => $this->provincia_id,
                        'desdefecha' => $desdeFecha->format('Y-m-d'),
                        'hastafecha' => $hastaFecha->format('Y-m-d'),
                        'tasapercepcion' => $columnas[5],
                        'tasaretencion' => $columnas[5],
                        'tasapercepciondiferencial' => null,
                        'tasaretenciondiferencial' => null,
                        'coeficiente' => null,
                        'riesgofiscal' => null,
                        'tipocontribuyente' => $columnas[7],
                        'excluido' => null
                    ];

                $padron_iibb_tasa = $this->padron_iibb_tasaRepository->create($arrayPadron_Iibb_Tasa);

                break;
            }
            DB::commit();
            
        } catch (\Exception $exception) {            
            DB::rollBack();
            
            return back()
                ->with('mensaje', $exception->getMessage());
        }        
    }

    // Usa chunk() para procesar el archivo en lotes
    public function chunkSize(): int
    {
        return 1000; // Ajusta el tamaño del fragmento según tus necesidades
    }    

    public function importa($file, $jurisdiccion, $provincia_id)
    {
        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $this->padron_iibbRepository = App::make(\App\Repositories\Configuracion\Padron_IibbRepositoryInterface::class);
        $this->padron_iibb_tasaRepository = App::make(\App\Repositories\Configuracion\Padron_Iibb_TasaRepositoryInterface::class);

        try {
            set_time_limit(0);

            DB::beginTransaction();

            switch($jurisdiccion)
            {
            case 902: // ARBA
                $columnas = explode(';', $row[0]);

                $desdeFecha = DateTime::createFromFormat('dmY', $columnas[2]);
                $hastaFecha = DateTime::createFromFormat('dmY', $columnas[3]);

                $arrayPadron_Iibb = [
                    'id' => $this->id++,
                    'cuit' => $columnas[4]
                ];

                $padron_iibb = $this->padron_iibbRepository->findPorCuit($columnas[4]);

                if ($padron_iibb)
                {
                    $operacion = 'update';
                    $this->padron_iibbRepository->update($arrayPadron_Iibb, $padron_iibb->id);
                }
                else
                {
                    $operacion = 'insert';
                    $padron_iibb = $this->padron_iibbRepository->create($arrayPadron_Iibb);
                }

                if ($columnas[0] == 'P')
                    $arrayPadron_Iibb_Tasa = [
                        'padron_iibb_id' => $padron_iibb->id,
                        'provincia_id' => $provincia_id,
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
                        'padron_iibb_id' => $padron_iibb->id,
                        'provincia_id' => $provincia_id,
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

                    if ($operacion == 'update')
                    {
                        foreach($padron_iibb as $tasa)
                        {
                            // Si ya esta cargado 
                            if ($tasa->provincia_id == $provincia_id &&
                                $tasa->desdefecha == $desdeFecha &&
                                $tasa->hastafecha == $hastaFecha)
                            {
                                $padron_iibb_tasa = $this->padron_iibb_tasaRepository->update($arrayPadron_Iibb_Tasa, $tasa->id);
                            }
                        }                
                    }
                    else
                        $padron_iibb_tasa = $this->padron_iibb_tasaRepository->create($arrayPadron_Iibb_Tasa);

                break;

            case 914: // Misiones
                if (($handle = fopen($file, "r")) !== FALSE) {
                    while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {

                        if (is_numeric(substr($data[0], 0, 1)))
                        {
                            $columnas = explode(';', $data[0]);

                            $fecha = DateTime::createFromFormat('Ymd', $columnas[0]."01");
                            $desdeFecha = $fecha;

                            $fecha = DateTime::createFromFormat('Ymd', $columnas[0]."01");
                            $hastaFecha = $fecha->modify('last day of this month');

                            $arrayPadron_Iibb = [
                                'cuit' => $columnas[2]
                            ];

                            $padron_iibb = $this->padron_iibbRepository->findPorCuit($columnas[2]);

                            if ($padron_iibb)
                            {
                                $operacion = 'update';
                                $this->padron_iibbRepository->update($arrayPadron_Iibb, $padron_iibb->id);
                            }
                            else
                            {
                                $operacion = 'insert';
                                $padron_iibb = $this->padron_iibbRepository->create($arrayPadron_Iibb);
                            }

                            $arrayPadron_Iibb_Tasa = [
                                    'padron_iibb_id' => $padron_iibb->id,
                                    'provincia_id' => $provincia_id,
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
                    }
                    fclose($handle);
                }
                break;
            }
            DB::commit();
            
        } catch (\Exception $exception) {            
            DB::rollBack();
            
            return back()
                ->with('mensaje', $exception->getMessage());
        }        
    }    
}


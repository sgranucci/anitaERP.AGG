<?php

namespace App\Imports\Configuracion;

use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Row;
use Illuminate\Support\Facades\App;
use Auth;
use Carbon\Carbon;
use DB;

class Padron_ExclusionpercepcionivaImport implements ToModel
{
    private $padron_exclusionpercepcionivaRepository;
    private $id;

    public function  __construct()
    {
        $this->id = 1;
    }

    /**
    * @param array $row
    *
    * @return \Illuminate\Database\Eloquent\Model|null
    */
    public function model(array $row)
    {
        $this->padron_exclusionpercepcionivaRepository = App::make(\App\Repositories\Configuracion\Padron_ExclusionpercepcionivaRepositoryInterface::class);

        $columnas = explode(';', $row[0]);

        // Si el cuit es un numero importa
        if (substr($columnas[0],1,1) >= '0' and substr($columnas[0],1,1) <= '9')
        {
            $desdefecha = strtotime($columnas[2] ?? '');
            $fechaDesde = date('Y-m-d', $desdefecha);
            
            if (!isset($columnas[3]))
                $fechaHasta = NULL;
            else
            {
                $hastafecha = strtotime($columnas[3] ?? '');
                $fechaHasta = date('Y-m-d', $hastafecha);

                if ($fechaHasta == "1969-12-31")
                    $fechaHasta = NULL;
            }

            $arrayPadron_Exclusionpercepcioniva = [
                'id' => $this->id++,
                'nombre' => $columnas[1],
                'cuit' => $columnas[0],
                'desdefecha' => $fechaDesde,
                'hastafecha' => $fechaHasta
            ];

            $this->padron_exclusionpercepcionivaRepository->create($arrayPadron_Exclusionpercepcioniva);
        }
    }
}

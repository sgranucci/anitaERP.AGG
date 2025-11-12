<?php

namespace App\Imports\Configuracion;

use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Row;
use Illuminate\Support\Facades\App;
use Auth;
use Carbon\Carbon;
use DB;

class Padron_MipymeImport implements ToModel
{
    private $padron_mipymeRepository;
    private $clienteRepository;
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
        $this->padron_mipymeRepository = App::make(\App\Repositories\Configuracion\Padron_MipymeRepositoryInterface::class);
        $this->clienteRepository = App::make(\App\Repositories\Ventas\ClienteRepositoryInterface::class);

        $columnas = explode(';', $row[0]);
        
        // Si el documento es un numero importa
        if (substr($columnas[0],1,1) >= '0' and substr($columnas[0],1,1) <= '9')
        {
            $fecha = strtotime($columnas[3] ?? '');
            $fechaInicio = date('Y-m-d', $fecha);

            $arrayPadron_Mipyme = [
                'id' => $this->id++,
                'nombre' => $columnas[1],
                'cuit' => $columnas[0],
                'actividad' => $columnas[2] ?? '',
                'fechainicio' => $fechaInicio
            ];

            $this->padron_mipymeRepository->create($arrayPadron_Mipyme);

            // Actualiza el cliente poniendo modo de facturacion en 'C' Factura de credito
            $this->clienteRepository->actualizaPadronMipymePorCuit($arrayPadron_Mipyme['cuit'], 'C');
        }
    }
}

<?php

namespace App\Imports\Configuracion;

use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Row;
use Illuminate\Support\Facades\App;
use Auth;
use Carbon\Carbon;
use DB;

class Retencionimpositiva_ArcaImport implements ToModel, WithHeadingRow
{
    private $retencionimpositiva_arcaRepository;
    private $id, $empresa_id;

    public function  __construct($empresa_id)
    {
        $this->empresa_id = $empresa_id;
    }

    /**
    * @param array $row
    *
    * @return \Illuminate\Database\Eloquent\Model|null
    */
    public function model(array $row)
    {
        $this->retencionimpositiva_arcaRepository = App::make(\App\Repositories\Configuracion\Retencionimpositiva_ArcaRepositoryInterface::class);

        // Si el documento es un numero importa
        if (substr($row['cuit_agente_retperc'],1,1) >= '0' and substr($row['cuit_agente_retperc'],1,1) <= '9')
        {
            $fecha = str_replace("/", "-", $row['fecha_retperc']); 
            $timestamp = strtotime($fecha);
            $fechaRetencionPercepcion = date("Y-m-d", $timestamp);
            $fecha = str_replace("/", "-", $row['fecha_comprobante']); 
            $timestamp = strtotime($fecha);
            $fechaComprobante = date("Y-m-d", $timestamp);
            $fecha = str_replace("/", "-", $row['fecha_registracion_dj_agret']); 
            $timestamp = strtotime($fecha);
            $fechaRegistracion = date("Y-m-d", $timestamp);

            $arrayRetencionimpositiva_Arca = [
                'empresa_id' => $this->empresa_id,
                'cuit' => $row['cuit_agente_retperc'],
                'nombre' => $row['denominacion_o_razon_social'],
                'impuesto' => $row['impuesto'],
                'descripcionimpuesto' => $row['descripcion_impuesto'],
                'regimen' => $row['regimen'],                
                'descripcionregimen' => $row['descripcion_regimen'],
                'fecharetencion' => $fechaRetencionPercepcion,
                'numerocertificado' => $row['numero_certificado'],
                'descripcionoperacion' => $row['descripcion_operacion'],
                'montoretencion' => $row['importe_retperc'],
                'numerocomprobante' => $row['numero_comprobante'],
                'fechacomprobante' => $fechaComprobante,
                'descripcioncomprobante' => $row['descripcion_comprobante'],
                'fecharegistracion' => $fechaRegistracion
            ];

            $this->retencionimpositiva_arcaRepository->create($arrayRetencionimpositiva_Arca);
        }
    }
}

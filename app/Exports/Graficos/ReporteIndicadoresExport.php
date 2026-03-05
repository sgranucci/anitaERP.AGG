<?php

namespace App\Exports\Graficos;

use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class ReporteIndicadoresExport implements WithMultipleSheets
{
    use Exportable;
    private $desdeFecha, $hastaFecha;
    private $desdeHora, $hastaHora;
    private $especie;
    private $compresion, $compresiontxt, $factorCompresion;
    private $calculoBase, $mmCorta, $mmLarga, $calculoBase_enum;
    private $largoVMA, $largoCCI, $largoXTL, $umbralXTL;
    private $cantidadContratos;
	private $k1, $k2;
    private $dataAnterior = [];
    private $fechaUltimaLectura;
    private $swingSize;
    protected $dates = ['fecha'];
    private $indicadores = [];
    private $operaciones = [];
    private $filtroSetup;
    private $administracionPosicion, $tiempo;

    public function __construct()
    {

    }

    /**
     * @return array
     */
    public function sheets(): array
    {
        $sheets = [];
        $calculoBaseTxt = $this->calculoBase_enum[$this->calculoBase];

        return [
            'Worksheet 1' => new IndicadoresExport($this->desdeFecha,
                                                    $this->hastaFecha,
                                                    $this->desdeHora,
                                                    $this->hastaHora,
                                                    $this->especie,
                                                    $this->compresion,
                                                    $this->mmCorta,
                                                    $this->mmLarga,
                                                    $this->largoVMA,
                                                    $this->largoCCI,
                                                    $this->largoXTL,
                                                    $this->umbralXTL,
                                                    $this->swingSize,
                                                    $this->filtroSetup,
                                                    $this->indicadores,
                                                    $this->cantidadContratos,
                                                    $calculoBaseTxt,
                                                    $this->administracionPosicion,
                                                    $this->tiempo),
            'Worksheet 2' => new OperacionesExport($this->operaciones)
        ];
    }

    public function parametros($desdefecha, $hastafecha, $desdehora, $hastahora, $especie, $calculobase, 
                                $mmcorta, $mmlarga, $compresion, $largovma, $largocci, $largoxtl,
                                $umbralxtl, $calculobase_enum, $swingSize, $filtroSetup, 
                                $cantidadContratos, $indicadores, $operaciones, $administracionposicion, $tiempo)
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
        $this->filtroSetup = $filtroSetup;
        $this->cantidadContratos = $cantidadContratos;
        $this->indicadores = $indicadores;
        $this->operaciones = $operaciones;
        $this->administracionPosicion = $administracionposicion;
        $this->tiempo = $tiempo;
        
        return $this;
    }
}
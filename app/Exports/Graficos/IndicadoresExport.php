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
    private $cantidadContratos;
    private $calculoBaseTxt;
	private $k1, $k2;
    private $dataAnterior = [];
    private $fechaUltimaLectura;
    private $swingSize;
    private $datas = [];
    public $operaciones = [];
	protected $dates = ['fecha'];
    private $filtroSetup;
    private $administracionPosicion, $tiempo;

    public function __construct($desdefecha, $hastafecha, $desdehora, $hastahora, $especie, $compresion,
                                $mmcorta, $mmlarga, $largovma, $largocci, $largoxtl,
                                $umbralxtl, $swingSize, $filtroSetup, $datos, $cantidadContratos, $calculoBaseTxt,
                                $administracionPosicion, $tiempo)
    {
        $this->desdeFecha = $desdefecha;
		$this->hastaFecha = $hastafecha;
		$this->desdeHora = $desdehora;
		$this->hastaHora = $hastahora;
        $this->especie = $especie;
		$this->compresion = $compresion;
        $this->mmCorta = $mmcorta;
        $this->mmLarga = $mmlarga;
        $this->largoVMA = $largovma;
        $this->largoCCI = $largocci;
        $this->largoXTL = $largoxtl;
        $this->umbralXTL = $umbralxtl;
        $this->swingSize = $swingSize;
        $this->datas = $datos;
        $this->cantidadContratos = $cantidadContratos;
        $this->calculoBaseTxt = $calculoBaseTxt;
        $this->filtroSetup = $filtroSetup;
        $this->administracionPosicion = $administracionPosicion;
        $this->tiempo = $tiempo;

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
    }
    
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

        $this->k2 = 2 / ($this->mmCorta + $this->mmLarga);
        $this->k1 = 1 - $this->k2;

        return view('exports.graficos.indicadores', ['comprobantes' => $this->datas,
                                                    'desdefecha' => $desde_fecha, 'hastafecha' => $hasta_fecha, 
                                                    'desdehora' => $this->desdeHora, 'hastahora' => $this->hastaHora, 
                                                    'compresiontxt' => $this->compresiontxt,
                                                    'mmcorta' => $this->mmCorta,
                                                    'mmlarga' => $this->mmLarga,
                                                    'largovma' => $this->largoVMA,
                                                    'largocci' => $this->largoCCI,
                                                    'largoxtl' => $this->largoXTL,
                                                    'umbralxtl' => $this->umbralXTL,
                                                    'calculobasetxt' => $this->calculoBaseTxt,
                                                    'swingsize' => $this->swingSize,
                                                    'especie' => $this->especie,
                                                    'filtroSetup' => $this->filtroSetup,
                                                    'cantidadcontratos' => $this->cantidadContratos,
                                                    'administracionposicion' => $this->administracionPosicion,
                                                    'tiempo' => $this->tiempo]);
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
            'BQ' => 100,
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
}

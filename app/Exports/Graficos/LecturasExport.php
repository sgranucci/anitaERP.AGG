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

class LecturasExport implements FromView, WithColumnFormatting, WithMapping, ShouldAutoSize, WithStyles, WithColumnWidths, WithEvents, WithTitle
{
	use Exportable;
	private $desdeFecha, $hastaFecha;
    private $desdeHora, $hastaHora;
    private $compresion, $compresiontxt, $factorCompresion;
	protected $dates = ['fecha'];

	public function view(): View
	{
        if ($this->factorCompresion == 3600)
        {
            $fechaAnterior = date("d-m-Y",strtotime($this->desdeFecha."- 1 days")); 
            $desde_fecha = strtotime($fechaAnterior.' '.'19:00')*1000;
        }
        else
            $desde_fecha = strtotime($this->desdeFecha.' '.$this->desdeHora)*1000;

        $hasta_fecha = strtotime($this->hastaFecha.' '.$this->hastaHora)*1000;
        
		$data = DB::connection('trade')->table('trade.lecturas')
				->select('fechaChar as fechastr',
                         'chartTime as fecha',
						 'openPrice as open',
						 'highPrice as high',
						 'lowPrice as low',
						 'closePrice as close',
						 'volume')
				->whereBetween('chartTime', [$desde_fecha, $hasta_fecha])
                ->get();

        // Procesa compresion
        $open = $close = $low = $high = $totVolume = 0;
        $cantLectura = 0;
        $datas = [];
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

            if ($this->factorCompresion > 1 && !$flEmpezoRango)
            {
                $minutoLect = date('i', ceil($lectura->fecha/1000));
                
                if ($minutoLect % $this->factorCompresion == 0)
                    $flEmpezoRango = true;
            }
            if ($flEmpezoRango)
            {
                $flCorte = false;
                // Corte si es por dia
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
                    
                    if ($difMinutos >= $this->factorCompresion)
                        $flCorte = true;
                }
                
                if ($flCorte)
                {
                    $datas[] = ['fechastr'=>$lectura->fechastr, 'fecha'=>$fecha, 'horainicio'=>$horaInicio,
                                'open'=>$open, 'close'=>$close,
                                'low'=>$low,'high'=>$high,'volume'=>$totVolume];
                    $cantLectura = 0;
                    $low = $high = $totVolume = $open = $close = 0;
                }

                $fecha = $lectura->fecha;
                $totVolume += $lectura->volume;
                $cantLectura++;

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
            $datas[] = ['fechastr'=>$lectura->fechastr, 'fecha'=>$fecha, 'horainicio'=>$horaInicio,
                        'open'=>$open, 'close'=>$close,
                        'low'=>$low,'high'=>$high,'volume'=>$totVolume];
        }
        return view('exports.graficos.lecturas', ['comprobantes' => $datas,
                  'desdefecha' => $desde_fecha, 'hastafecha' => $hasta_fecha, 
                  'desdehora' => $this->desdeHora, 'hastahora' => $this->hastaHora, 'compresiontxt' => $this->compresiontxt]);
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
        return 'Lecturas ESU22';
    }

	public function parametros($desdefecha, $hastafecha, $desdehora, $hastahora, $compresion)
	{
        $this->desdeFecha = $desdefecha;
		$this->hastaFecha = $hastafecha;
		$this->desdeHora = $desdehora;
		$this->hastaHora = $hastahora;
		$this->compresion = $compresion;

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

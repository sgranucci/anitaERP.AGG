<?php

namespace App\Exports\Produccion;

use App\Services\Ventas\OrdentrabajoService;
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

class TotalParesExport implements FromView, WithColumnFormatting, WithMapping, ShouldAutoSize, WithStyles, WithColumnWidths, WithEvents, WithTitle
{
	use Exportable;
	private $desdeFecha, $hastaFecha;
    private $ordenestrabajo;
    private $apertura;
	protected $dates = ['fecha'];

    private $ordentrabajoService;

    public function __construct(
                                OrdentrabajoService $ordentrabajoservice
								)
    {
        $this->ordentrabajoService = $ordentrabajoservice;
    }

	public function view(): View
	{
        // Lee informacion del listado
        $data = $this->ordentrabajoService->generaDatosRepTotalPares($this->desdeFecha, 
                                                    $this->hastaFecha, 
                                                    $this->ordenestrabajo,
                                                    $this->apertura);
        
        $tareas = config('repestadoot.tareas');

        // Arma array de tareas para encabezado por periodo
        $datas = [];
        $encData = [];
        $tareaData = [];
        $currPeriodo = '';
        foreach($data['data'] as $otperiodo)
        {
            // Encabezado de OT
            if ($currPeriodo != $otperiodo->periodo)
            {
                // Si ya tiene un periodo abierto en proceso cierra 
                if ($currPeriodo != '')
                {
                    $datas[] = [
                            'encabezado' => $encData, 
                            'tareas' => $tareaData
                    ];
                    $encData = [];
                }
                $currPeriodo = $otperiodo->periodo;

                // Arma encabezado
                if ($this->apertura == 'SEMANAL')
                {
                    $semana = getFirstDayWeek($otperiodo->periodo, $otperiodo->year);
                    $encData[] = [
                            'periodo' => $otperiodo->periodo,
                            'detalle' => 'Semana del '.date('d-m-Y', strtotime($semana['start'])).' al '.date('d-m-Y', strtotime($semana['end']))
                    ];
                }
                else
                    $encData[] = [
                            'periodo' =>$otperiodo->periodo
                            ];
                $tareaData = [];
            }

            // Busca columna con tarea
            foreach ($tareas as $key => $value)
            {
                // Si la tarea coincide con ids de la columna asigna
                if (in_array($otperiodo->tarea_id, $value))
                {
                    $tareaData[] = [
                                'columna' => $key, 
                                'periodo' => $otperiodo->periodo, 
                                'pares' => $otperiodo->pares, 
                                ];
                }
            }
        }
        // Cierra ultimo ciclo
        if ($currPeriodo != '')
        {
            $datas[] = [
                    'encabezado' => $encData, 
                    'tareas' => $tareaData
            ];
        }

        return view('exports.produccion.totalpares', [
                        'comprobantes' => $datas,
                        'tareas' => $tareas,
                        'desdefecha' => $this->desdeFecha, 
                        'hastafecha' => $this->hastaFecha,
                        'ordenestrabajo' => $this->ordenestrabajo,
                        'apertura' => $this->apertura
                        ]
                    );
	}

	public function columnFormats(): array
    {
        return [
            'A' => NumberFormat::FORMAT_TEXT,
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
            'A' => ['font' => ['bold' => true]],
        ];
    }

	public function columnWidths(): array
    {
        return [

        ];
    }

	public function registerEvents(): array
    {
    
        return [
            AfterSheet::class    => function(AfterSheet $event) {

                $event->sheet->getDelegate()->freezePane('A4');

            },
            AfterSheet::class    => function(AfterSheet $event) {
                $cellRange = 'A5:'.$event->sheet->getHighestColumn().$event->sheet->getHighestRow();
                
                $event->sheet->getStyle($cellRange)->applyFromArray([
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                            'color' => ['argb' => '000000'],
                        ],
                    ],
                ])->getAlignment()->setWrapText(true);
            },
         ];
    }

	public function title(): string
    {
        return 'Total de Pares';
    }

	public function parametros($desdefecha, $hastafecha, $ordenestrabajo, $apertura)
	{
        $this->desdeFecha = $desdefecha;
		$this->hastaFecha = $hastafecha;
        $this->ordenestrabajo = $ordenestrabajo;
        $this->apertura = $apertura;

        return $this;
	}

}

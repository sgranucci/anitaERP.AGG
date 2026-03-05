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

class ConsumoCajaExport implements FromView, WithColumnFormatting, WithMapping, ShouldAutoSize, WithStyles, WithColumnWidths, WithEvents, WithTitle
{
	use Exportable;
	private $desdeFecha, $hastaFecha;
    private $ordenesTrabajo;
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
        $data = $this->ordentrabajoService->generaDatosRepConsumoCaja($this->desdeFecha, 
                                                    $this->hastaFecha, 
                                                    $this->ordenesTrabajo
                                                    );

        return view('exports.produccion.reporteconsumocaja.reporteconsumocaja', [
                        'cajas' => $data['cajas'],
                        'cajasespeciales' => $data['cajasespeciales'],
                        'desdefecha' => $this->desdeFecha, 
                        'hastafecha' => $this->hastaFecha,
                        'ordenestrabajo' => $this->ordenesTrabajo,
                        ]
                    );
	}

	public function columnFormats(): array
    {
        return [
            'A' => NumberFormat::FORMAT_TEXT,
            'C' => NumberFormat::FORMAT_TEXT,
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
            3   => ['font' => ['bold' => true,
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
            'D' => ['font' => ['bold' => true]],
        ];
    }

	public function columnWidths(): array
    {
        return [
                'A' => 20,
                'B' => 10,
                'C' => 30,
                'E' => 20,
                'F' => 10,
                'G' => 30,
        ];
    }

	public function registerEvents(): array
    {
    
        return [
            AfterSheet::class    => function(AfterSheet $event) {

                $event->sheet->getDelegate()->freezePane('A3');

            },
            AfterSheet::class    => function(AfterSheet $event) {
                $cellRange = 'A3:'.$event->sheet->getHighestColumn().$event->sheet->getHighestRow();
                
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
        return 'Consumo de OT';
    }

	public function parametros($desdefecha, $hastafecha, $ordenestrabajo)
	{
        $this->desdeFecha = $desdefecha;
		$this->hastaFecha = $hastafecha;
        $this->ordenesTrabajo = $ordenestrabajo;
        
        return $this;
	}

}

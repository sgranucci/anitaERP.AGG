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

class ProgArmadoExport implements FromView, WithColumnFormatting, WithMapping, ShouldAutoSize, WithStyles, WithColumnWidths, WithEvents, WithTitle
{
	use Exportable;
    private $ordenesTrabajo;
    private $tipoProgramacion;
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
        $data = $this->ordentrabajoService->generaDatosRepProgArmado(
                                                    $this->ordenesTrabajo,
                                                    $this->tipoProgramacion
                                                    );
    
        $_fecha = Carbon::now();
        return view('exports.produccion.reporteprogarmado.reporteprogarmado', [
                        'data' => $data,
                        'ordenestrabajo' => $this->ordenesTrabajo,
                        'fecha' => $_fecha
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
            'C' => ['font' => ['bold' => true]],
            'F' => ['font' => ['bold' => true]],
        ];
    }

	public function columnWidths(): array
    {
        return [
                'A' => 8,
                'J' => 8,
                'G' => 20,
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
        return 'Programación de armado';
    }

	public function parametros($ordenestrabajo, $tipoprogramacion)
	{
        $this->ordenesTrabajo = $ordenestrabajo;
        $this->tipoProgramacion = $tipoprogramacion;
        
        return $this;
	}

}

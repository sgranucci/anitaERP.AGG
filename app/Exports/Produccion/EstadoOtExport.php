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

class EstadoOtExport implements FromView, WithColumnFormatting, WithMapping, ShouldAutoSize, WithStyles, WithColumnWidths, WithEvents, WithTitle
{
	use Exportable;
	private $desdeFecha, $hastaFecha;
    private $ordenestrabajo;
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
        $data = $this->ordentrabajoService->generaDatosRepEstadoOt($this->desdeFecha, $this->hastaFecha, $this->ordenestrabajo);

        // Asigna desde configuracion columnas del encabezado
        $tareas = config('repestadoot.tareas');

        // Arma array de tareas para encabezado
        $datas = [];
        $encData = [];
        $tareaData = [];
        $currOrdenTrabajo_id = 0;
        foreach($data['data'] as $ottarea)
        {
            // Encabezado de OT
            if ($currOrdenTrabajo_id != $ottarea->ordentrabajo_id)
            {
                // Si ya tiene una OT en proceso cierra 
                if ($currOrdenTrabajo_id != 0)
                {
                    if (count($tareaData) > 0)
                        $datas[] = [
                            'encabezado' => $encData, 
                            'tareas' => $tareaData
                        ];

                    $encData = [];
                }
                $currOrdenTrabajo_id = $ottarea->ordentrabajo_id;

                if (!$ottarea->pedidos_combinacion)
                {
                    // Lee el item desde la OT
                    $ot = $this->ordentrabajoService->traeArticuloOtPorId($ottarea->ordentrabajo_id);

                    $sku = $ot['sku'];
                    $linea = $ot['nombrelinea'];
                    $pares = $ot['pares'];
                }
                else
                {
                    $sku = $ottarea->pedidos_combinacion->articulos->sku;
                    $linea = $ottarea->pedidos_combinacion->articulos->lineas->nombre;
                    $pares = $ottarea->pares;
                }
                
                // Arma encabezado
                $encData[] = [
                            'ordentrabajo_id' => $ottarea->ordentrabajo_id,
                            'codigo' => $ottarea->ordenestrabajo->codigo ?? '',
                            'articulo' => $sku,
                            'linea' => $linea,
                            'pares' => $pares
                ];
                $tareaData = [];
            }

            // Busca columna con tareas
            foreach ($tareas as $key => $value)
            {
                // Si la tarea coincide con ids de la columna asigna
                if (in_array($ottarea->tarea_id, $value))
                {
                    $flRepetida = false;
                    if (count($tareaData) > 0)
                    {
                        if ($tareaData[count($tareaData)-1]['tarea_id'] == $ottarea->tareas->id)
                            $flRepetida = true;
                    }
                    if (!$flRepetida)
                        $tareaData[] = ['columna' => $key, 
                                'fechainicio' => $ottarea->desdefecha, 
                                'fechafin' => $ottarea->hastafecha, 
                                'empleado_id' => $ottarea->empleado_id,
                                'empleado' => isset($ottarea->empleados->nombre) ? 
                                                $ottarea->empleados->nombre : 
                                                $ottarea->usuarios->nombre,
                                'tarea_id' => $ottarea->tareas->id, 
                                'tarea_nombre' => $ottarea->tareas->nombre
                            ];
                }
            }
        }
        
        // Cierra ultimo ciclo
        if ($currOrdenTrabajo_id != 0)
        {
            if (count($tareaData) > 0)
                $datas[] = [
                    'encabezado' => $encData, 
                    'tareas' => $tareaData
                ];
        }

        return view('exports.produccion.estadoot', [
                        'comprobantes' => $datas,
                        'tareas' => $tareas,
                        'desdefecha' => $this->desdeFecha, 
                        'hastafecha' => $this->hastaFecha,
                        'ordenestrabajo' => $this->ordenestrabajo
                        ]
                    );
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
            5   => ['font' => ['bold' => true,
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
            'A' => 15,

        ];
    }

	public function registerEvents(): array
    {
    
        return [
            AfterSheet::class    => function(AfterSheet $event) {

                $event->sheet->getDelegate()->freezePane('A5');

            },
            AfterSheet::class    => function(AfterSheet $event) {
                $cellRange = 'A6:'.$event->sheet->getHighestColumn().$event->sheet->getHighestRow();
                
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
        return 'Estado de OT en Fabrica';
    }

	public function parametros($desdefecha, $hastafecha, $ordenestrabajo)
	{
        $this->desdeFecha = $desdefecha;
		$this->hastaFecha = $hastafecha;
        $this->ordenestrabajo = $ordenestrabajo;

        return $this;
	}

}

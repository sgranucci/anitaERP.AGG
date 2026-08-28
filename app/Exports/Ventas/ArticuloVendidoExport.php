<?php

namespace App\Exports\Ventas;

use App\Services\Ventas\RepArticuloVendidoFerliService;
use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ArticuloVendidoExport implements FromView, WithColumnFormatting, WithMapping, ShouldAutoSize, WithStyles, WithColumnWidths, WithEvents, WithTitle
{
    use Exportable;

    private $tipoOrigen;

    private $desdefecha;

    private $hastafecha;

    private $desdearticulo_id;

    private $hastaarticulo_id;

    private $desdecliente_id;

    private $hastacliente_id;

    private $desdelinea_id;

    private $hastalinea_id;

    private $mventa_id;

    private $nombremventa;

    private $logoPath;

    private $filaEncabezadoColumnas = 5;

    private $reporteService;

    public function __construct(RepArticuloVendidoFerliService $reporteService)
    {
        $this->reporteService = $reporteService;
    }

    public function view(): View
    {
        $reporte = $this->reporteService->generaDatosRepArticulosVendidos(
            $this->tipoOrigen,
            $this->desdefecha,
            $this->hastafecha,
            $this->desdearticulo_id,
            $this->hastaarticulo_id,
            $this->desdecliente_id,
            $this->hastacliente_id,
            $this->desdelinea_id,
            $this->hastalinea_id,
            $this->mventa_id
        );

        $titulo = $this->tipoOrigen == 'NACIONAL'
            ? 'LISTADO DE VENTAS POR ARTICULO NACIONAL CON IDENTIFICACION DE CLIENTE'
            : 'LISTADO DE VENTAS POR ARTICULO IMPORTADO CON IDENTIFICACION DE CLIENTE';

        $this->logoPath = $this->resolverRutaLogo();

        return view('exports.ventas.reportearticulovendido.reportearticulovendido', [
            'titulo' => $titulo,
            'empresa' => config('app.empresa'),
            'articulos' => $reporte['articulos'],
            'totales' => $reporte['totales'],
            'desdefecha' => $this->desdefecha,
            'hastafecha' => $this->hastafecha,
            'marca' => $this->nombremventa,
            'filaEncabezadoColumnas' => $this->filaEncabezadoColumnas,
        ]);
    }

    public function columnFormats(): array
    {
        return [];
    }

    public function map($row): array
    {
        return [];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            2 => ['font' => ['bold' => true, 'size' => 14, 'name' => 'Arial', 'color' => ['rgb' => '17202A']]],
            $this->filaEncabezadoColumnas => [
                'font' => ['bold' => true, 'size' => 10, 'name' => 'Arial', 'color' => ['rgb' => 'FFFFFF']],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'color' => ['rgb' => '4472C4'],
                ],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ],
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();

                if ($this->logoPath && file_exists($this->logoPath)) {
                    $drawing = new Drawing;
                    $drawing->setName('Logo');
                    $drawing->setDescription('Logo empresa');
                    $drawing->setPath($this->logoPath);
                    $drawing->setHeight(52);
                    $drawing->setCoordinates('A1');
                    $drawing->setOffsetX(5);
                    $drawing->setOffsetY(5);
                    $drawing->setWorksheet($sheet);
                }

                $sheet->getRowDimension(1)->setRowHeight(48);
                $sheet->mergeCells('B1:J1');
                $sheet->mergeCells('B2:J2');
                $sheet->mergeCells('B3:J3');
                $sheet->getStyle('B1:I1')->getAlignment()->setVertical(
                    Alignment::VERTICAL_CENTER
                );

                $sheet->freezePane('A'.($this->filaEncabezadoColumnas + 1));

                $sheet->getStyle('A'.$this->filaEncabezadoColumnas.':J'.$this->filaEncabezadoColumnas)
                    ->getBorders()->getAllBorders()
                    ->setBorderStyle(Border::BORDER_THIN);
            },
        ];
    }

    private function resolverRutaLogo(): string
    {
        $nombreMarca = 'Ferli';
        if ($this->nombremventa && $this->nombremventa != 'Todas las marcas') {
            $nombreMarca = preg_replace('/[^A-Za-z0-9]/', '', $this->nombremventa);
        }

        $rutaMarca = public_path('storage/imagenes/logos/logo'.$nombreMarca.'.jpg');
        if (file_exists($rutaMarca)) {
            return $rutaMarca;
        }

        $rutaFerli = public_path('storage/imagenes/logos/logoFerli.jpg');
        if (file_exists($rutaFerli)) {
            return $rutaFerli;
        }

        $logos = glob(public_path('storage/imagenes/logos/logo*.jpg'));

        return $logos ? $logos[0] : '';
    }

    public function columnWidths(): array
    {
        return [
            'A' => 14,
            'B' => 7,
            'C' => 20,
            'D' => 14,
            'E' => 10,
            'F' => 12,
            'G' => 5,
            'H' => 18,
            'I' => 10,
            'J' => 38,
        ];
    }

    public function title(): string
    {
        return $this->tipoOrigen == 'NACIONAL' ? 'Articulos Nacionales' : 'Articulos Importados';
    }

    public function parametros($tipoOrigen, $desdefecha, $hastafecha,
        $desdearticulo_id, $hastaarticulo_id,
        $desdecliente_id, $hastacliente_id,
        $desdelinea_id, $hastalinea_id,
        $mventa_id, $nombremventa)
    {
        $this->tipoOrigen = $tipoOrigen;
        $this->desdefecha = $desdefecha;
        $this->hastafecha = $hastafecha;
        $this->desdearticulo_id = $desdearticulo_id;
        $this->hastaarticulo_id = $hastaarticulo_id;
        $this->desdecliente_id = $desdecliente_id;
        $this->hastacliente_id = $hastacliente_id;
        $this->desdelinea_id = $desdelinea_id;
        $this->hastalinea_id = $hastalinea_id;
        $this->mventa_id = $mventa_id;
        $this->nombremventa = $nombremventa;

        return $this;
    }
}

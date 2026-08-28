<?php

namespace App\Exports\Ventas;

use App\Services\Ventas\FacturacionService;
use App\Support\Configuracion\EmpresaLogoArchivo;
use App\Support\Export\ExcelFormatoNumero;
use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class FacturaExport implements FromView, WithColumnFormatting, WithColumnWidths, WithEvents, WithStyles, WithTitle
{
    use Exportable;

    private const COL_ULTIMA = 'J';

    /** Congela también ID y Fecha (columnas A y B): el freeze arranca en C. */
    private const COL_FREEZE = 'C';

    /** @var array<string, mixed>|string|null */
    private $filtros;

    private bool $esCsv = false;

    private $facturacionService;

    private bool $hayFilaLogos = false;

    private int $filaTituloExcel = 1;

    private int $filaSubtituloExcel = 2;

    private int $filaCabecerasExcel = 3;

    private int $filaPrimeraDatosExcel = 4;

    /** @var list<string> */
    private array $rutasLogosExcel = [];

    public function __construct(FacturacionService $facturacionservice)
    {
        $this->facturacionService = $facturacionservice;
    }

    public function view(): View
    {
        $ventas = $this->facturacionService->leeSinPaginar($this->filtros);
        $totalesPorReparto = $this->facturacionService->totalesIndexPorReparto($this->filtros);

        foreach ($ventas as $row) {
            $row->nombreempresa = $row->nombreempresa ?? ($row->puntoventas->empresas->nombre ?? '');
        }

        $this->rutasLogosExcel = EmpresaLogoArchivo::rutasLogosCabeceraDesdeColeccion($ventas);
        $this->hayFilaLogos = count($this->rutasLogosExcel) > 0;
        $this->filaTituloExcel = $this->hayFilaLogos ? 2 : 1;
        $this->filaSubtituloExcel = $this->filaTituloExcel + 1;
        $this->filaCabecerasExcel = $this->filaSubtituloExcel + 1;
        $this->filaPrimeraDatosExcel = $this->filaCabecerasExcel + 1;

        return view('exports.ventas.factura', [
            'ventas' => $ventas,
            'totalesPorReparto' => $totalesPorReparto,
            'esExcel' => true,
            'reservarFilaLogoExcel' => $this->hayFilaLogos,
            'formatoNumero' => $this->formatoNumeroEfectivo(),
        ]);
    }

    public function columnFormats(): array
    {
        $num = ExcelFormatoNumero::codigoColumna(ExcelFormatoNumero::preferenciaGlobal(), 2);

        return [
            'A' => NumberFormat::FORMAT_TEXT,
            'F' => $num,
            'G' => $num,
            'H' => $num,
            'J' => $num,
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 10,
            'B' => 12,
            'C' => 28,
            'D' => 28,
            'E' => 18,
            'F' => 12,
            'G' => 12,
            'H' => 12,
            'I' => 18,
            'J' => 14,
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $col = self::COL_ULTIMA;

                if ($this->hayFilaLogos && count($this->rutasLogosExcel) > 0) {
                    $sheet->getRowDimension(1)->setRowHeight(54);
                    $offsetX = 6;
                    foreach ($this->rutasLogosExcel as $ruta) {
                        if (! is_string($ruta) || ! is_readable($ruta)) {
                            continue;
                        }
                        $drawing = new Drawing;
                        $drawing->setName('Logo');
                        $drawing->setDescription('Logo empresa');
                        $drawing->setPath($ruta);
                        $drawing->setResizeProportional(true);
                        $drawing->setHeight(46);
                        $drawing->setCoordinates('A1');
                        $drawing->setOffsetX($offsetX);
                        $drawing->setOffsetY(4);
                        $drawing->setWorksheet($sheet);
                        $offsetX += 160;
                    }
                }

                $sheet->mergeCells('A'.$this->filaTituloExcel.':'.$col.$this->filaTituloExcel);
                $sheet->mergeCells('A'.$this->filaSubtituloExcel.':'.$col.$this->filaSubtituloExcel);
                $sheet->getStyle('A'.$this->filaTituloExcel)->getFont()->setName('Arial')->setSize(16)->setBold(true)->getColor()->setRGB('17202A');
                $sheet->getStyle('A'.$this->filaSubtituloExcel)->getFont()->setName('Arial')->setSize(10)->setBold(true)->getColor()->setRGB('444444');
                $sheet->getRowDimension($this->filaTituloExcel)->setRowHeight(28);

                $rangoCab = 'A'.$this->filaCabecerasExcel.':'.$col.$this->filaCabecerasExcel;
                $sheet->getStyle($rangoCab)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('85C1E9');
                $sheet->getStyle($rangoCab)->getFont()->setName('Arial')->setSize(11)->setBold(true)->getColor()->setRGB('17202A');
                $sheet->getStyle($rangoCab)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                $sheet->freezePane(self::COL_FREEZE.$this->filaPrimeraDatosExcel);
            },
        ];
    }

    public function title(): string
    {
        return 'Reporte de Comprobantes de Ventas';
    }

    public function parametros($filtros, bool $esCsv = false)
    {
        $this->filtros = $filtros;
        $this->esCsv = $esCsv;

        return $this;
    }

    private function formatoNumeroEfectivo(): string
    {
        $global = ExcelFormatoNumero::preferenciaGlobal();

        return $this->esCsv ? ExcelFormatoNumero::paraCsv($global) : $global;
    }
}

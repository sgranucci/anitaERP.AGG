<?php

namespace App\Exports\Contable;

use App\Support\Configuracion\EmpresaLogoArchivo;
use App\Support\Contable\FlashContableReporteSupport;
use App\Support\Export\ExcelFormatoNumero;
use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class FlashContableExport implements FromView, ShouldAutoSize, WithColumnFormatting, WithEvents, WithStyles, WithTitle
{
    use Exportable;

    /** @var list<string> */
    private array $rutasLogosExcel = [];

    private bool $hayFilaLogos = false;

    private int $filaTituloExcel = 1;

    private int $filaCabEmpresaExcel = 4;

    private int $filaCabMetricasExcel = 5;

    private int $filaPrimeraDatosExcel = 6;

    private int $cantidadColumnas = 8;

    private string $formatoNumero = ExcelFormatoNumero::AUTO;

    /**
     * @param  array<string, mixed>  $reporte
     */
    public function __construct(
        private array $reporte,
        private bool $esCsv = false,
    ) {
    }

    public function view(): View
    {
        $nombreLogo = (string) ($this->reporte['empresas_texto'] ?? '');
        if ($nombreLogo !== '') {
            $this->rutasLogosExcel = EmpresaLogoArchivo::rutasLogosCabeceraDesdeColeccion(collect([
                (object) ['nombreempresa' => $nombreLogo],
            ]));
        }
        $this->hayFilaLogos = count($this->rutasLogosExcel) > 0;
        $this->formatoNumero = $this->esCsv
            ? ExcelFormatoNumero::paraCsv(ExcelFormatoNumero::preferenciaGlobal())
            : ExcelFormatoNumero::preferenciaGlobal();
        $this->cantidadColumnas = max(2, (int) ($this->reporte['cantidad_columnas'] ?? 8));
        $this->calcularFilasEncabezado();

        return view('exports.contable.flash_contable', [
            'reporte' => $this->reporte,
            'reservarFilaLogoExcel' => $this->hayFilaLogos,
            'formatoNumero' => $this->formatoNumero,
            'esCsv' => $this->esCsv,
        ]);
    }

    private function calcularFilasEncabezado(): void
    {
        $offsetLogo = $this->hayFilaLogos ? 1 : 0;
        $this->filaTituloExcel = $offsetLogo + 1;
        // título + generado + período
        $this->filaCabEmpresaExcel = $offsetLogo + 4;
        $this->filaCabMetricasExcel = $this->filaCabEmpresaExcel + 1;
        $this->filaPrimeraDatosExcel = $this->filaCabMetricasExcel + 1;
    }

    public function columnFormats(): array
    {
        $formatos = [
            'A' => 'DD/MM/YYYY',
        ];
        $codigoEntero = ExcelFormatoNumero::codigoColumna($this->formatoNumero, 0);
        $codigoImporte = ExcelFormatoNumero::codigoColumna($this->formatoNumero, 2);
        $codigoTexto = \PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_TEXT;
        $empresas = $this->reporte['empresas'] ?? [];
        $col = 2;
        foreach ($empresas as $_) {
            foreach (FlashContableReporteSupport::METRICAS as $clave) {
                $letra = Coordinate::stringFromColumnIndex($col);
                $formatos[$letra] = FlashContableReporteSupport::esTilde($clave)
                    ? $codigoTexto
                    : (FlashContableReporteSupport::esEntero($clave) ? $codigoEntero : $codigoImporte);
                $col++;
            }
        }

        return $formatos;
    }

    public function styles(Worksheet $sheet): array
    {
        return [];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $colUltima = Coordinate::stringFromColumnIndex($this->cantidadColumnas);

                if ($this->hayFilaLogos && $this->rutasLogosExcel !== []) {
                    $sheet->getRowDimension(1)->setRowHeight(54);
                    foreach ($this->rutasLogosExcel as $idx => $ruta) {
                        if (! is_string($ruta) || ! is_readable($ruta)) {
                            continue;
                        }
                        $drawing = new Drawing;
                        $drawing->setPath($ruta);
                        $drawing->setResizeProportional(true);
                        $drawing->setHeight(46);
                        $drawing->setCoordinates('A1');
                        $drawing->setOffsetX(6 + $idx * 160);
                        $drawing->setOffsetY(4);
                        $drawing->setWorksheet($sheet);
                    }
                }

                for ($i = 0; $i < 3; $i++) {
                    $fila = $this->filaTituloExcel + $i;
                    $sheet->mergeCells('A'.$fila.':'.$colUltima.$fila);
                }

                $sheet->getRowDimension($this->filaTituloExcel)->setRowHeight(26);
                $sheet->getStyle('A'.$this->filaTituloExcel.':'.$colUltima.$this->filaTituloExcel)->applyFromArray([
                    'font' => [
                        'bold' => true,
                        'size' => 16,
                        'name' => 'Arial',
                        'color' => ['rgb' => '17202A'],
                    ],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_LEFT,
                        'vertical' => Alignment::VERTICAL_CENTER,
                    ],
                ]);

                $empresas = $this->reporte['empresas'] ?? [];
                $col = 2;
                foreach ($empresas as $_) {
                    $colFin = $col + count(FlashContableReporteSupport::METRICAS) - 1;
                    if ($colFin > $col) {
                        $sheet->mergeCells(
                            Coordinate::stringFromColumnIndex($col).$this->filaCabEmpresaExcel
                            .':'.Coordinate::stringFromColumnIndex($colFin).$this->filaCabEmpresaExcel
                        );
                    }
                    $col = $colFin + 1;
                }

                $rangoCab = 'A'.$this->filaCabEmpresaExcel.':'.$colUltima.$this->filaCabMetricasExcel;
                $sheet->getStyle($rangoCab)->applyFromArray([
                    'font' => [
                        'bold' => true,
                        'color' => ['rgb' => '17202A'],
                        'size' => 10,
                        'name' => 'Arial',
                    ],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'color' => ['rgb' => '85C1E9'],
                    ],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical' => Alignment::VERTICAL_CENTER,
                        'wrapText' => true,
                    ],
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                            'color' => ['rgb' => '5DADE2'],
                        ],
                    ],
                ]);
                $sheet->getRowDimension($this->filaCabEmpresaExcel)->setRowHeight(22);
                $sheet->getRowDimension($this->filaCabMetricasExcel)->setRowHeight(28);
                $sheet->freezePane('B'.$this->filaPrimeraDatosExcel);

                $ultimaFila = $sheet->getHighestRow();
                if ($ultimaFila >= $this->filaPrimeraDatosExcel) {
                    $sheet->getStyle('A'.$this->filaPrimeraDatosExcel.':A'.$ultimaFila)
                        ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $sheet->getStyle('A'.$ultimaFila.':'.$colUltima.$ultimaFila)
                        ->getFont()->setBold(true);
                }
            },
        ];
    }

    public function title(): string
    {
        return 'Flash Report';
    }
}

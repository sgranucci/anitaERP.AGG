<?php

namespace App\Exports\Compras;

use App\Support\Configuracion\EmpresaLogoArchivo;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
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

class RequisicionReporteExport implements FromView, ShouldAutoSize, WithColumnFormatting, WithColumnWidths, WithEvents, WithStyles, WithTitle
{
    use Exportable;

    private const COL_ULTIMA = 'AH';

    /** Importes: miles y 2 decimales (FORMAT_NUMBER_COMMA_SEPARATED2 → 1.234,56 en locale AR). */
    private const FORMAT_IMPORTE = NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED2;

    /** @var list<string> Columnas de importe / montos en Excel. */
    private const COLUMNAS_IMPORTE = ['L', 'M', 'AC', 'AE'];

    private bool $hayFilaLogos = false;

    private int $filasMetaEncabezado = 2;

    private int $filaInicioMeta = 1;

    private int $filaCabecerasExcel = 2;

    private int $filaPrimeraDatosExcel = 3;

    private int $filaSubtituloExcel = 0;

    /** @var list<string> */
    private array $rutasLogosExcel = [];

    /**
     * @param  list<array<string, mixed>>  $filas
     * @param  array<string, mixed>  $totales
     */
    public function __construct(
        private array $filas,
        private string $titulo,
        private string $subtitulo = '',
        private array $totales = [],
    ) {}

    public function view(): View
    {
        $coleccionLogos = $this->coleccionParaLogos();
        $this->rutasLogosExcel = EmpresaLogoArchivo::rutasLogosCabeceraDesdeColeccion($coleccionLogos);
        $this->hayFilaLogos = count($this->rutasLogosExcel) > 0;
        $this->filasMetaEncabezado = $this->contarFilasMetaEncabezado();
        $offsetLogo = $this->hayFilaLogos ? 1 : 0;
        $this->filaInicioMeta = $offsetLogo + 1;
        $this->filaCabecerasExcel = $offsetLogo + $this->filasMetaEncabezado + 1;
        $this->filaPrimeraDatosExcel = $this->filaCabecerasExcel + 1;
        $this->filaSubtituloExcel = trim($this->subtitulo) !== ''
            ? $this->filaInicioMeta + 2
            : 0;

        return view('exports.compras.requisicion_reporteindex', [
            'filas' => $this->filas,
            'titulo' => $this->titulo,
            'subtitulo' => $this->subtitulo,
            'totales' => $this->totales,
            'total_lineas' => $this->contarLineasDetalle(),
            'reservarFilaLogoExcel' => $this->hayFilaLogos,
            'para_pdf' => true,
            'para_excel' => true,
            'puede_ver_articulo' => false,
            'puede_ver_requisicion' => false,
            'puede_ver_centrocosto' => false,
            'puede_ver_ordencompra' => false,
        ]);
    }

    public function columnFormats(): array
    {
        $fmtImporte = self::FORMAT_IMPORTE;

        return [
            'A' => NumberFormat::FORMAT_TEXT,
            'D' => NumberFormat::FORMAT_TEXT,
            'E' => NumberFormat::FORMAT_TEXT,
            'I' => NumberFormat::FORMAT_NUMBER,
            'J' => NumberFormat::FORMAT_NUMBER,
            'K' => NumberFormat::FORMAT_NUMBER,
            'L' => $fmtImporte,
            'M' => $fmtImporte,
            'O' => NumberFormat::FORMAT_NUMBER,
            'R' => NumberFormat::FORMAT_TEXT,
            'T' => NumberFormat::FORMAT_TEXT,
            'U' => NumberFormat::FORMAT_TEXT,
            'AC' => $fmtImporte,
            'AD' => NumberFormat::FORMAT_NUMBER_00,
            'AE' => $fmtImporte,
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            $this->filaCabecerasExcel => [
                'font' => [
                    'bold' => true,
                    'color' => ['rgb' => '17202A'],
                    'size' => 11,
                    'name' => 'Arial',
                ],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'color' => ['rgb' => '85C1E9'],
                ],
            ],
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 13, 'B' => 28, 'C' => 6, 'D' => 9, 'E' => 9, 'F' => 10, 'G' => 10,
            'H' => 5, 'I' => 8, 'J' => 8, 'K' => 8, 'L' => 10, 'M' => 12, 'N' => 5,
            'O' => 8, 'P' => 5, 'Q' => 14, 'R' => 7, 'S' => 22, 'T' => 7, 'U' => 7,
            'V' => 14, 'W' => 24, 'X' => 10, 'Y' => 22, 'Z' => 16, 'AA' => 4,
            'AB' => 18, 'AC' => 12, 'AD' => 10, 'AE' => 12, 'AF' => 14, 'AG' => 14, 'AH' => 8,
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $colUltima = self::COL_ULTIMA;

                if ($this->hayFilaLogos && count($this->rutasLogosExcel) > 0) {
                    $sheet->getRowDimension(1)->setRowHeight(54);
                    $offsetXp = 6;
                    foreach ($this->rutasLogosExcel as $idx => $ruta) {
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
                        $drawing->setOffsetX($offsetXp + $idx * 160);
                        $drawing->setOffsetY(4);
                        $drawing->setWorksheet($sheet);
                    }
                }

                $filaFinMeta = $this->filaInicioMeta + $this->filasMetaEncabezado - 1;
                for ($fila = $this->filaInicioMeta; $fila <= $filaFinMeta; $fila++) {
                    $sheet->mergeCells('A'.$fila.':'.$colUltima.$fila);
                }

                $filaTit = $this->filaInicioMeta;
                $sheet->getRowDimension($filaTit)->setRowHeight(28);
                $sheet->getStyle('A'.$filaTit.':'.$colUltima.$filaTit)->applyFromArray([
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

                for ($fila = $filaTit + 1; $fila <= $filaFinMeta; $fila++) {
                    $altura = ($this->filaSubtituloExcel > 0 && $fila === $this->filaSubtituloExcel) ? 42 : 20;
                    $sheet->getRowDimension($fila)->setRowHeight($altura);
                    $sheet->getStyle('A'.$fila.':'.$colUltima.$fila)->applyFromArray([
                        'font' => [
                            'bold' => true,
                            'size' => 10,
                            'name' => 'Arial',
                            'color' => ['rgb' => '444444'],
                        ],
                        'alignment' => [
                            'horizontal' => Alignment::HORIZONTAL_LEFT,
                            'vertical' => Alignment::VERTICAL_CENTER,
                            'wrapText' => true,
                        ],
                    ]);
                }

                $filaCab = $this->filaCabecerasExcel;
                $sheet->getStyle('A'.$filaCab.':'.$colUltima.$filaCab)->applyFromArray([
                    'font' => [
                        'bold' => true,
                        'color' => ['rgb' => '17202A'],
                        'size' => 11,
                        'name' => 'Arial',
                    ],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'color' => ['rgb' => '85C1E9'],
                    ],
                    'alignment' => [
                        'vertical' => Alignment::VERTICAL_CENTER,
                    ],
                ]);

                foreach (['B', 'S', 'W', 'Y', 'AB', 'AF'] as $colWrap) {
                    $sheet->getStyle($colWrap.$this->filaPrimeraDatosExcel.':'.$colWrap.$sheet->getHighestRow())
                        ->getAlignment()
                        ->setWrapText(true);
                }

                $filaMax = $sheet->getHighestRow();
                foreach (self::COLUMNAS_IMPORTE as $colImporte) {
                    $rango = $colImporte.$this->filaPrimeraDatosExcel.':'.$colImporte.$filaMax;
                    $sheet->getStyle($rango)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                    $sheet->getStyle($rango)->getNumberFormat()->setFormatCode(self::FORMAT_IMPORTE);
                }

                $sheet->getStyle('L'.$this->filaCabecerasExcel.':M'.$this->filaCabecerasExcel)
                    ->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_RIGHT);

                $sheet->freezePane('A'.$this->filaPrimeraDatosExcel);
            },
        ];
    }

    public function title(): string
    {
        return 'Requisiciones compras';
    }

    private function contarLineasDetalle(): int
    {
        return collect($this->filas)
            ->filter(fn (array $f) => ($f['tipo_fila'] ?? 'detalle') === 'detalle')
            ->count();
    }

    private function contarFilasMetaEncabezado(): int
    {
        $filas = 2;

        if (trim($this->subtitulo) !== '') {
            $filas++;
        }

        if ($this->totales !== []) {
            $filas++;
        }

        if ($this->contarLineasDetalle() > 0) {
            $filas++;
        }

        return $filas;
    }

    private function coleccionParaLogos(): Collection
    {
        return collect($this->filas)
            ->filter(fn (array $f) => ($f['tipo_fila'] ?? '') === 'detalle')
            ->map(fn (array $f) => (object) [
                'nombreempresa' => (string) ($f['nombreempresa'] ?? ''),
            ]);
    }
}

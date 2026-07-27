<?php

namespace App\Exports\Solicitudpago;

use App\Support\Configuracion\EmpresaLogoArchivo;
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

class SolicitudpagoMaeReporteExport implements FromView, WithColumnFormatting, WithColumnWidths, WithEvents, WithStyles, WithTitle
{
    use Exportable;

    private bool $hayFilaLogos = false;

    private int $filasMetaEncabezado = 2;

    private int $filaInicioMeta = 1;

    private int $filaCabecerasExcel = 2;

    private int $filaPrimeraDatosExcel = 3;

    private int $filaSubtituloExcel = 0;

    /** @var list<string> */
    private array $rutasLogosExcel = [];

    private string $colUltima = 'O';

    /** @var list<string> */
    private array $columnasImporte = ['K'];

    /**
     * @param  list<object|array<string, mixed>>  $filas
     * @param  array<string, mixed>  $totales
     */
    public function __construct(
        private array $filas,
        private array $totales,
        private string $subtitulo = '',
        private bool $muestraCuota = false,
        private bool $incluirConciliacion = false,
    ) {
        $this->colUltima = $this->resolverColUltima();
        $this->columnasImporte = $this->resolverColumnasImporte();
    }

    public function view(): View
    {
        $this->rutasLogosExcel = EmpresaLogoArchivo::rutasLogosCabeceraDesdeColeccion($this->filas);
        $this->hayFilaLogos = count($this->rutasLogosExcel) > 0;
        $this->filasMetaEncabezado = $this->contarFilasMetaEncabezado();
        $offsetLogo = $this->hayFilaLogos ? 1 : 0;
        $this->filaInicioMeta = $offsetLogo + 1;
        $this->filaCabecerasExcel = $offsetLogo + $this->filasMetaEncabezado + 1;
        $this->filaPrimeraDatosExcel = $this->filaCabecerasExcel + 1;
        $this->filaSubtituloExcel = trim($this->subtitulo) !== ''
            ? $this->filaInicioMeta + 2
            : 0;

        return view('exports.solicitudpago.solicitudpagomaereporteindex', [
            'filas' => $this->filas,
            'totales' => $this->totales,
            'subtitulo' => $this->subtitulo,
            'muestra_cuota' => $this->muestraCuota,
            'incluir_conciliacion' => $this->incluirConciliacion,
            'reservarFilaLogoExcel' => $this->hayFilaLogos,
            'colspan' => $this->contarColumnas(),
        ]);
    }

    public function columnFormats(): array
    {
        $formats = [
            'A' => NumberFormat::FORMAT_TEXT,
            'B' => NumberFormat::FORMAT_TEXT,
            'C' => NumberFormat::FORMAT_TEXT,
            'H' => NumberFormat::FORMAT_TEXT,
            'J' => NumberFormat::FORMAT_TEXT,
        ];

        // Importes van preformateados AR (1.234.567,89) como texto
        foreach ($this->columnasImporte as $col) {
            $formats[$col] = NumberFormat::FORMAT_TEXT;
        }

        return $formats;
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
        // Anchos pensados para montos hasta miles de millones y textos típicos del informe
        $widths = [
            'A' => 9,   // Numero
            'B' => 12,  // Fecha
            'C' => 12,  // Vence
            'D' => 14,  // Tratamiento
            'E' => 18,  // Sector
            'F' => 28,  // Concepto
            'G' => 16,  // Forma de pago
            'H' => 9,   // N.Pro.
            'I' => 26,  // Proveedor
            'J' => 6,   // Mon
            'K' => 18,  // Importe
        ];

        $col = 'L';
        if ($this->muestraCuota) {
            $widths[$col] = 16; // Monto cuota
            $col++;
            $widths[$col] = 11; // Cuota paga
            $col++;
        }
        $widths[$col] = 12; // Estado
        $col++;
        $widths[$col] = 9; // Refer.
        $col++;
        $widths[$col] = 28; // Observacion
        $col++;
        $widths[$col] = 18; // Empresa
        $col++;
        if ($this->incluirConciliacion) {
            foreach (['SP Debe', 'SP Haber', 'Mayor Debe', 'Mayor Haber', 'Diff'] as $_) {
                $widths[$col] = 14;
                $col++;
            }
            $widths[$col] = 9; // Concil.
        }

        return $widths;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $colUltima = $this->colUltima;

                if ($this->hayFilaLogos && count($this->rutasLogosExcel) > 0) {
                    $sheet->getRowDimension(1)->setRowHeight(54);
                    $offsetXp = 6;
                    $saltoXp = 160;
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
                        $drawing->setOffsetX($offsetXp + $idx * $saltoXp);
                        $drawing->setOffsetY(4);
                        $drawing->setWorksheet($sheet);
                    }
                }

                $filaFinMeta = $this->filaInicioMeta + $this->filasMetaEncabezado - 1;
                for ($f = $this->filaInicioMeta; $f <= $filaFinMeta; $f++) {
                    $sheet->mergeCells('A'.$f.':'.$colUltima.$f);
                }

                $sheet->getRowDimension($this->filaInicioMeta)->setRowHeight(28);
                $sheet->getStyle('A'.$this->filaInicioMeta.':'.$colUltima.$this->filaInicioMeta)->applyFromArray([
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

                for ($f = $this->filaInicioMeta + 1; $f <= $filaFinMeta; $f++) {
                    $sheet->getStyle('A'.$f.':'.$colUltima.$f)->applyFromArray([
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

                if ($this->filaSubtituloExcel > 0) {
                    $sheet->getRowDimension($this->filaSubtituloExcel)->setRowHeight(42);
                }

                $sheet->getStyle('A'.$this->filaCabecerasExcel.':'.$colUltima.$this->filaCabecerasExcel)->applyFromArray([
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
                ]);

                $ultimaFila = max($this->filaPrimeraDatosExcel, $sheet->getHighestRow());
                foreach ($this->columnasImporte as $col) {
                    $sheet->getStyle($col.$this->filaPrimeraDatosExcel.':'.$col.$ultimaFila)
                        ->getAlignment()
                        ->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                }

                $sheet->freezePane('A'.$this->filaPrimeraDatosExcel);
            },
        ];
    }

    public function title(): string
    {
        return 'Solicitudes de pago';
    }

    private function contarFilasMetaEncabezado(): int
    {
        $filas = 2; // título + generado
        if (trim($this->subtitulo) !== '') {
            $filas++;
        }
        if ($this->totales !== []) {
            $filas++;
        }
        if (count($this->filas) > 0) {
            $filas++;
        }

        return $filas;
    }

    private function contarColumnas(): int
    {
        $n = 15;
        if ($this->muestraCuota) {
            $n += 2;
        }
        if ($this->incluirConciliacion) {
            $n += 6;
        }

        return $n;
    }

    /**
     * @return list<string>
     */
    private function resolverColumnasImporte(): array
    {
        $cols = ['K']; // Importe
        $col = 'L';
        if ($this->muestraCuota) {
            $cols[] = $col; // Monto cuota
            $col++;
            $col++; // salta cuota paga
        }
        $col++; // Estado
        $col++; // Refer
        $col++; // Observacion
        $col++; // Empresa
        if ($this->incluirConciliacion) {
            for ($i = 0; $i < 5; $i++) {
                $cols[] = $col;
                $col++;
            }
        }

        return $cols;
    }

    private function resolverColUltima(): string
    {
        $n = $this->contarColumnas();
        $col = '';
        while ($n > 0) {
            $n--;
            $col = chr(65 + ($n % 26)).$col;
            $n = intdiv($n, 26);
        }

        return $col;
    }
}

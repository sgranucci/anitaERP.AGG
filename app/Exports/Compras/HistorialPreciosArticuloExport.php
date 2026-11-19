<?php

namespace App\Exports\Compras;

use App\Support\Compras\HistorialPreciosArticuloFiltros;
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
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class HistorialPreciosArticuloExport implements FromView, ShouldAutoSize, WithColumnFormatting, WithColumnWidths, WithEvents, WithStyles, WithTitle
{
    use Exportable;

    private const FORMAT_IMPORTE = NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED2;

    private bool $hayFilaLogos = false;

    private int $filasMetaEncabezado = 2;

    private int $filaInicioMeta = 1;

    private int $filaCabecerasExcel = 2;

    private int $filaPrimeraDatosExcel = 3;

    /** @var list<string> */
    private array $rutasLogosExcel = [];

    /**
     * @param  list<array<string, mixed>>  $filas
     * @param  array<string, mixed>  $totales
     * @param  list<string>  $meses
     */
    public function __construct(
        private array $filas,
        private string $titulo,
        private string $subtitulo = '',
        private array $totales = [],
        private string $modo = HistorialPreciosArticuloFiltros::MODO_RESUMEN,
        private array $meses = [],
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

        return view('exports.compras.historial_precios_articuloindex', [
            'filas' => $this->filas,
            'titulo' => $this->titulo,
            'subtitulo' => $this->subtitulo,
            'totales' => $this->totales,
            'modo' => $this->modo,
            'meses' => $this->meses,
            'total_lineas' => count($this->filas),
            'reservarFilaLogoExcel' => $this->hayFilaLogos,
            'para_pdf' => true,
            'para_excel' => true,
            'puede_ver_articulo' => false,
            'puede_ver_proveedor' => false,
            'puede_ver_recepcion' => false,
            'puede_ver_ordencompra' => false,
        ]);
    }

    public function columnFormats(): array
    {
        if ($this->esMensualOc()) {
            $formats = [
                'A' => NumberFormat::FORMAT_TEXT,
                'B' => NumberFormat::FORMAT_TEXT,
                'D' => NumberFormat::FORMAT_TEXT,
                'E' => NumberFormat::FORMAT_TEXT,
            ];
            $col = 8; // H = primer mes
            foreach ($this->meses as $ym) {
                $formats[Coordinate::stringFromColumnIndex($col)] = self::FORMAT_IMPORTE;
                $col++;
            }

            return $formats;
        }

        if ($this->esMensual()) {
            $formats = [
                'A' => NumberFormat::FORMAT_TEXT,
                'C' => NumberFormat::FORMAT_TEXT,
            ];
            $col = 6; // F = primer precio
            foreach ($this->meses as $i => $ym) {
                $formats[Coordinate::stringFromColumnIndex($col)] = self::FORMAT_IMPORTE;
                $col++;
                if ($i > 0) {
                    $formats[Coordinate::stringFromColumnIndex($col)] = NumberFormat::FORMAT_NUMBER_00;
                    $col++;
                }
            }
            $formats[Coordinate::stringFromColumnIndex($col)] = NumberFormat::FORMAT_NUMBER_00;

            return $formats;
        }

        return [
            'A' => NumberFormat::FORMAT_TEXT,
            'C' => NumberFormat::FORMAT_TEXT,
            'E' => self::FORMAT_IMPORTE,
            'F' => self::FORMAT_IMPORTE,
            'G' => self::FORMAT_IMPORTE,
            'H' => NumberFormat::FORMAT_NUMBER_00,
            'I' => NumberFormat::FORMAT_TEXT,
            'K' => NumberFormat::FORMAT_TEXT,
            'L' => NumberFormat::FORMAT_TEXT,
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
        if ($this->esMensualOc()) {
            $widths = [
                'A' => 16,
                'B' => 14,
                'C' => 36,
                'D' => 12,
                'E' => 10,
                'F' => 28,
                'G' => 8,
            ];
            $col = 8;
            foreach ($this->meses as $ym) {
                $widths[Coordinate::stringFromColumnIndex($col)] = 11;
                $col++;
            }

            return $widths;
        }

        if ($this->esMensual()) {
            $widths = [
                'A' => 14,
                'B' => 36,
                'C' => 10,
                'D' => 28,
                'E' => 8,
            ];
            $col = 6;
            foreach ($this->meses as $i => $ym) {
                $widths[Coordinate::stringFromColumnIndex($col)] = 11;
                $col++;
                if ($i > 0) {
                    $widths[Coordinate::stringFromColumnIndex($col)] = 8;
                    $col++;
                }
            }
            $widths[Coordinate::stringFromColumnIndex($col)] = 12;

            return $widths;
        }

        return [
            'A' => 14,
            'B' => 36,
            'C' => 10,
            'D' => 28,
            'E' => 12,
            'F' => 12,
            'G' => 12,
            'H' => 10,
            'I' => 12,
            'J' => 8,
            'K' => 12,
            'L' => 12,
            'M' => 18,
            'N' => 18,
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $colUltima = $this->columnaUltima();

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

                for ($i = 0; $i < $this->filasMetaEncabezado; $i++) {
                    $fila = $this->filaInicioMeta + $i;
                    $sheet->mergeCells('A'.$fila.':'.$colUltima.$fila);
                }

                $sheet->getStyle('A'.$this->filaInicioMeta)->applyFromArray([
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
                $sheet->getRowDimension($this->filaInicioMeta)->setRowHeight(28);

                $sheet->getStyle(
                    'A'.$this->filaCabecerasExcel.':'.$colUltima.$this->filaCabecerasExcel
                )->applyFromArray([
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

                $sheet->freezePane('A'.$this->filaPrimeraDatosExcel);
            },
        ];
    }

    public function title(): string
    {
        return 'Historial precios';
    }

    private function esMensual(): bool
    {
        return $this->modo === HistorialPreciosArticuloFiltros::MODO_MENSUAL;
    }

    private function esMensualOc(): bool
    {
        return $this->modo === HistorialPreciosArticuloFiltros::MODO_MENSUAL_OC;
    }

    private function cantidadColumnas(): int
    {
        $nMeses = count($this->meses);
        if ($this->esMensualOc()) {
            return 7 + $nMeses;
        }
        if ($this->esMensual()) {
            return 5 + $nMeses + max(0, $nMeses - 1) + 1;
        }

        return 14;
    }

    private function columnaUltima(): string
    {
        return Coordinate::stringFromColumnIndex($this->cantidadColumnas());
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

    private function coleccionParaLogos(): Collection
    {
        return collect($this->filas)
            ->map(fn ($f) => (object) ['nombreempresa' => $f['nombreempresa'] ?? ''])
            ->filter(fn ($o) => trim((string) $o->nombreempresa) !== '');
    }
}

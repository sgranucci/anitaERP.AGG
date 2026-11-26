<?php

namespace App\Exports\Compras;

use App\Support\Configuracion\EmpresaLogoArchivo;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Excel / CSV del reporte de ahorros. Estructura estándar del ERP:
 * logos, título, generado, filtros, totales, cabecera #85C1E9, importes numéricos y total general.
 */
class AhorroReporteExport implements FromView, WithColumnFormatting, WithColumnWidths, WithEvents, WithStyles, WithTitle
{
    use Exportable;

    private const COLUMNA_ULTIMA = 'T';

    /** Columnas con importe / cantidad numérica (cantidad, precios, ahorros, % línea). */
    private const COLUMNAS_NUMERICAS = ['I', 'J', 'K', 'M', 'N', 'O', 'P'];

    private bool $hayFilaLogos = false;

    private int $filasMeta = 4;

    private int $filaInicioMeta = 1;

    private int $filaCabeceras = 5;

    private int $filaPrimeraDatos = 6;

    /** @var list<string> */
    private array $rutasLogos = [];

    /**
     * @param  Collection<int, object>  $filas
     * @param  array{ahorro_pesos: float, base_pesos: float, porcentaje: float|null, lineas_con_ahorro: int}  $resumen
     */
    public function __construct(
        private Collection $filas,
        private array $resumen,
        private string $titulo = 'Reporte de ahorros en compras',
        private string $subtitulo = '',
    ) {}

    public function view(): View
    {
        $this->rutasLogos = EmpresaLogoArchivo::rutasLogosCabeceraDesdeColeccion($this->filas);
        $this->hayFilaLogos = count($this->rutasLogos) > 0;

        // título + generado + totales (+ subtítulo) (+ contador de líneas)
        $this->filasMeta = 3;
        if (trim($this->subtitulo) !== '') {
            $this->filasMeta++;
        }
        if ($this->filas->count() > 0) {
            $this->filasMeta++;
        }

        $offsetLogo = $this->hayFilaLogos ? 1 : 0;
        $this->filaInicioMeta = $offsetLogo + 1;
        $this->filaCabeceras = $offsetLogo + $this->filasMeta + 1;
        $this->filaPrimeraDatos = $this->filaCabeceras + 1;

        return view('exports.compras.ahorro_reporteindex', [
            'filas' => $this->filas,
            'resumen' => $this->resumen,
            'titulo' => $this->titulo,
            'subtitulo' => $this->subtitulo,
            'reservarFilaLogoExcel' => $this->hayFilaLogos,
        ]);
    }

    public function title(): string
    {
        return 'Ahorro compras';
    }

    public function columnFormats(): array
    {
        return [
            'A' => NumberFormat::FORMAT_TEXT,
            'C' => NumberFormat::FORMAT_TEXT,
            'E' => NumberFormat::FORMAT_TEXT,
            'I' => '#,##0.00',
            'J' => '#,##0.00',
            'K' => '#,##0.00',
            'M' => '#,##0.00',
            'N' => '#,##0.00',
            'O' => '#,##0.00',
            'P' => '#,##0.00',
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 12, 'B' => 11, 'C' => 9, 'D' => 30, 'E' => 14, 'F' => 38, 'G' => 18, 'H' => 22,
            'I' => 11, 'J' => 14, 'K' => 14, 'L' => 7, 'M' => 14, 'N' => 18, 'O' => 16, 'P' => 10,
            'Q' => 22, 'R' => 22, 'S' => 24, 'T' => 30,
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            $this->filaCabeceras => [
                'font' => ['bold' => true, 'color' => ['rgb' => '17202A'], 'size' => 11, 'name' => 'Arial'],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => '85C1E9']],
            ],
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $ultima = self::COLUMNA_ULTIMA;

                if ($this->hayFilaLogos) {
                    $sheet->getRowDimension(1)->setRowHeight(54);
                    foreach ($this->rutasLogos as $idx => $ruta) {
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
                        $drawing->setOffsetX(6 + $idx * 160);
                        $drawing->setOffsetY(4);
                        $drawing->setWorksheet($sheet);
                    }
                }

                // Meta: una fila por dato, fusionada hasta la última columna.
                for ($i = 0; $i < $this->filasMeta; $i++) {
                    $fila = $this->filaInicioMeta + $i;
                    $sheet->mergeCells('A'.$fila.':'.$ultima.$fila);
                }
                $sheet->getStyle('A'.$this->filaInicioMeta)->applyFromArray([
                    'font' => ['bold' => true, 'size' => 16, 'name' => 'Arial', 'color' => ['rgb' => '17202A']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER],
                ]);
                $sheet->getRowDimension($this->filaInicioMeta)->setRowHeight(28);
                if ($this->filasMeta > 1) {
                    $desde = $this->filaInicioMeta + 1;
                    $hasta = $this->filaInicioMeta + $this->filasMeta - 1;
                    $sheet->getStyle("A{$desde}:A{$hasta}")->applyFromArray([
                        'font' => ['bold' => true, 'size' => 10, 'name' => 'Arial', 'color' => ['rgb' => '444444']],
                        'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'wrapText' => true],
                    ]);
                }
                if (trim($this->subtitulo) !== '') {
                    $sheet->getRowDimension($this->filaInicioMeta + 2)->setRowHeight(32);
                }

                // Cabecera de columnas.
                $sheet->getStyle("A{$this->filaCabeceras}:{$ultima}{$this->filaCabeceras}")->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['rgb' => '17202A'], 'size' => 11, 'name' => 'Arial'],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => '85C1E9']],
                    'alignment' => ['wrapText' => true, 'vertical' => Alignment::VERTICAL_CENTER],
                ]);
                $sheet->getRowDimension($this->filaCabeceras)->setRowHeight(32);

                // Importes y cantidades: números reales, no texto.
                $ultimaDatos = $this->filaPrimeraDatos + $this->filas->count(); // incluye el total general
                foreach (self::COLUMNAS_NUMERICAS as $col) {
                    for ($fila = $this->filaPrimeraDatos; $fila <= $ultimaDatos; $fila++) {
                        $celda = $sheet->getCell($col.$fila);
                        $valor = $celda->getValue();
                        if ($valor === null || $valor === '') {
                            continue;
                        }
                        $celda->setValueExplicit((float) $valor, DataType::TYPE_NUMERIC);
                    }
                    $sheet->getStyle($col.$this->filaPrimeraDatos.':'.$col.$ultimaDatos)
                        ->getNumberFormat()->setFormatCode('#,##0.00');
                    $sheet->getStyle($col.$this->filaPrimeraDatos.':'.$col.$ultimaDatos)
                        ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                }

                // Total general.
                $sheet->getStyle("A{$ultimaDatos}:{$ultima}{$ultimaDatos}")->applyFromArray([
                    'font' => ['bold' => true, 'name' => 'Arial', 'color' => ['rgb' => '17202A']],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => 'AED6F1']],
                ]);

                $sheet->freezePane('A'.$this->filaPrimeraDatos);
            },
        ];
    }
}

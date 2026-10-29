<?php

namespace App\Exports\Stock;

use App\Support\Configuracion\EmpresaLogoArchivo;
use App\Support\Stock\KilosArticuloSurmarFiltros;
use Illuminate\Contracts\View\View;
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

class KilosArticuloSurmarExport implements FromView, WithColumnFormatting, WithColumnWidths, WithEvents, WithStyles, WithTitle
{
    use Exportable;

    private bool $hayFilaLogos = false;

    private int $filasMetaEncabezado = 5;

    private int $filaInicioMeta = 1;

    private int $filaCabecerasExcel = 6;

    private int $filaPrimeraDatosExcel = 7;

    private int $filaTotalExcel = 0;

    /** @var list<string> */
    private array $rutasLogosExcel = [];

    private string $colUltima = 'F';

    /** @var list<string> */
    private array $columnasImporte = ['C', 'D', 'E', 'F'];

    /**
     * @param  list<array<string, mixed>>  $filas
     * @param  array<string, mixed>  $totales
     * @param  array<string, mixed>  $filtros
     * @param  list<string>  $advertencias
     */
    public function __construct(
        private array $filas,
        private array $totales,
        private array $filtros,
        private string $titulo,
        private string $subtitulo,
        private array $advertencias = [],
    ) {
        $modo = (string) ($this->filtros['tipo'] ?? KilosArticuloSurmarFiltros::TIPO_TODOS);
        if (in_array($modo, [KilosArticuloSurmarFiltros::TIPO_REM, KilosArticuloSurmarFiltros::TIPO_AP, KilosArticuloSurmarFiltros::TIPO_COM], true)) {
            $this->colUltima = 'E';
            $this->columnasImporte = ['C', 'D', 'E'];
        }
    }

    public function view(): View
    {
        $this->rutasLogosExcel = EmpresaLogoArchivo::rutasLogosCabeceraDesdeColeccion(collect($this->filas));
        $this->hayFilaLogos = count($this->rutasLogosExcel) > 0;
        $this->filasMetaEncabezado = 5 + ($this->advertencias !== [] ? 1 : 0);
        $offsetLogo = $this->hayFilaLogos ? 1 : 0;
        $this->filaInicioMeta = $offsetLogo + 1;
        $this->filaCabecerasExcel = $offsetLogo + $this->filasMetaEncabezado + 1;
        $this->filaPrimeraDatosExcel = $this->filaCabecerasExcel + 1;
        $this->filaTotalExcel = count($this->filas) > 0
            ? $this->filaPrimeraDatosExcel + count($this->filas)
            : 0;

        return view('exports.stock.kilos_articulo_surmarindex', [
            'filas' => $this->filas,
            'totales' => $this->totales,
            'filtros' => $this->filtros,
            'titulo' => $this->titulo,
            'subtitulo' => $this->subtitulo,
            'advertencias' => $this->advertencias,
            'reservarFilaLogoExcel' => $this->hayFilaLogos,
        ]);
    }

    public function columnFormats(): array
    {
        $cols = [
            'A' => NumberFormat::FORMAT_TEXT,
            'B' => NumberFormat::FORMAT_TEXT,
        ];
        foreach ($this->columnasImporte as $col) {
            $cols[$col] = '#,##0.00';
        }

        return $cols;
    }

    public function columnWidths(): array
    {
        return [
            'A' => 16,
            'B' => 36,
            'C' => 18,
            'D' => 26,
            'E' => 20,
            'F' => 20,
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

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $colUltima = $this->colUltima;

                if ($this->hayFilaLogos) {
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
                        $drawing->setOffsetX($offsetXp + ($idx * 160));
                        $drawing->setOffsetY(4);
                        $drawing->setWorksheet($sheet);
                    }
                }

                $filaFinMeta = $this->filaInicioMeta + $this->filasMetaEncabezado - 1;
                for ($fila = $this->filaInicioMeta; $fila <= $filaFinMeta; $fila++) {
                    $sheet->mergeCells('A'.$fila.':'.$colUltima.$fila);
                }
                $sheet->getRowDimension($this->filaInicioMeta)->setRowHeight(28);
                $sheet->getStyle('A'.$this->filaInicioMeta.':'.$colUltima.$this->filaInicioMeta)->applyFromArray([
                    'font' => ['bold' => true, 'size' => 16, 'name' => 'Arial', 'color' => ['rgb' => '17202A']],
                ]);
                for ($fila = $this->filaInicioMeta + 1; $fila <= $filaFinMeta; $fila++) {
                    $sheet->getRowDimension($fila)->setRowHeight(20);
                    $sheet->getStyle('A'.$fila)->getAlignment()->setWrapText(true);
                }

                $filaCab = $this->filaCabecerasExcel;
                $sheet->getStyle('A'.$filaCab.':'.$colUltima.$filaCab)->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['rgb' => '17202A'], 'size' => 11, 'name' => 'Arial'],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => '85C1E9']],
                ]);

                $ultima = (int) $sheet->getHighestRow();
                $desde = $this->filaPrimeraDatosExcel;
                if ($desde <= $ultima) {
                    foreach ($this->columnasImporte as $col) {
                        for ($fila = $desde; $fila <= $ultima; $fila++) {
                            $celda = $sheet->getCell($col.$fila);
                            $texto = trim((string) $celda->getValue());
                            if ($texto === '' || ! is_numeric($texto)) {
                                continue;
                            }
                            $celda->setValueExplicit((float) $texto, DataType::TYPE_NUMERIC);
                        }
                        $sheet->getStyle($col.$desde.':'.$col.$ultima)
                            ->getNumberFormat()
                            ->setFormatCode('#,##0.00');
                        $sheet->getStyle($col.$desde.':'.$col.$ultima)
                            ->getAlignment()
                            ->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                    }
                }

                if ($this->filaTotalExcel > 0) {
                    $sheet->getStyle('A'.$this->filaTotalExcel.':'.$colUltima.$this->filaTotalExcel)->applyFromArray([
                        'font' => ['bold' => true, 'name' => 'Arial'],
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => 'D6EAF8']],
                    ]);
                }

                $sheet->freezePane('A'.$this->filaPrimeraDatosExcel);
            },
        ];
    }

    public function title(): string
    {
        return 'Kilos por artículo';
    }
}

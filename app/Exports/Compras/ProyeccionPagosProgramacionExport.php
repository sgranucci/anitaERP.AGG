<?php

declare(strict_types=1);

namespace App\Exports\Compras;

use App\Support\Configuracion\EmpresaLogoArchivo;
use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ProyeccionPagosProgramacionExport implements FromView, WithColumnFormatting, WithColumnWidths, WithEvents, WithStyles, WithTitle
{
    private const COL_ULTIMA = 'N';

    private bool $hayFilaLogos = false;

    /** @var list<string> */
    private array $rutasLogosExcel = [];

    private int $filaCabecerasExcel = 5;

    private int $filaPrimeraDatosExcel = 6;

    /**
     * @param  list<array<string, mixed>>  $filas
     */
    public function __construct(
        private array $filas,
        private string $titulo,
        private string $subtitulo,
    ) {
    }

    public function view(): View
    {
        $coleccion = collect($this->filas);
        $this->rutasLogosExcel = EmpresaLogoArchivo::rutasLogosCabeceraDesdeColeccion($coleccion);
        $this->hayFilaLogos = $this->rutasLogosExcel !== [];
        $filasMeta = 4;
        $offset = $this->hayFilaLogos ? 1 : 0;
        $this->filaCabecerasExcel = $offset + $filasMeta + 1;
        $this->filaPrimeraDatosExcel = $this->filaCabecerasExcel + 1;

        return view('exports.compras.programarpagosindex', [
            'filas' => $this->filas,
            'titulo' => $this->titulo,
            'subtitulo' => $this->subtitulo,
            'reservarFilaLogoExcel' => $this->hayFilaLogos,
        ]);
    }

    public function columnFormats(): array
    {
        $cols = [];
        foreach (range('A', self::COL_ULTIMA) as $c) {
            $cols[$c] = NumberFormat::FORMAT_TEXT;
        }
        $cols['J'] = '#,##0.00';

        return $cols;
    }

    public function columnWidths(): array
    {
        return [
            'A' => 22, 'B' => 12, 'C' => 32, 'D' => 8, 'E' => 20,
            'F' => 12, 'G' => 12, 'H' => 24, 'I' => 8, 'J' => 16,
            'K' => 28, 'L' => 24, 'M' => 12, 'N' => 14,
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            $this->filaCabecerasExcel => [
                'font' => ['bold' => true, 'color' => ['rgb' => '17202A'], 'name' => 'Arial', 'size' => 11],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '85C1E9'],
                ],
            ],
        ];
    }

    public function title(): string
    {
        return 'Programar pagos';
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                if ($this->hayFilaLogos) {
                    $sheet->getRowDimension(1)->setRowHeight(54);
                    $offsetX = 0;
                    foreach ($this->rutasLogosExcel as $ruta) {
                        if (! is_file($ruta)) {
                            continue;
                        }
                        $drawing = new Drawing;
                        $drawing->setPath($ruta);
                        $drawing->setHeight(48);
                        $drawing->setCoordinates('A1');
                        $drawing->setOffsetX($offsetX);
                        $drawing->setWorksheet($sheet);
                        $offsetX += 120;
                    }
                }
                $sheet->freezePane('A'.$this->filaPrimeraDatosExcel);
                $ultima = (int) $sheet->getHighestRow();
                for ($fila = $this->filaPrimeraDatosExcel; $fila <= $ultima; $fila++) {
                    $celda = $sheet->getCell('J'.$fila);
                    $raw = str_replace(',', '', (string) $celda->getValue());
                    if (is_numeric($raw)) {
                        $celda->setValueExplicit((float) $raw, DataType::TYPE_NUMERIC);
                    }
                }
            },
        ];
    }
}

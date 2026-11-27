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
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;

/**
 * Detalle de ahorros del tablero de compras (Excel / CSV): una fila por línea de OC con ahorro cargado.
 */
class ComprasTableroOcAhorrosExport implements FromView, WithColumnFormatting, WithColumnWidths, WithEvents, WithTitle
{
    use Exportable;

    private const COL_ULTIMA = 'Q';

    private const FORMAT_IMPORTE = NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED2;

    private bool $hayFilaLogos = false;

    private int $filaInicioMeta = 1;

    private int $filaCabecerasExcel = 5;

    private int $filaPrimeraDatosExcel = 6;

    /** @var list<string> */
    private array $rutasLogosExcel = [];

    /**
     * @param  list<array<string, mixed>>  $filas
     */
    public function __construct(
        private array $filas,
        private string $titulo,
        private string $subtitulo = '',
    ) {}

    public function view(): View
    {
        $this->rutasLogosExcel = EmpresaLogoArchivo::rutasLogosCabeceraDesdeColeccion($this->coleccionParaLogos());
        $this->hayFilaLogos = count($this->rutasLogosExcel) > 0;

        $filasMeta = 3 + (trim($this->subtitulo) !== '' ? 1 : 0);
        $offsetLogo = $this->hayFilaLogos ? 1 : 0;
        $this->filaInicioMeta = $offsetLogo + 1;
        $this->filaCabecerasExcel = $offsetLogo + $filasMeta + 1;
        $this->filaPrimeraDatosExcel = $this->filaCabecerasExcel + 1;

        return view('exports.compras.tablero_ocahorros', [
            'filas' => $this->filas,
            'titulo' => $this->titulo,
            'subtitulo' => $this->subtitulo,
            'reservarFilaLogoExcel' => $this->hayFilaLogos,
        ]);
    }

    public function columnFormats(): array
    {
        return [
            'B' => NumberFormat::FORMAT_TEXT,
            'D' => NumberFormat::FORMAT_TEXT,
            'G' => NumberFormat::FORMAT_TEXT,
            'I' => '#,##0.00',
            'J' => self::FORMAT_IMPORTE,
            'L' => self::FORMAT_IMPORTE,
            'M' => self::FORMAT_IMPORTE,
            'O' => '#,##0.00',
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 11, 'B' => 10, 'C' => 20, 'D' => 13, 'E' => 22, 'F' => 34, 'G' => 14,
            'H' => 42, 'I' => 11, 'J' => 14, 'K' => 8, 'L' => 14, 'M' => 16, 'N' => 6,
            'O' => 10, 'P' => 28, 'Q' => 40,
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $ultima = self::COL_ULTIMA;

                if ($this->hayFilaLogos) {
                    $sheet->getRowDimension(1)->setRowHeight(54);
                    foreach ($this->rutasLogosExcel as $idx => $ruta) {
                        if (! is_string($ruta) || ! is_readable($ruta)) {
                            continue;
                        }
                        $drawing = new Drawing;
                        $drawing->setName('Logo');
                        $drawing->setPath($ruta);
                        $drawing->setResizeProportional(true);
                        $drawing->setHeight(46);
                        $drawing->setCoordinates('A1');
                        $drawing->setOffsetX(6 + $idx * 160);
                        $drawing->setOffsetY(4);
                        $drawing->setWorksheet($sheet);
                    }
                }

                $sheet->getRowDimension($this->filaInicioMeta)->setRowHeight(28);
                $sheet->getStyle('A'.$this->filaInicioMeta.':'.$ultima.$this->filaInicioMeta)->applyFromArray([
                    'font' => ['bold' => true, 'size' => 16, 'name' => 'Arial', 'color' => ['rgb' => '17202A']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER],
                ]);

                $filaCab = $this->filaCabecerasExcel;
                $sheet->getStyle('A'.$filaCab.':'.$ultima.$filaCab)->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['rgb' => '17202A'], 'size' => 11, 'name' => 'Arial'],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => '85C1E9']],
                    'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
                ]);

                $filaMax = $sheet->getHighestRow();
                foreach (['E', 'F', 'H', 'P', 'Q'] as $colWrap) {
                    $sheet->getStyle($colWrap.$this->filaPrimeraDatosExcel.':'.$colWrap.$filaMax)
                        ->getAlignment()->setWrapText(true);
                }
                foreach (['I', 'J', 'L', 'M', 'O'] as $colNum) {
                    $sheet->getStyle($colNum.$this->filaPrimeraDatosExcel.':'.$colNum.$filaMax)
                        ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                }

                $sheet->freezePane('A'.$this->filaPrimeraDatosExcel);
            },
        ];
    }

    public function title(): string
    {
        return 'Ahorros';
    }

    private function coleccionParaLogos(): Collection
    {
        return collect($this->filas)->map(fn (array $f) => (object) ['nombreempresa' => (string) ($f['nombreempresa'] ?? '')]);
    }
}

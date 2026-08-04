<?php

namespace App\Exports\Contable;

use App\Queries\Contable\AsientoQueryInterface;
use App\Support\Configuracion\EmpresaLogoArchivo;
use App\Support\Contable\AsientoListadoFiltros;
use App\Support\Export\ExcelFormatoNumero;
use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class AsientoExport implements FromView, WithColumnFormatting, ShouldAutoSize, WithStyles, WithColumnWidths, WithEvents, WithTitle
{
    use Exportable;

    private const COL_ULTIMA = 'O';

    private AsientoQueryInterface $asientoQuery;

    /** @var array<string, mixed>|string|null */
    private $filtros;

    private bool $hayFilaLogos = false;

    private int $filaCabecerasExcel = 2;

    private int $filaPrimeraDatosExcel = 3;

    private int $filaTituloExcel = 1;

    /** @var list<string> */
    private array $rutasLogosExcel = [];

    public function __construct(AsientoQueryInterface $asientoquery)
    {
        $this->asientoQuery = $asientoquery;
    }

    public function view(): View
    {
        $asientos = $this->asientoQuery->leeAsiento($this->filtros, false);

        $this->rutasLogosExcel = EmpresaLogoArchivo::rutasLogosCabeceraDesdeColeccion($asientos);
        $this->hayFilaLogos = count($this->rutasLogosExcel) > 0;
        $this->filaTituloExcel = $this->hayFilaLogos ? 2 : 1;
        $this->filaCabecerasExcel = $this->hayFilaLogos ? 3 : 2;
        $this->filaPrimeraDatosExcel = $this->filaCabecerasExcel + 1;

        return view('exports.contable.asientoindex', [
            'asientos' => $asientos,
            'reservarFilaLogoExcel' => $this->hayFilaLogos,
        ]);
    }

    public function columnFormats(): array
    {
        $formato = ExcelFormatoNumero::preferenciaGlobal();

        return [
            'A' => NumberFormat::FORMAT_TEXT,
            'B' => NumberFormat::FORMAT_TEXT,
            'C' => NumberFormat::FORMAT_TEXT,
            'G' => ExcelFormatoNumero::codigoColumna($formato, 2),
            'K' => ExcelFormatoNumero::codigoColumna($formato, 2),
            'L' => ExcelFormatoNumero::codigoColumna($formato, 2),
            'N' => ExcelFormatoNumero::codigoColumna($formato, 4),
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
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'color' => ['rgb' => '85C1E9'],
                ],
            ],
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 8,
            'B' => 18,
            'C' => 12,
            'D' => 12,
            'E' => 18,
            'F' => 28,
            'G' => 12,
            'H' => 14,
            'I' => 28,
            'J' => 16,
            'K' => 12,
            'L' => 12,
            'M' => 10,
            'N' => 12,
            'O' => 28,
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $colUltima = self::COL_ULTIMA;

                if ($this->hayFilaLogos) {
                    $sheet->getRowDimension(1)->setRowHeight(54);
                    $offsetX = 8;
                    foreach ($this->rutasLogosExcel as $rutaLogo) {
                        if (! is_file($rutaLogo)) {
                            continue;
                        }
                        $drawing = new Drawing();
                        $drawing->setPath($rutaLogo);
                        $drawing->setHeight(48);
                        $drawing->setCoordinates('A1');
                        $drawing->setOffsetX($offsetX);
                        $drawing->setOffsetY(4);
                        $drawing->setWorksheet($sheet);
                        $offsetX += 130;
                    }
                }

                $sheet->mergeCells('A'.$this->filaTituloExcel.':'.$colUltima.$this->filaTituloExcel);
                $sheet->getStyle('A'.$this->filaTituloExcel)->getFont()
                    ->setName('Arial')->setSize(16)->setBold(true)
                    ->getColor()->setRGB('17202A');
                $sheet->getRowDimension($this->filaTituloExcel)->setRowHeight(28);

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
                        'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                        'color' => ['rgb' => '85C1E9'],
                    ],
                ]);

                $sheet->freezePane('A'.$this->filaPrimeraDatosExcel);
            },
        ];
    }

    public function title(): string
    {
        return 'Reporte de Asientos';
    }

    /**
     * @param  array<string, mixed>|string|null  $filtros
     * @param  int  $empresaId  Compat legacy (segundo arg).
     */
    public function parametros($filtros, $empresaId = 0)
    {
        if (is_string($filtros) || $filtros === null) {
            $this->filtros = [
                'modo' => AsientoListadoFiltros::MODO_TODOS,
                'campo' => 'numeroasiento',
                'operador' => 'contiene',
                'valor' => trim((string) $filtros),
                'busqueda' => trim((string) $filtros),
                'empresa_id' => (int) $empresaId > 0 ? (int) $empresaId : null,
                'empresa_scope' => (int) $empresaId > 0 ? 'una' : 'todas',
            ];
        } else {
            $this->filtros = $filtros;
        }

        return $this;
    }
}

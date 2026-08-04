<?php

namespace App\Exports\Stock;

use App\Repositories\Stock\MovimientoStockRepositoryInterface;
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
use App\Support\Configuracion\EmpresaLogoArchivo;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class MovimientoStockListadoExport implements FromView, ShouldAutoSize, WithColumnFormatting, WithColumnWidths, WithEvents, WithStyles, WithTitle
{
    use Exportable;

    private const COL_ULTIMA = 'O';

    private MovimientoStockRepositoryInterface $movimientoStockRepository;

    /** @var array<string, mixed> */
    private array $filtros = [];

    /** @var array<string, string> */
    private array $estadoEnum = [];

    private bool $flDesdeIndex = false;

    private bool $hayFilaLogos = false;

    private int $filaCabecerasExcel = 2;

    private int $filaPrimeraDatosExcel = 3;

    private int $filaTituloExcel = 1;

    /** @var list<string> */
    private array $rutasLogosExcel = [];

    public function __construct(MovimientoStockRepositoryInterface $movimientoStockRepository)
    {
        $this->movimientoStockRepository = $movimientoStockRepository;
    }

    public function view(): View
    {
        $datas = $this->movimientoStockRepository->leeMovimientoStock($this->filtros, false);

        $logosCol = collect($datas)->map(fn ($fila) => (object) [
            'nombreempresa' => $fila->nombreEmpresa ?? '',
        ]);
        $this->rutasLogosExcel = EmpresaLogoArchivo::rutasLogosCabeceraDesdeColeccion($logosCol);
        $this->hayFilaLogos = count($this->rutasLogosExcel) > 0;
        $this->filaTituloExcel = $this->hayFilaLogos ? 2 : 1;
        $this->filaCabecerasExcel = $this->hayFilaLogos ? 3 : 2;
        $this->filaPrimeraDatosExcel = $this->filaCabecerasExcel + 1;
        $this->flDesdeIndex = true;

        return view('exports.stock.movimientostockindex', [
            'datas' => $datas,
            'estado_enum' => $this->estadoEnum,
            'reservarFilaLogoExcel' => $this->hayFilaLogos,
        ]);
    }

    public function columnFormats(): array
    {
        $cols = [];
        foreach (range('A', self::COL_ULTIMA) as $c) {
            $cols[$c] = NumberFormat::FORMAT_TEXT;
        }

        return $cols;
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
            'B' => 12,
            'C' => 12,
            'D' => 22,
            'E' => 14,
            'F' => 18,
            'G' => 18,
            'H' => 10,
            'I' => 18,
            'J' => 12,
            'K' => 12,
            'L' => 8,
            'M' => 14,
            'N' => 10,
            'O' => 10,
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                if (! $this->flDesdeIndex) {
                    return;
                }

                $sheet = $event->sheet->getDelegate();

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

                $filaTit = $this->filaTituloExcel;
                $sheet->mergeCells('A'.$filaTit.':'.self::COL_ULTIMA.$filaTit);
                $sheet->getRowDimension($filaTit)->setRowHeight(30);
                $sheet->getStyle('A'.$filaTit.':'.self::COL_ULTIMA.$filaTit)->applyFromArray([
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

                $sheet->freezePane('A'.$this->filaPrimeraDatosExcel);
            },
        ];
    }

    public function title(): string
    {
        return 'Movimientos de stock';
    }

    /**
     * @param  array<string, mixed>|string  $filtros
     * @param  array<string, string>  $estadoEnum
     */
    public function parametros($filtros, array $estadoEnum = []): self
    {
        $this->filtros = is_array($filtros) ? $filtros : ['busqueda' => (string) $filtros];
        $this->estadoEnum = $estadoEnum;

        return $this;
    }
}

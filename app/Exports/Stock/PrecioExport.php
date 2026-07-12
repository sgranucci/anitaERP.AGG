<?php

namespace App\Exports\Stock;

use App\Queries\Stock\PrecioQueryInterface;
use App\Support\Configuracion\EmpresaLogoArchivo;
use App\Support\Stock\PrecioListadoFiltros;
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
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class PrecioExport implements FromView, ShouldAutoSize, WithColumnFormatting, WithColumnWidths, WithEvents, WithStyles, WithTitle
{
    use Exportable;

    private const COL_ULTIMA = 'J';

    private PrecioQueryInterface $precioQuery;

    /** @var array<string, mixed> */
    private array $filtros = [];

    /** @var list<object>|Collection<int, object> */
    private $listasPrecio = [];

    private bool $flDesdeIndex = false;

    private bool $hayFilaLogos = false;

    private int $filaCabecerasExcel = 2;

    private int $filaPrimeraDatosExcel = 3;

    private int $filaTituloExcel = 1;

    private int $filasMetaEncabezado = 2;

    /** @var list<string> */
    private array $rutasLogosExcel = [];

    public function __construct(PrecioQueryInterface $precioQuery)
    {
        $this->precioQuery = $precioQuery;
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @param  list<object>|Collection<int, object>|null  $listasPrecio
     */
    public function parametros(array $filtros, $listasPrecio = null): self
    {
        $this->filtros = $filtros;
        $this->listasPrecio = $listasPrecio ?? [];
        $this->flDesdeIndex = true;

        return $this;
    }

    public function view(): View
    {
        $precios = $this->precioQuery->leePrecios($this->filtros, false);
        $fechaReferencia = (string) ($this->filtros['fecha_vigencia'] ?? date('Y-m-d'));
        $subtituloFiltros = PrecioListadoFiltros::subtituloExport($this->filtros, $this->listasPrecio);
        $totalFilas = is_countable($precios) ? count($precios) : 0;

        $this->rutasLogosExcel = EmpresaLogoArchivo::rutasLogosCabeceraDesdeColeccion(collect());
        $this->hayFilaLogos = count($this->rutasLogosExcel) > 0;

        $this->filasMetaEncabezado = 2;
        if (trim($subtituloFiltros) !== '') {
            $this->filasMetaEncabezado++;
        }
        if ($totalFilas > 0) {
            $this->filasMetaEncabezado++;
        }

        $offsetLogo = $this->hayFilaLogos ? 1 : 0;
        $this->filaTituloExcel = $offsetLogo + 1;
        $this->filaCabecerasExcel = $offsetLogo + $this->filasMetaEncabezado + 1;
        $this->filaPrimeraDatosExcel = $this->filaCabecerasExcel + 1;

        return view('exports.stock.precioindex', [
            'precios' => $precios,
            'fechaReferencia' => $fechaReferencia,
            'subtituloFiltros' => $subtituloFiltros,
            'totalFilas' => $totalFilas,
            'reservarFilaLogoExcel' => $this->hayFilaLogos,
        ]);
    }

    public function columnFormats(): array
    {
        if (! $this->flDesdeIndex) {
            return [];
        }

        $cols = [];
        foreach (range('A', self::COL_ULTIMA) as $c) {
            $cols[$c] = NumberFormat::FORMAT_TEXT;
        }
        $cols['H'] = NumberFormat::FORMAT_NUMBER_00;
        $cols['I'] = NumberFormat::FORMAT_NUMBER_00;

        return $cols;
    }

    public function styles(Worksheet $sheet)
    {
        if (! $this->flDesdeIndex) {
            return [];
        }

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
        if (! $this->flDesdeIndex) {
            return [];
        }

        return [
            'A' => 8,
            'B' => 12,
            'C' => 28,
            'D' => 18,
            'E' => 16,
            'F' => 11,
            'G' => 10,
            'H' => 12,
            'I' => 12,
            'J' => 20,
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

                $filaInicioMeta = $this->filaTituloExcel;
                $filaFinMeta = $filaInicioMeta + $this->filasMetaEncabezado - 1;
                for ($fila = $filaInicioMeta; $fila <= $filaFinMeta; $fila++) {
                    $sheet->mergeCells('A'.$fila.':'.self::COL_ULTIMA.$fila);
                    $sheet->getStyle('A'.$fila.':'.self::COL_ULTIMA.$fila)->applyFromArray([
                        'font' => [
                            'bold' => $fila === $filaInicioMeta,
                            'size' => $fila === $filaInicioMeta ? 16 : 10,
                            'name' => 'Arial',
                            'color' => ['rgb' => $fila === $filaInicioMeta ? '17202A' : '444444'],
                        ],
                        'alignment' => [
                            'horizontal' => Alignment::HORIZONTAL_LEFT,
                            'vertical' => Alignment::VERTICAL_CENTER,
                            'wrapText' => $fila !== $filaInicioMeta,
                        ],
                    ]);
                    $sheet->getRowDimension($fila)->setRowHeight($fila === $filaInicioMeta ? 28 : ($fila === $filaInicioMeta + 1 ? 18 : 16));
                }

                $sheet->freezePane('A'.$this->filaPrimeraDatosExcel);

                $sheet->getStyle('C'.$this->filaPrimeraDatosExcel.':C'.$sheet->getHighestRow())
                    ->getAlignment()
                    ->setWrapText(true)
                    ->setVertical(Alignment::VERTICAL_TOP);
            },
        ];
    }

    public function title(): string
    {
        return 'Precios de venta';
    }
}

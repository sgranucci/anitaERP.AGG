<?php

namespace App\Exports\Ventas;

use App\Services\Ventas\RemitoService;
use App\Support\Configuracion\EmpresaLogoArchivo;
use App\Support\Ventas\RemitoListadoFiltros;
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
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class RemitoListadoExport implements FromView, ShouldAutoSize, WithColumnFormatting, WithColumnWidths, WithEvents, WithStyles, WithTitle
{
    use Exportable;

    private const COL_ULTIMA = 'J';

    private RemitoService $remitoService;

    /** @var array<string, mixed> */
    private array $filtros = [];

    private bool $flDesdeIndex = false;

    private bool $hayFilaLogos = false;

    private int $filaCabecerasExcel = 2;

    private int $filaPrimeraDatosExcel = 3;

    private int $filaTituloExcel = 1;

    private int $filaInicioMeta = 1;

    private int $filasMeta = 2;

    private string $subtituloFiltros = '';

    /** @var list<string> */
    private array $rutasLogosExcel = [];

    public function __construct(RemitoService $remitoService)
    {
        $this->remitoService = $remitoService;
        $this->filtros = RemitoListadoFiltros::filtrosVacios();
    }

    public function view(): View
    {
        $remitos = $this->remitoService->leeRemitosIndex($this->filtros, false);

        $this->rutasLogosExcel = EmpresaLogoArchivo::rutasLogosCabeceraDesdeColeccion($remitos);
        $this->hayFilaLogos = count($this->rutasLogosExcel) > 0;
        $this->subtituloFiltros = RemitoListadoFiltros::subtituloFiltros($this->filtros);

        $this->filasMeta = 2; // título + generado
        if (trim($this->subtituloFiltros) !== '') {
            $this->filasMeta++;
        }
        if (count($remitos) > 0) {
            $this->filasMeta++; // contador
        }

        $offsetLogo = $this->hayFilaLogos ? 1 : 0;
        $this->filaInicioMeta = $offsetLogo + 1;
        $this->filaTituloExcel = $this->filaInicioMeta;
        $this->filaCabecerasExcel = $offsetLogo + $this->filasMeta + 1;
        $this->filaPrimeraDatosExcel = $this->filaCabecerasExcel + 1;

        return view('exports.ventas.remitoindex', [
            'remitos' => $remitos,
            'reservarFilaLogoExcel' => $this->hayFilaLogos,
            'subtituloFiltros' => $this->subtituloFiltros,
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
        $cols['E'] = NumberFormat::FORMAT_GENERAL;
        $cols['F'] = NumberFormat::FORMAT_GENERAL;
        $cols['G'] = NumberFormat::FORMAT_GENERAL;
        $cols['H'] = NumberFormat::FORMAT_GENERAL;

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
            'C' => 14,
            'D' => 35,
            'E' => 10,
            'F' => 10,
            'G' => 10,
            'H' => 10,
            'I' => 22,
            'J' => 14,
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

                for ($i = 0; $i < $this->filasMeta; $i++) {
                    $fila = $this->filaInicioMeta + $i;
                    $sheet->mergeCells('A'.$fila.':'.self::COL_ULTIMA.$fila);
                }

                $sheet->getRowDimension($this->filaTituloExcel)->setRowHeight(28);
                $sheet->getStyle('A'.$this->filaTituloExcel.':'.self::COL_ULTIMA.$this->filaTituloExcel)->applyFromArray([
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

                for ($i = 1; $i < $this->filasMeta; $i++) {
                    $fila = $this->filaInicioMeta + $i;
                    $sheet->getRowDimension($fila)->setRowHeight($i === 1 && trim($this->subtituloFiltros) !== '' ? 42 : 18);
                    $sheet->getStyle('A'.$fila.':'.self::COL_ULTIMA.$fila)->applyFromArray([
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

                $sheet->getStyle('A'.$this->filaCabecerasExcel.':'.self::COL_ULTIMA.$this->filaCabecerasExcel)->applyFromArray([
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

                $primera = $this->filaPrimeraDatosExcel;
                $sheet->getStyle('D'.$primera.':D'.$sheet->getHighestRow())
                    ->getAlignment()
                    ->setWrapText(true)
                    ->setVertical(Alignment::VERTICAL_TOP);
            },
        ];
    }

    public function title(): string
    {
        return 'Remitos de clientes';
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    public function parametros(array $filtros): self
    {
        $this->filtros = $filtros;
        $this->flDesdeIndex = true;

        return $this;
    }
}

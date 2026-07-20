<?php

namespace App\Exports\Caja\Bingo;

use App\Repositories\Caja\Bingo\BingoCartonRepositoryInterface;
use App\Support\Configuracion\EmpresaLogoArchivo;
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
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class BingoCartonListadoExport implements FromView, ShouldAutoSize, WithColumnFormatting, WithColumnWidths, WithEvents, WithStyles, WithTitle
{
    use Exportable;

    private const COL_ULTIMA = 'H';

    /** Congela también ID y Código (columnas A y B): el freeze arranca en C. */
    private const COL_FREEZE = 'C';

    private BingoCartonRepositoryInterface $repository;

    /** @var array<string, mixed>|string|null */
    private $filtros;

    private bool $flDesdeIndex = false;

    private bool $esCsv = false;

    private bool $hayFilaLogos = false;

    private int $filaCabecerasExcel = 3;

    private int $filaPrimeraDatosExcel = 4;

    private int $filaTituloExcel = 1;

    private int $filaSubtituloExcel = 2;

    /** @var list<string> */
    private array $rutasLogosExcel = [];

    public function __construct(BingoCartonRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function view(): View
    {
        if ($this->flDesdeIndex) {
            $datas = $this->repository->leeBingoCarton($this->filtros, false);
            self::enriquecerNombreEmpresa($datas);

            $this->rutasLogosExcel = EmpresaLogoArchivo::rutasLogosCabeceraDesdeColeccion($datas);
            $this->hayFilaLogos = count($this->rutasLogosExcel) > 0;
            $this->filaTituloExcel = $this->hayFilaLogos ? 2 : 1;
            $this->filaSubtituloExcel = $this->filaTituloExcel + 1;
            $this->filaCabecerasExcel = $this->filaSubtituloExcel + 1;
            $this->filaPrimeraDatosExcel = $this->filaCabecerasExcel + 1;

            return view('exports.caja.bingo.carton_index', [
                'datas' => $datas,
                'esExcel' => true,
                'reservarFilaLogoExcel' => $this->hayFilaLogos,
                'formatoNumero' => $this->formatoNumeroEfectivo(),
            ]);
        }

        $this->hayFilaLogos = false;
        $this->filaTituloExcel = 1;
        $this->filaSubtituloExcel = 2;
        $this->filaCabecerasExcel = 3;
        $this->filaPrimeraDatosExcel = 4;
        $this->rutasLogosExcel = [];

        return view('exports.caja.bingo.carton_index', [
            'datas' => collect(),
            'esExcel' => true,
            'reservarFilaLogoExcel' => false,
            'formatoNumero' => $this->formatoNumeroEfectivo(),
        ]);
    }

    public function columnFormats(): array
    {
        if ($this->flDesdeIndex) {
            return [
                'A' => NumberFormat::FORMAT_TEXT,
                'B' => NumberFormat::FORMAT_TEXT,
                // D = Precio: número real con máscara neutra (sumable/adaptable).
                'D' => ExcelFormatoNumero::codigoColumna(ExcelFormatoNumero::preferenciaGlobal(), 2),
            ];
        }

        return [];
    }

    public function styles(Worksheet $sheet)
    {
        if ($this->flDesdeIndex) {
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

        return [];
    }

    public function columnWidths(): array
    {
        if ($this->flDesdeIndex) {
            return [
                'A' => 8,
                'B' => 14,
                'C' => 36,
                'D' => 12,
                'E' => 10,
                'F' => 8,
                'G' => 28,
                'H' => 16,
            ];
        }

        return [];
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

                $filaSub = $this->filaSubtituloExcel;
                $sheet->mergeCells('A'.$filaSub.':'.self::COL_ULTIMA.$filaSub);
                $sheet->getStyle('A'.$filaSub)->getFont()->setName('Arial')->setSize(10)->setBold(true)->getColor()->setRGB('444444');

                $sheet->freezePane(self::COL_FREEZE.$this->filaPrimeraDatosExcel);

                $primera = $this->filaPrimeraDatosExcel;
                $sheet->getStyle('C'.$primera.':C'.$sheet->getHighestRow())
                    ->getAlignment()
                    ->setWrapText(true)
                    ->setVertical(Alignment::VERTICAL_TOP);
            },
        ];
    }

    public function title(): string
    {
        return 'Cartones bingo';
    }

    /**
     * @param  array<string, mixed>|string|null  $filtros
     */
    public function parametros($filtros, bool $esCsv = false): self
    {
        $this->filtros = $filtros;
        $this->esCsv = $esCsv;
        $this->flDesdeIndex = true;

        return $this;
    }

    private function formatoNumeroEfectivo(): string
    {
        $global = ExcelFormatoNumero::preferenciaGlobal();

        return $this->esCsv ? ExcelFormatoNumero::paraCsv($global) : $global;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, \App\Models\Caja\Bingo\BingoCarton>|\Illuminate\Database\Eloquent\Collection  $datas
     */
    private static function enriquecerNombreEmpresa($datas): void
    {
        foreach ($datas as $row) {
            $row->nombreempresa = $row->empresa->nombre ?? '';
        }
    }
}

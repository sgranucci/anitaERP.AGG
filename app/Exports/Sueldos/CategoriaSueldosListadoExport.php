<?php

namespace App\Exports\Sueldos;

use App\Repositories\Sueldos\Categoria_SueldosRepositoryInterface;
use App\Services\Sueldos\CategoriaBaseSueldosService;
use App\Support\Configuracion\EmpresaLogoArchivo;
use App\Support\Sueldos\CategoriaOrigenBases;
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

class CategoriaSueldosListadoExport implements FromView, ShouldAutoSize, WithColumnFormatting, WithColumnWidths, WithEvents, WithStyles, WithTitle
{
    use Exportable;

    private const COL_ULTIMA = 'E';

    private Categoria_SueldosRepositoryInterface $categoriaRepository;

    private CategoriaBaseSueldosService $baseService;

    /** @var array<string, mixed>|string|null */
    private $filtros;

    private bool $flDesdeIndex = false;

    private bool $hayFilaLogos = false;

    private int $filaCabecerasExcel = 2;

    private int $filaPrimeraDatosExcel = 3;

    private int $filaTituloExcel = 1;

    /** @var list<string> */
    private array $rutasLogosExcel = [];

    public function __construct(
        Categoria_SueldosRepositoryInterface $categoriaRepository,
        CategoriaBaseSueldosService $baseService
    ) {
        $this->categoriaRepository = $categoriaRepository;
        $this->baseService = $baseService;
    }

    public function view(): View
    {
        if ($this->flDesdeIndex) {
            $datas = $this->categoriaRepository->leeCategoria($this->filtros, false);
            $this->adjuntarBasesVigentes($datas);

            $this->rutasLogosExcel = EmpresaLogoArchivo::rutasLogosCabeceraDesdeColeccion($datas);
            $this->hayFilaLogos = count($this->rutasLogosExcel) > 0;
            $this->filaTituloExcel = $this->hayFilaLogos ? 2 : 1;
            $this->filaCabecerasExcel = $this->hayFilaLogos ? 3 : 2;
            $this->filaPrimeraDatosExcel = $this->filaCabecerasExcel + 1;

            return view('exports.sueldos.categoriaindex', [
                'datas' => $datas,
                'origenLabels' => CategoriaOrigenBases::LABELS,
                'reservarFilaLogoExcel' => $this->hayFilaLogos,
            ]);
        }

        $this->hayFilaLogos = false;
        $this->filaTituloExcel = 1;
        $this->filaCabecerasExcel = 2;
        $this->filaPrimeraDatosExcel = 3;
        $this->rutasLogosExcel = [];

        return view('exports.sueldos.categoriaindex', [
            'datas' => collect(),
            'origenLabels' => CategoriaOrigenBases::LABELS,
            'reservarFilaLogoExcel' => false,
        ]);
    }

    public function columnFormats(): array
    {
        if ($this->flDesdeIndex) {
            $cols = [];
            foreach (range('A', self::COL_ULTIMA) as $c) {
                $cols[$c] = NumberFormat::FORMAT_TEXT;
            }

            return $cols;
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
                'B' => 12,
                'C' => 34,
                'D' => 26,
                'E' => 60,
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

                $sheet->freezePane('A'.$this->filaPrimeraDatosExcel);

                $primera = $this->filaPrimeraDatosExcel;
                $sheet->getStyle('E'.$primera.':E'.$sheet->getHighestRow())
                    ->getAlignment()
                    ->setWrapText(true)
                    ->setVertical(Alignment::VERTICAL_TOP);
            },
        ];
    }

    public function title(): string
    {
        return 'Categorías de sueldos';
    }

    /**
     * @param  array<string, mixed>|string|null  $filtros
     */
    public function parametros($filtros)
    {
        $this->filtros = $filtros;
        $this->flDesdeIndex = true;

        return $this;
    }

    /**
     * @param  \Illuminate\Support\Collection|\Illuminate\Database\Eloquent\Collection  $datas
     */
    private function adjuntarBasesVigentes($datas): void
    {
        $ids = [];
        foreach ($datas as $d) {
            $ids[] = (int) $d->id;
        }

        $basesPorCategoria = $this->baseService->basesVigentesParaCategorias($ids);

        foreach ($datas as $d) {
            $d->bases_vigentes = $basesPorCategoria[(int) $d->id] ?? [];
        }
    }
}

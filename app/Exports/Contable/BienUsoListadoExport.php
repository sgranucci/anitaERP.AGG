<?php

namespace App\Exports\Contable;

use App\Models\Contable\BienUso;
use App\Repositories\Contable\BienUsoRepositoryInterface;
use App\Support\Configuracion\EmpresaLogoArchivo;
use App\Support\Contable\BienUsoVisibilidadSupport;
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

class BienUsoListadoExport implements FromView, ShouldAutoSize, WithColumnFormatting, WithColumnWidths, WithEvents, WithStyles, WithTitle
{
    use Exportable;

    private const COL_ULTIMA = 'N';

    private BienUsoRepositoryInterface $repository;

    /** @var array<string, mixed>|string|null */
    private $filtros;

    private bool $flDesdeIndex = false;

    private bool $hayFilaLogos = false;

    private int $filaCabecerasExcel = 2;

    private int $filaPrimeraDatosExcel = 3;

    private int $filaTituloExcel = 1;

    /** @var list<string> */
    private array $rutasLogosExcel = [];

    public function __construct(BienUsoRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function view(): View
    {
        if ($this->flDesdeIndex) {
            $datas = $this->repository->leeBienUso($this->filtros, false);
            self::enriquecerNombreEmpresa($datas);

            $this->rutasLogosExcel = EmpresaLogoArchivo::rutasLogosCabeceraDesdeColeccion($datas);
            $this->hayFilaLogos = count($this->rutasLogosExcel) > 0;
            $this->filaTituloExcel = $this->hayFilaLogos ? 2 : 1;
            $this->filaCabecerasExcel = $this->hayFilaLogos ? 3 : 2;
            $this->filaPrimeraDatosExcel = $this->filaCabecerasExcel + 1;

            return view('exports.contable.bien_usoindex', [
                'datas' => $datas,
                'estado_enum' => BienUso::$enumEstado,
                'centrocosto_enum' => BienUsoVisibilidadSupport::opcionesCentrocostoAbm(),
                'tipo_bien_enum' => BienUso::$enumTipoBien,
                'reservarFilaLogoExcel' => $this->hayFilaLogos,
            ]);
        }

        return view('exports.contable.bien_usoindex', [
            'datas' => collect(),
            'estado_enum' => BienUso::$enumEstado,
            'centrocosto_enum' => BienUsoVisibilidadSupport::opcionesCentrocostoAbm(),
            'tipo_bien_enum' => BienUso::$enumTipoBien,
            'reservarFilaLogoExcel' => false,
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
            'C' => 10,
            'D' => 18,
            'E' => 20,
            'F' => 14,
            'G' => 18,
            'H' => 16,
            'I' => 18,
            'J' => 14,
            'K' => 10,
            'L' => 14,
            'M' => 12,
            'N' => 24,
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

                $primera = $this->filaPrimeraDatosExcel;
                $sheet->getStyle('N'.$primera.':N'.$sheet->getHighestRow())
                    ->getAlignment()
                    ->setWrapText(true)
                    ->setVertical(Alignment::VERTICAL_TOP);
            },
        ];
    }

    public function title(): string
    {
        return 'Bienes de uso';
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
     * @param  \Illuminate\Support\Collection<int, \App\Models\Contable\BienUso>|\Illuminate\Database\Eloquent\Collection<int, \App\Models\Contable\BienUso>  $datas
     */
    private static function enriquecerNombreEmpresa($datas): void
    {
        $nombre = (string) config('app.empresa', '');
        foreach ($datas as $row) {
            $row->nombreempresa = $nombre;
        }
    }
}

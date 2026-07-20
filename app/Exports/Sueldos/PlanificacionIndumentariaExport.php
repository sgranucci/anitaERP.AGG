<?php

namespace App\Exports\Sueldos;

use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Support\Configuracion\EmpresaLogoArchivo;
use App\Support\Sueldos\PlanificacionIndumentariaConsulta;
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
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class PlanificacionIndumentariaExport implements FromView, ShouldAutoSize, WithColumnFormatting, WithColumnWidths, WithEvents, WithStyles, WithTitle
{
    use Exportable;

    private const COL_ULTIMA = 'J';

    private EmpresaRepositoryInterface $empresaRepository;

    /** @var array<string, mixed> */
    private array $filtros = [];

    private bool $flDesdeIndex = false;

    private bool $hayLogo = false;

    private int $filaTitulo = 1;

    private int $filaCabeceras = 2;

    private int $filaPrimeraDatos = 3;

    private ?string $rutaLogo = null;

    public function __construct(EmpresaRepositoryInterface $empresaRepository)
    {
        $this->empresaRepository = $empresaRepository;
    }

    public function view(): View
    {
        $filas = $this->flDesdeIndex
            ? PlanificacionIndumentariaConsulta::filas($this->filtros, $this->empresaRepository)
            : [];

        $this->rutaLogo = EmpresaLogoArchivo::rutaPngDefault();
        $this->hayLogo = $this->flDesdeIndex && is_string($this->rutaLogo) && is_readable($this->rutaLogo);
        $this->filaTitulo = $this->hayLogo ? 2 : 1;
        $this->filaCabeceras = $this->hayLogo ? 3 : 2;
        $this->filaPrimeraDatos = $this->filaCabeceras + 1;

        return view('exports.sueldos.planificacionindex', [
            'filas' => $filas,
            'reservarFilaLogoExcel' => $this->hayLogo,
        ]);
    }

    public function columnFormats(): array
    {
        if (! $this->flDesdeIndex) {
            return [];
        }

        return [
            'A' => NumberFormat::FORMAT_TEXT,
            'C' => NumberFormat::FORMAT_TEXT,
            'D' => NumberFormat::FORMAT_NUMBER,
            'E' => NumberFormat::FORMAT_NUMBER_00,
            'F' => NumberFormat::FORMAT_NUMBER_00,
            'G' => NumberFormat::FORMAT_NUMBER_00,
            'H' => NumberFormat::FORMAT_NUMBER_00,
            'I' => NumberFormat::FORMAT_NUMBER_00,
            'J' => NumberFormat::FORMAT_NUMBER,
        ];
    }

    public function styles(Worksheet $sheet)
    {
        if (! $this->flDesdeIndex) {
            return [];
        }

        return [
            $this->filaCabeceras => [
                'font' => ['bold' => true, 'color' => ['rgb' => '17202A'], 'size' => 11, 'name' => 'Arial'],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => '85C1E9']],
            ],
        ];
    }

    public function columnWidths(): array
    {
        if (! $this->flDesdeIndex) {
            return [];
        }

        return [
            'A' => 10, 'B' => 40, 'C' => 8, 'D' => 12, 'E' => 12,
            'F' => 12, 'G' => 12, 'H' => 12, 'I' => 10, 'J' => 14,
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

                if ($this->hayLogo && is_string($this->rutaLogo)) {
                    $sheet->getRowDimension(1)->setRowHeight(54);
                    $drawing = new Drawing;
                    $drawing->setName('Logo');
                    $drawing->setDescription('Logo empresa');
                    $drawing->setPath($this->rutaLogo);
                    $drawing->setResizeProportional(true);
                    $drawing->setHeight(46);
                    $drawing->setCoordinates('A1');
                    $drawing->setOffsetX(6);
                    $drawing->setOffsetY(4);
                    $drawing->setWorksheet($sheet);
                }

                $filaTit = $this->filaTitulo;
                $sheet->mergeCells('A'.$filaTit.':'.self::COL_ULTIMA.$filaTit);
                $sheet->getRowDimension($filaTit)->setRowHeight(30);
                $sheet->getStyle('A'.$filaTit.':'.self::COL_ULTIMA.$filaTit)->applyFromArray([
                    'font' => ['bold' => true, 'size' => 16, 'name' => 'Arial', 'color' => ['rgb' => '17202A']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER],
                ]);

                $sheet->freezePane('A'.$this->filaPrimeraDatos);
            },
        ];
    }

    public function title(): string
    {
        return 'Planificación indumentaria';
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    public function parametros(array $filtros)
    {
        $this->filtros = $filtros;
        $this->flDesdeIndex = true;

        return $this;
    }
}

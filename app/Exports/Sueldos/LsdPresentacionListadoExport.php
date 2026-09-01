<?php

namespace App\Exports\Sueldos;

use App\Models\Sueldos\Lsd_Presentacion_Sueldos;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Support\Configuracion\EmpresaLogoArchivo;
use App\Support\Sueldos\LsdPresentacionListadoFiltros;
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

class LsdPresentacionListadoExport implements FromView, ShouldAutoSize, WithColumnFormatting, WithColumnWidths, WithEvents, WithStyles, WithTitle
{
    use Exportable;

    private const COL_ULTIMA = 'J';

    /** @var array<string, mixed>|string|null */
    private $filtros;

    private bool $flDesdeIndex = false;

    private bool $hayFilaLogos = false;

    private int $filaCabecerasExcel = 2;

    private int $filaPrimeraDatosExcel = 3;

    private int $filaTituloExcel = 1;

    /** @var list<string> */
    private array $rutasLogosExcel = [];

    public function __construct(private EmpresaRepositoryInterface $empresaRepository)
    {
    }

    public function view(): View
    {
        if (! $this->flDesdeIndex) {
            $this->hayFilaLogos = false;
            $this->filaTituloExcel = 1;
            $this->filaCabecerasExcel = 2;
            $this->filaPrimeraDatosExcel = 3;
            $this->rutasLogosExcel = [];

            return view('exports.sueldos.lsdindex', [
                'datas' => collect(),
                'reservarFilaLogoExcel' => false,
            ]);
        }

        $filtros = is_array($this->filtros) ? $this->filtros : LsdPresentacionListadoFiltros::filtrosVacios();
        $query = Lsd_Presentacion_Sueldos::query()->with(['empresa', 'liquidacion']);
        if (($filtros['empresa_scope'] ?? 'una') === 'todas' || empty($filtros['empresa_id'])) {
            $this->empresaRepository->aplicarFiltroEmpresasAsignadas($query, 'lsd_presentacion_sueldos.empresa_id');
        }
        LsdPresentacionListadoFiltros::aplicar($query, $filtros);
        $datas = $query->orderByDesc('periodo')->orderByDesc('nro_liquidacion_afip')->get();

        $this->rutasLogosExcel = EmpresaLogoArchivo::rutasLogosCabeceraDesdeColeccion($datas);
        $this->hayFilaLogos = count($this->rutasLogosExcel) > 0;
        $this->filaTituloExcel = $this->hayFilaLogos ? 2 : 1;
        $this->filaCabecerasExcel = $this->hayFilaLogos ? 3 : 2;
        $this->filaPrimeraDatosExcel = $this->filaCabecerasExcel + 1;

        return view('exports.sueldos.lsdindex', [
            'datas' => $datas,
            'reservarFilaLogoExcel' => $this->hayFilaLogos,
        ]);
    }

    public function columnFormats(): array
    {
        return $this->flDesdeIndex ? ['C' => NumberFormat::FORMAT_TEXT, 'D' => NumberFormat::FORMAT_TEXT] : [];
    }

    public function styles(Worksheet $sheet)
    {
        if (! $this->flDesdeIndex) {
            return [];
        }

        return [
            $this->filaCabecerasExcel => [
                'font' => ['bold' => true, 'color' => ['rgb' => '17202A'], 'size' => 11, 'name' => 'Arial'],
                'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'color' => ['rgb' => '85C1E9']],
            ],
        ];
    }

    public function columnWidths(): array
    {
        return $this->flDesdeIndex
            ? ['A' => 18, 'B' => 10, 'C' => 10, 'D' => 8, 'E' => 12, 'F' => 28, 'G' => 16, 'H' => 12, 'I' => 12, 'J' => 28]
            : [];
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
                $filaTit = $this->filaTituloExcel;
                $sheet->mergeCells('A'.$filaTit.':'.self::COL_ULTIMA.$filaTit);
                $sheet->getRowDimension($filaTit)->setRowHeight(30);
                $sheet->getStyle('A'.$filaTit.':'.self::COL_ULTIMA.$filaTit)->applyFromArray([
                    'font' => ['bold' => true, 'size' => 16, 'name' => 'Arial', 'color' => ['rgb' => '17202A']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER],
                ]);
                $sheet->freezePane('A'.$this->filaPrimeraDatosExcel);
            },
        ];
    }

    public function title(): string
    {
        return 'LSD';
    }

    /** @param  array<string, mixed>|string|null  $filtros */
    public function parametros($filtros)
    {
        $this->filtros = $filtros;
        $this->flDesdeIndex = true;

        return $this;
    }
}

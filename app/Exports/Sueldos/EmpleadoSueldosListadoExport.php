<?php

namespace App\Exports\Sueldos;

use App\Repositories\Sueldos\Empleado_SueldosRepositoryInterface;
use App\Support\Configuracion\EmpresaLogoArchivo;
use App\Support\Listado\ListadoExportPresentacionSupport;
use App\Support\Sueldos\EmpleadoSueldosListadoColumnas;
use App\Support\Sueldos\EmpleadoSueldosListadoFiltros;
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

class EmpleadoSueldosListadoExport implements FromView, ShouldAutoSize, WithColumnFormatting, WithColumnWidths, WithEvents, WithStyles, WithTitle
{
    use Exportable;

    private Empleado_SueldosRepositoryInterface $repository;

    /** @var list<string> */
    private array $columnas = [];

    /** @var array<string, string> */
    private array $etiquetas = [];

    /** @var array<string, mixed>|string|null */
    private $filtros;

    private bool $flDesdeIndex = false;

    private bool $hayFilaLogos = false;

    private int $filaCabecerasExcel = 2;

    private int $filaPrimeraDatosExcel = 3;

    private int $filaTituloExcel = 1;

    /** @var list<string> */
    private array $rutasLogosExcel = [];

    public function __construct(Empleado_SueldosRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function view(): View
    {
        if ($this->flDesdeIndex) {
            $datas = $this->repository->leeEmpleado($this->filtros, false);

            $this->rutasLogosExcel = EmpresaLogoArchivo::rutasLogosCabeceraDesdeColeccion($datas);
            $this->hayFilaLogos = count($this->rutasLogosExcel) > 0;
            $filtrosPdf = is_array($this->filtros) ? $this->filtros : [];
            $filtrosPdf['orden'] = $filtrosPdf['orden'] ?? ($filtrosPdf['sort'] ?? []);
            $subtitulo = ListadoExportPresentacionSupport::subtitulo(
                $filtrosPdf,
                $this->etiquetas,
                EmpleadoSueldosListadoFiltros::camposOrdenables()
            );
            $this->filaTituloExcel = $this->hayFilaLogos ? 2 : 1;
            $this->filaCabecerasExcel = $this->filaTituloExcel + 1 + ($subtitulo !== '' ? 1 : 0);
            $this->filaPrimeraDatosExcel = $this->filaCabecerasExcel + 1;

            return view('exports.sueldos.empleadoindex', [
                'datas' => $datas,
                'reservarFilaLogoExcel' => $this->hayFilaLogos,
                'columnasVisibles' => $this->columnas,
                'etiquetasColumnas' => $this->etiquetas,
                'subtitulo' => $subtitulo,
            ]);
        }

        $this->hayFilaLogos = false;
        $this->filaTituloExcel = 1;
        $this->filaCabecerasExcel = 2;
        $this->filaPrimeraDatosExcel = 3;
        $this->rutasLogosExcel = [];

        return view('exports.sueldos.empleadoindex', [
            'datas' => collect(),
            'reservarFilaLogoExcel' => false,
            'columnasVisibles' => $this->columnas,
            'etiquetasColumnas' => $this->etiquetas,
        ]);
    }

    public function columnFormats(): array
    {
        if ($this->flDesdeIndex) {
            $formatos = [];
            $catalogo = EmpleadoSueldosListadoColumnas::catalogoActivo();
            foreach ($this->columnasExport() as $i => $key) {
                $type = $catalogo[$key]['type'] ?? 'texto';
                $formatos[ListadoExportPresentacionSupport::columnaLetra($i)] = $type === 'decimal'
                    ? NumberFormat::FORMAT_NUMBER_00
                    : NumberFormat::FORMAT_TEXT;
            }

            return $formatos;
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
                'C' => 50,
                'D' => 14,
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
                $ultima = $this->columnaUltima();
                $sheet->mergeCells('A'.$filaTit.':'.$ultima.$filaTit);
                $sheet->getRowDimension($filaTit)->setRowHeight(30);
                $sheet->getStyle('A'.$filaTit.':'.$ultima.$filaTit)->applyFromArray([
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
        return 'Empleados';
    }

    /**
     * @param  array<string, mixed>|string|null  $filtros
     * @param  list<string>|null  $columnas
     * @param  array<string, string>|null  $etiquetas
     */
    public function parametros($filtros, ?array $columnas = null, ?array $etiquetas = null)
    {
        $this->filtros = $filtros;
        $this->flDesdeIndex = true;
        $this->columnas = EmpleadoSueldosListadoColumnas::normalizarVisibles($columnas);
        $this->etiquetas = is_array($etiquetas) ? $etiquetas : [];

        return $this;
    }

    /**
     * @return list<string>
     */
    private function columnasExport(): array
    {
        $catalogo = EmpleadoSueldosListadoColumnas::catalogoActivo();
        $columnas = array_values(array_filter(
            $this->columnas,
            static fn ($k) => isset($catalogo[$k]) && ! empty($catalogo[$k]['export'])
        ));

        return $columnas !== [] ? $columnas : EmpleadoSueldosListadoColumnas::defaultsVisibles();
    }

    private function columnaUltima(): string
    {
        $n = count($this->columnasExport());

        return ListadoExportPresentacionSupport::columnaLetra(max(0, $n - 1));
    }
}

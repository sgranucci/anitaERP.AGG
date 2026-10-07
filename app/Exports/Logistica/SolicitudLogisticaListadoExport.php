<?php

namespace App\Exports\Logistica;

use App\Support\Configuracion\EmpresaLogoArchivo;
use App\Support\Listado\ListadoExportPresentacionSupport;
use App\Support\Logistica\SolicitudLogisticaListadoColumnas;
use App\Support\Logistica\SolicitudLogisticaListadoFiltros;
use App\Support\Logistica\SolicitudLogisticaListadoQuery;
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

class SolicitudLogisticaListadoExport implements FromView, ShouldAutoSize, WithColumnFormatting, WithColumnWidths, WithEvents, WithStyles, WithTitle
{
    use Exportable;

    /** @var list<string> */
    private array $columnas = [];

    /** @var array<string, string> */
    private array $etiquetas = [];

    /** @var array<string, mixed> */
    private array $filtros = [];

    private int $usuarioId = 0;

    private bool $puedeTodas = false;

    private bool $flDesdeIndex = false;

    private bool $hayFilaLogos = false;

    private int $filaCabecerasExcel = 2;

    private int $filaPrimeraDatosExcel = 3;

    private int $filaTituloExcel = 1;

    /** @var list<string> */
    private array $rutasLogosExcel = [];

    public function view(): View
    {
        $datas = SolicitudLogisticaListadoQuery::filtrada($this->filtros, $this->usuarioId, $this->puedeTodas)->get();
        $this->rutasLogosExcel = EmpresaLogoArchivo::rutasLogosCabeceraDesdeColeccion($datas);
        $this->hayFilaLogos = count($this->rutasLogosExcel) > 0;
        $filtrosPdf = $this->filtros;
        $filtrosPdf['orden'] = $filtrosPdf['orden'] ?? ($filtrosPdf['sort'] ?? []);
        $subtitulo = ListadoExportPresentacionSupport::subtitulo(
            $filtrosPdf,
            $this->etiquetas,
            SolicitudLogisticaListadoFiltros::camposOrdenables()
        );
        $this->filaTituloExcel = $this->hayFilaLogos ? 2 : 1;
        $this->filaCabecerasExcel = $this->filaTituloExcel + 1 + ($subtitulo !== '' ? 1 : 0);
        $this->filaPrimeraDatosExcel = $this->filaCabecerasExcel + 1;

        return view('exports.logistica.solicitudindex', [
            'datas' => $datas,
            'reservarFilaLogoExcel' => $this->hayFilaLogos,
            'columnasVisibles' => $this->columnas,
            'etiquetasColumnas' => $this->etiquetas,
            'subtitulo' => $subtitulo,
        ]);
    }

    public function columnFormats(): array
    {
        $formatos = [];
        $catalogo = SolicitudLogisticaListadoColumnas::catalogoActivo();
        foreach ($this->columnasExport() as $i => $key) {
            $type = $catalogo[$key]['type'] ?? 'texto';
            $formatos[ListadoExportPresentacionSupport::columnaLetra($i)] = $type === 'decimal'
                ? NumberFormat::FORMAT_NUMBER_00
                : NumberFormat::FORMAT_TEXT;
        }

        return $formatos;
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
        $anchos = [16, 14, 32, 22, 28, 14, 24, 16, 10, 18];
        $out = [];
        foreach ($this->columnasExport() as $i => $key) {
            $out[ListadoExportPresentacionSupport::columnaLetra($i)] = $anchos[$i] ?? 18;
        }

        return $out;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
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
        return 'Solicitudes';
    }

    /**
     * @param  array<string, mixed>|string|null  $filtros
     * @param  list<string>|null  $columnas
     * @param  array<string, string>|null  $etiquetas
     */
    public function parametros($filtros, ?array $columnas = null, ?array $etiquetas = null, int $usuarioId = 0, bool $puedeTodas = false)
    {
        $this->filtros = is_array($filtros) ? $filtros : [];
        $this->flDesdeIndex = true;
        $this->usuarioId = $usuarioId;
        $this->puedeTodas = $puedeTodas;
        $this->columnas = SolicitudLogisticaListadoColumnas::normalizarVisibles($columnas);
        $this->etiquetas = is_array($etiquetas) ? $etiquetas : [];

        return $this;
    }

    /**
     * @return list<string>
     */
    private function columnasExport(): array
    {
        $catalogo = SolicitudLogisticaListadoColumnas::catalogoActivo();
        $columnas = array_values(array_filter(
            $this->columnas,
            static fn ($k) => isset($catalogo[$k]) && ! empty($catalogo[$k]['export'])
        ));

        return $columnas !== [] ? $columnas : SolicitudLogisticaListadoColumnas::defaultsVisibles();
    }

    private function columnaUltima(): string
    {
        $n = count($this->columnasExport());

        return ListadoExportPresentacionSupport::columnaLetra(max(0, $n - 1));
    }
}

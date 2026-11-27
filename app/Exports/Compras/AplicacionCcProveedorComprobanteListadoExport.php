<?php

namespace App\Exports\Compras;

use App\Support\Compras\AplicacionCcProveedorComprobanteListadoColumnas;
use App\Support\Compras\AplicacionCcProveedorComprobanteListadoFiltros;
use App\Support\Compras\AplicacionCcProveedorComprobanteListadoQuery;
use App\Support\Configuracion\EmpresaLogoArchivo;
use App\Support\Listado\ListadoAgrupacionSupport;
use App\Support\Listado\ListadoExportPresentacionSupport;
use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class AplicacionCcProveedorComprobanteListadoExport implements FromView, WithColumnFormatting, WithColumnWidths, WithEvents, WithStyles, WithTitle
{
    use Exportable;

    private const FORMATO_IMPORTE = '#,##0.00';

    /** @var list<string> */
    private array $columnas = [];

    /** @var array<string, string> */
    private array $etiquetas = [];

    /** @var array<string, mixed> */
    private array $filtros = [];

    private bool $hayFilaLogos = false;

    private int $filaTituloExcel = 1;

    private int $filaCabecerasExcel = 2;

    private int $filaPrimeraDatosExcel = 3;

    /** @var list<string> */
    private array $rutasLogosExcel = [];

    public function view(): View
    {
        $datas = AplicacionCcProveedorComprobanteListadoQuery::lee($this->filtros, false);
        $this->rutasLogosExcel = EmpresaLogoArchivo::rutasLogosCabeceraDesdeColeccion($datas);
        $this->hayFilaLogos = count($this->rutasLogosExcel) > 0;

        $campos = AplicacionCcProveedorComprobanteListadoFiltros::camposOrdenables();
        $filtrosPdf = $this->filtros;
        $filtrosPdf['orden'] = $filtrosPdf['orden'] ?? ($filtrosPdf['sort'] ?? []);
        $subtitulo = ListadoExportPresentacionSupport::subtitulo($filtrosPdf, $this->etiquetas, $campos);
        $this->filaTituloExcel = $this->hayFilaLogos ? 2 : 1;
        $this->filaCabecerasExcel = $this->filaTituloExcel + 1 + ($subtitulo !== '' ? 1 : 0);
        $this->filaPrimeraDatosExcel = $this->filaCabecerasExcel + 1;

        return view('exports.compras.aplicacion_cc_comprobanteindex', [
            'datas' => $datas,
            'filasSegmentadas' => ListadoAgrupacionSupport::segmentar(
                $datas,
                ListadoAgrupacionSupport::normalizar($this->filtros['agrupar'] ?? [], $campos),
                static fn ($row, string $campo): string => AplicacionCcProveedorComprobanteListadoColumnas::valorCelda($row, $campo),
                $this->etiquetas
            ),
            'reservarFilaLogoExcel' => $this->hayFilaLogos,
            'columnasVisibles' => $this->columnasExport(),
            'etiquetasColumnas' => $this->etiquetas,
            'subtitulo' => $subtitulo,
        ]);
    }

    public function columnFormats(): array
    {
        $formatos = [];
        $catalogo = AplicacionCcProveedorComprobanteListadoColumnas::catalogoActivo();
        foreach ($this->columnasExport() as $i => $key) {
            $type = $catalogo[$key]['type'] ?? 'texto';
            $formatos[ListadoExportPresentacionSupport::columnaLetra($i)] = $type === 'decimal'
                ? self::FORMATO_IMPORTE
                : \PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_TEXT;
        }

        return $formatos;
    }

    public function styles(Worksheet $sheet)
    {
        return [
            $this->filaCabecerasExcel => [
                'font' => ['bold' => true, 'color' => ['rgb' => '17202A'], 'size' => 11, 'name' => 'Arial'],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => '85C1E9']],
            ],
        ];
    }

    public function columnWidths(): array
    {
        $catalogo = AplicacionCcProveedorComprobanteListadoColumnas::catalogoActivo();
        $out = [];
        foreach ($this->columnasExport() as $i => $key) {
            $out[ListadoExportPresentacionSupport::columnaLetra($i)] = match ($key) {
                'detalle', 'observacion' => 60,
                'proveedor', 'empresa' => 32,
                default => ($catalogo[$key]['type'] ?? '') === 'decimal' ? 16 : 14,
            };
        }

        return $out;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();

                if ($this->hayFilaLogos) {
                    $sheet->getRowDimension(1)->setRowHeight(54);
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
                        $drawing->setOffsetX(6 + $idx * 160);
                        $drawing->setOffsetY(4);
                        $drawing->setWorksheet($sheet);
                    }
                }

                $filaTit = $this->filaTituloExcel;
                $ultima = ListadoExportPresentacionSupport::columnaLetra(max(0, count($this->columnasExport()) - 1));
                $sheet->mergeCells('A'.$filaTit.':'.$ultima.$filaTit);
                $sheet->getRowDimension($filaTit)->setRowHeight(30);
                $sheet->getStyle('A'.$filaTit.':'.$ultima.$filaTit)->applyFromArray([
                    'font' => ['bold' => true, 'size' => 16, 'name' => 'Arial', 'color' => ['rgb' => '17202A']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER],
                ]);

                foreach ($this->columnasExport() as $i => $key) {
                    if (in_array($key, ['detalle', 'observacion'], true)) {
                        $letra = ListadoExportPresentacionSupport::columnaLetra($i);
                        $sheet->getStyle($letra.$this->filaPrimeraDatosExcel.':'.$letra.$sheet->getHighestRow())
                            ->getAlignment()->setWrapText(true);
                    }
                }

                $sheet->freezePane('A'.$this->filaPrimeraDatosExcel);
            },
        ];
    }

    public function title(): string
    {
        return 'Aplicaciones CC';
    }

    /**
     * @param  array<string, mixed>|null  $filtros
     * @param  list<string>|null  $columnas
     * @param  array<string, string>|null  $etiquetas
     */
    public function parametros(?array $filtros, ?array $columnas = null, ?array $etiquetas = null): static
    {
        $this->filtros = $filtros ?? [];
        $this->columnas = AplicacionCcProveedorComprobanteListadoColumnas::normalizarVisibles($columnas);
        $this->etiquetas = $etiquetas ?? [];

        return $this;
    }

    /**
     * @return list<string>
     */
    private function columnasExport(): array
    {
        $catalogo = AplicacionCcProveedorComprobanteListadoColumnas::catalogoActivo();
        $columnas = array_values(array_filter(
            $this->columnas,
            static fn ($k) => isset($catalogo[$k]) && ! empty($catalogo[$k]['export'])
        ));

        return $columnas !== [] ? $columnas : AplicacionCcProveedorComprobanteListadoColumnas::defaultsVisibles();
    }
}

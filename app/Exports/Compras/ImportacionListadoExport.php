<?php

declare(strict_types=1);

namespace App\Exports\Compras;

use App\Repositories\Compras\ImportacionRepositoryInterface;
use App\Support\Compras\ImportacionListadoColumnas;
use App\Support\Compras\ImportacionListadoFiltros;
use App\Support\Configuracion\EmpresaLogoArchivo;
use App\Support\Listado\ListadoAgrupacionSupport;
use App\Support\Listado\ListadoColumnaEtiquetaSupport;
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
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Excel/CSV del listado de importaciones: mismos filtros (chips + QBE), orden, agrupación
 * y columnas/títulos que la grilla de pantalla.
 */
class ImportacionListadoExport implements FromView, WithColumnFormatting, WithColumnWidths, WithEvents, WithStyles, WithTitle
{
    use Exportable;

    /** @var array<string, mixed>|string|null */
    private $filtros;

    /** @var list<string> */
    private array $columnas = [];

    /** @var array<string, string> */
    private array $etiquetas = [];

    private bool $hayFilaLogos = false;

    private int $filaTituloExcel = 1;

    private int $filaCabecerasExcel = 4;

    private int $filaPrimeraDatosExcel = 5;

    private string $colUltima = 'A';

    /** @var list<string> */
    private array $rutasLogosExcel = [];

    /** @var list<array{fila: int, nivel: int}> */
    private array $filasGrupoExcel = [];

    public function __construct(
        private readonly ImportacionRepositoryInterface $repository,
    ) {}

    /**
     * @param  array<string, mixed>|string|null  $filtros
     * @param  list<string>|null  $columnas
     * @param  array<string, string>|null  $etiquetas
     */
    public function parametros($filtros, ?array $columnas = null, ?array $etiquetas = null): self
    {
        $this->filtros = $filtros;
        $this->columnas = is_array($columnas) ? $columnas : [];
        $this->etiquetas = is_array($etiquetas) ? $etiquetas : [];

        return $this;
    }

    public function view(): View
    {
        $columnasExport = $this->columnasEfectivasExport();
        $etiquetas = $this->etiquetas !== []
            ? $this->etiquetas
            : ListadoColumnaEtiquetaSupport::etiquetasEfectivas(
                ImportacionListadoColumnas::RECURSO,
                ImportacionListadoColumnas::catalogoActivo()
            );

        $filtros = is_array($this->filtros) ? $this->filtros : [];
        $campos = ImportacionListadoFiltros::camposOrdenables();
        $datas = $this->repository->leeImportacion($this->filtros, false);

        $agrupar = ListadoAgrupacionSupport::normalizar($filtros['agrupar'] ?? [], $campos);
        $filas = ListadoAgrupacionSupport::segmentar(
            $datas,
            $agrupar,
            static fn (object $row, string $campo): string => ImportacionListadoColumnas::valorCelda($row, $campo),
            $etiquetas
        );

        $this->rutasLogosExcel = EmpresaLogoArchivo::rutasLogosCabeceraDesdeColeccion($datas);
        $this->hayFilaLogos = $this->rutasLogosExcel !== [];
        $subtitulo = ListadoExportPresentacionSupport::subtitulo($filtros, $etiquetas, $campos);
        $filasMeta = 3 + ($subtitulo !== '' ? 1 : 0);
        $offsetLogo = $this->hayFilaLogos ? 1 : 0;
        $this->filaTituloExcel = $offsetLogo + 1;
        $this->filaCabecerasExcel = $offsetLogo + $filasMeta + 1;
        $this->filaPrimeraDatosExcel = $this->filaCabecerasExcel + 1;
        $this->colUltima = ListadoExportPresentacionSupport::columnaLetra(max(0, count($columnasExport) - 1));

        $this->filasGrupoExcel = [];
        $fila = $this->filaPrimeraDatosExcel;
        foreach ($filas as $item) {
            if (($item['type'] ?? '') === 'header') {
                $this->filasGrupoExcel[] = ['fila' => $fila, 'nivel' => (int) ($item['nivel'] ?? 0)];
            }
            $fila++;
        }

        return view('exports.compras.importacionindex', [
            'datas' => $datas,
            'filasSegmentadas' => $filas,
            'columnasVisibles' => $columnasExport,
            'etiquetasColumnas' => $etiquetas,
            'reservarFilaLogoExcel' => $this->hayFilaLogos,
            'subtitulo' => $subtitulo,
            'totalFilas' => is_countable($datas) ? count($datas) : 0,
        ]);
    }

    public function title(): string
    {
        return 'Importaciones';
    }

    /** @return array<string, string> */
    public function columnFormats(): array
    {
        $formats = [];
        foreach ($this->columnasEfectivasExport() as $i => $key) {
            $meta = ImportacionListadoColumnas::COLUMNAS[$key] ?? [];
            $letra = ListadoExportPresentacionSupport::columnaLetra($i);
            if (($meta['type'] ?? '') === 'decimal') {
                $formats[$letra] = (int) ($meta['decimales'] ?? 2) > 2 ? '#,##0.000000' : '#,##0.00';
            } elseif ($key === 'codigo') {
                $formats[$letra] = NumberFormat::FORMAT_TEXT;
            }
        }

        return $formats;
    }

    /** @return array<string, int> */
    public function columnWidths(): array
    {
        $widths = [];
        foreach ($this->columnasEfectivasExport() as $i => $key) {
            $widths[ListadoExportPresentacionSupport::columnaLetra($i)] = match ($key) {
                'id' => 8,
                'codigo' => 16,
                'empresa' => 18,
                'proveedor' => 30,
                'estado' => 16,
                'moneda' => 8,
                default => 15,
            };
        }

        return $widths;
    }

    public function styles(Worksheet $sheet): array
    {
        return [];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $col = $this->colUltima;

                if ($this->hayFilaLogos) {
                    $sheet->getRowDimension(1)->setRowHeight(54);
                    $offsetX = 6;
                    foreach ($this->rutasLogosExcel as $idx => $ruta) {
                        if (! is_string($ruta) || ! is_readable($ruta)) {
                            continue;
                        }
                        $drawing = new Drawing;
                        $drawing->setName('Logo');
                        $drawing->setDescription('Logo');
                        $drawing->setPath($ruta);
                        $drawing->setResizeProportional(true);
                        $drawing->setHeight(46);
                        $drawing->setCoordinates('A1');
                        $drawing->setOffsetX($offsetX + $idx * 160);
                        $drawing->setOffsetY(4);
                        $drawing->setWorksheet($sheet);
                    }
                }

                $ultimaMeta = $this->filaCabecerasExcel - 1;
                for ($f = $this->filaTituloExcel; $f <= $ultimaMeta; $f++) {
                    $sheet->mergeCells('A'.$f.':'.$col.$f);
                }
                $sheet->getRowDimension($this->filaTituloExcel)->setRowHeight(28);
                $sheet->getStyle('A'.$this->filaTituloExcel)->applyFromArray([
                    'font' => ['bold' => true, 'size' => 16, 'name' => 'Arial', 'color' => ['rgb' => '17202A']],
                    'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
                ]);
                if ($ultimaMeta > $this->filaTituloExcel) {
                    $sheet->getStyle('A'.($this->filaTituloExcel + 1).':'.$col.$ultimaMeta)->applyFromArray([
                        'font' => ['size' => 10, 'name' => 'Arial', 'color' => ['rgb' => '444444']],
                        'alignment' => ['wrapText' => true, 'vertical' => Alignment::VERTICAL_CENTER],
                    ]);
                }

                $last = $sheet->getHighestRow();
                $sheet->getStyle('A'.$this->filaCabecerasExcel.':'.$col.$last)->applyFromArray([
                    'borders' => [
                        'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'CCCCCC']],
                    ],
                    'font' => ['name' => 'Arial', 'size' => 10],
                ]);
                $sheet->getStyle('A'.$this->filaCabecerasExcel.':'.$col.$this->filaCabecerasExcel)->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['rgb' => '17202A'], 'size' => 11, 'name' => 'Arial'],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => '85C1E9']],
                ]);

                foreach ($this->columnasEfectivasExport() as $i => $key) {
                    if ((ImportacionListadoColumnas::COLUMNAS[$key]['type'] ?? '') !== 'decimal') {
                        continue;
                    }
                    $letra = ListadoExportPresentacionSupport::columnaLetra($i);
                    $sheet->getStyle($letra.$this->filaPrimeraDatosExcel.':'.$letra.$last)
                        ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                }

                foreach ($this->filasGrupoExcel as $grupo) {
                    $rgb = ($grupo['nivel'] ?? 0) === 0 ? 'D6EAF8' : 'EAF2F8';
                    $sheet->mergeCells('A'.$grupo['fila'].':'.$col.$grupo['fila']);
                    $sheet->getStyle('A'.$grupo['fila'].':'.$col.$grupo['fila'])->applyFromArray([
                        'font' => ['bold' => true, 'color' => ['rgb' => '1B4F72'], 'name' => 'Arial'],
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => $rgb]],
                    ]);
                }

                $sheet->freezePane('A'.$this->filaPrimeraDatosExcel);
            },
        ];
    }

    /**
     * @return list<string>
     */
    private function columnasEfectivasExport(): array
    {
        $columnas = $this->columnas !== []
            ? ImportacionListadoColumnas::normalizarVisibles($this->columnas)
            : ImportacionListadoColumnas::defaultsVisibles();

        return array_values(array_filter(
            $columnas,
            static fn ($k) => ! empty(ImportacionListadoColumnas::COLUMNAS[$k]['export'])
        ));
    }
}

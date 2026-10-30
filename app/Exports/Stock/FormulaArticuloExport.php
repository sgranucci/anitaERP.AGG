<?php

namespace App\Exports\Stock;

use App\Queries\Stock\FormulaArticuloQueryInterface;
use App\Support\Configuracion\EmpresaLogoArchivo;
use App\Support\Stock\ArticuloPrecioUltimaCompraSupport;
use App\Support\Stock\FormulaArticuloListadoColumnas;
use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;

class FormulaArticuloExport implements FromView, WithColumnFormatting, WithColumnWidths, WithEvents, WithTitle
{
    use Exportable;

    private FormulaArticuloQueryInterface $formulaQuery;

    /** @var array<string, mixed> */
    private array $filtros = [];

    /** @var list<string> */
    private array $columnasVisibles = [];

    /** @var array<string, string> */
    private array $etiquetas = [];

    private string $subtitulo = '';

    private bool $hayFilaLogos = false;

    private int $filaCabecerasExcel = 4;

    private int $filaPrimeraDatosExcel = 5;

    private int $filasMeta = 3;

    private string $colUltima = 'A';

    /** @var list<string> */
    private array $rutasLogosExcel = [];

    public function __construct(FormulaArticuloQueryInterface $formulaQuery)
    {
        $this->formulaQuery = $formulaQuery;
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @param  list<string>  $columnasVisibles
     * @param  array<string, string>  $etiquetas
     */
    public function parametros(array $filtros, array $columnasVisibles, array $etiquetas, string $subtitulo): self
    {
        $this->filtros = $filtros;
        $this->columnasVisibles = $columnasVisibles !== []
            ? $columnasVisibles
            : FormulaArticuloListadoColumnas::defaultsVisibles();
        $this->etiquetas = $etiquetas;
        $this->subtitulo = $subtitulo;
        $n = max(1, count($this->columnasVisibles));
        $this->colUltima = Coordinate::stringFromColumnIndex($n);

        return $this;
    }

    public function view(): View
    {
        $formulas = $this->formulaQuery->leeListado($this->filtros, false, true);
        ArticuloPrecioUltimaCompraSupport::enriquecerFormulasPaginadasConCosto($formulas);
        $this->rutasLogosExcel = EmpresaLogoArchivo::rutasLogosCabeceraDesdeColeccion($formulas);
        $this->hayFilaLogos = count($this->rutasLogosExcel) > 0;
        $this->filasMeta = 2;
        if (trim($this->subtitulo) !== '') {
            $this->filasMeta++;
        }
        $this->filasMeta++;
        $offsetLogo = $this->hayFilaLogos ? 1 : 0;
        $this->filaCabecerasExcel = $offsetLogo + $this->filasMeta + 1;
        $this->filaPrimeraDatosExcel = $this->filaCabecerasExcel + 1;

        return view('exports.stock.formulaarticuloindex', [
            'formulas' => $formulas,
            'columnasVisibles' => $this->columnasVisibles,
            'etiquetasColumnas' => $this->etiquetas,
            'subtitulo' => $this->subtitulo,
            'totalFilas' => $formulas->count(),
            'reservarFilaLogoExcel' => $this->hayFilaLogos,
        ]);
    }

    public function title(): string
    {
        return 'Fórmulas de artículos';
    }

    public function columnFormats(): array
    {
        $formats = [];
        foreach ($this->columnasVisibles as $i => $key) {
            $letra = Coordinate::stringFromColumnIndex($i + 1);
            $formats[$letra] = $key === 'cantidadunidad'
                ? NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1
                : NumberFormat::FORMAT_TEXT;
        }

        return $formats;
    }

    public function columnWidths(): array
    {
        $anchos = [];
        foreach ($this->columnasVisibles as $i => $key) {
            $letra = Coordinate::stringFromColumnIndex($i + 1);
            $anchos[$letra] = match ($key) {
                'id', 'numero', 'cantidadunidad', 'estado' => 14,
                'sku' => 16,
                'articulo', 'detalle' => 32,
                'items' => 48,
                'fecha_alta', 'fecha_modificacion' => 18,
                default => 22,
            };
        }

        return $anchos;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $ultima = $this->colUltima;

                if ($this->hayFilaLogos && $this->rutasLogosExcel !== []) {
                    $sheet->getRowDimension(1)->setRowHeight(54);
                    $offsetXp = 6;
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
                        $drawing->setOffsetX($offsetXp + $idx * 160);
                        $drawing->setOffsetY(4);
                        $drawing->setWorksheet($sheet);
                    }
                }

                $inicioMeta = $this->hayFilaLogos ? 2 : 1;
                $finMeta = $inicioMeta + $this->filasMeta - 1;
                for ($fila = $inicioMeta; $fila <= $finMeta; $fila++) {
                    $sheet->mergeCells('A'.$fila.':'.$ultima.$fila);
                }
                $sheet->getStyle('A'.$inicioMeta)->getFont()->setName('Arial')->setSize(16)->setBold(true)->getColor()->setRGB('17202A');
                $sheet->getRowDimension($inicioMeta)->setRowHeight(28);
                if ($finMeta > $inicioMeta) {
                    $sheet->getStyle('A'.($inicioMeta + 1).':A'.$finMeta)
                        ->getFont()->setName('Arial')->setSize(10)->setBold(true)->getColor()->setRGB('444444');
                    $sheet->getStyle('A'.($inicioMeta + 1).':A'.$finMeta)->getAlignment()->setWrapText(true);
                }

                $cab = $this->filaCabecerasExcel;
                $sheet->getStyle('A'.$cab.':'.$ultima.$cab)->applyFromArray([
                    'font' => [
                        'name' => 'Arial',
                        'bold' => true,
                        'color' => ['rgb' => '17202A'],
                    ],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => '85C1E9'],
                    ],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_LEFT,
                        'vertical' => Alignment::VERTICAL_CENTER,
                    ],
                ]);

                $primera = $this->filaPrimeraDatosExcel;
                $ultimaFila = max($primera, $sheet->getHighestRow());
                foreach ($this->columnasVisibles as $i => $key) {
                    $letra = Coordinate::stringFromColumnIndex($i + 1);
                    if ($key === 'cantidadunidad') {
                        $sheet->getStyle($letra.$primera.':'.$letra.$ultimaFila)
                            ->getNumberFormat()->setFormatCode('#,##0.00');
                        $sheet->getStyle($letra.$primera.':'.$letra.$ultimaFila)
                            ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                        for ($fila = $primera; $fila <= $ultimaFila; $fila++) {
                            $crudo = $sheet->getCell($letra.$fila)->getValue();
                            if ($crudo === null || $crudo === '') {
                                continue;
                            }
                            $sheet->getCell($letra.$fila)->setValueExplicit((float) $crudo, DataType::TYPE_NUMERIC);
                        }
                    }
                    if ($key === 'items' || $key === 'detalle' || $key === 'articulo') {
                        $sheet->getStyle($letra.$primera.':'.$letra.$ultimaFila)
                            ->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_TOP);
                    }
                }

                $sheet->freezePane('A'.$this->filaPrimeraDatosExcel);
            },
        ];
    }
}

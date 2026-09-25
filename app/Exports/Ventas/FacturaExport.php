<?php

namespace App\Exports\Ventas;

use App\Services\Ventas\FacturacionService;
use App\Support\Configuracion\EmpresaLogoArchivo;
use App\Support\Export\ExcelFormatoNumero;
use App\Support\Ventas\FacturaListadoFiltros;
use App\Support\Ventas\VentasListadoEtiquetasSupport;
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
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class FacturaExport implements FromView, WithColumnFormatting, WithColumnWidths, WithEvents, WithStyles, WithTitle
{
    use Exportable;

    /** Congela también ID y Fecha (columnas A y B): el freeze arranca en C. */
    private const COL_FREEZE = 'C';

    /** @var array<string, mixed>|string|null */
    private $filtros;

    private bool $esCsv = false;

    private $facturacionService;

    private bool $hayFilaLogos = false;

    private int $filaTituloExcel = 1;

    private int $filaSubtituloExcel = 2;

    private int $filaCabecerasExcel = 3;

    private int $filaPrimeraDatosExcel = 4;

    /** @var list<string> */
    private array $rutasLogosExcel = [];

    public function __construct(FacturacionService $facturacionservice)
    {
        $this->facturacionService = $facturacionservice;
    }

    private function colUltima(): string
    {
        // detalle = 11 cols (A–K); solo cantidad = 9 cols (A–I)
        return VentasListadoEtiquetasSupport::muestraCajaUnidad() ? 'K' : 'I';
    }

    public function view(): View
    {
        $filtros = is_array($this->filtros) ? $this->filtros : [];
        $ventas = $this->facturacionService->leeSinPaginar($this->filtros);
        $totalesPorReparto = FacturaListadoFiltros::esOrdenReparto($filtros)
            ? $this->facturacionService->totalesIndexPorReparto($this->filtros)
            : collect();
        $totalesRango = $this->facturacionService->totalesIndexRango($this->filtros);

        foreach ($ventas as $row) {
            $row->nombreempresa = $row->nombreempresa ?? ($row->puntoventas->empresas->nombre ?? '');
        }

        $this->rutasLogosExcel = EmpresaLogoArchivo::rutasLogosCabeceraDesdeColeccion($ventas);
        $this->hayFilaLogos = count($this->rutasLogosExcel) > 0;
        $this->filaTituloExcel = $this->hayFilaLogos ? 2 : 1;
        $this->filaSubtituloExcel = $this->filaTituloExcel + 1;
        $this->filaCabecerasExcel = $this->filaSubtituloExcel + 1;
        $this->filaPrimeraDatosExcel = $this->filaCabecerasExcel + 1;

        return view('exports.ventas.factura', [
            'ventas' => $ventas,
            'totalesPorReparto' => $totalesPorReparto,
            'totalesRango' => $totalesRango,
            'filtros' => $filtros,
            'esExcel' => true,
            'reservarFilaLogoExcel' => $this->hayFilaLogos,
            'formatoNumero' => $this->formatoNumeroEfectivo(),
        ]);
    }

    public function columnFormats(): array
    {
        $num = ExcelFormatoNumero::codigoColumna(ExcelFormatoNumero::preferenciaGlobal(), 2);
        $detalle = VentasListadoEtiquetasSupport::muestraCajaUnidad();

        if ($detalle) {
            return [
                'A' => NumberFormat::FORMAT_TEXT,
                'F' => $num,
                'G' => $num,
                'H' => $num,
                'K' => $num,
            ];
        }

        return [
            'A' => NumberFormat::FORMAT_TEXT,
            'F' => $num,
            'I' => $num,
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [];
    }

    public function columnWidths(): array
    {
        $base = [
            'A' => 10,
            'B' => 12,
            'C' => 28,
            'D' => 28,
            'E' => 18,
            'F' => 12,
        ];

        if (VentasListadoEtiquetasSupport::muestraCajaUnidad()) {
            return $base + [
                'G' => 12,
                'H' => 12,
                'I' => 18,
                'J' => 10,
                'K' => 14,
            ];
        }

        return $base + [
            'G' => 18,
            'H' => 10,
            'I' => 14,
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $col = $this->colUltima();

                if ($this->hayFilaLogos && count($this->rutasLogosExcel) > 0) {
                    $sheet->getRowDimension(1)->setRowHeight(54);
                    $offsetX = 6;
                    foreach ($this->rutasLogosExcel as $ruta) {
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
                        $drawing->setOffsetX($offsetX);
                        $drawing->setOffsetY(4);
                        $drawing->setWorksheet($sheet);
                        $offsetX += 160;
                    }
                }

                $sheet->mergeCells('A'.$this->filaTituloExcel.':'.$col.$this->filaTituloExcel);
                $sheet->mergeCells('A'.$this->filaSubtituloExcel.':'.$col.$this->filaSubtituloExcel);
                $sheet->getStyle('A'.$this->filaTituloExcel)->getFont()->setName('Arial')->setSize(16)->setBold(true)->getColor()->setRGB('17202A');
                $sheet->getStyle('A'.$this->filaSubtituloExcel)->getFont()->setName('Arial')->setSize(10)->setBold(true)->getColor()->setRGB('444444');
                $sheet->getRowDimension($this->filaTituloExcel)->setRowHeight(28);

                $rangoCab = 'A'.$this->filaCabecerasExcel.':'.$col.$this->filaCabecerasExcel;
                $sheet->getStyle($rangoCab)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('85C1E9');
                $sheet->getStyle($rangoCab)->getFont()->setName('Arial')->setSize(11)->setBold(true)->getColor()->setRGB('17202A');
                $sheet->getStyle($rangoCab)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                $sheet->freezePane(self::COL_FREEZE.$this->filaPrimeraDatosExcel);
            },
        ];
    }

    public function title(): string
    {
        return 'Reporte de Comprobantes de Ventas';
    }

    public function parametros($filtros, bool $esCsv = false)
    {
        $this->filtros = $filtros;
        $this->esCsv = $esCsv;

        return $this;
    }

    private function formatoNumeroEfectivo(): string
    {
        $global = ExcelFormatoNumero::preferenciaGlobal();

        return $this->esCsv ? ExcelFormatoNumero::paraCsv($global) : $global;
    }
}

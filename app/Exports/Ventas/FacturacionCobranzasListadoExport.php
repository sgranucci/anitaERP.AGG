<?php

declare(strict_types=1);

namespace App\Exports\Ventas;

use App\Services\Ventas\FacturacionCobranzasReporteService;
use App\Support\Configuracion\EmpresaLogoArchivo;
use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class FacturacionCobranzasListadoExport implements FromView, WithColumnFormatting, WithColumnWidths, WithEvents, WithStyles, WithTitle
{
    use Exportable;

    /** @var array<string, mixed> */
    private array $filtros = [];

    /** @var array<string, float|int> */
    private array $totales = [];

    private int $totalLineas = 0;

    private bool $hayFilaLogos = false;

    private int $filasMetaEncabezado = 2;

    private int $filaInicioMeta = 1;

    private int $filaCabecerasExcel = 2;

    private int $filaPrimeraDatosExcel = 3;

    private int $filaSubtituloExcel = 0;

    /** @var list<string> */
    private array $rutasLogosExcel = [];

    /** @var list<int> */
    private array $filasCorteExcel = [];

    private string $colUltima = 'P';

    private string $titulo = 'Facturación y cobranzas';

    private string $subtitulo = '';

    public function __construct(
        private readonly FacturacionCobranzasReporteService $reporteService,
    ) {}

    /**
     * @param  array<string, mixed>  $filtros
     */
    public function parametros(array $filtros, string $titulo = 'Facturación y cobranzas', string $subtitulo = ''): self
    {
        $this->filtros = $filtros;
        $this->titulo = $titulo;
        $this->subtitulo = $subtitulo;

        return $this;
    }

    public function view(): View
    {
        $resultado = $this->reporteService->generar($this->filtros);
        $filas = $resultado['filas'];
        $this->totales = $resultado['totales'];
        $this->totalLineas = (int) ($this->totales['cantidad_detalle'] ?? 0);

        $this->rutasLogosExcel = EmpresaLogoArchivo::rutasLogosCabeceraDesdeColeccion(collect($filas));
        $this->hayFilaLogos = count($this->rutasLogosExcel) > 0;
        $this->filasMetaEncabezado = $this->contarFilasMetaEncabezado();
        $offsetLogo = $this->hayFilaLogos ? 1 : 0;
        $this->filaInicioMeta = $offsetLogo + 1;
        $this->filaCabecerasExcel = $offsetLogo + $this->filasMetaEncabezado + 1;
        $this->filaPrimeraDatosExcel = $this->filaCabecerasExcel + 1;
        // Meta fija: título, generado, subtítulo, movimientos (siempre 4 filas).
        $this->filaSubtituloExcel = $this->filaInicioMeta + 2;

        $this->filasCorteExcel = [];
        $filaExcel = $this->filaPrimeraDatosExcel;
        foreach ($filas as $fila) {
            $tipo = (string) ($fila['tipo_fila'] ?? '');
            if (in_array($tipo, ['subtotal_reparto', 'total_final'], true)) {
                $this->filasCorteExcel[] = $filaExcel;
            }
            $filaExcel++;
        }

        return view('exports.ventas.facturacioncobranzasindex', [
            'filas' => $filas,
            'filtros' => $this->filtros,
            'titulo' => $this->titulo,
            'subtitulo' => $this->subtitulo,
            'totales' => $this->totales,
            'total_lineas' => $this->totalLineas,
            'reservarFilaLogoExcel' => $this->hayFilaLogos,
            'para_excel' => true,
            'para_pdf' => true,
            'puede_ver_venta' => false,
            'puede_ver_cliente' => false,
            'puede_ver_cobranza' => false,
        ]);
    }

    public function columnFormats(): array
    {
        return [
            'A' => NumberFormat::FORMAT_TEXT,
            'B' => NumberFormat::FORMAT_TEXT,
            'C' => NumberFormat::FORMAT_TEXT,
            'D' => NumberFormat::FORMAT_TEXT,
            'E' => NumberFormat::FORMAT_TEXT,
            'F' => '#,##0.00',
            'G' => '#,##0.00',
            'H' => '#,##0.00',
            'I' => '#,##0.00',
            'J' => '#,##0.00',
            'K' => '#,##0.00',
            'L' => '#,##0.00',
            'M' => '#,##0.00',
            'N' => '#,##0.00',
            'O' => '#,##0.00',
            'P' => NumberFormat::FORMAT_TEXT,
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 6,   // Tip
            'B' => 18,  // Número (A-00001-00722126)
            'C' => 12,  // Fecha
            'D' => 10,  // Cliente
            'E' => 32,  // Nombre
            'F' => 11,  // Kilos
            'G' => 12,  // Abastos
            'H' => 14,  // Gravado
            'I' => 12,  // Varios
            'J' => 14,  // Total comp.
            'K' => 14,  // Efectivo
            'L' => 15,  // Cheques
            'M' => 15,  // Valores
            'N' => 14,  // Varios cobro
            'O' => 15,  // Total cobro
            'P' => 8,   // Fuente
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [];
    }

    public function title(): string
    {
        return 'Fact. y cobranzas';
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $ultima = $this->colUltima;

                if ($this->hayFilaLogos) {
                    $sheet->getRowDimension(1)->setRowHeight(54);
                    $offsetX = 6;
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
                        $drawing->setOffsetX($offsetX + $idx * 160);
                        $drawing->setOffsetY(4);
                        $drawing->setWorksheet($sheet);
                    }
                }

                $filaFinMeta = $this->filaInicioMeta + $this->filasMetaEncabezado - 1;
                for ($fila = $this->filaInicioMeta; $fila <= $filaFinMeta; $fila++) {
                    $sheet->mergeCells('A'.$fila.':'.$ultima.$fila);
                }

                $filaTit = $this->filaInicioMeta;
                $sheet->getRowDimension($filaTit)->setRowHeight(28);
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

                for ($fila = $filaTit + 1; $fila <= $filaFinMeta; $fila++) {
                    $altura = ($this->filaSubtituloExcel > 0 && $fila === $this->filaSubtituloExcel) ? 36 : 18;
                    $sheet->getRowDimension($fila)->setRowHeight($altura);
                    $sheet->getStyle('A'.$fila.':'.$ultima.$fila)->applyFromArray([
                        'font' => [
                            'bold' => true,
                            'size' => 10,
                            'name' => 'Arial',
                            'color' => ['rgb' => '444444'],
                        ],
                        'alignment' => [
                            'horizontal' => Alignment::HORIZONTAL_LEFT,
                            'vertical' => Alignment::VERTICAL_CENTER,
                            'wrapText' => true,
                        ],
                    ]);
                }

                $rangoCab = 'A'.$this->filaCabecerasExcel.':'.$ultima.$this->filaCabecerasExcel;
                $sheet->getRowDimension($this->filaCabecerasExcel)->setRowHeight(30);
                $sheet->getStyle($rangoCab)->applyFromArray([
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => '85C1E9'],
                    ],
                    'font' => [
                        'bold' => true,
                        'size' => 10,
                        'name' => 'Arial',
                        'color' => ['rgb' => '17202A'],
                    ],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical' => Alignment::VERTICAL_CENTER,
                        'wrapText' => true,
                    ],
                ]);
                $sheet->freezePane('A'.$this->filaPrimeraDatosExcel);

                $colsImporte = ['F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O'];
                $ultimaFila = max($this->filaPrimeraDatosExcel, $sheet->getHighestRow());
                for ($fila = $this->filaPrimeraDatosExcel; $fila <= $ultimaFila; $fila++) {
                    foreach ($colsImporte as $col) {
                        $cell = $sheet->getCell($col.$fila);
                        $raw = trim((string) $cell->getValue());
                        if ($raw === '' || ! is_numeric($raw)) {
                            continue;
                        }
                        $cell->setValueExplicit((float) $raw, DataType::TYPE_NUMERIC);
                    }
                }
                foreach ($colsImporte as $col) {
                    $sheet->getStyle($col.$this->filaCabecerasExcel.':'.$col.$ultimaFila)
                        ->getNumberFormat()->setFormatCode('#,##0.00');
                    $sheet->getStyle($col.$this->filaPrimeraDatosExcel.':'.$col.$ultimaFila)
                        ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                }

                foreach ($this->filasCorteExcel as $filaCorte) {
                    $sheet->getStyle('A'.$filaCorte.':'.$ultima.$filaCorte)->getFont()->setBold(true);
                    $sheet->getStyle('A'.$filaCorte.':'.$ultima.$filaCorte)->getFill()
                        ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('D6EAF8');
                }
            },
        ];
    }

    private function contarFilasMetaEncabezado(): int
    {
        // Debe coincidir con la vista Excel (una sola tabla): título + generado + subtítulo + movimientos.
        return 4;
    }
}

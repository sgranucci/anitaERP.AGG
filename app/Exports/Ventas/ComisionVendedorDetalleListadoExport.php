<?php

declare(strict_types=1);

namespace App\Exports\Ventas;

use App\Services\Ventas\ComisionVendedorReporteService;
use App\Support\Configuracion\EmpresaLogoArchivo;
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
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ComisionVendedorDetalleListadoExport implements FromView, ShouldAutoSize, WithColumnFormatting, WithColumnWidths, WithEvents, WithStyles, WithTitle
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

    private string $colUltima = 'I';

    private string $titulo = 'Comisiones de vendedores — detalle';

    private string $subtitulo = '';

    public function __construct(
        private readonly ComisionVendedorReporteService $reporteService,
    ) {
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    public function parametros(array $filtros, string $titulo = '', string $subtitulo = ''): self
    {
        $this->filtros = $filtros;
        if ($titulo !== '') {
            $this->titulo = $titulo;
        }
        $this->subtitulo = $subtitulo;

        return $this;
    }

    public function view(): View
    {
        $resultado = $this->reporteService->generarDetalle($this->filtros);
        $filas = $resultado['filas'];
        $this->totales = $resultado['totales'];
        $this->totalLineas = (int) ($this->totales['cantidad_comprobantes'] ?? 0);

        $this->rutasLogosExcel = EmpresaLogoArchivo::rutasLogosCabeceraDesdeColeccion(collect($filas));
        $this->hayFilaLogos = count($this->rutasLogosExcel) > 0;
        $this->filasMetaEncabezado = $this->contarFilasMetaEncabezado();
        $offsetLogo = $this->hayFilaLogos ? 1 : 0;
        $this->filaInicioMeta = $offsetLogo + 1;
        $this->filaCabecerasExcel = $offsetLogo + $this->filasMetaEncabezado + 1;
        $this->filaPrimeraDatosExcel = $this->filaCabecerasExcel + 1;
        $this->filaSubtituloExcel = trim($this->subtitulo) !== ''
            ? $this->filaInicioMeta + 2
            : 0;

        $this->filasCorteExcel = [];
        $filaExcel = $this->filaPrimeraDatosExcel;
        foreach ($filas as $fila) {
            $tipo = $fila['tipo_fila'] ?? 'detalle';
            if (in_array($tipo, ['header_vendedor', 'total_vendedor', 'total_final'], true)) {
                $this->filasCorteExcel[] = $filaExcel;
            }
            $filaExcel++;
        }

        return view('exports.ventas.comisionvendedordetalleindex', [
            'filas' => $filas,
            'filtros' => $this->filtros,
            'titulo' => $this->titulo,
            'subtitulo' => $this->subtitulo,
            'totales' => $this->totales,
            'total_lineas' => $this->totalLineas,
            'reservarFilaLogoExcel' => $this->hayFilaLogos,
            'para_pdf' => true,
            'puede_ver_venta' => false,
            'puede_ver_cliente' => false,
            'puede_ver_vendedor' => false,
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
            'I' => NumberFormat::FORMAT_TEXT,
        ];
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
                    'fillType' => Fill::FILL_SOLID,
                    'color' => ['rgb' => '85C1E9'],
                ],
            ],
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 12,
            'B' => 10,
            'C' => 32,
            'D' => 8,
            'E' => 22,
            'F' => 14,
            'G' => 10,
            'H' => 14,
            'I' => 16,
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $colUltima = $this->colUltima;

                if ($this->hayFilaLogos && count($this->rutasLogosExcel) > 0) {
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

                $filaFinMeta = $this->filaInicioMeta + $this->filasMetaEncabezado - 1;
                for ($fila = $this->filaInicioMeta; $fila <= $filaFinMeta; $fila++) {
                    $sheet->mergeCells('A'.$fila.':'.$colUltima.$fila);
                }

                $filaTit = $this->filaInicioMeta;
                $sheet->getRowDimension($filaTit)->setRowHeight(28);
                $sheet->getStyle('A'.$filaTit.':'.$colUltima.$filaTit)->applyFromArray([
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
                    $altura = ($this->filaSubtituloExcel > 0 && $fila === $this->filaSubtituloExcel) ? 42 : 20;
                    $sheet->getRowDimension($fila)->setRowHeight($altura);
                    $sheet->getStyle('A'.$fila.':'.$colUltima.$fila)->applyFromArray([
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

                $highest = $sheet->getHighestRow();
                for ($fila = $this->filaPrimeraDatosExcel; $fila <= $highest; $fila++) {
                    foreach (['F', 'G', 'H'] as $col) {
                        $raw = $sheet->getCell($col.$fila)->getValue();
                        if ($raw === null || $raw === '') {
                            continue;
                        }
                        if (! is_numeric($raw)) {
                            continue;
                        }
                        $sheet->getCell($col.$fila)->setValueExplicit((float) $raw, DataType::TYPE_NUMERIC);
                    }
                }

                $sheet->getStyle('F'.$this->filaPrimeraDatosExcel.':H'.$highest)
                    ->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                $sheet->getStyle('F'.$this->filaPrimeraDatosExcel.':H'.$highest)
                    ->getNumberFormat()
                    ->setFormatCode('#,##0.00');

                foreach ($this->filasCorteExcel as $filaCorte) {
                    $sheet->getStyle('A'.$filaCorte.':'.$colUltima.$filaCorte)->applyFromArray([
                        'font' => ['bold' => true],
                        'fill' => [
                            'fillType' => Fill::FILL_SOLID,
                            'color' => ['rgb' => 'D6EAF8'],
                        ],
                    ]);
                }

                $sheet->freezePane('A'.$this->filaPrimeraDatosExcel);
            },
        ];
    }

    public function title(): string
    {
        return 'Comisiones detalle';
    }

    private function contarFilasMetaEncabezado(): int
    {
        $filas = 2;
        if (trim($this->subtitulo) !== '') {
            $filas++;
        }
        if ($this->totales !== []) {
            $filas++;
        }
        if ($this->totalLineas > 0) {
            $filas++;
        }

        return $filas;
    }
}

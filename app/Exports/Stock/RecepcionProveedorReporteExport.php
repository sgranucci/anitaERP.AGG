<?php

namespace App\Exports\Stock;

use App\Support\Configuracion\EmpresaLogoArchivo;
use App\Support\Stock\RecepcionProveedorReporteFiltros;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
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

class RecepcionProveedorReporteExport implements FromView, WithColumnFormatting, WithColumnWidths, WithEvents, WithStyles, WithTitle
{
    use Exportable;

    private bool $hayFilaLogos = false;

    private int $filasMetaEncabezado = 2;

    private int $filaInicioMeta = 1;

    private int $filaCabecerasExcel = 2;

    private int $filaPrimeraDatosExcel = 3;

    private string $colUltima = 'AQ';

    /** @var list<string> */
    private array $rutasLogosExcel = [];

    /**
     * @param  Collection<int, array<string, mixed>>|iterable  $filas
     * @param  array<string, mixed>  $totales
     * @param  array<string, mixed>  $kpis
     */
    public function __construct(
        private iterable $filas,
        private string $titulo,
        private string $subtitulo = '',
        private array $totales = [],
        private array $kpis = [],
        private string $modo = RecepcionProveedorReporteFiltros::MODO_DETALLE,
        private ?string $advertencia = null,
    ) {
        $this->colUltima = $this->modo === RecepcionProveedorReporteFiltros::MODO_RESUMEN ? 'R' : 'AQ';
    }

    public function view(): View
    {
        $coleccion = collect($this->filas);
        $this->rutasLogosExcel = EmpresaLogoArchivo::rutasLogosCabeceraDesdeColeccion(
            $coleccion->map(fn ($f) => (object) ['nombreempresa' => is_array($f) ? ($f['nombreempresa'] ?? '') : ''])
        );
        $this->hayFilaLogos = count($this->rutasLogosExcel) > 0;
        $this->filasMetaEncabezado = $this->contarFilasMetaEncabezado();
        $offsetLogo = $this->hayFilaLogos ? 1 : 0;
        $this->filaInicioMeta = $offsetLogo + 1;
        $this->filaCabecerasExcel = $offsetLogo + $this->filasMetaEncabezado + 1;
        $this->filaPrimeraDatosExcel = $this->filaCabecerasExcel + 1;

        return view('exports.stock.recepcion_proveedor_reporteindex', [
            'filas' => $coleccion,
            'titulo' => $this->titulo,
            'subtitulo' => $this->subtitulo,
            'totales' => $this->totales,
            'kpis' => $this->kpis,
            'modo' => $this->modo,
            'advertencia_cotizacion' => $this->advertencia,
            'total_lineas' => $coleccion->filter(fn ($f) => (is_array($f) ? ($f['tipo_fila'] ?? 'dato') : 'dato') === 'dato')->count(),
            'reservarFilaLogoExcel' => $this->hayFilaLogos,
            'colspan_excel' => $this->modo === RecepcionProveedorReporteFiltros::MODO_RESUMEN ? 18 : 43,
            'para_pdf' => true,
            'para_excel' => true,
            'columnas_completas' => true,
            'puede_ver_recepcion' => false,
            'puede_ver_articulo' => false,
            'puede_ver_ordencompra' => false,
            'puede_ver_requisicion' => false,
            'puede_ver_proveedor' => false,
            'puede_ver_cuentacontable' => false,
            'puede_ver_comprobante' => false,
        ]);
    }

    public function columnFormats(): array
    {
        if ($this->modo === RecepcionProveedorReporteFiltros::MODO_RESUMEN) {
            return [
                'A' => NumberFormat::FORMAT_TEXT,
                'C' => NumberFormat::FORMAT_TEXT,
                'D' => NumberFormat::FORMAT_TEXT,
                'E' => NumberFormat::FORMAT_TEXT,
            ];
        }

        return [
            'A' => NumberFormat::FORMAT_TEXT,
            'C' => NumberFormat::FORMAT_TEXT,
            'D' => NumberFormat::FORMAT_TEXT,
            'H' => NumberFormat::FORMAT_TEXT,
            'I' => NumberFormat::FORMAT_TEXT,
            'J' => NumberFormat::FORMAT_TEXT,
            'S' => NumberFormat::FORMAT_TEXT,
            'Y' => NumberFormat::FORMAT_TEXT,
            'AB' => NumberFormat::FORMAT_TEXT,
            'AC' => NumberFormat::FORMAT_TEXT,
            'AE' => NumberFormat::FORMAT_TEXT,
            'AM' => NumberFormat::FORMAT_TEXT,
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
        if ($this->modo === RecepcionProveedorReporteFiltros::MODO_RESUMEN) {
            return [
                'A' => 12, 'B' => 10, 'C' => 16, 'D' => 12, 'E' => 12, 'F' => 28,
                'G' => 22, 'H' => 14, 'I' => 14, 'J' => 16, 'K' => 18, 'L' => 10,
                'M' => 14, 'N' => 22, 'O' => 14, 'P' => 16, 'Q' => 18, 'R' => 18,
            ];
        }

        return [
            'A' => 14, 'B' => 28, 'C' => 10, 'D' => 10, 'E' => 16, 'F' => 14,
            'G' => 12, 'H' => 12, 'I' => 16, 'J' => 10, 'K' => 24, 'L' => 12,
            'M' => 12, 'N' => 10, 'O' => 8, 'P' => 12, 'Q' => 12, 'R' => 12,
            'S' => 10, 'T' => 12, 'U' => 10, 'V' => 12, 'W' => 10, 'X' => 10,
            'Y' => 14, 'Z' => 10, 'AA' => 10, 'AB' => 14, 'AC' => 16, 'AD' => 10,
            'AE' => 10, 'AF' => 12, 'AG' => 10, 'AH' => 24, 'AI' => 14, 'AJ' => 16,
            'AK' => 8, 'AL' => 14, 'AM' => 10, 'AN' => 16, 'AO' => 12,
            'AP' => 18, 'AQ' => 18,
        ];
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

                for ($i = 0; $i < $this->filasMetaEncabezado; $i++) {
                    $fila = $this->filaInicioMeta + $i;
                    $sheet->mergeCells('A'.$fila.':'.$this->colUltima.$fila);
                }

                $sheet->getStyle('A'.$this->filaInicioMeta)->applyFromArray([
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
                $sheet->getRowDimension($this->filaInicioMeta)->setRowHeight(28);

                $sheet->getStyle(
                    'A'.$this->filaCabecerasExcel.':'.$this->colUltima.$this->filaCabecerasExcel
                )->applyFromArray([
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
                ]);

                $sheet->getStyle('AI'.$this->filaPrimeraDatosExcel.':AI'.$sheet->getHighestRow())
                    ->getAlignment()
                    ->setWrapText(true);

                $sheet->freezePane('A'.$this->filaPrimeraDatosExcel);
            },
        ];
    }

    public function title(): string
    {
        return 'Recepción proveedores';
    }

    private function contarFilasMetaEncabezado(): int
    {
        $filas = 2;
        if (trim($this->subtitulo) !== '') {
            $filas++;
        }
        if ($this->totales !== [] || $this->kpis !== []) {
            $filas++;
        }
        if (trim((string) $this->advertencia) !== '') {
            $filas++;
        }
        $filas++;

        return $filas;
    }
}

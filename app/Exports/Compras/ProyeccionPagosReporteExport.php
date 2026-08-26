<?php

namespace App\Exports\Compras;

use App\Support\Compras\ProyeccionPagosColumnasSupport;
use App\Support\Configuracion\EmpresaLogoArchivo;
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
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ProyeccionPagosReporteExport implements FromView, WithColumnFormatting, WithColumnWidths, WithEvents, WithStyles, WithTitle
{
    use Exportable;

    private const FORMAT_IMPORTE = NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED2;

    /** @var list<string> Claves de columna que conviene forzar a texto (códigos y números de comprobante). */
    private const CLAVES_TEXTO = [
        'proveedor_codigo', 'comprobante', 'cuota', 'nro_referencia', 'requisicion',
        'usuario_requisicion', 'autorizante_requisicion', 'concepto', 'moneda', 'tipo',
    ];

    private bool $hayFilaLogos = false;

    private int $filasMetaEncabezado = 2;

    private int $filaInicioMeta = 1;

    private int $filaCabecerasExcel = 2;

    private int $filaPrimeraDatosExcel = 3;

    private int $filaSubtituloExcel = 0;

    /** @var list<string> */
    private array $rutasLogosExcel = [];

    /**
     * @param  list<array<string, mixed>>  $filas
     * @param  list<array<string, mixed>>  $columnas
     * @param  array<string, mixed>  $totales
     */
    public function __construct(
        private array $filas,
        private array $columnas,
        private string $titulo,
        private string $subtitulo = '',
        private array $totales = [],
    ) {}

    public function view(): View
    {
        $this->rutasLogosExcel = EmpresaLogoArchivo::rutasLogosCabeceraDesdeColeccion($this->coleccionParaLogos());
        $this->hayFilaLogos = count($this->rutasLogosExcel) > 0;
        $this->filasMetaEncabezado = $this->contarFilasMetaEncabezado();
        $offsetLogo = $this->hayFilaLogos ? 1 : 0;
        $this->filaInicioMeta = $offsetLogo + 1;
        $this->filaCabecerasExcel = $offsetLogo + $this->filasMetaEncabezado + 1;
        $this->filaPrimeraDatosExcel = $this->filaCabecerasExcel + 1;
        $this->filaSubtituloExcel = trim($this->subtitulo) !== '' ? $this->filaInicioMeta + 2 : 0;

        return view('exports.compras.proyeccion_pagosindex', [
            'filas' => $this->filas,
            'columnas' => $this->columnas,
            'titulo' => $this->titulo,
            'subtitulo' => $this->subtitulo,
            'totales' => $this->totales,
            'total_lineas' => $this->contarLineasDetalle(),
            'reservarFilaLogoExcel' => $this->hayFilaLogos,
            'para_excel' => true,
        ]);
    }

    public function columnFormats(): array
    {
        $formatos = [];

        foreach ($this->columnas as $indice => $columna) {
            $letra = Coordinate::stringFromColumnIndex($indice + 1);
            $clave = (string) $columna['clave'];

            $formatos[$letra] = match (true) {
                in_array($clave, self::CLAVES_TEXTO, true) => NumberFormat::FORMAT_TEXT,
                $columna['tipo'] === ProyeccionPagosColumnasSupport::TIPO_IMPORTE => self::FORMAT_IMPORTE,
                $columna['tipo'] === ProyeccionPagosColumnasSupport::TIPO_ENTERO => NumberFormat::FORMAT_NUMBER,
                $columna['tipo'] === ProyeccionPagosColumnasSupport::TIPO_RATIO => '#,##0.0000',
                default => NumberFormat::FORMAT_TEXT,
            };
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
        $anchos = [];

        foreach ($this->columnas as $indice => $columna) {
            $anchos[Coordinate::stringFromColumnIndex($indice + 1)] = (int) ($columna['ancho_excel'] ?? 14);
        }

        return $anchos;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $colUltima = $this->columnaUltima();

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

                $filaFinMeta = $this->filaInicioMeta + $this->filasMetaEncabezado - 1;
                for ($fila = $this->filaInicioMeta; $fila <= $filaFinMeta; $fila++) {
                    $sheet->mergeCells('A'.$fila.':'.$colUltima.$fila);
                }

                $filaTit = $this->filaInicioMeta;
                $sheet->getRowDimension($filaTit)->setRowHeight(28);
                $sheet->getStyle('A'.$filaTit.':'.$colUltima.$filaTit)->applyFromArray([
                    'font' => ['bold' => true, 'size' => 16, 'name' => 'Arial', 'color' => ['rgb' => '17202A']],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_LEFT,
                        'vertical' => Alignment::VERTICAL_CENTER,
                    ],
                ]);

                for ($fila = $filaTit + 1; $fila <= $filaFinMeta; $fila++) {
                    $altura = ($this->filaSubtituloExcel > 0 && $fila === $this->filaSubtituloExcel) ? 42 : 20;
                    $sheet->getRowDimension($fila)->setRowHeight($altura);
                    $sheet->getStyle('A'.$fila.':'.$colUltima.$fila)->applyFromArray([
                        'font' => ['bold' => true, 'size' => 10, 'name' => 'Arial', 'color' => ['rgb' => '444444']],
                        'alignment' => [
                            'horizontal' => Alignment::HORIZONTAL_LEFT,
                            'vertical' => Alignment::VERTICAL_CENTER,
                            'wrapText' => true,
                        ],
                    ]);
                }

                $filaCab = $this->filaCabecerasExcel;
                $sheet->getStyle('A'.$filaCab.':'.$colUltima.$filaCab)->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['rgb' => '17202A'], 'size' => 11, 'name' => 'Arial'],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => '85C1E9']],
                    'alignment' => [
                        'vertical' => Alignment::VERTICAL_CENTER,
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'wrapText' => true,
                    ],
                ]);

                $filaMax = $sheet->getHighestRow();

                foreach ($this->columnas as $indice => $columna) {
                    $letra = Coordinate::stringFromColumnIndex($indice + 1);
                    $rango = $letra.$this->filaPrimeraDatosExcel.':'.$letra.$filaMax;

                    if (in_array($columna['tipo'], [
                        ProyeccionPagosColumnasSupport::TIPO_IMPORTE,
                        ProyeccionPagosColumnasSupport::TIPO_ENTERO,
                        ProyeccionPagosColumnasSupport::TIPO_RATIO,
                    ], true)) {
                        $sheet->getStyle($rango)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

                        continue;
                    }

                    if ((int) ($columna['ancho_excel'] ?? 0) >= 24) {
                        $sheet->getStyle($rango)->getAlignment()->setWrapText(true);
                    }
                }

                $sheet->freezePane('A'.$this->filaPrimeraDatosExcel);
            },
        ];
    }

    public function title(): string
    {
        return 'Proyección de pagos';
    }

    private function columnaUltima(): string
    {
        return Coordinate::stringFromColumnIndex(max(1, count($this->columnas)));
    }

    private function contarLineasDetalle(): int
    {
        return collect($this->filas)
            ->filter(fn (array $f) => ($f['tipo_fila'] ?? 'detalle') === 'detalle')
            ->count();
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

        return $filas;
    }

    /** @return Collection<int, object> */
    private function coleccionParaLogos(): Collection
    {
        return collect($this->filas)
            ->filter(fn (array $f) => ($f['tipo_fila'] ?? '') === 'detalle')
            ->map(fn (array $f) => (object) ['nombreempresa' => (string) ($f['nombreempresa'] ?? '')]);
    }
}

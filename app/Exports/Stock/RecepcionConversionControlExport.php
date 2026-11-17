<?php

namespace App\Exports\Stock;

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

class RecepcionConversionControlExport implements FromView, WithColumnFormatting, WithColumnWidths, WithEvents, WithStyles, WithTitle
{
    use Exportable;

    private const COL_ULTIMA = 'R';

    private bool $hayFilaLogos = false;

    private int $filasMetaEncabezado = 3;

    private int $filaInicioMeta = 1;

    private int $filaCabecerasExcel = 4;

    private int $filaPrimeraDatosExcel = 5;

    /** @var list<string> */
    private array $rutasLogosExcel = [];

    /** @var array<int, string> */
    private array $filasCorteExcel = [];

    /**
     * @param  list<array<string, mixed>>|iterable  $filas
     * @param  array<string, mixed>  $totales
     */
    public function __construct(
        private iterable $filas,
        private string $titulo,
        private string $subtitulo = '',
        private array $totales = [],
    ) {}

    public function view(): View
    {
        $coleccion = collect($this->filas);
        $this->rutasLogosExcel = EmpresaLogoArchivo::rutasLogosCabeceraDesdeColeccion(
            $coleccion->map(fn ($f) => (object) ['nombreempresa' => is_array($f) ? ($f['nombreempresa'] ?? '') : ''])
        );
        $this->hayFilaLogos = count($this->rutasLogosExcel) > 0;

        $filasMeta = 2; // título + generado
        if (trim($this->subtitulo) !== '') {
            $filasMeta++;
        }
        $filasMeta++; // contador
        $this->filasMetaEncabezado = $filasMeta;

        $offsetLogo = $this->hayFilaLogos ? 1 : 0;
        $this->filaInicioMeta = $offsetLogo + 1;
        $this->filaCabecerasExcel = $offsetLogo + $filasMeta + 1;
        $this->filaPrimeraDatosExcel = $this->filaCabecerasExcel + 1;

        $this->filasCorteExcel = [];
        $n = $this->filaPrimeraDatosExcel;
        foreach ($coleccion as $fila) {
            if ((is_array($fila) ? ($fila['tipo_fila'] ?? 'dato') : 'dato') === 'header_empresa') {
                $this->filasCorteExcel[$n] = 'header_empresa';
            }
            $n++;
        }

        return view('exports.stock.recepcion_conversion_controlindex', [
            'filas' => $coleccion,
            'titulo' => $this->titulo,
            'subtitulo' => $this->subtitulo,
            'totales' => $this->totales,
            'reservarFilaLogoExcel' => $this->hayFilaLogos,
            'excel' => true,
            'puede_ver_recepcion' => false,
            'puede_ver_articulo' => false,
            'puede_ver_proveedor' => false,
        ]);
    }

    public function columnFormats(): array
    {
        return [
            'E' => NumberFormat::FORMAT_TEXT,
            'F' => NumberFormat::FORMAT_TEXT,
            'H' => '#,##0.0000',
            'J' => '#,##0.0000',
            'K' => '#,##0.00',
            'M' => NumberFormat::FORMAT_TEXT,
            'O' => '#,##0.0000',
            'Q' => '#,##0.0000',
            'R' => '#,##0.00',
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 12,  // Fecha
            'B' => 18,  // Empresa
            'C' => 28,  // Proveedor
            'D' => 12,  // Tipo
            'E' => 12,  // Nro
            'F' => 16,  // SKU compra
            'G' => 36,  // Descripción
            'H' => 12,  // Cant.
            'I' => 11,  // UM compra
            'J' => 11,  // Coef.
            'K' => 12,  // Precio
            'L' => 11,  // UM stock
            'M' => 14,  // SKU insumo
            'N' => 34,  // Insumo
            'O' => 13,  // Cant.insumo
            'P' => 11,  // UM insumo
            'Q' => 12,  // Coef.ins.
            'R' => 13,  // Costo conv.
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                /** @var Worksheet $sheet */
                $sheet = $event->sheet->getDelegate();
                $ultima = self::COL_ULTIMA;
                $cab = $this->filaCabecerasExcel;
                $datos = $this->filaPrimeraDatosExcel;

                if ($this->hayFilaLogos) {
                    $sheet->getRowDimension(1)->setRowHeight(54);
                    $offsetX = 5;
                    foreach ($this->rutasLogosExcel as $ruta) {
                        if (! is_string($ruta) || $ruta === '' || ! is_file($ruta)) {
                            continue;
                        }
                        $drawing = new Drawing;
                        $drawing->setPath($ruta);
                        $drawing->setHeight(42);
                        $drawing->setCoordinates('A1');
                        $drawing->setOffsetX($offsetX);
                        $drawing->setWorksheet($sheet);
                        $offsetX += 90;
                    }
                }

                for ($i = 0; $i < $this->filasMetaEncabezado; $i++) {
                    $fila = $this->filaInicioMeta + $i;
                    $sheet->mergeCells('A'.$fila.':'.$ultima.$fila);
                }
                $sheet->getStyle('A'.$this->filaInicioMeta)->getFont()->setBold(true)->setSize(16)->setName('Arial')->getColor()->setRGB('17202A');
                $sheet->getRowDimension($this->filaInicioMeta)->setRowHeight(28);
                if ($this->filasMetaEncabezado > 1) {
                    $sheet->getStyle('A'.($this->filaInicioMeta + 1).':A'.($this->filaInicioMeta + $this->filasMetaEncabezado - 1))
                        ->getFont()->setBold(true)->setSize(10)->setName('Arial')->getColor()->setRGB('444444');
                    $sheet->getStyle('A'.($this->filaInicioMeta + 1).':A'.($this->filaInicioMeta + $this->filasMetaEncabezado - 1))
                        ->getAlignment()->setWrapText(true);
                }

                $sheet->getStyle('A'.$cab.':'.$ultima.$cab)->applyFromArray([
                    'font' => ['bold' => true, 'name' => 'Arial', 'size' => 10, 'color' => ['rgb' => '17202A']],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '85C1E9']],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical' => Alignment::VERTICAL_CENTER,
                        'wrapText' => true,
                    ],
                ]);
                $sheet->getRowDimension($cab)->setRowHeight(32);
                $sheet->freezePane('A'.$datos);

                $max = $sheet->getHighestRow();
                if ($max >= $datos) {
                    // SKU / nro como texto (evita 7.79E+12)
                    foreach (['E', 'F', 'M'] as $colTexto) {
                        $sheet->getStyle($colTexto.$datos.':'.$colTexto.$max)
                            ->getNumberFormat()
                            ->setFormatCode(NumberFormat::FORMAT_TEXT);
                        for ($r = $datos; $r <= $max; $r++) {
                            if (isset($this->filasCorteExcel[$r])) {
                                continue;
                            }
                            $valor = $sheet->getCell($colTexto.$r)->getValue();
                            if ($valor === null || $valor === '') {
                                continue;
                            }
                            $sheet->getCell($colTexto.$r)->setValueExplicit((string) $valor, DataType::TYPE_TEXT);
                        }
                    }

                    $numericos = [
                        'H' => '#,##0.0000',
                        'J' => '#,##0.0000',
                        'K' => '#,##0.00',
                        'O' => '#,##0.0000',
                        'Q' => '#,##0.0000',
                        'R' => '#,##0.00',
                    ];
                    foreach ($numericos as $col => $formato) {
                        $sheet->getStyle($col.$datos.':'.$col.$max)
                            ->getNumberFormat()
                            ->setFormatCode($formato);
                        $sheet->getStyle($col.$datos.':'.$col.$max)
                            ->getAlignment()
                            ->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                        for ($r = $datos; $r <= $max; $r++) {
                            if (isset($this->filasCorteExcel[$r])) {
                                continue;
                            }
                            $valor = $sheet->getCell($col.$r)->getValue();
                            if ($valor === null || $valor === '' || ! is_numeric($valor)) {
                                continue;
                            }
                            $sheet->getCell($col.$r)->setValueExplicit((float) $valor, DataType::TYPE_NUMERIC);
                        }
                    }

                    $sheet->getStyle('G'.$datos.':G'.$max)->getAlignment()->setWrapText(true);
                    $sheet->getStyle('N'.$datos.':N'.$max)->getAlignment()->setWrapText(true);
                }

                foreach ($this->filasCorteExcel as $fila => $_tipo) {
                    $sheet->getStyle('A'.$fila.':'.$ultima.$fila)->applyFromArray([
                        'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1B4F72']],
                    ]);
                }
            },
        ];
    }

    public function title(): string
    {
        return 'Control conversión';
    }
}

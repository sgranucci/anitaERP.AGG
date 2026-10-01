<?php

namespace App\Exports\Stock;

use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class PrecioListaFerliExport implements FromView, WithEvents, WithTitle
{
    use Exportable;

    private int $filaCabecerasExcel = 4;

    private int $filaPrimeraDatosExcel = 5;

    private int $filaTituloExcel = 1;

    private bool $hayFilaLogos = false;

    private string $colUltima = 'B';

    /**
     * @param  list<object>  $filas
     * @param  list<array{id: int, codigo: string, nombre: string, encabezado: string}>  $listas
     * @param  list<string>  $rutasLogos
     */
    public function __construct(
        private array $filas,
        private array $listas,
        private string $titulo,
        private string $subtitulo = '',
        private array $rutasLogos = [],
    ) {}

    public function view(): \Illuminate\Contracts\View\View
    {
        $this->hayFilaLogos = $this->rutasLogos !== [];
        $filasMeta = ($this->hayFilaLogos ? 1 : 0) + 2;
        if (trim($this->subtitulo) !== '') {
            $filasMeta++;
        }
        $filasMeta++;
        $this->filaTituloExcel = $this->hayFilaLogos ? 2 : 1;
        $this->filaCabecerasExcel = $filasMeta + 1;
        $this->filaPrimeraDatosExcel = $this->filaCabecerasExcel + 1;
        $this->colUltima = self::columna(2 + count($this->listas));

        return view('exports.stock.precio_lista_ferliindex', [
            'filas' => $this->filas,
            'listas' => $this->listas,
            'titulo' => $this->titulo,
            'subtitulo' => $this->subtitulo,
            'hayFilaLogos' => $this->hayFilaLogos,
            'colspan' => 2 + count($this->listas),
            'totalFilas' => count($this->filas),
        ]);
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                /** @var Worksheet $sheet */
                $sheet = $event->sheet->getDelegate();
                $ultima = $this->colUltima;
                $cab = $this->filaCabecerasExcel;
                $datos = $this->filaPrimeraDatosExcel;

                if ($this->hayFilaLogos) {
                    $sheet->getRowDimension(1)->setRowHeight(54);
                    $offsetX = 6;
                    foreach ($this->rutasLogos as $idx => $ruta) {
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

                for ($fila = 1; $fila < $cab; $fila++) {
                    $sheet->mergeCells('A'.$fila.':'.$ultima.$fila);
                }

                $sheet->getRowDimension($this->filaTituloExcel)->setRowHeight(28);
                $sheet->getStyle('A'.$this->filaTituloExcel)->getFont()->setBold(true)->setSize(16)->setName('Arial')->getColor()->setRGB('17202A');
                $desdeMeta = $this->filaTituloExcel + 1;
                if ($desdeMeta < $cab) {
                    $sheet->getStyle('A'.$desdeMeta.':A'.($cab - 1))->getFont()->setBold(true)->setSize(10)->setName('Arial')->getColor()->setRGB('444444');
                    $sheet->getStyle('A'.$desdeMeta.':A'.($cab - 1))->getAlignment()->setWrapText(true);
                }

                $sheet->getStyle('A'.$cab.':'.$ultima.$cab)->applyFromArray([
                    'font' => ['bold' => true, 'name' => 'Arial', 'size' => 11, 'color' => ['rgb' => '17202A']],
                    'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '85C1E9']],
                ]);
                $sheet->freezePane('A'.$datos);
                $sheet->getColumnDimension('A')->setWidth(16);
                $sheet->getColumnDimension('B')->setWidth(36);
                $sheet->getStyle('A:A')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);
                $sheet->getStyle('B:B')->getAlignment()->setWrapText(true);

                $max = $sheet->getHighestRow();
                for ($i = 0; $i < count($this->listas); $i++) {
                    $col = self::columna(3 + $i);
                    $sheet->getColumnDimension($col)->setWidth(18);
                    if ($max < $datos) {
                        continue;
                    }
                    $sheet->getStyle($col.$datos.':'.$col.$max)
                        ->getNumberFormat()
                        ->setFormatCode('#,##0.00');
                    $sheet->getStyle($col.$datos.':'.$col.$max)
                        ->getAlignment()
                        ->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                    for ($r = $datos; $r <= $max; $r++) {
                        $valor = $sheet->getCell($col.$r)->getValue();
                        if ($valor === null || $valor === '' || ! is_numeric($valor)) {
                            continue;
                        }
                        $sheet->getCell($col.$r)->setValueExplicit((float) $valor, DataType::TYPE_NUMERIC);
                    }
                }

                if ($max >= $datos) {
                    for ($r = $datos; $r <= $max; $r++) {
                        $sku = $sheet->getCell('A'.$r)->getValue();
                        if ($sku === null || $sku === '') {
                            continue;
                        }
                        $sheet->getCell('A'.$r)->setValueExplicit((string) $sku, DataType::TYPE_STRING);
                    }
                }
            },
        ];
    }

    public function title(): string
    {
        return 'Lista de precios';
    }

    private static function columna(int $numero): string
    {
        $texto = '';
        while ($numero > 0) {
            $numero--;
            $texto = chr(65 + ($numero % 26)).$texto;
            $numero = intdiv($numero, 26);
        }

        return $texto;
    }
}

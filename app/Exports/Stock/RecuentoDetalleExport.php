<?php

namespace App\Exports\Stock;

use App\Models\Stock\Recuento;
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
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class RecuentoDetalleExport implements FromView, ShouldAutoSize, WithColumnFormatting, WithColumnWidths, WithEvents, WithStyles, WithTitle
{
    use Exportable;

    private const COL_ULTIMA = 'I';

    /** Filas meta en la vista: título, datos generales, comentario/costos. */
    private const FILAS_META_ENCABEZADO = 3;

    private ?Recuento $recuento = null;

    private bool $hayFilaLogos = false;

    private int $filaInicioMeta = 1;

    private int $filaCabecerasExcel = 4;

    private int $filaPrimeraDatosExcel = 5;

    /** @var list<string> */
    private array $rutasLogosExcel = [];

    public function parametros(Recuento $recuento): self
    {
        $this->recuento = $recuento;

        $paraLogos = collect([$recuento])->map(function (Recuento $r) {
            $r->setAttribute('nombreempresa', optional($r->empresa)->nombre);

            return $r;
        });

        $this->rutasLogosExcel = EmpresaLogoArchivo::rutasLogosCabeceraDesdeColeccion($paraLogos);
        $this->hayFilaLogos = count($this->rutasLogosExcel) > 0;

        $offsetLogo = $this->hayFilaLogos ? 1 : 0;
        $this->filaInicioMeta = $offsetLogo + 1;
        $this->filaCabecerasExcel = $offsetLogo + self::FILAS_META_ENCABEZADO + 1;
        $this->filaPrimeraDatosExcel = $this->filaCabecerasExcel + 1;

        return $this;
    }

    public function view(): View
    {
        return view('exports.stock.recuento_detalle', [
            'recuento' => $this->recuento,
            'reservarFilaLogoExcel' => $this->hayFilaLogos,
        ]);
    }

    public function columnFormats(): array
    {
        return [
            'A' => NumberFormat::FORMAT_TEXT,
            'B' => NumberFormat::FORMAT_TEXT,
            'C' => NumberFormat::FORMAT_TEXT,
            'D' => '#,##0.######',
            'E' => '#,##0.######',
            'F' => '#,##0.######',
            'G' => '#,##0.0000',
            'H' => '#,##0.00',
            'I' => '#,##0.00',
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 14,
            'B' => 36,
            'C' => 8,
            'D' => 12,
            'E' => 12,
            'F' => 14,
            'G' => 12,
            'H' => 14,
            'I' => 14,
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
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'color' => ['rgb' => '85C1E9'],
                ],
            ],
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $colUltima = self::COL_ULTIMA;

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

                $filaFinMeta = $this->filaInicioMeta + self::FILAS_META_ENCABEZADO - 1;
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
                    $sheet->getRowDimension($fila)->setRowHeight(20);
                    $sheet->getStyle('A'.$fila.':'.$colUltima.$fila)->applyFromArray([
                        'font' => [
                            'size' => 10,
                            'name' => 'Arial',
                            'color' => ['rgb' => '444444'],
                        ],
                        'alignment' => [
                            'wrapText' => true,
                            'vertical' => Alignment::VERTICAL_TOP,
                        ],
                    ]);
                }

                $filaCab = $this->filaCabecerasExcel;
                $sheet->getStyle('A'.$filaCab.':'.$colUltima.$filaCab)->applyFromArray([
                    'font' => [
                        'bold' => true,
                        'color' => ['rgb' => '17202A'],
                        'size' => 11,
                        'name' => 'Arial',
                    ],
                    'fill' => [
                        'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                        'color' => ['rgb' => '85C1E9'],
                    ],
                ]);

                $sheet->freezePane('A'.$this->filaPrimeraDatosExcel);

                $ultimaFila = $sheet->getHighestRow();
                if ($ultimaFila >= $this->filaCabecerasExcel) {
                    $sheet->getStyle('D'.$this->filaCabecerasExcel.':'.$colUltima.$ultimaFila)
                        ->getAlignment()
                        ->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                }

                if ($ultimaFila >= $this->filaPrimeraDatosExcel) {
                    $sheet->getStyle('B'.$this->filaPrimeraDatosExcel.':B'.$ultimaFila)
                        ->getAlignment()
                        ->setWrapText(true);
                }
            },
        ];
    }

    public function title(): string
    {
        $codigo = preg_replace('/[^\w\-]+/', '_', (string) ($this->recuento->codigo ?? 'Recuento'));

        return substr($codigo, 0, 31);
    }
}

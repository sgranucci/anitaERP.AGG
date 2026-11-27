<?php

namespace App\Exports\Contable;

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
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ConciliacionBancariaSinConciliarExport implements FromView, WithColumnFormatting, WithColumnWidths, WithEvents, WithStyles, WithTitle
{
    use Exportable;

    private const COL_ULTIMA = 'L';

    /** @var array<string, mixed> */
    private array $reporte = [];

    /** @var array<string, mixed> */
    private array $meta = [];

    private bool $hayFilaLogos = false;

    private int $filaInicioMeta = 1;

    private int $filaCabecerasExcel = 5;

    private int $filaPrimeraDatosExcel = 6;

    /** @var list<string> */
    private array $rutasLogosExcel = [];

    /**
     * @param  array<string, mixed>  $reporte
     * @param  array<string, mixed>  $meta
     */
    public function parametros(array $reporte, array $meta): self
    {
        $this->reporte = $reporte;
        $this->meta = $meta;

        $paraLogos = collect([(object) ['nombreempresa' => (string) ($meta['empresa_nombre'] ?? '')]]);
        $this->rutasLogosExcel = EmpresaLogoArchivo::rutasLogosCabeceraDesdeColeccion($paraLogos);
        $this->hayFilaLogos = count($this->rutasLogosExcel) > 0;

        $filasMeta = 3;
        $offsetLogo = $this->hayFilaLogos ? 1 : 0;
        $this->filaInicioMeta = $offsetLogo + 1;
        $this->filaCabecerasExcel = $offsetLogo + $filasMeta + 1;
        $this->filaPrimeraDatosExcel = $this->filaCabecerasExcel + 1;

        return $this;
    }

    public function view(): View
    {
        return view('exports.contable.conciliacion_bancaria_sin_conciliarindex', [
            'reporte' => $this->reporte,
            'meta' => $this->meta,
            'reservarFilaLogoExcel' => $this->hayFilaLogos,
        ]);
    }

    public function title(): string
    {
        return ($this->meta['lado'] ?? '') === 'interbanking' ? 'Interbanking sin conciliar' : 'Sin conciliar';
    }

    public function columnFormats(): array
    {
        return [
            'A' => NumberFormat::FORMAT_TEXT,
            'B' => NumberFormat::FORMAT_TEXT,
            'C' => NumberFormat::FORMAT_TEXT,
            'D' => NumberFormat::FORMAT_TEXT,
            'E' => NumberFormat::FORMAT_TEXT,
            'F' => NumberFormat::FORMAT_TEXT,
            'G' => NumberFormat::FORMAT_TEXT,
            'H' => NumberFormat::FORMAT_TEXT,
            'I' => '#,##0.00',
            'J' => '#,##0.00',
            'K' => NumberFormat::FORMAT_TEXT,
            'L' => NumberFormat::FORMAT_TEXT,
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 8,
            'B' => 12,
            'C' => 10,
            'D' => 12,
            'E' => 12,
            'F' => 12,
            'G' => 12,
            'H' => 36,
            'I' => 14,
            'J' => 14,
            'K' => 8,
            'L' => 18,
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            $this->filaCabecerasExcel => [
                'font' => ['bold' => true, 'color' => ['rgb' => '17202A'], 'size' => 11, 'name' => 'Arial'],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'color' => ['rgb' => '85C1E9'],
                ],
            ],
        ];
    }

    /**
     * Filas finales (Total general y Saldo): importes numéricos, negrita y fondo de corte general.
     */
    private function aplicarTotalesFinales(Worksheet $sheet): void
    {
        $filas = array_values($this->reporte['filas'] ?? []);
        $debitos = 0.0;
        $creditos = 0.0;
        foreach ($filas as $fila) {
            $debitos += (float) ($fila['debito'] ?? 0);
            $creditos += (float) ($fila['credito'] ?? 0);
        }
        $debitos = round($debitos, 2);
        $creditos = round($creditos, 2);
        $saldo = round($debitos - $creditos, 2);

        $filaTotal = $this->filaPrimeraDatosExcel + count($filas);
        $filaSaldo = $filaTotal + 1;

        $sheet->setCellValueExplicit('I'.$filaTotal, $debitos, DataType::TYPE_NUMERIC);
        $sheet->setCellValueExplicit('J'.$filaTotal, $creditos, DataType::TYPE_NUMERIC);
        $sheet->setCellValueExplicit('I'.$filaSaldo, $saldo, DataType::TYPE_NUMERIC);

        foreach ([$filaTotal, $filaSaldo] as $fila) {
            $rango = 'A'.$fila.':'.self::COL_ULTIMA.$fila;
            $sheet->getStyle($rango)->getFont()->setName('Arial')->setSize(11)->setBold(true)->getColor()->setRGB('17202A');
            $sheet->getStyle($rango)->getFill()
                ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                ->getStartColor()->setRGB('AED6F1');
            foreach (['I', 'J'] as $col) {
                $sheet->getStyle($col.$fila)->getNumberFormat()->setFormatCode('#,##0.00');
                $sheet->getStyle($col.$fila)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            }
        }
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                if ($this->hayFilaLogos) {
                    $sheet->getRowDimension(1)->setRowHeight(54);
                    $x = 5;
                    foreach ($this->rutasLogosExcel as $ruta) {
                        if (! is_readable($ruta)) {
                            continue;
                        }
                        $d = new Drawing;
                        $d->setPath($ruta);
                        $d->setHeight(48);
                        $d->setCoordinates('A1');
                        $d->setOffsetX($x);
                        $d->setWorksheet($sheet);
                        $x += 90;
                    }
                }
                foreach (range($this->filaInicioMeta, $this->filaCabecerasExcel - 1) as $fila) {
                    $sheet->mergeCells('A'.$fila.':'.self::COL_ULTIMA.$fila);
                }
                $sheet->getStyle('A'.$this->filaInicioMeta)->getFont()->setName('Arial')->setSize(16)->setBold(true)->getColor()->setRGB('17202A');
                $sheet->getRowDimension($this->filaInicioMeta)->setRowHeight(28);
                $sheet->freezePane('A'.$this->filaPrimeraDatosExcel);

                foreach (array_values($this->reporte['filas'] ?? []) as $i => $fila) {
                    $excelRow = $this->filaPrimeraDatosExcel + $i;
                    foreach (['I' => 'debito', 'J' => 'credito'] as $col => $campo) {
                        $val = round((float) ($fila[$campo] ?? 0), 2);
                        if (abs($val) < 0.005) {
                            $sheet->setCellValue($col.$excelRow, null);
                            continue;
                        }
                        $sheet->setCellValueExplicit($col.$excelRow, $val, DataType::TYPE_NUMERIC);
                        $sheet->getStyle($col.$excelRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                        $sheet->getStyle($col.$excelRow)->getNumberFormat()->setFormatCode('#,##0.00');
                    }
                }

                $this->aplicarTotalesFinales($sheet);
            },
        ];
    }
}

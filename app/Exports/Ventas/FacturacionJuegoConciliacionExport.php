<?php

declare(strict_types=1);

namespace App\Exports\Ventas;

use App\Support\Configuracion\EmpresaLogoArchivo;
use App\Support\Export\ExcelFormatoNumero;
use Illuminate\Contracts\View\View;
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

class FacturacionJuegoConciliacionExport implements FromView, WithColumnFormatting, WithColumnWidths, WithEvents, WithStyles, WithTitle
{
    private const COL_ULTIMA = 'L';

    private bool $hayFilaLogos = false;

    private int $filaTituloExcel = 1;

    private int $filaCabecerasExcel = 4;

    private int $filaPrimeraDatosExcel = 5;

    /** @var list<string> */
    private array $rutasLogosExcel = [];

    /**
     * @param  array<string, mixed>  $resultado
     */
    public function __construct(
        private array $resultado,
        private bool $esCsv = false,
    ) {
    }

    public function view(): View
    {
        $filas = $this->resultado['filas'] ?? [];
        $paraLogos = collect([(object) ['nombreempresa' => (string) config('app.empresa', '')]]);
        $this->rutasLogosExcel = EmpresaLogoArchivo::rutasLogosCabeceraDesdeColeccion($paraLogos);
        $this->hayFilaLogos = count($this->rutasLogosExcel) > 0 && ! $this->esCsv;

        $filasMeta = 3; // título + generado + subtítulo
        $offsetLogo = $this->hayFilaLogos ? 1 : 0;
        $this->filaTituloExcel = $offsetLogo + 1;
        $this->filaCabecerasExcel = $offsetLogo + $filasMeta + 1;
        $this->filaPrimeraDatosExcel = $this->filaCabecerasExcel + 1;

        return view('exports.ventas.facturacion_juego_conciliacionindex', [
            'resultado' => $this->resultado,
            'filas' => $filas,
            'reservarFilaLogoExcel' => $this->hayFilaLogos,
            'esExcel' => true,
            'formatoNumero' => $this->formatoNumeroEfectivo(),
        ]);
    }

    public function title(): string
    {
        return 'Conciliación juego';
    }

    public function columnFormats(): array
    {
        $pref = ExcelFormatoNumero::preferenciaGlobal();
        $importe = ExcelFormatoNumero::codigoColumna($pref, 2);

        return [
            'E' => $importe,
            'F' => $importe,
            'G' => $importe,
            'H' => $importe,
            'I' => $importe,
            'J' => $importe,
            'K' => $importe,
            'L' => NumberFormat::FORMAT_TEXT,
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 12,
            'B' => 10,
            'C' => 16,
            'D' => 14,
            'E' => 14,
            'F' => 14,
            'G' => 14,
            'H' => 14,
            'I' => 12,
            'J' => 12,
            'K' => 12,
            'L' => 10,
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

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();

                if ($this->hayFilaLogos && $this->rutasLogosExcel !== []) {
                    $sheet->getRowDimension(1)->setRowHeight(54);
                    $offsetX = 5;
                    foreach ($this->rutasLogosExcel as $rutaLogo) {
                        if (! is_readable($rutaLogo)) {
                            continue;
                        }
                        $drawing = new Drawing();
                        $drawing->setPath($rutaLogo);
                        $drawing->setHeight(48);
                        $drawing->setCoordinates('A1');
                        $drawing->setOffsetX($offsetX);
                        $drawing->setWorksheet($sheet);
                        $offsetX += 120;
                    }
                }

                $colUltima = self::COL_ULTIMA;
                for ($f = $this->filaTituloExcel; $f < $this->filaCabecerasExcel; $f++) {
                    $sheet->mergeCells('A'.$f.':'.$colUltima.$f);
                }

                $sheet->getStyle('A'.$this->filaTituloExcel)->applyFromArray([
                    'font' => ['bold' => true, 'size' => 16, 'name' => 'Arial', 'color' => ['rgb' => '17202A']],
                    'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
                ]);
                $sheet->getRowDimension($this->filaTituloExcel)->setRowHeight(28);

                for ($f = $this->filaTituloExcel + 1; $f < $this->filaCabecerasExcel; $f++) {
                    $sheet->getStyle('A'.$f)->applyFromArray([
                        'font' => ['bold' => true, 'size' => 10, 'name' => 'Arial', 'color' => ['rgb' => '444444']],
                        'alignment' => ['wrapText' => true],
                    ]);
                }

                $sheet->getStyle('A'.$this->filaCabecerasExcel.':'.$colUltima.$this->filaCabecerasExcel)->applyFromArray([
                    'font' => ['bold' => true, 'size' => 11, 'name' => 'Arial', 'color' => ['rgb' => '17202A']],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => '85C1E9']],
                ]);

                $sheet->freezePane('A'.$this->filaPrimeraDatosExcel);

                $filas = $this->resultado['filas'] ?? [];
                $pref = ExcelFormatoNumero::preferenciaGlobal();
                $formatoImporte = ExcelFormatoNumero::codigoColumna($pref, 2);
                $ultimaFila = $this->filaPrimeraDatosExcel + count($filas) - 1;
                if ($ultimaFila >= $this->filaPrimeraDatosExcel) {
                    foreach (['E', 'F', 'G', 'H', 'I', 'J', 'K'] as $col) {
                        $sheet->getStyle($col.$this->filaPrimeraDatosExcel.':'.$col.$ultimaFila)
                            ->getNumberFormat()
                            ->setFormatCode($formatoImporte);
                        $sheet->getStyle($col.$this->filaPrimeraDatosExcel.':'.$col.$ultimaFila)
                            ->getAlignment()
                            ->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                    }
                    for ($i = 0; $i < count($filas); $i++) {
                        $filaExcel = $this->filaPrimeraDatosExcel + $i;
                        $fila = $filas[$i];
                        foreach ([
                            'E' => (float) ($fila['flash'] ?? 0),
                            'F' => (float) ($fila['ventas'] ?? 0),
                            'G' => (float) ($fila['erp'] ?? 0),
                            'H' => (float) ($fila['anita'] ?? 0),
                            'I' => (float) ($fila['dif_ventas'] ?? 0),
                            'J' => (float) ($fila['dif_erp'] ?? 0),
                            'K' => (float) ($fila['dif_anita'] ?? 0),
                        ] as $col => $valor) {
                            $sheet->setCellValueExplicit($col.$filaExcel, $valor, DataType::TYPE_NUMERIC);
                        }
                    }
                }
            },
        ];
    }

    private function formatoNumeroEfectivo(): string
    {
        $global = ExcelFormatoNumero::preferenciaGlobal();

        return $this->esCsv ? ExcelFormatoNumero::paraCsv($global) : $global;
    }
}

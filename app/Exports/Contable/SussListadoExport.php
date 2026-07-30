<?php

declare(strict_types=1);

namespace App\Exports\Contable;

use App\Support\Configuracion\EmpresaLogoArchivo;
use App\Support\Export\ExcelFormatoNumero;
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

class SussListadoExport implements FromView, WithColumnFormatting, WithColumnWidths, WithEvents, WithStyles, WithTitle
{
    use Exportable;

    private const COL_ULTIMA = 'G';

    private bool $hayFilaLogos = false;

    private int $filaTituloExcel = 1;

    private int $filaCabecerasExcel = 2;

    private int $filaPrimeraDatosExcel = 3;

    /** Última fila de metadatos (título/generado/subtítulo/contador) fusionable A:G. */
    private int $filaUltimaMetaExcel = 2;

    /** @var list<string> */
    private array $rutasLogosExcel = [];

    /**
     * @param  list<object>  $filasParaLogo
     * @param  list<array<string, mixed>>  $registros
     * @param  array<string, mixed>  $totales
     * @param  array<string, mixed>  $conciliacion
     */
    public function __construct(
        private array $filasParaLogo,
        private array $registros,
        private array $totales,
        private array $conciliacion,
        private string $titulo,
        private string $subtitulo = '',
    ) {
    }

    public function view(): View
    {
        $this->rutasLogosExcel = EmpresaLogoArchivo::rutasLogosCabeceraDesdeColeccion(collect($this->filasParaLogo));
        $this->hayFilaLogos = count($this->rutasLogosExcel) > 0;

        $filasMeta = 2; // título + generado
        if (trim($this->subtitulo) !== '') {
            $filasMeta++;
        }
        $filasMeta++; // contador registros

        $offsetLogo = $this->hayFilaLogos ? 1 : 0;
        // Solo las filas de metadatos se fusionan A:G; la tabla de conciliación
        // (Cód./Concepto/…) debe conservar sus columnas.
        $this->filaUltimaMetaExcel = $offsetLogo + $filasMeta;

        $mostrarConciliacion = ! empty($this->conciliacion['habilitada'])
            && ! empty($this->conciliacion['items']);
        if ($mostrarConciliacion) {
            // título conciliación + thead + ítems + título detalle
            $filasMeta += 2 + count($this->conciliacion['items']) + 1;
        }

        $this->filaTituloExcel = $offsetLogo + 1;
        $this->filaCabecerasExcel = $offsetLogo + $filasMeta + 1;
        $this->filaPrimeraDatosExcel = $this->filaCabecerasExcel + 1;

        return view('exports.contable.sussindex', [
            'registros' => $this->registros,
            'totales' => $this->totales,
            'conciliacion' => $this->conciliacion,
            'titulo' => $this->titulo,
            'subtitulo' => $this->subtitulo,
            'reservarFilaLogoExcel' => $this->hayFilaLogos,
            'esExcel' => true,
        ]);
    }

    public function columnFormats(): array
    {
        // A Cert. | B Documento | C Razón | D Fecha | E Alícuota | F Base | G Importe
        return [
            'A' => NumberFormat::FORMAT_TEXT,
            'B' => NumberFormat::FORMAT_TEXT,
            'C' => NumberFormat::FORMAT_TEXT,
            'D' => NumberFormat::FORMAT_TEXT,
            'E' => '#,##0.00',
            'F' => '#,##0.00',
            'G' => '#,##0.00',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [];
    }

    public function columnWidths(): array
    {
        // Detalle: Cert | Documento | Razón | Fecha | Alícuota | Base | Importe
        // Conciliación reutiliza las mismas columnas: Cód | Concepto | Total SUSS |
        // Total mayor | Diferencia | Saldo | Dif. vs saldo.
        // D/F/G deben alcanzar para montos tipo -19.537.010,57 (si no, Excel muestra ####).
        return [
            'A' => 10,
            'B' => 24,
            'C' => 28,
            'D' => 16,
            'E' => 14,
            'F' => 16,
            'G' => 16,
        ];
    }

    public function title(): string
    {
        return 'SUSS';
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event): void {
                $sheet = $event->sheet->getDelegate();
                $colUltima = self::COL_ULTIMA;

                $filaCabDetalle = $this->localizarFilaCabeceraDetalle($sheet) ?? $this->filaCabecerasExcel;
                $this->filaCabecerasExcel = $filaCabDetalle;
                $this->filaPrimeraDatosExcel = $filaCabDetalle + 1;

                if ($this->hayFilaLogos) {
                    $sheet->getRowDimension(1)->setRowHeight(54);
                    $offsetX = 5;
                    foreach ($this->rutasLogosExcel as $ruta) {
                        if (! is_readable($ruta)) {
                            continue;
                        }
                        $drawing = new Drawing();
                        $drawing->setPath($ruta);
                        $drawing->setHeight(48);
                        $drawing->setCoordinates('A1');
                        $drawing->setOffsetX($offsetX);
                        $drawing->setOffsetY(3);
                        $drawing->setWorksheet($sheet);
                        $offsetX += 130;
                    }
                }

                $filasYaFusionadas = [];
                foreach (array_keys($sheet->getMergeCells()) as $rango) {
                    if (preg_match('/^[A-Z]+(\d+):/', $rango, $m)) {
                        $filasYaFusionadas[(int) $m[1]] = true;
                    }
                }

                $filaInicioMeta = $this->hayFilaLogos ? 2 : 1;
                $filaFinMeta = min($this->filaUltimaMetaExcel, $this->filaCabecerasExcel - 1);
                for ($f = $filaInicioMeta; $f <= $filaFinMeta; $f++) {
                    if (isset($filasYaFusionadas[$f])) {
                        continue;
                    }
                    $sheet->mergeCells('A'.$f.':'.$colUltima.$f);
                }

                $sheet->getStyle('A'.$this->filaTituloExcel)->applyFromArray([
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
                $sheet->getRowDimension($this->filaTituloExcel)->setRowHeight(28);

                for ($f = $this->filaTituloExcel + 1; $f <= $filaFinMeta; $f++) {
                    $sheet->getStyle('A'.$f)->applyFromArray([
                        'font' => [
                            'bold' => true,
                            'size' => 10,
                            'name' => 'Arial',
                            'color' => ['rgb' => '444444'],
                        ],
                        'alignment' => ['wrapText' => true],
                    ]);
                }

                // Cabecera de la tabla de conciliación (fila "Cód."), mismo estilo que el detalle.
                for ($f = $filaFinMeta + 1; $f < $this->filaCabecerasExcel; $f++) {
                    if (trim((string) ($sheet->getCell('A'.$f)->getValue() ?? '')) === 'Cód.') {
                        $sheet->getStyle('A'.$f.':'.$colUltima.$f)->applyFromArray([
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
                    }
                }

                $sheet->getStyle('A'.$this->filaCabecerasExcel.':'.$colUltima.$this->filaCabecerasExcel)->applyFromArray([
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

                $this->formatearMontosConciliacion($sheet);

                $sheet->freezePane('A'.$this->filaPrimeraDatosExcel);
            },
        ];
    }

    /**
     * Reaplica número real en C/D/E de filas de conciliación (Total SUSS, mayor, diferencia).
     */
    private function formatearMontosConciliacion(Worksheet $sheet): void
    {
        if (empty($this->conciliacion['habilitada']) || empty($this->conciliacion['items'])) {
            return;
        }

        $mascara = ExcelFormatoNumero::codigoColumna(ExcelFormatoNumero::preferenciaGlobal(), 2);
        $limite = min($sheet->getHighestRow(), $this->filaCabecerasExcel);

        for ($row = 1; $row <= $limite; $row++) {
            $concepto = trim((string) ($sheet->getCell('B'.$row)->getValue() ?? ''));
            $cod = trim((string) ($sheet->getCell('A'.$row)->getValue() ?? ''));
            if ($cod === '' || $cod === 'Cód.' || $concepto === '' || $concepto === 'Concepto') {
                continue;
            }
            // Filas de conciliación: tienen montos en C/D/E y no son cabecera de detalle.
            $celdaC = $sheet->getCell('C'.$row)->getValue();
            if (! is_numeric($celdaC) && ! is_float($celdaC) && ! is_int($celdaC)) {
                continue;
            }

            foreach (['C', 'D', 'E', 'F', 'G'] as $col) {
                $celda = $sheet->getCell($col.$row);
                $valor = $celda->getValue();
                if (is_numeric($valor)) {
                    $celda->setValueExplicit(
                        (float) $valor,
                        \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC,
                    );
                    $sheet->getStyle($col.$row)->getNumberFormat()->setFormatCode($mascara);
                }
            }
        }
    }

    /**
     * Fila de la cabecera del detalle SUSS (primer campo "Cert.").
     */
    private function localizarFilaCabeceraDetalle(Worksheet $sheet): ?int
    {
        $highestRow = min($sheet->getHighestRow(), 500);

        for ($row = 1; $row <= $highestRow; $row++) {
            if (trim((string) ($sheet->getCell('A'.$row)->getValue() ?? '')) === 'Cert.') {
                return $row;
            }
        }

        return null;
    }
}

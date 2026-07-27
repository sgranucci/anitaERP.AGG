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

class SicoreListadoExport implements FromView, WithColumnFormatting, WithColumnWidths, WithEvents, WithStyles, WithTitle
{
    use Exportable;

    private const COL_ULTIMA = 'G';

    private bool $hayFilaLogos = false;

    private int $filaTituloExcel = 1;

    private int $filaCabecerasExcel = 2;

    private int $filaPrimeraDatosExcel = 3;

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

        $mostrarConciliacion = ! empty($this->conciliacion['habilitada'])
            && ! empty($this->conciliacion['items']);
        if ($mostrarConciliacion) {
            // título conciliación + thead + ítems + título detalle
            $filasMeta += 2 + count($this->conciliacion['items']) + 1;
        }

        $offsetLogo = $this->hayFilaLogos ? 1 : 0;
        $this->filaTituloExcel = $offsetLogo + 1;
        // Cabecera azul = thead del detalle SICORE (última fila meta antes de datos)
        $this->filaCabecerasExcel = $offsetLogo + $filasMeta + 1;
        $this->filaPrimeraDatosExcel = $this->filaCabecerasExcel + 1;

        return view('exports.contable.sicoreindex', [
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
        // A Reg. | B Imp. | C Documento | D Razón social | E Fecha | F Base | G Importe
        return [
            'A' => NumberFormat::FORMAT_TEXT,
            'B' => NumberFormat::FORMAT_TEXT,
            'C' => NumberFormat::FORMAT_TEXT,
            'D' => NumberFormat::FORMAT_TEXT,
            'E' => NumberFormat::FORMAT_TEXT,
            'F' => '#,##0.00',
            'G' => '#,##0.00',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        // El estilo de la cabecera del detalle se aplica en AfterSheet sobre la fila real
        // detectada ("Reg."), para no pintar una fila equivocada si el conteo meta difiere.
        return [];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 8,
            'B' => 8,
            'C' => 16,
            'D' => 28,
            'E' => 12,
            'F' => 14,
            'G' => 14,
        ];
    }

    public function title(): string
    {
        return 'SICORE';
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event): void {
                $sheet = $event->sheet->getDelegate();
                $colUltima = self::COL_ULTIMA;

                // Localizar de forma robusta la cabecera del detalle (fila cuyo primer campo es "Reg.").
                // El conteo de filas meta puede desalinearse (conciliación, wrap de subtítulo, etc.),
                // por eso preferimos la fila real detectada para pintar y congelar.
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

                // El lector HTML ya fusiona por colspan (título, generado, subtítulo, filas de
                // conciliación, etc.). Volver a fusionar A:G sobre esas filas genera
                // rangos SOLAPADOS que corrompen el xlsx (Excel pide "reparar"). Solo fusionamos las
                // filas meta que quedaron sin fusionar.
                $filasYaFusionadas = [];
                foreach (array_keys($sheet->getMergeCells()) as $rango) {
                    if (preg_match('/^[A-Z]+(\d+):/', $rango, $m)) {
                        $filasYaFusionadas[(int) $m[1]] = true;
                    }
                }

                $filaInicioMeta = $this->hayFilaLogos ? 2 : 1;
                for ($f = $filaInicioMeta; $f < $this->filaCabecerasExcel; $f++) {
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

                for ($f = $this->filaTituloExcel + 1; $f < $this->filaCabecerasExcel; $f++) {
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

                // Bloque de conciliación: sus importes viven en D/E/F (Total SICORE, Total mayor,
                // Diferencia), columnas que en el detalle son texto. Se detectan por el Estado
                // ("Cuadra"/"Diferencia" en col G) y se les aplica máscara numérica neutra para que
                // queden como número real sumable y adaptable a la config regional de cada PC.
                $this->formatearMontosConciliacion($sheet);

                // Congelar en la fila siguiente a la cabecera del detalle ("Reg."), para que los
                // encabezados de columna queden fijos al desplazar los datos.
                $sheet->freezePane('A'.$this->filaPrimeraDatosExcel);
            },
        ];
    }

    /**
     * Reaplica número real (máscara neutra) en D/E/F de las filas de conciliación.
     */
    private function formatearMontosConciliacion(Worksheet $sheet): void
    {
        if (empty($this->conciliacion['habilitada']) || empty($this->conciliacion['items'])) {
            return;
        }

        $mascara = ExcelFormatoNumero::codigoColumna(ExcelFormatoNumero::preferenciaGlobal(), 2);
        $limite = min($sheet->getHighestRow(), $this->filaCabecerasExcel);

        for ($row = 1; $row <= $limite; $row++) {
            $estado = trim((string) ($sheet->getCell('G'.$row)->getValue() ?? ''));
            if ($estado !== 'Cuadra' && $estado !== 'Diferencia') {
                continue;
            }

            foreach (['D', 'E', 'F'] as $col) {
                $celda = $sheet->getCell($col.$row);
                $valor = $celda->getValue();
                if (is_numeric($valor)) {
                    $celda->setValueExplicit(
                        (float) $valor,
                        \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC,
                    );
                }
                $sheet->getStyle($col.$row)->getNumberFormat()->setFormatCode($mascara);
            }
        }
    }

    /**
     * Fila de la cabecera del detalle SICORE (primer campo "Reg."). Devuelve null si no se encuentra.
     */
    private function localizarFilaCabeceraDetalle(Worksheet $sheet): ?int
    {
        $highestRow = min($sheet->getHighestRow(), 500);

        for ($row = 1; $row <= $highestRow; $row++) {
            if (trim((string) ($sheet->getCell('A'.$row)->getValue() ?? '')) === 'Reg.') {
                return $row;
            }
        }

        return null;
    }
}

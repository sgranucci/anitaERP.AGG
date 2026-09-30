<?php

namespace App\Exports\Ventas;

use App\Support\Configuracion\EmpresaLogoArchivo;
use App\Support\Ventas\FacturacionLocal\StockLocalErpMovimientosSupport;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
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

class StockLocalInformeExport implements FromView, ShouldAutoSize, WithColumnFormatting, WithEvents, WithStyles, WithTitle
{
    use Exportable;

    private bool $hayFilaLogos = false;

    private int $filasMetaEncabezado = 2;

    private int $filaInicioMeta = 1;

    private int $filaCabecerasExcel = 2;

    private int $filaPrimeraDatosExcel = 3;

    private int $totalColumnas = 6;

    private int $columnasFijas = 5;

    private string $colUltima = 'F';

    /** Entero sin decimales; si hay fracción, hasta 2. El separador de miles lo pone Excel. */
    private const FORMATO_CANTIDAD = '#,##0.##';

    /** @var list<string> */
    private array $rutasLogosExcel = [];

    /**
     * @param  list<int|string>  $medidas
     * @param  iterable<int, array<string, mixed>>  $filas
     * @param  array<string, mixed>  $totales
     */
    public function __construct(
        private array $medidas,
        private iterable $filas,
        private string $titulo,
        private string $subtitulo = '',
        private array $totales = [],
    ) {
        $this->resolverLayoutColumnas();
    }

    public function view(): View
    {
        $this->resolverLayoutColumnas();
        $coleccionLogos = collect($this->filas);
        foreach ($coleccionLogos as $i => $fila) {
            if (is_array($fila) && empty($fila['nombreempresa'])) {
                $coleccionLogos[$i] = array_merge($fila, ['nombreempresa' => config('app.empresa')]);
            }
        }
        $this->rutasLogosExcel = EmpresaLogoArchivo::rutasLogosCabeceraDesdeColeccion($coleccionLogos);
        $this->hayFilaLogos = count($this->rutasLogosExcel) > 0;
        $this->filasMetaEncabezado = $this->contarFilasMetaEncabezado();
        $offsetLogo = $this->hayFilaLogos ? 1 : 0;
        $this->filaInicioMeta = $offsetLogo + 1;
        $this->filaCabecerasExcel = $offsetLogo + $this->filasMetaEncabezado + 1;
        $this->filaPrimeraDatosExcel = $this->filaCabecerasExcel + 1;

        return view('exports.ventas.stock_local_informeindex', [
            'medidas' => $this->medidas,
            'filas' => $this->filas,
            'titulo' => $this->titulo,
            'subtitulo' => $this->subtitulo,
            'totales' => $this->totales,
            'total_columnas' => $this->totalColumnas,
            'reservarFilaLogoExcel' => $this->hayFilaLogos,
        ]);
    }

    public function columnFormats(): array
    {
        $formats = [];
        // Medidas + total. No desde la columna 6 fija: en detalle Tipo/Número van antes.
        for ($i = $this->columnasFijas + 1; $i <= $this->totalColumnas; $i++) {
            $formats[$this->indiceAColumna($i)] = self::FORMATO_CANTIDAD;
        }
        $formats['A'] = NumberFormat::FORMAT_TEXT;
        $formats['C'] = NumberFormat::FORMAT_TEXT;

        return $formats;
    }

    public function styles(Worksheet $sheet)
    {
        return [];
    }

    public function title(): string
    {
        return 'Stock local';
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                if ($this->hayFilaLogos) {
                    $sheet->getRowDimension(1)->setRowHeight(54);
                    $offsetX = 0;
                    foreach ($this->rutasLogosExcel as $ruta) {
                        if (! is_string($ruta) || $ruta === '' || ! is_file($ruta)) {
                            continue;
                        }
                        $drawing = new Drawing;
                        $drawing->setPath($ruta);
                        $drawing->setHeight(48);
                        $drawing->setCoordinates('A1');
                        $drawing->setOffsetX($offsetX);
                        $drawing->setWorksheet($sheet);
                        $offsetX += 90;
                    }
                }

                for ($i = 0; $i < $this->filasMetaEncabezado; $i++) {
                    $fila = $this->filaInicioMeta + $i;
                    $sheet->mergeCells('A'.$fila.':'.$this->colUltima.$fila);
                }

                $sheet->getStyle('A'.$this->filaInicioMeta)->applyFromArray([
                    'font' => ['name' => 'Arial', 'size' => 16, 'bold' => true, 'color' => ['rgb' => '17202A']],
                ]);
                $sheet->getRowDimension($this->filaInicioMeta)->setRowHeight(28);

                for ($i = 1; $i < $this->filasMetaEncabezado; $i++) {
                    $fila = $this->filaInicioMeta + $i;
                    $sheet->getStyle('A'.$fila)->applyFromArray([
                        'font' => ['name' => 'Arial', 'size' => 10, 'bold' => true, 'color' => ['rgb' => '444444']],
                        'alignment' => ['wrapText' => true],
                    ]);
                }

                $sheet->getStyle('A'.$this->filaCabecerasExcel.':'.$this->colUltima.$this->filaCabecerasExcel)->applyFromArray([
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => '85C1E9'],
                    ],
                    'font' => ['name' => 'Arial', 'size' => 11, 'bold' => true, 'color' => ['rgb' => '17202A']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                ]);

                // El formato de la columna no debe convertir el talle 23 en 23.00.
                $ultimaMedida = $this->totalColumnas - 1;
                for ($i = $this->columnasFijas + 1; $i <= $ultimaMedida; $i++) {
                    $cell = $this->indiceAColumna($i).$this->filaCabecerasExcel;
                    $valor = $sheet->getCell($cell)->getValue();
                    $sheet->setCellValueExplicit(
                        $cell,
                        StockLocalErpMovimientosSupport::etiquetaMedida($valor),
                        DataType::TYPE_STRING
                    );
                    $sheet->getStyle($cell)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);
                }

                $sheet->freezePane('A'.$this->filaPrimeraDatosExcel);
            },
        ];
    }

    private function resolverLayoutColumnas(): void
    {
        $modoDetalle = false;
        foreach ($this->filas as $fila) {
            if (is_array($fila) && (($fila['tipo_fila'] ?? '') === 'detalle')) {
                $modoDetalle = true;
                break;
            }
        }
        // Detalle: Fecha + SKU + Desc + Color + Color desc + Tipo + Número + medidas + Total
        // Otros: SKU + Desc + Color + Color desc + Concepto + medidas + Total
        $this->columnasFijas = $modoDetalle ? 7 : 5;
        $this->totalColumnas = $this->columnasFijas + count($this->medidas) + 1;
        $this->colUltima = $this->indiceAColumna($this->totalColumnas);
    }

    private function contarFilasMetaEncabezado(): int
    {
        $filas = 2; // título + generado
        if (trim($this->subtitulo) !== '') {
            $filas++;
        }
        if (! empty($this->totales)) {
            $filas++;
        }

        return $filas;
    }

    private function indiceAColumna(int $indice): string
    {
        $columna = '';
        while ($indice > 0) {
            $resto = ($indice - 1) % 26;
            $columna = chr(65 + $resto).$columna;
            $indice = intdiv($indice - 1, 26);
        }

        return $columna;
    }
}

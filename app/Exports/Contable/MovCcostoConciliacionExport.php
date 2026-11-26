<?php

namespace App\Exports\Contable;

use App\Support\Configuracion\EmpresaLogoArchivo;
use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;

/**
 * Excel / CSV de la conciliación de centros de costo (movimientos sin centro en cuentas con CC).
 */
class MovCcostoConciliacionExport implements FromView, WithColumnWidths, WithEvents, WithTitle
{
    use Exportable;

    /** @var list<array<string, mixed>> */
    private array $filas = [];

    /** @var array<string, mixed> */
    private array $totales = [];

    private bool $soloResumen = false;

    private bool $multiempresa = false;

    private string $titulo = '';

    private string $subtitulo = '';

    /** @var list<array{nombreempresa: string}> */
    private array $fuenteLogos = [];

    /** @var list<string> */
    private array $rutasLogosExcel = [];

    private bool $hayFilaLogos = false;

    private int $filaInicioMeta = 1;

    private int $filasMeta = 4;

    private int $filaCabecerasExcel = 5;

    private int $filaPrimeraDatosExcel = 6;

    /** @var array<int, string> fila Excel → tipo_fila (cortes) */
    private array $filasCorte = [];

    /**
     * @param  list<array<string, mixed>>  $filas
     * @param  array<string, mixed>  $totales
     * @param  list<array{nombreempresa: string}>  $fuenteLogos
     */
    public function parametros(
        array $filas,
        array $totales,
        bool $soloResumen,
        bool $multiempresa,
        string $titulo,
        string $subtitulo,
        array $fuenteLogos,
    ): self {
        $this->filas = $filas;
        $this->totales = $totales;
        $this->soloResumen = $soloResumen;
        $this->multiempresa = $multiempresa;
        $this->titulo = $titulo;
        $this->subtitulo = $subtitulo;
        $this->fuenteLogos = $fuenteLogos;

        return $this;
    }

    public function view(): View
    {
        $this->rutasLogosExcel = EmpresaLogoArchivo::rutasLogosCabeceraDesdeColeccion($this->fuenteLogos);
        $this->hayFilaLogos = count($this->rutasLogosExcel) > 0;

        $offsetLogo = $this->hayFilaLogos ? 1 : 0;
        $this->filaInicioMeta = $offsetLogo + 1;
        $this->filasMeta = trim($this->subtitulo) !== '' ? 4 : 3;
        $this->filaCabecerasExcel = $offsetLogo + $this->filasMeta + 1;
        $this->filaPrimeraDatosExcel = $this->filaCabecerasExcel + 1;

        $this->filasCorte = [];
        foreach ($this->filas as $idx => $fila) {
            $tipo = (string) ($fila['tipo_fila'] ?? '');
            if (in_array($tipo, ['cuenta', 'total_cuenta', 'total_general'], true)) {
                $this->filasCorte[$this->filaPrimeraDatosExcel + $idx] = $tipo;
            }
        }

        return view('exports.contable.movccostoconciliacionindex', [
            'filas' => $this->filas,
            'totales' => $this->totales,
            'solo_resumen' => $this->soloResumen,
            'multiempresa' => $this->multiempresa,
            'titulo' => $this->titulo,
            'subtitulo' => $this->subtitulo,
            'reservarFilaLogoExcel' => $this->hayFilaLogos,
        ]);
    }

    public function title(): string
    {
        return 'Sin centro de costo';
    }

    private function cantColumnas(): int
    {
        return ($this->soloResumen ? 3 : 7) + ($this->multiempresa ? 1 : 0) + 3;
    }

    public function columnWidths(): array
    {
        $anchos = $this->soloResumen
            ? [14, 42, 8]
            : [11, 10, 8, 18, 20, 48, 7];
        if ($this->multiempresa) {
            array_unshift($anchos, 26);
        }
        $anchos = array_merge($anchos, [16, 16, 16]);

        $out = [];
        foreach ($anchos as $i => $ancho) {
            $out[Coordinate::stringFromColumnIndex($i + 1)] = $ancho;
        }

        return $out;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $cant = $this->cantColumnas();
                $colUltima = Coordinate::stringFromColumnIndex($cant);

                if ($this->hayFilaLogos) {
                    $sheet->getRowDimension(1)->setRowHeight(54);
                    $offsetX = 5;
                    foreach ($this->rutasLogosExcel as $ruta) {
                        if (! is_file($ruta)) {
                            continue;
                        }
                        $drawing = new Drawing;
                        $drawing->setPath($ruta);
                        $drawing->setHeight(48);
                        $drawing->setCoordinates('A1');
                        $drawing->setOffsetX($offsetX);
                        $drawing->setOffsetY(3);
                        $drawing->setWorksheet($sheet);
                        $offsetX += 120;
                    }
                }

                for ($i = 0; $i < $this->filasMeta; $i++) {
                    $fila = $this->filaInicioMeta + $i;
                    $sheet->mergeCells('A'.$fila.':'.$colUltima.$fila);
                    $sheet->getStyle('A'.$fila)->applyFromArray($i === 0
                        ? ['font' => ['name' => 'Arial', 'size' => 16, 'bold' => true, 'color' => ['rgb' => '17202A']]]
                        : [
                            'font' => ['name' => 'Arial', 'size' => 10, 'bold' => true, 'color' => ['rgb' => '444444']],
                            'alignment' => ['wrapText' => true],
                        ]);
                }
                $sheet->getRowDimension($this->filaInicioMeta)->setRowHeight(28);

                $sheet->getStyle('A'.$this->filaCabecerasExcel.':'.$colUltima.$this->filaCabecerasExcel)->applyFromArray([
                    'font' => ['name' => 'Arial', 'size' => 11, 'bold' => true, 'color' => ['rgb' => '17202A']],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '85C1E9']],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical' => Alignment::VERTICAL_CENTER,
                        'wrapText' => true,
                    ],
                    'borders' => [
                        'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'CCCCCC']],
                    ],
                ]);

                $ultimaFila = $sheet->getHighestRow();
                if ($ultimaFila >= $this->filaPrimeraDatosExcel) {
                    // Importes numéricos con #,##0.00 (regla export-excel-importes-cortes).
                    $primeraImporte = $cant - 2;
                    for ($fila = $this->filaPrimeraDatosExcel; $fila <= $ultimaFila; $fila++) {
                        for ($c = $primeraImporte; $c <= $cant; $c++) {
                            $celda = $sheet->getCell(Coordinate::stringFromColumnIndex($c).$fila);
                            $valor = $celda->getValue();
                            if ($valor !== null && $valor !== '' && is_numeric($valor)) {
                                $celda->setValueExplicit((float) $valor, DataType::TYPE_NUMERIC);
                            }
                        }
                    }
                    $rango = Coordinate::stringFromColumnIndex($primeraImporte).$this->filaPrimeraDatosExcel
                        .':'.$colUltima.$ultimaFila;
                    $sheet->getStyle($rango)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED2);
                    $sheet->getStyle($rango)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                }

                $colores = [
                    'cuenta' => 'E5E7E9',
                    'total_cuenta' => 'EAF2F8',
                    'total_general' => 'AED6F1',
                ];
                foreach ($this->filasCorte as $fila => $tipo) {
                    $sheet->getStyle('A'.$fila.':'.$colUltima.$fila)->applyFromArray([
                        'font' => ['bold' => true],
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $colores[$tipo]]],
                    ]);
                }

                $sheet->freezePane('A'.$this->filaPrimeraDatosExcel);
            },
        ];
    }
}

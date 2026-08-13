<?php

namespace App\Exports\Contable;

use App\Support\Configuracion\EmpresaLogoArchivo;
use App\Support\Export\ExcelFormatoNumero;
use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class CierreRendicionBingoConciliacionFlashExport implements FromView, ShouldAutoSize, WithColumnFormatting, WithColumnWidths, WithEvents, WithStyles, WithTitle
{
    private bool $hayFilaLogos = false;

    private int $filaTituloExcel = 1;

    private int $filaCabecerasExcel = 4;

    private int $filaPrimeraDatosExcel = 6;

    private int $cantidadColumnas = 1;

    /** @var list<string> */
    private array $rutasLogosExcel = [];

    /**
     * @param  array<string, mixed>  $resultado
     */
    public function __construct(
        private array $resultado,
        private bool $esCsv = false,
    ) {
        $this->cantidadColumnas = max(1, count($this->resultado['columnas'] ?? []));
    }

    public function view(): View
    {
        $paraLogos = collect([(object) [
            'nombreempresa' => (string) ($this->resultado['empresa_nombre'] ?? ''),
        ]]);
        $this->rutasLogosExcel = EmpresaLogoArchivo::rutasLogosCabeceraDesdeColeccion($paraLogos);
        $this->hayFilaLogos = count($this->rutasLogosExcel) > 0;

        $filasMeta = 3;
        $offsetLogo = $this->hayFilaLogos ? 1 : 0;
        $this->filaTituloExcel = $offsetLogo + 1;
        $this->filaCabecerasExcel = $offsetLogo + $filasMeta + 1;
        $this->filaPrimeraDatosExcel = $this->filaCabecerasExcel + 2;

        return view('contable.cierre_rendicion_bingo.conciliacion_flash_listado', [
            'resultado' => $this->resultado,
            'esExcel' => true,
            'reservarFilaLogoExcel' => $this->hayFilaLogos,
            'filas' => self::aplanarFilas($this->resultado),
            'formatoNumero' => $this->formatoNumeroEfectivo(),
        ]);
    }

    private function formatoNumeroEfectivo(): string
    {
        $global = ExcelFormatoNumero::preferenciaGlobal();

        return $this->esCsv ? ExcelFormatoNumero::paraCsv($global) : $global;
    }

    /**
     * @param  array<string, mixed>  $resultado
     * @return list<array<string, mixed>>
     */
    public static function aplanarFilas(array $resultado): array
    {
        $filas = [];
        foreach ($resultado['dias'] ?? [] as $dia) {
            $valores = is_array($dia['valores'] ?? null) ? $dia['valores'] : [];
            $filas[] = array_merge($valores, [
                'fecha_fmt' => $dia['fecha_fmt'] ?? '',
                'estado' => $dia['estado'] ?? '',
                'cantidad_rendiciones' => (int) ($dia['cantidad_rendiciones'] ?? 0),
                'cantidad_pendiente' => (int) ($dia['cantidad_pendiente'] ?? 0),
                'estado_cierre' => (string) ($dia['estado_cierre'] ?? ''),
            ]);
        }

        $totales = is_array($resultado['totales'] ?? null) ? $resultado['totales'] : [];
        if ($filas !== [] && $totales !== []) {
            $filas[] = array_merge($totales, [
                'fecha_fmt' => 'Total',
                'estado' => '',
                'cantidad_rendiciones' => (int) ($totales['cantidad_rendiciones'] ?? 0),
                'cantidad_pendiente' => 0,
                'estado_cierre' => '',
                'es_total' => true,
            ]);
        }

        return $filas;
    }

    public function styles(Worksheet $sheet): array
    {
        $colUltima = $this->colUltima();
        $rango1 = 'A'.$this->filaCabecerasExcel.':'.$colUltima.$this->filaCabecerasExcel;
        $rango2 = 'A'.($this->filaCabecerasExcel + 1).':'.$colUltima.($this->filaCabecerasExcel + 1);

        return [
            $rango1 => [
                'font' => [
                    'bold' => true,
                    'color' => ['rgb' => '17202A'],
                    'size' => 8,
                    'name' => 'Arial',
                ],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'color' => ['rgb' => '85C1E9'],
                ],
            ],
            $rango2 => [
                'font' => [
                    'bold' => true,
                    'color' => ['rgb' => '17202A'],
                    'size' => 8,
                    'name' => 'Arial',
                ],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'color' => ['rgb' => '85C1E9'],
                ],
            ],
        ];
    }

    public function columnWidths(): array
    {
        $widths = [];
        $columnas = $this->resultado['columnas'] ?? [];
        foreach (array_values($columnas) as $i => $col) {
            $tipo = (string) ($col['tipo'] ?? 'numero');
            $ancho = match ($tipo) {
                'texto' => 12,
                'entero' => 8,
                default => 12,
            };
            $widths[Coordinate::stringFromColumnIndex($i + 1)] = $ancho;
        }

        return $widths;
    }

    /**
     * @return array<string, string>
     */
    public function columnFormats(): array
    {
        $codigo = ExcelFormatoNumero::codigoColumna(ExcelFormatoNumero::preferenciaGlobal(), 2);
        $formatos = [];
        $columnas = $this->resultado['columnas'] ?? [];
        foreach (array_values($columnas) as $i => $col) {
            if (($col['tipo'] ?? '') === 'numero') {
                $formatos[Coordinate::stringFromColumnIndex($i + 1)] = $codigo;
            }
        }

        return $formatos;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $colUltima = $this->colUltima();

                if ($this->hayFilaLogos && count($this->rutasLogosExcel) > 0) {
                    $sheet->getRowDimension(1)->setRowHeight(54);
                    $offsetXp = 6;
                    foreach ($this->rutasLogosExcel as $ruta) {
                        if (! is_string($ruta) || ! is_readable($ruta)) {
                            continue;
                        }
                        $drawing = new Drawing();
                        $drawing->setPath($ruta);
                        $drawing->setHeight(48);
                        $drawing->setCoordinates('A1');
                        $drawing->setOffsetX($offsetXp);
                        $drawing->setWorksheet($sheet);
                        $offsetXp += 90;
                    }
                }

                $sheet->mergeCells('A'.$this->filaTituloExcel.':'.$colUltima.($this->filaTituloExcel + 2));
                $sheet->getStyle('A'.$this->filaTituloExcel)->getFont()->setName('Arial')->setSize(14)->setBold(true);
                $sheet->getStyle('A'.$this->filaTituloExcel)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                $filaSub = $this->filaCabecerasExcel + 1;
                $colIdx = 1;
                foreach ($this->resultado['grupos_columnas'] ?? [] as $grupo) {
                    $span = count($grupo['columnas'] ?? []);
                    if ($span > 1) {
                        $desde = Coordinate::stringFromColumnIndex($colIdx);
                        $hasta = Coordinate::stringFromColumnIndex($colIdx + $span - 1);
                        $sheet->mergeCells($desde.$this->filaCabecerasExcel.':'.$hasta.$this->filaCabecerasExcel);
                    }
                    $colIdx += max(1, $span);
                }

                $rangoCab = 'A'.$this->filaCabecerasExcel.':'.$colUltima.$filaSub;
                $sheet->getStyle($rangoCab)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('85C1E9');
                $sheet->getStyle($rangoCab)->getFont()->setBold(true)->getColor()->setRGB('17202A');
                $sheet->getStyle($rangoCab)->getAlignment()->setWrapText(true)->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->freezePane('A'.$this->filaPrimeraDatosExcel);
            },
        ];
    }

    public function title(): string
    {
        return 'Conciliación bingo';
    }

    private function colUltima(): string
    {
        return Coordinate::stringFromColumnIndex(max(1, $this->cantidadColumnas));
    }
}

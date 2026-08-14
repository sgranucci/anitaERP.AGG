<?php

namespace App\Exports\Contable;

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
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class CierreRendicionBingoConciliacionFlashExport implements FromView, WithColumnFormatting, WithColumnWidths, WithEvents, WithStyles, WithTitle
{
    private bool $hayFilaLogos = false;

    private int $filaTituloExcel = 1;

    private int $filaCabecerasExcel = 4;

    private int $filaPrimeraDatosExcel = 6;

    private int $cantidadColumnas = 1;

    /** @var list<string> */
    private array $rutasLogosExcel = [];

    /**
     * Anchos mínimos por clave (Excel muestra ##### si no alcanzan).
     *
     * @var array<string, float>
     */
    private const ANCHO_POR_CLAVE = [
        'fecha_fmt' => 12,
        'estado' => 8,
        'cantidad_rendiciones' => 8,
        'tot_recaudacion' => 16,
        'flash_venta' => 15,
        'dif_venta' => 15,
        'tot_resultado_flash' => 15,
        'flash_resultado' => 15,
        'dif_resultado' => 15,
        'tot_pozo' => 14,
        'tot_pantalla' => 14,
        'tot_si_pozo_ac' => 15,
        'tot_efectivo' => 15,
        'tot_dif_caja' => 14,
        'tot_f_fijo' => 13,
        'tot_pago_hospital' => 14,
        'tot_vta_acumulada' => 17,
        'estado_cierre' => 12,
    ];

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
        $titulosGrupo = [];
        foreach ($this->resultado['grupos_columnas'] ?? [] as $grupo) {
            $cols = $grupo['columnas'] ?? [];
            if (count($cols) === 1) {
                $titulosGrupo[(string) ($cols[0]['key'] ?? '')] = (string) ($grupo['titulo'] ?? '');
            }
        }

        foreach (array_values($columnas) as $i => $col) {
            $key = (string) ($col['key'] ?? '');
            $tipo = (string) ($col['tipo'] ?? 'numero');
            $titulo = $titulosGrupo[$key] ?? '';
            $subtitulo = (string) ($col['subtitulo'] ?? '');
            $largoRotulo = max(mb_strlen($titulo), mb_strlen($subtitulo));

            $base = self::ANCHO_POR_CLAVE[$key] ?? match ($tipo) {
                'texto' => 12.0,
                'entero' => 9.0,
                default => 13.0,
            };

            // Cabeceras largas (Bingo 47%, Municipalidad, Evol. SI…) + importes ###.###.###,##
            $ancho = max($base, min(20.0, $largoRotulo + 2.5));
            if ($tipo === 'numero') {
                $ancho = max($ancho, 14.0);
            }
            if (str_starts_with($key, 'c') && str_contains($key, '_')) {
                $ancho = max($ancho, 13.0);
            }

            $widths[Coordinate::stringFromColumnIndex($i + 1)] = round($ancho, 1);
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

                // El HTML ya trae colspan en meta y en cabeceras de grupo: no re-mergear
                // (merge vertical A{titulo}:A{titulo+2} pisa A2/A3/A4 y Excel marca el xlsx como corrupto).
                $sheet->getStyle('A'.$this->filaTituloExcel)->getFont()->setName('Arial')->setSize(14)->setBold(true);
                $sheet->getStyle('A'.$this->filaTituloExcel)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getRowDimension($this->filaTituloExcel)->setRowHeight(28);

                $filaSub = $this->filaCabecerasExcel + 1;
                $rangoCab = 'A'.$this->filaCabecerasExcel.':'.$colUltima.$filaSub;
                $sheet->getStyle($rangoCab)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('85C1E9');
                $sheet->getStyle($rangoCab)->getFont()->setBold(true)->getColor()->setRGB('17202A');
                $sheet->getStyle($rangoCab)->getAlignment()->setWrapText(true)->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->freezePane('A'.$this->filaPrimeraDatosExcel);

                $ultimaFila = (int) $sheet->getHighestRow();
                if ($ultimaFila >= $this->filaPrimeraDatosExcel
                    && strcasecmp(trim((string) $sheet->getCell('A'.$ultimaFila)->getValue()), 'Total') === 0) {
                    $rangoTotal = 'A'.$ultimaFila.':'.$colUltima.$ultimaFila;
                    $sheet->getStyle($rangoTotal)->applyFromArray([
                        'font' => [
                            'bold' => true,
                            'name' => 'Arial',
                            'size' => 9,
                            'color' => ['rgb' => '17202A'],
                        ],
                        'fill' => [
                            'fillType' => Fill::FILL_SOLID,
                            'color' => ['rgb' => 'D6EAF8'],
                        ],
                    ]);
                }
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

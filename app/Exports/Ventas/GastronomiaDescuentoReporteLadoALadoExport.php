<?php

namespace App\Exports\Ventas;

use App\Support\Configuracion\EmpresaLogoArchivo;
use App\Support\Export\ExcelFormatoNumero;
use App\Support\Ventas\GastronomiaDescuentoReporteExcelLayout;
use App\Support\Ventas\GastronomiaDescuentoReporteTipoArticuloSupport;
use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Varios códigos del reporte de descuentos en una sola hoja:
 * cada código ocupa el mismo bloque de 7 columnas que su solapa, uno al lado del otro.
 */
class GastronomiaDescuentoReporteLadoALadoExport implements FromView, WithColumnFormatting, WithColumnWidths, WithEvents, WithTitle
{
    private const COLUMNAS_BLOQUE = 7;

    /** @var list<int> */
    private const ANCHOS_BLOQUE = [16, 34, 12, 14, 14, 14, 14];

    /** @var array<string, mixed> */
    private array $resultado = [];

    private string $titulo = '';

    private string $subtitulo = '';

    private string $empresaNombre = '';

    private ?string $tituloHoja = null;

    private bool $hayFilaLogos = false;

    private bool $esCsv = false;

    private int $cantidadBloques = 0;

    private int $filaTituloExcel = 2;

    private int $filaCabecerasExcel = 6;

    /** @var list<string> */
    private array $rutasLogosExcel = [];

    /**
     * @param  array<string, mixed>  $resultado
     */
    public function parametros(
        array $resultado,
        string $titulo,
        string $subtitulo,
        string $empresaNombre,
        bool $esCsv = false,
    ): self {
        $this->resultado = $resultado;
        $this->titulo = $titulo;
        $this->subtitulo = $subtitulo;
        $this->empresaNombre = $empresaNombre;
        $this->esCsv = $esCsv;

        return $this;
    }

    public function setTitle(string $tituloHoja): void
    {
        $tituloHoja = trim($tituloHoja);
        $this->tituloHoja = $tituloHoja !== '' ? GastronomiaDescuentoReporteExport::sanitizarNombreHoja($tituloHoja) : null;
    }

    public function view(): View
    {
        $formatoNumero = $this->formatoNumeroEfectivo();
        $fmtMonto = ExcelFormatoNumero::formateadorMonto($formatoNumero, 2);
        $fmtUnidades = ExcelFormatoNumero::formateadorMonto($formatoNumero, 2);

        $bloques = [];
        $matrices = [];
        $maxFilas = 0;
        foreach ($this->resultado['bloques'] ?? [] as $bloque) {
            $filas = $this->filasDeBloque($bloque, $fmtUnidades, $fmtMonto);
            if ($filas === []) {
                continue;
            }
            $tituloBloque = trim((string) (($bloque['codigo'] ?? '').' — '.($bloque['nombre'] ?? '')));
            $bloques[] = [
                'titulo' => $tituloBloque !== '' && $tituloBloque !== '—' ? $tituloBloque : 'Descuento',
            ];
            $matrices[] = $filas;
            $maxFilas = max($maxFilas, count($filas));
        }

        $this->cantidadBloques = count($bloques);

        $coleccionLogo = $this->empresaNombre !== ''
            ? collect([(object) ['nombreempresa' => $this->empresaNombre]])
            : collect();
        $this->rutasLogosExcel = EmpresaLogoArchivo::rutasLogosCabeceraDesdeColeccion($coleccionLogo);
        $this->hayFilaLogos = count($this->rutasLogosExcel) > 0;
        $this->filaTituloExcel = $this->hayFilaLogos ? 2 : 1;
        $this->filaCabecerasExcel = $this->filaTituloExcel + 4;

        return view('exports.ventas.gastronomia_descuento_reporte_lado_a_lado', [
            'bloques' => $bloques,
            'matrices' => $matrices,
            'max_filas' => $maxFilas,
            'titulo' => $this->titulo !== '' ? $this->titulo : 'Reporte descuentos gastronomía',
            'subtitulo' => $this->subtitulo,
            'reservarFilaLogoExcel' => $this->hayFilaLogos,
            'columnas_bloque' => self::COLUMNAS_BLOQUE,
        ]);
    }

    public function columnFormats(): array
    {
        $mascara = ExcelFormatoNumero::codigoColumna(ExcelFormatoNumero::preferenciaGlobal(), 2);
        $formatos = [];
        $bloques = max($this->cantidadBloques, $this->contarBloquesConFilas());

        for ($bloque = 0; $bloque < $bloques; $bloque++) {
            $inicio = $bloque * self::COLUMNAS_BLOQUE;
            $formatos[Coordinate::stringFromColumnIndex($inicio + 1)] = NumberFormat::FORMAT_TEXT;
            $formatos[Coordinate::stringFromColumnIndex($inicio + 2)] = NumberFormat::FORMAT_TEXT;
            for ($offset = 3; $offset <= self::COLUMNAS_BLOQUE; $offset++) {
                $formatos[Coordinate::stringFromColumnIndex($inicio + $offset)] = $mascara;
            }
        }

        return $formatos;
    }

    public function columnWidths(): array
    {
        $anchos = [];
        $bloques = max($this->cantidadBloques, 1);

        for ($bloque = 0; $bloque < $bloques; $bloque++) {
            foreach (self::ANCHOS_BLOQUE as $offset => $ancho) {
                $anchos[Coordinate::stringFromColumnIndex($bloque * self::COLUMNAS_BLOQUE + $offset + 1)] = $ancho;
            }
        }

        return $anchos;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $layout = new GastronomiaDescuentoReporteExcelLayout($this->hayFilaLogos, 4);
                $layout->aplicarLogos($sheet, $this->rutasLogosExcel);

                $colUltima = Coordinate::stringFromColumnIndex(max($this->cantidadBloques, 1) * self::COLUMNAS_BLOQUE);
                $filaTitulo = $this->filaTituloExcel;
                $filaGenerado = $filaTitulo + 1;
                $filaCodigo = $filaTitulo + 2;
                $filaPeriodo = $filaTitulo + 3;
                $filaCabecera = $this->filaCabecerasExcel;

                $sheet->getRowDimension($filaTitulo)->setRowHeight(28);
                $sheet->getStyle('A'.$filaTitulo.':'.$colUltima.$filaTitulo)->applyFromArray([
                    'font' => [
                        'bold' => true,
                        'size' => 16,
                        'name' => 'Arial',
                        'color' => ['rgb' => '17202A'],
                    ],
                ]);
                $sheet->getStyle('A'.$filaGenerado.':'.$colUltima.$filaPeriodo)->applyFromArray([
                    'font' => [
                        'bold' => true,
                        'size' => 10,
                        'name' => 'Arial',
                        'color' => ['rgb' => '444444'],
                    ],
                    'alignment' => [
                        'wrapText' => true,
                        'vertical' => Alignment::VERTICAL_CENTER,
                    ],
                ]);
                $sheet->getStyle('A'.$filaCodigo.':'.$colUltima.$filaCodigo)->applyFromArray([
                    'font' => [
                        'bold' => true,
                        'size' => 11,
                        'name' => 'Arial',
                        'color' => ['rgb' => '17202A'],
                    ],
                ]);
                $sheet->getStyle('A'.$filaCabecera.':'.$colUltima.$filaCabecera)->applyFromArray([
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

                $highest = (int) $sheet->getHighestRow();
                for ($fila = $filaCabecera + 1; $fila <= $highest; $fila++) {
                    for ($bloque = 0; $bloque < $this->cantidadBloques; $bloque++) {
                        $col = Coordinate::stringFromColumnIndex($bloque * self::COLUMNAS_BLOQUE + 1);
                        $valor = trim((string) $sheet->getCell($col.$fila)->getValue());
                        if ($valor === '') {
                            continue;
                        }
                        $hasta = Coordinate::stringFromColumnIndex(($bloque + 1) * self::COLUMNAS_BLOQUE);
                        $rango = $col.$fila.':'.$hasta.$fila;
                        if (str_starts_with($valor, 'Tipo:')) {
                            $sheet->getStyle($rango)->applyFromArray([
                                'font' => ['bold' => true, 'name' => 'Arial'],
                                'fill' => [
                                    'fillType' => Fill::FILL_SOLID,
                                    'color' => ['rgb' => 'D5E8F5'],
                                ],
                            ]);
                        } elseif (str_starts_with($valor, 'Total')) {
                            $sheet->getStyle($rango)->getFont()->setBold(true);
                        }
                    }
                }

                for ($bloque = 0; $bloque < $this->cantidadBloques; $bloque++) {
                    $colDesc = Coordinate::stringFromColumnIndex($bloque * self::COLUMNAS_BLOQUE + 2);
                    $sheet->getStyle($colDesc.($filaCabecera + 1).':'.$colDesc.$highest)
                        ->getAlignment()
                        ->setWrapText(true);
                }

                $sheet->freezePane('A'.($filaCabecera + 1));
            },
        ];
    }

    public function title(): string
    {
        return $this->tituloHoja ?? 'Detalle';
    }

    /**
     * @param  array<string, mixed>  $bloque
     * @return list<list<array{valor:string,colspan:int}>>
     */
    private function filasDeBloque(array $bloque, callable $fmtUnidades, callable $fmtMonto): array
    {
        $grupos = $bloque['grupos'] ?? null;
        if ($grupos === null) {
            $grupos = GastronomiaDescuentoReporteTipoArticuloSupport::agruparFilas($bloque['filas'] ?? [])['grupos'];
        }
        if ($grupos === []) {
            return [];
        }

        $filas = [];
        foreach ($grupos as $grupo) {
            $cantidad = (int) ($grupo['cantidad_lineas'] ?? count($grupo['filas'] ?? []));
            $filas[] = [[
                'valor' => 'Tipo: '.($grupo['tipo_nombre'] ?? '').' ('.$cantidad.' línea'.($cantidad === 1 ? '' : 's').')',
                'colspan' => self::COLUMNAS_BLOQUE,
            ]];
            foreach ($grupo['filas'] ?? [] as $fila) {
                $filas[] = [
                    ['valor' => (string) ($fila['sku'] ?? ''), 'colspan' => 1],
                    ['valor' => (string) ($fila['descripcion'] ?? ''), 'colspan' => 1],
                    ['valor' => $fmtUnidades($fila['unidades'] ?? 0), 'colspan' => 1],
                    ['valor' => $fmtMonto($fila['costo_unitario'] ?? 0), 'colspan' => 1],
                    ['valor' => $fmtMonto($fila['costo_total'] ?? 0), 'colspan' => 1],
                    ['valor' => $fmtMonto($fila['precio_venta'] ?? 0), 'colspan' => 1],
                    ['valor' => $fmtMonto($fila['total_venta'] ?? 0), 'colspan' => 1],
                ];
            }
            $filas[] = [
                ['valor' => 'Total parcial '.($grupo['tipo_nombre'] ?? ''), 'colspan' => 2],
                ['valor' => $fmtUnidades($grupo['subtotal_unidades'] ?? 0), 'colspan' => 1],
                ['valor' => '', 'colspan' => 1],
                ['valor' => $fmtMonto($grupo['subtotal_costo_total'] ?? 0), 'colspan' => 1],
                ['valor' => '', 'colspan' => 1],
                ['valor' => $fmtMonto($grupo['subtotal_total_venta'] ?? 0), 'colspan' => 1],
            ];
        }

        $totales = $bloque['totales'] ?? [];
        $filas[] = [
            ['valor' => 'Total final', 'colspan' => 2],
            ['valor' => $fmtUnidades($totales['unidades'] ?? 0), 'colspan' => 1],
            ['valor' => '', 'colspan' => 1],
            ['valor' => $fmtMonto($totales['costo_total'] ?? 0), 'colspan' => 1],
            ['valor' => '', 'colspan' => 1],
            ['valor' => $fmtMonto($totales['total_venta'] ?? 0), 'colspan' => 1],
        ];

        return $filas;
    }

    private function contarBloquesConFilas(): int
    {
        $cantidad = 0;
        foreach ($this->resultado['bloques'] ?? [] as $bloque) {
            $filas = $bloque['filas'] ?? [];
            $grupos = $bloque['grupos'] ?? [];
            if ($filas !== [] || $grupos !== []) {
                $cantidad++;
            }
        }

        return $cantidad;
    }

    private function formatoNumeroEfectivo(): string
    {
        $global = ExcelFormatoNumero::preferenciaGlobal();

        return $this->esCsv ? ExcelFormatoNumero::paraCsv($global) : $global;
    }
}

<?php

declare(strict_types=1);

namespace App\Exports\Ventas;

use App\Services\Ventas\GastronomiaControlContableCigarrillosService;
use App\Support\Configuracion\EmpresaLogoArchivo;
use App\Support\Export\ExcelFormatoNumero;
use App\Support\Ventas\GastronomiaInsumosTipoarticuloReporteFiltros;
use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;

/**
 * Planilla Contaduría cigarrillos: colores del Excel original + formato numérico regional
 * ({@see ExcelFormatoNumero}, mismo patrón que conciliaciones de cierre gastronomía/estacionamiento).
 */
class GastronomiaControlContableCigarrillosExport implements FromView, WithColumnWidths, WithEvents, WithTitle
{
    use Exportable;

    /** Celeste claro del Excel original (theme accent + tint ≈ Cantidad / cabecera fechas). */
    private const COLOR_CELESTE = 'DDEBF7';

    /** Rojo Contaduría para VENTA / II / Gravado / NETO / IVA / REDONDEO. */
    private const COLOR_ROJO = 'C00000';

    /** Amarillo fila Diferencias. */
    private const COLOR_AMARILLO = 'FFFF00';

    /** @var array<string, mixed> */
    private array $filtros = [];

    private string $titulo = 'Control contable cigarrillos';

    private string $subtitulo = '';

    private string $empresaNombre = '';

    private bool $esCsv = false;

    private bool $hayFilaLogos = false;

    /** @var list<string> */
    private array $rutasLogosExcel = [];

    private string $colUltima = 'D';

    private int $filaTituloExcel = 1;

    private int $numDias = 0;

    /** @var array<string, mixed> */
    private array $resultado = [];

    public function __construct(
        private readonly GastronomiaControlContableCigarrillosService $reporteService,
    ) {
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    public function parametros(
        array $filtros,
        string $titulo,
        string $subtitulo,
        string $empresaNombre = '',
        bool $esCsv = false,
    ): self {
        $this->filtros = $filtros;
        $this->titulo = $titulo;
        $this->subtitulo = $subtitulo;
        $this->empresaNombre = trim($empresaNombre);
        $this->esCsv = $esCsv;

        return $this;
    }

    public function view(): View
    {
        $this->resultado = $this->reporteService->generar($this->filtros);
        $coleccionLogo = $this->empresaNombre !== ''
            ? collect([(object) ['nombreempresa' => $this->empresaNombre]])
            : collect();
        $this->rutasLogosExcel = EmpresaLogoArchivo::rutasLogosCabeceraDesdeColeccion($coleccionLogo);
        $this->hayFilaLogos = count($this->rutasLogosExcel) > 0 && ! $this->esCsv;
        $this->filaTituloExcel = $this->hayFilaLogos ? 2 : 1;

        $this->numDias = count($this->resultado['columnas_dias'] ?? []);
        $numCols = 2 + max(1, $this->numDias);
        $this->colUltima = Coordinate::stringFromColumnIndex($numCols);

        $formatoNumero = $this->formatoNumeroEfectivo();

        return view('exports.ventas.gastronomia_control_contable_cigarrillosindex', [
            'resultado' => $this->resultado,
            'titulo' => $this->titulo,
            'subtitulo' => $this->subtitulo,
            'reservarFilaLogoExcel' => $this->hayFilaLogos,
            'formatoNumero' => $formatoNumero,
            'esCsv' => $this->esCsv,
        ]);
    }

    public function columnWidths(): array
    {
        $widths = ['A' => 28, 'B' => 26];
        $numDias = count(GastronomiaInsumosTipoarticuloReporteFiltros::columnasDias(
            (string) ($this->filtros['fecha_desde'] ?? ''),
            (string) ($this->filtros['fecha_hasta'] ?? ''),
        ));
        for ($i = 0; $i < $numDias; $i++) {
            $widths[Coordinate::stringFromColumnIndex(3 + $i)] = 12;
        }

        return $widths;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                if ($this->esCsv) {
                    return;
                }

                $sheet = $event->sheet->getDelegate();
                $colUltima = $this->colUltima;
                $formato = $this->formatoNumeroEfectivo();
                $maskMonto = ExcelFormatoNumero::codigoColumna($formato, 2);
                $maskCant = ExcelFormatoNumero::codigoColumna($formato, 0);

                if ($this->hayFilaLogos && count($this->rutasLogosExcel) > 0) {
                    $sheet->getRowDimension(1)->setRowHeight(54);
                    $offsetXp = 6;
                    foreach ($this->rutasLogosExcel as $idx => $ruta) {
                        if (! is_string($ruta) || ! is_readable($ruta)) {
                            continue;
                        }
                        $drawing = new Drawing;
                        $drawing->setPath($ruta);
                        $drawing->setResizeProportional(true);
                        $drawing->setHeight(46);
                        $drawing->setCoordinates('A1');
                        $drawing->setOffsetX($offsetXp + $idx * 160);
                        $drawing->setOffsetY(4);
                        $drawing->setWorksheet($sheet);
                    }
                }

                $filaTit = $this->filaTituloExcel;
                $sheet->mergeCells('A'.$filaTit.':'.$colUltima.$filaTit);
                $sheet->getStyle('A'.$filaTit.':'.$colUltima.$filaTit)->applyFromArray([
                    'font' => ['bold' => true, 'size' => 14, 'name' => 'Arial', 'color' => ['rgb' => '17202A']],
                    'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
                ]);

                if ($this->subtitulo !== '') {
                    $filaSub = $filaTit + 1;
                    $sheet->mergeCells('A'.$filaSub.':'.$colUltima.$filaSub);
                }

                $sheet->getStyle('A')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);
                $sheet->getStyle('B')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);

                $highestRow = (int) $sheet->getHighestRow();

                for ($row = 1; $row <= $highestRow; $row++) {
                    $concepto = mb_strtoupper(trim((string) $sheet->getCell('B'.$row)->getValue()));
                    $tipoCig = mb_strtoupper(trim((string) $sheet->getCell('A'.$row)->getValue()));

                    if ($tipoCig === 'TIPO CIG' || $concepto === 'CONCEPTOS') {
                        $sheet->getStyle('A'.$row.':'.$colUltima.$row)->applyFromArray([
                            'font' => ['bold' => true, 'name' => 'Arial', 'color' => ['rgb' => '17202A']],
                            'fill' => [
                                'fillType' => Fill::FILL_SOLID,
                                'color' => ['rgb' => self::COLOR_CELESTE],
                            ],
                            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                        ]);
                        continue;
                    }

                    if (str_contains($concepto, 'CANTIDAD')) {
                        $sheet->getStyle('A'.$row.':'.$colUltima.$row)->applyFromArray([
                            'fill' => [
                                'fillType' => Fill::FILL_SOLID,
                                'color' => ['rgb' => self::COLOR_CELESTE],
                            ],
                        ]);
                        if (ExcelFormatoNumero::esAuto($formato)) {
                            $sheet->getStyle('C'.$row.':'.$colUltima.$row)
                                ->getNumberFormat()->setFormatCode($maskCant);
                        }
                        continue;
                    }

                    if (str_contains($concepto, 'DIFERENCIA')) {
                        $sheet->getStyle('C'.$row.':'.$colUltima.$row)->applyFromArray([
                            'fill' => [
                                'fillType' => Fill::FILL_SOLID,
                                'color' => ['rgb' => self::COLOR_AMARILLO],
                            ],
                        ]);
                        if (ExcelFormatoNumero::esAuto($formato)) {
                            $sheet->getStyle('C'.$row.':'.$colUltima.$row)
                                ->getNumberFormat()->setFormatCode($maskMonto);
                        }
                        continue;
                    }

                    if ($this->conceptoEsRojoContaduria($concepto)) {
                        $sheet->getStyle('B'.$row.':'.$colUltima.$row)->getFont()->getColor()->setRGB(self::COLOR_ROJO);
                        if (ExcelFormatoNumero::esAuto($formato)) {
                            $sheet->getStyle('C'.$row.':'.$colUltima.$row)
                                ->getNumberFormat()->setFormatCode($maskMonto);
                        }
                        continue;
                    }

                    if (
                        str_contains($concepto, 'SUMATORIA')
                        || str_contains($concepto, 'MAYOR')
                        || str_contains($concepto, 'PCIO')
                        || str_contains($concepto, '% IMP')
                    ) {
                        if (ExcelFormatoNumero::esAuto($formato)) {
                            $sheet->getStyle('C'.$row.':'.$colUltima.$row)
                                ->getNumberFormat()->setFormatCode($maskMonto);
                        }
                    }
                }

                $sheet->freezePane('C'.($filaTit + ($this->subtitulo !== '' ? 3 : 2)));
            },
        ];
    }

    public function title(): string
    {
        return 'Control cigarrillos';
    }

    private function formatoNumeroEfectivo(): string
    {
        $global = ExcelFormatoNumero::preferenciaGlobal();

        return $this->esCsv ? ExcelFormatoNumero::paraCsv($global) : $global;
    }

    private function conceptoEsRojoContaduria(string $concepto): bool
    {
        if ($concepto === '' || str_contains($concepto, 'SUMATORIA') || str_starts_with($concepto, '%')) {
            return false;
        }

        return str_contains($concepto, 'VENTA TOTAL')
            || $concepto === 'IMP INTERNO'
            || $concepto === 'GRAVADO'
            || $concepto === 'NETO'
            || $concepto === 'IVA'
            || str_contains($concepto, 'REDONDEO');
    }
}

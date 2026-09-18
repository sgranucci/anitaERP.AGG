<?php

declare(strict_types=1);

namespace App\Exports\Caja;

use App\Support\Caja\CierreCajaReporteSecciones;
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
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class CierreCajaReporteExport implements FromView, WithColumnFormatting, WithColumnWidths, WithEvents, WithStyles, WithTitle
{
    use Exportable;

    private const COL_ULTIMA = 'G';

    private bool $hayFilaLogos = false;

    private int $filaTituloExcel = 1;

    private int $filaCabecerasExcel = 2;

    private int $filaPrimeraDatosExcel = 3;

    /** @var list<string> */
    private array $rutasLogosExcel = [];

    /** @var list<int> */
    private array $filasSeparador = [];

    /** @var list<int> */
    private array $filasTituloSeccion = [];

    /** @var list<int> */
    private array $filasCabeceraSeccion = [];

    /** @var list<int> */
    private array $filasTotal = [];

    /**
     * @var list<array{
     *     header: int,
     *     from: int,
     *     to: int,
     *     col_ultima: string,
     *     cols_numericas: list<string>
     * }>
     */
    private array $rangosBloque = [];

    /**
     * @param  list<array<string, mixed>>  $filas
     * @param  array<string, mixed>  $resultado
     */
    public function __construct(
        private readonly array $filas,
        private readonly string $titulo,
        private readonly string $subtitulo,
        private readonly array $resultado,
    ) {
        $coleccion = collect($this->filas)->map(fn ($f) => (object) $f);
        $this->rutasLogosExcel = EmpresaLogoArchivo::rutasLogosCabeceraDesdeColeccion($coleccion);
        $this->hayFilaLogos = count($this->rutasLogosExcel) > 0;
        $filasMeta = $this->contarFilasMetaEncabezado();
        $offsetLogo = $this->hayFilaLogos ? 1 : 0;
        $this->filaTituloExcel = $offsetLogo + 1;
        $this->mapearFilasBloques($offsetLogo + $filasMeta);
        $this->filaCabecerasExcel = $this->filasCabeceraSeccion[0] ?? ($offsetLogo + $filasMeta + 1);
        $this->filaPrimeraDatosExcel = $this->filasTituloSeccion[0] ?? ($this->filaCabecerasExcel);
    }

    public function view(): View
    {
        return view('exports.caja.cierre_caja_reporteindex', [
            'filas' => $this->filas,
            'titulo' => $this->titulo,
            'subtitulo' => $this->subtitulo,
            'resultado' => $this->resultado,
            'reservarFilaLogoExcel' => $this->hayFilaLogos,
        ]);
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
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 14,
            'B' => 36,
            'C' => 16,
            'D' => 16,
            'E' => 16,
            'F' => 14,
            'G' => 14,
        ];
    }

    public function styles(Worksheet $sheet)
    {
        $estiloCab = [
            'font' => ['bold' => true, 'color' => ['rgb' => '17202A'], 'size' => 11, 'name' => 'Arial'],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => '85C1E9']],
        ];
        if ($this->filasCabeceraSeccion === []) {
            return [];
        }

        return [
            $this->filasCabeceraSeccion[0] => $estiloCab,
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                if ($this->hayFilaLogos && $this->rutasLogosExcel !== []) {
                    $sheet->getRowDimension(1)->setRowHeight(54);
                    $offsetXp = 6;
                    foreach ($this->rutasLogosExcel as $idx => $ruta) {
                        if (! is_string($ruta) || ! is_readable($ruta)) {
                            continue;
                        }
                        $drawing = new Drawing;
                        $drawing->setName('Logo');
                        $drawing->setPath($ruta);
                        $drawing->setResizeProportional(true);
                        $drawing->setHeight(46);
                        $drawing->setCoordinates('A1');
                        $drawing->setOffsetX($offsetXp + $idx * 160);
                        $drawing->setOffsetY(4);
                        $drawing->setWorksheet($sheet);
                    }
                }
                $filasMeta = $this->contarFilasMetaEncabezado();
                $filaTit = $this->filaTituloExcel;
                $ultimaMeta = $filaTit + $filasMeta - 1;
                for ($f = $filaTit; $f <= $ultimaMeta; $f++) {
                    $this->mergeSiHaceFalta($sheet, 'A'.$f.':'.self::COL_ULTIMA.$f);
                }
                $sheet->getRowDimension($filaTit)->setRowHeight(28);
                $sheet->getStyle('A'.$filaTit.':'.self::COL_ULTIMA.$filaTit)->applyFromArray([
                    'font' => ['bold' => true, 'size' => 16, 'name' => 'Arial', 'color' => ['rgb' => '17202A']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER],
                ]);
                if ($ultimaMeta > $filaTit) {
                    $sheet->getStyle('A'.($filaTit + 1).':'.self::COL_ULTIMA.$ultimaMeta)->applyFromArray([
                        'font' => ['bold' => true, 'size' => 10, 'name' => 'Arial', 'color' => ['rgb' => '444444']],
                    ]);
                }
                foreach ($this->filasSeparador as $filaSep) {
                    $this->mergeSiHaceFalta($sheet, 'A'.$filaSep.':'.self::COL_ULTIMA.$filaSep);
                    $sheet->getRowDimension($filaSep)->setRowHeight(18);
                }
                foreach ($this->filasTituloSeccion as $filaTitulo) {
                    $this->mergeSiHaceFalta($sheet, 'A'.$filaTitulo.':'.self::COL_ULTIMA.$filaTitulo);
                    $sheet->getRowDimension($filaTitulo)->setRowHeight(22);
                    $sheet->getStyle('A'.$filaTitulo.':'.self::COL_ULTIMA.$filaTitulo)->applyFromArray([
                        'font' => ['bold' => true, 'size' => 11, 'name' => 'Arial', 'color' => ['rgb' => '17202A']],
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'D6EAF8']],
                        'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER],
                    ]);
                }
                foreach ($this->rangosBloque as $rango) {
                    $colUlt = $rango['col_ultima'];
                    $sheet->getStyle('A'.$rango['header'].':'.$colUlt.$rango['header'])->applyFromArray([
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '85C1E9']],
                        'font' => ['bold' => true, 'color' => ['rgb' => '17202A'], 'name' => 'Arial', 'size' => 11],
                    ]);
                    if ($rango['from'] <= $rango['to'] && $rango['cols_numericas'] !== []) {
                        foreach ($rango['cols_numericas'] as $col) {
                            $sheet->getStyle($col.$rango['from'].':'.$col.$rango['to'])->applyFromArray([
                                'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT],
                            ]);
                        }
                    }
                }
                foreach ($this->filasTotal as $filaTotal) {
                    $sheet->getStyle('A'.$filaTotal.':'.self::COL_ULTIMA.$filaTotal)->applyFromArray([
                        'font' => ['bold' => true, 'name' => 'Arial', 'color' => ['rgb' => '17202A']],
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'D5D8DC']],
                    ]);
                }
                $sheet->freezePane('A'.$this->filaPrimeraDatosExcel);
            },
        ];
    }

    public function title(): string
    {
        return 'Cierre de caja';
    }

    private function contarFilasMetaEncabezado(): int
    {
        $filasMeta = 2;
        if (trim($this->subtitulo) !== '') {
            $filasMeta++;
        }

        return $filasMeta;
    }

    private function mergeSiHaceFalta(Worksheet $sheet, string $range): void
    {
        foreach ($sheet->getMergeCells() as $merged) {
            if ($merged === $range) {
                return;
            }
        }
        $sheet->mergeCells($range);
    }

    private function mapearFilasBloques(int $filaAnterior): void
    {
        $fila = $filaAnterior;
        foreach (CierreCajaReporteSecciones::bloques($this->resultado) as $bloque) {
            $fila++;
            $this->filasSeparador[] = $fila;
            $fila++;
            $this->filasTituloSeccion[] = $fila;
            $fila++;
            $this->filasCabeceraSeccion[] = $fila;
            $header = $fila;
            $from = $fila + 1;
            foreach ($bloque['filas'] as $r) {
                $fila++;
                if (($r['tipo_fila'] ?? '') === 'total') {
                    $this->filasTotal[] = $fila;
                }
            }
            $this->rangosBloque[] = [
                'header' => $header,
                'from' => $from,
                'to' => $fila,
                'col_ultima' => (string) $bloque['col_ultima'],
                'cols_numericas' => $bloque['cols_numericas'],
            ];
        }
    }
}

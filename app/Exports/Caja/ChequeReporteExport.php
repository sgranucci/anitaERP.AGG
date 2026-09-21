<?php

namespace App\Exports\Caja;

use App\Models\Caja\Cheque;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Support\Caja\ChequeReporteFiltros;
use App\Support\Caja\ChequeReporteSupport;
use App\Support\Configuracion\EmpresaLogoArchivo;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
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

class ChequeReporteExport implements FromView, WithColumnFormatting, WithColumnWidths, WithEvents, WithStyles, WithTitle
{
    use Exportable;

    private const COL_ULTIMA = 'M';

    /** Columna Importe (numérica). */
    private const COL_IMPORTE = 'D';

    private EmpresaRepositoryInterface $empresaRepository;

    /** @var array<string, mixed> */
    private array $filtros = [];

    private bool $flDesdeIndex = false;

    private bool $hayFilaLogos = false;

    private int $filaCabecerasExcel = 4;

    private int $filaPrimeraDatosExcel = 5;

    private int $filaTituloExcel = 1;

    /** @var list<string> */
    private array $rutasLogosExcel = [];

    /** Filas de corte (Total dia / Total general) para negrita. */
    /** @var list<int> */
    private array $filasCorteExcel = [];

    public function __construct(EmpresaRepositoryInterface $empresaRepository)
    {
        $this->empresaRepository = $empresaRepository;
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    public function parametros(array $filtros): self
    {
        $this->filtros = $filtros;
        $this->flDesdeIndex = true;

        return $this;
    }

    public function view(): View
    {
        $datas = collect();
        $filas = [];
        $this->filasCorteExcel = [];
        if ($this->flDesdeIndex) {
            $datas = ChequeReporteSupport::listar($this->filtros, $this->empresaRepository, false);
            $filas = ChequeReporteSupport::filasConSubtotalesDiarios($datas, $this->filtros);
            self::enriquecerNombreEmpresa($datas);
        }

        $this->rutasLogosExcel = EmpresaLogoArchivo::rutasLogosCabeceraDesdeColeccion($datas);
        $this->hayFilaLogos = count($this->rutasLogosExcel) > 0;
        $offset = $this->hayFilaLogos ? 1 : 0;
        $this->filaTituloExcel = $offset + 1;
        $this->filaCabecerasExcel = $offset + 4;
        $this->filaPrimeraDatosExcel = $this->filaCabecerasExcel + 1;

        $filaExcel = $this->filaPrimeraDatosExcel;
        foreach ($filas as $fila) {
            $tipo = (string) ($fila['tipo'] ?? '');
            if ($tipo === 'total_dia' || $tipo === 'total_general') {
                $this->filasCorteExcel[] = $filaExcel;
            }
            $filaExcel++;
        }

        $tipo = ($this->filtros['tipo'] ?? 'E') === 'R' ? 'R' : 'E';

        return view('exports.caja.chequereporteindex', [
            'datas' => $datas,
            'filas' => $filas,
            'tipo' => $tipo,
            'subtitulo' => ChequeReporteFiltros::subtitulo($this->filtros),
            'titulo' => $tipo === 'R' ? 'Cheques recibidos' : 'Cheques emitidos',
            'reservarFilaLogoExcel' => $this->hayFilaLogos,
        ]);
    }

    public function columnFormats(): array
    {
        if (! $this->flDesdeIndex) {
            return [];
        }

        return [
            'A' => NumberFormat::FORMAT_TEXT,
            'B' => NumberFormat::FORMAT_TEXT,
            'C' => NumberFormat::FORMAT_TEXT,
            self::COL_IMPORTE => '#,##0.00',
            'E' => NumberFormat::FORMAT_TEXT,
            'F' => NumberFormat::FORMAT_TEXT,
            'G' => NumberFormat::FORMAT_TEXT,
            'H' => NumberFormat::FORMAT_TEXT,
            'I' => NumberFormat::FORMAT_TEXT,
            'J' => NumberFormat::FORMAT_TEXT,
            'K' => NumberFormat::FORMAT_TEXT,
            'L' => NumberFormat::FORMAT_TEXT,
            'M' => NumberFormat::FORMAT_TEXT,
        ];
    }

    public function styles(Worksheet $sheet)
    {
        if (! $this->flDesdeIndex) {
            return [];
        }

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

    public function columnWidths(): array
    {
        if (! $this->flDesdeIndex) {
            return [];
        }

        return [
            'A' => 18,
            'B' => 12,
            'C' => 12,
            'D' => 16,
            'E' => 10,
            'F' => 32,
            'G' => 28,
            'H' => 14,
            'I' => 26,
            'J' => 8,
            'K' => 14,
            'L' => 10,
            'M' => 16,
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                if (! $this->flDesdeIndex) {
                    return;
                }

                $sheet = $event->sheet->getDelegate();

                if ($this->hayFilaLogos && count($this->rutasLogosExcel) > 0) {
                    $sheet->getRowDimension(1)->setRowHeight(54);
                    $offsetXp = 6;
                    $saltoXp = 160;
                    foreach ($this->rutasLogosExcel as $idx => $ruta) {
                        if (! is_string($ruta) || ! is_readable($ruta)) {
                            continue;
                        }
                        $drawing = new Drawing;
                        $drawing->setName('Logo');
                        $drawing->setDescription('Logo empresa');
                        $drawing->setPath($ruta);
                        $drawing->setResizeProportional(true);
                        $drawing->setHeight(46);
                        $drawing->setCoordinates('A1');
                        $drawing->setOffsetX($offsetXp + $idx * $saltoXp);
                        $drawing->setOffsetY(4);
                        $drawing->setWorksheet($sheet);
                    }
                }

                $filaTit = $this->filaTituloExcel;
                $sheet->mergeCells('A'.$filaTit.':'.self::COL_ULTIMA.$filaTit);
                $sheet->getRowDimension($filaTit)->setRowHeight(28);
                $hastaMeta = $filaTit + 2;
                $sheet->mergeCells('A'.($filaTit + 1).':'.self::COL_ULTIMA.($filaTit + 1));
                $sheet->mergeCells('A'.$hastaMeta.':'.self::COL_ULTIMA.$hastaMeta);
                $sheet->getStyle('A'.$filaTit.':'.self::COL_ULTIMA.$filaTit)->applyFromArray([
                    'font' => [
                        'bold' => true,
                        'size' => 16,
                        'name' => 'Arial',
                        'color' => ['rgb' => '17202A'],
                    ],
                ]);
                $sheet->getStyle('A'.$this->filaCabecerasExcel.':'.self::COL_ULTIMA.$this->filaCabecerasExcel)->applyFromArray([
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

                $ultimaFila = max($this->filaPrimeraDatosExcel, (int) $sheet->getHighestRow());
                if ($ultimaFila >= $this->filaPrimeraDatosExcel) {
                    $rangoImporte = self::COL_IMPORTE.$this->filaPrimeraDatosExcel.':'.self::COL_IMPORTE.$ultimaFila;
                    $sheet->getStyle($rangoImporte)->getNumberFormat()->setFormatCode('#,##0.00');
                    $sheet->getStyle($rangoImporte)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

                    // Forzar tipo numérico en Importe (FromView a veces deja string).
                    for ($r = $this->filaPrimeraDatosExcel; $r <= $ultimaFila; $r++) {
                        $cell = $sheet->getCell(self::COL_IMPORTE.$r);
                        $raw = $cell->getValue();
                        if ($raw === null || $raw === '') {
                            continue;
                        }
                        if (is_numeric($raw)) {
                            $cell->setValueExplicit((float) $raw, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
                        }
                    }
                }

                foreach ($this->filasCorteExcel as $filaCorte) {
                    $rango = 'A'.$filaCorte.':'.self::COL_ULTIMA.$filaCorte;
                    $esGeneral = str_starts_with(
                        (string) $sheet->getCell('A'.$filaCorte)->getValue(),
                        'Total general'
                    );
                    $sheet->getStyle($rango)->applyFromArray([
                        'font' => [
                            'bold' => true,
                            'name' => 'Arial',
                            'size' => 10,
                            'color' => ['rgb' => '17202A'],
                        ],
                        'fill' => [
                            'fillType' => Fill::FILL_SOLID,
                            'color' => ['rgb' => $esGeneral ? 'AED6F1' : 'D6EAF8'],
                        ],
                    ]);
                    $sheet->getStyle(self::COL_IMPORTE.$filaCorte)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                }

                $sheet->freezePane('A'.$this->filaPrimeraDatosExcel);
            },
        ];
    }

    public function title(): string
    {
        return ($this->filtros['tipo'] ?? 'E') === 'R' ? 'Cheques recibidos' : 'Cheques emitidos';
    }

    /**
     * @param  Collection<int, Cheque>  $datas
     */
    private static function enriquecerNombreEmpresa(Collection $datas): void
    {
        foreach ($datas as $row) {
            $row->nombreempresa = $row->empresas->nombre ?? '';
        }
    }
}

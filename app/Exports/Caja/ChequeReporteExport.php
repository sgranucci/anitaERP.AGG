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
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ChequeReporteExport implements FromView, ShouldAutoSize, WithColumnFormatting, WithColumnWidths, WithEvents, WithStyles, WithTitle
{
    use Exportable;

    private const COL_ULTIMA = 'K';

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
        $totales = collect();
        if ($this->flDesdeIndex) {
            $datas = ChequeReporteSupport::listar($this->filtros, $this->empresaRepository, false);
            $totales = ChequeReporteSupport::totales($this->filtros, $this->empresaRepository);
            self::enriquecerNombreEmpresa($datas);
        }

        $this->rutasLogosExcel = EmpresaLogoArchivo::rutasLogosCabeceraDesdeColeccion($datas);
        $this->hayFilaLogos = count($this->rutasLogosExcel) > 0;
        $offset = $this->hayFilaLogos ? 1 : 0;
        $this->filaTituloExcel = $offset + 1;
        $this->filaCabecerasExcel = $offset + 4;
        $this->filaPrimeraDatosExcel = $this->filaCabecerasExcel + 1;

        return view('exports.caja.chequereporteindex', [
            'datas' => $datas,
            'totales' => $totales,
            'subtitulo' => ChequeReporteFiltros::subtitulo($this->filtros),
            'titulo' => (($this->filtros['tipo'] ?? 'E') === 'R')
                ? 'Cheques recibidos'
                : 'Cheques emitidos',
            'origen_enum' => Cheque::$enumOrigen,
            'estado_enum' => Cheque::$enumEstado,
            'reservarFilaLogoExcel' => $this->hayFilaLogos,
            'etiquetaFechaDoc' => (($this->filtros['tipo'] ?? 'E') === 'R') ? 'Ingreso' : 'Emisión',
        ]);
    }

    public function columnFormats(): array
    {
        if (! $this->flDesdeIndex) {
            return [];
        }
        $cols = [];
        foreach (range('A', self::COL_ULTIMA) as $c) {
            $cols[$c] = NumberFormat::FORMAT_TEXT;
        }

        return $cols;
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
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
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
            'A' => 8,
            'B' => 14,
            'C' => 10,
            'D' => 14,
            'E' => 12,
            'F' => 12,
            'G' => 24,
            'H' => 18,
            'I' => 14,
            'J' => 8,
            'K' => 28,
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

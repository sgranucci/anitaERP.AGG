<?php

namespace App\Exports\Solicitudpago;

use App\Repositories\Solicitudpago\SolicitudpagoRepositoryInterface;
use App\Support\Configuracion\EmpresaLogoArchivo;
use App\Support\Export\ExcelFormatoNumero;
use App\Support\Solicitudpago\SolicitudpagoEstados;
use App\Support\Solicitudpago\SolicitudpagoTratamientos;
use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class SolicitudpagoListadoExport implements FromView, ShouldAutoSize, WithColumnFormatting, WithColumnWidths, WithEvents, WithStyles, WithTitle
{
    use Exportable;

    private const COL_ULTIMA = 'N';

    /** Congela Código, Fecha y Vencimiento (A–C): freeze arranca en D. */
    private const COL_FREEZE = 'D';

    private SolicitudpagoRepositoryInterface $repository;

    /** @var array<string, mixed>|string|null */
    private $filtros;

    private bool $flDesdeIndex = false;

    private bool $esCsv = false;

    private bool $hayFilaLogos = false;

    private int $filaCabecerasExcel = 3;

    private int $filaPrimeraDatosExcel = 4;

    private int $filaTituloExcel = 1;

    private int $filaSubtituloExcel = 2;

    /** @var list<string> */
    private array $rutasLogosExcel = [];

    public function __construct(SolicitudpagoRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function view(): View
    {
        if ($this->flDesdeIndex) {
            $datas = $this->repository->leeSolicitudpago($this->filtros, false);
            self::enriquecerNombreEmpresa($datas);

            $this->rutasLogosExcel = EmpresaLogoArchivo::rutasLogosCabeceraDesdeColeccion($datas);
            $this->hayFilaLogos = count($this->rutasLogosExcel) > 0;
            $this->filaTituloExcel = $this->hayFilaLogos ? 2 : 1;
            $this->filaSubtituloExcel = $this->filaTituloExcel + 1;
            $this->filaCabecerasExcel = $this->filaSubtituloExcel + 1;
            $this->filaPrimeraDatosExcel = $this->filaCabecerasExcel + 1;

            return view('exports.solicitudpago.solicitudpagoindex', [
                'datas' => $datas,
                'estado_enum' => SolicitudpagoEstados::opciones(),
                'tratamiento_enum' => SolicitudpagoTratamientos::opciones(),
                'reservarFilaLogoExcel' => $this->hayFilaLogos,
                'esExcel' => true,
                'formatoNumero' => $this->formatoNumeroEfectivo(),
            ]);
        }

        $this->hayFilaLogos = false;
        $this->filaTituloExcel = 1;
        $this->filaSubtituloExcel = 2;
        $this->filaCabecerasExcel = 3;
        $this->filaPrimeraDatosExcel = 4;
        $this->rutasLogosExcel = [];

        return view('exports.solicitudpago.solicitudpagoindex', [
            'datas' => collect(),
            'estado_enum' => SolicitudpagoEstados::opciones(),
            'tratamiento_enum' => SolicitudpagoTratamientos::opciones(),
            'reservarFilaLogoExcel' => false,
            'esExcel' => true,
            'formatoNumero' => $this->formatoNumeroEfectivo(),
        ]);
    }

    public function columnFormats(): array
    {
        if (! $this->flDesdeIndex) {
            return [];
        }

        // Identificadores/textos como texto; I = Monto; M = cuotas (entero).
        return [
            'A' => NumberFormat::FORMAT_TEXT,
            'B' => NumberFormat::FORMAT_TEXT,
            'C' => NumberFormat::FORMAT_TEXT,
            'D' => NumberFormat::FORMAT_TEXT,
            'E' => NumberFormat::FORMAT_TEXT,
            'F' => NumberFormat::FORMAT_TEXT,
            'G' => NumberFormat::FORMAT_TEXT,
            'H' => NumberFormat::FORMAT_TEXT,
            'I' => ExcelFormatoNumero::codigoColumna(ExcelFormatoNumero::preferenciaGlobal(), 2),
            'J' => NumberFormat::FORMAT_TEXT,
            'K' => NumberFormat::FORMAT_TEXT,
            'L' => NumberFormat::FORMAT_TEXT,
            'M' => NumberFormat::FORMAT_NUMBER,
            'N' => NumberFormat::FORMAT_TEXT,
        ];
    }

    private function formatoNumeroEfectivo(): string
    {
        $global = ExcelFormatoNumero::preferenciaGlobal();

        return $this->esCsv ? ExcelFormatoNumero::paraCsv($global) : $global;
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
            'A' => 10,
            'B' => 12,
            'C' => 12,
            'D' => 22,
            'E' => 16,
            'F' => 16,
            'G' => 12,
            'H' => 28,
            'I' => 12,
            'J' => 14,
            'K' => 14,
            'L' => 12,
            'M' => 10,
            'N' => 18,
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
                $sheet->getRowDimension($filaTit)->setRowHeight(30);
                $sheet->getStyle('A'.$filaTit.':'.self::COL_ULTIMA.$filaTit)->applyFromArray([
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

                $sheet->mergeCells('A'.$this->filaSubtituloExcel.':'.self::COL_ULTIMA.$this->filaSubtituloExcel);
                $sheet->getStyle('A'.$this->filaSubtituloExcel)->getFont()->setName('Arial')->setSize(10)->setBold(true)->getColor()->setRGB('444444');

                $sheet->freezePane(self::COL_FREEZE.$this->filaPrimeraDatosExcel);
            },
        ];
    }

    public function title(): string
    {
        return 'Solicitudes de pago';
    }

    /**
     * @param  array<string, mixed>|string|null  $filtros
     */
    public function parametros($filtros, bool $esCsv = false)
    {
        $this->filtros = $filtros;
        $this->esCsv = $esCsv;
        $this->flDesdeIndex = true;

        return $this;
    }

    private static function enriquecerNombreEmpresa($datas): void
    {
        foreach ($datas as $row) {
            $row->nombreempresa = $row->empresas->nombre ?? '';
        }
    }
}

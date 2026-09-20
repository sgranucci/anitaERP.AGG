<?php

namespace App\Exports\Caja;

use App\Models\Caja\Cheque;
use App\Repositories\Caja\ChequeRepositoryInterface;
use App\Support\Configuracion\EmpresaLogoArchivo;
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

class ChequeListadoExport implements FromView, ShouldAutoSize, WithColumnFormatting, WithColumnWidths, WithEvents, WithStyles, WithTitle
{
    use Exportable;

    private const COL_ULTIMA = 'M';

    private ChequeRepositoryInterface $chequeRepository;

    /** @var array<string, mixed>|string|null */
    private $filtros;

    private bool $flDesdeIndex = false;

    private bool $hayFilaLogos = false;

    private int $filaCabecerasExcel = 2;

    private int $filaPrimeraDatosExcel = 3;

    private int $filaTituloExcel = 1;

    /** @var list<string> */
    private array $rutasLogosExcel = [];

    /** @var list<int> */
    private array $filasTotalExcel = [];

    public function __construct(ChequeRepositoryInterface $chequeRepository)
    {
        $this->chequeRepository = $chequeRepository;
    }

    public function view(): View
    {
        if ($this->flDesdeIndex) {
            $datas = $this->chequeRepository->leeCheque($this->filtros, false);
            self::enriquecerNombreEmpresa($datas);

            $totales = self::totalesPorMoneda($datas);

            $this->rutasLogosExcel = EmpresaLogoArchivo::rutasLogosCabeceraDesdeColeccion($datas);
            $this->hayFilaLogos = count($this->rutasLogosExcel) > 0;
            $this->filaTituloExcel = $this->hayFilaLogos ? 2 : 1;
            $this->filaCabecerasExcel = $this->hayFilaLogos ? 3 : 2;
            $this->filaPrimeraDatosExcel = $this->filaCabecerasExcel + 1;
            $this->filasTotalExcel = self::calcularFilasTotal(
                $this->filaPrimeraDatosExcel,
                $datas->count(),
                count($totales)
            );

            return view('exports.caja.chequeindex', [
                'datas' => $datas,
                'totales' => $totales,
                'origen_enum' => Cheque::$enumOrigen,
                'estado_enum' => Cheque::$enumEstado,
                'reservarFilaLogoExcel' => $this->hayFilaLogos,
            ]);
        }

        $this->hayFilaLogos = false;
        $this->filaTituloExcel = 1;
        $this->filaCabecerasExcel = 2;
        $this->filaPrimeraDatosExcel = 3;
        $this->rutasLogosExcel = [];
        $this->filasTotalExcel = [];

        return view('exports.caja.chequeindex', [
            'datas' => collect(),
            'totales' => [],
            'origen_enum' => Cheque::$enumOrigen,
            'estado_enum' => Cheque::$enumEstado,
            'reservarFilaLogoExcel' => false,
        ]);
    }

    public function columnFormats(): array
    {
        if ($this->flDesdeIndex) {
            $cols = [];
            foreach (range('A', self::COL_ULTIMA) as $c) {
                $cols[$c] = NumberFormat::FORMAT_TEXT;
            }

            return $cols;
        }

        return [];
    }

    public function styles(Worksheet $sheet)
    {
        if ($this->flDesdeIndex) {
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

        return [];
    }

    public function columnWidths(): array
    {
        if ($this->flDesdeIndex) {
            return [
                'A' => 8,
                'B' => 14,
                'C' => 12,
                'D' => 12,
                'E' => 10,
                'F' => 12,
                'G' => 12,
                'H' => 12,
                'I' => 20,
                'J' => 16,
                'K' => 14,
                'L' => 10,
                'M' => 22,
            ];
        }

        return [];
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

                foreach ($this->filasTotalExcel as $filaTotal) {
                    $sheet->getStyle('A'.$filaTotal.':'.self::COL_ULTIMA.$filaTotal)->applyFromArray([
                        'font' => [
                            'bold' => true,
                            'name' => 'Arial',
                            'color' => ['rgb' => '17202A'],
                        ],
                    ]);
                }

                $sheet->freezePane('A'.$this->filaPrimeraDatosExcel);
            },
        ];
    }

    public function title(): string
    {
        return 'Cheques';
    }

    /**
     * @param  array<string, mixed>|string|null  $filtros
     */
    public function parametros($filtros)
    {
        $this->filtros = $filtros;
        $this->flDesdeIndex = true;

        return $this;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Cheque>|\Illuminate\Database\Eloquent\Collection<int, Cheque>  $datas
     */
    private static function enriquecerNombreEmpresa($datas): void
    {
        foreach ($datas as $row) {
            $row->nombreempresa = $row->empresas->nombre ?? '';
        }
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Cheque>|\Illuminate\Database\Eloquent\Collection<int, Cheque>  $datas
     * @return list<array{moneda:string, monto:float, cantidad:int}>
     */
    private static function totalesPorMoneda($datas): array
    {
        $totalesMap = [];
        foreach ($datas as $cheque) {
            $moneda = trim((string) ($cheque->monedas->abreviatura ?? ''));
            if ($moneda === '') {
                $moneda = trim((string) ($cheque->monedas->nombre ?? ''));
            }
            if ($moneda === '') {
                $moneda = '$';
            }
            if (! isset($totalesMap[$moneda])) {
                $totalesMap[$moneda] = ['moneda' => $moneda, 'monto' => 0.0, 'cantidad' => 0];
            }
            $totalesMap[$moneda]['monto'] = round($totalesMap[$moneda]['monto'] + (float) $cheque->monto, 2);
            $totalesMap[$moneda]['cantidad']++;
        }

        return array_values($totalesMap);
    }

    /**
     * @return list<int>
     */
    private static function calcularFilasTotal(int $filaPrimeraDatos, int $cantidadDatos, int $cantidadTotales): array
    {
        if ($cantidadTotales <= 0) {
            return [];
        }

        $filas = [];
        $primeraTotal = $filaPrimeraDatos + $cantidadDatos;
        for ($i = 0; $i < $cantidadTotales; $i++) {
            $filas[] = $primeraTotal + $i;
        }

        return $filas;
    }
}

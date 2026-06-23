<?php

namespace App\Exports\Uif;

use App\Repositories\Uif\Cliente_Premio_UifRepository;
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

class ClienteUifPremiosExport implements FromView, ShouldAutoSize, WithColumnFormatting, WithColumnWidths, WithEvents, WithStyles, WithTitle
{
    use Exportable;

    private const COL_ULTIMA = 'H';

    private Cliente_Premio_UifRepository $clientePremioUifRepository;

    private ?int $clienteUifId = null;

    private ?object $clienteUif = null;

    private bool $flActivo = false;

    private bool $hayFilaLogos = false;

    private int $filaCabecerasExcel = 2;

    private int $filaPrimeraDatosExcel = 3;

    private int $filaTituloExcel = 1;

    /** @var list<string> */
    private array $rutasLogosExcel = [];

    public function __construct(Cliente_Premio_UifRepository $clientePremioUifRepository)
    {
        $this->clientePremioUifRepository = $clientePremioUifRepository;
    }

    public function view(): View
    {
        if ($this->flActivo && $this->clienteUifId) {
            $premios = $this->clientePremioUifRepository->leePremiosPorClienteUif($this->clienteUifId);

            $this->rutasLogosExcel = EmpresaLogoArchivo::rutasLogosCabeceraDesdeColeccion($premios);
            $this->hayFilaLogos = count($this->rutasLogosExcel) > 0;
            $this->filaTituloExcel = $this->hayFilaLogos ? 2 : 1;
            $this->filaCabecerasExcel = $this->hayFilaLogos ? 3 : 2;
            $this->filaPrimeraDatosExcel = $this->filaCabecerasExcel + 1;

            return view('exports.uif.cliente_uif_premiosindex', [
                'premios' => $premios,
                'cliente_uif' => $this->clienteUif,
                'reservarFilaLogoExcel' => $this->hayFilaLogos,
            ]);
        }

        return view('exports.uif.cliente_uif_premiosindex', [
            'premios' => collect(),
            'cliente_uif' => null,
            'reservarFilaLogoExcel' => false,
        ]);
    }

    public function columnFormats(): array
    {
        if (! $this->flActivo) {
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
        if (! $this->flActivo) {
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
        if (! $this->flActivo) {
            return [];
        }

        return [
            'A' => 10,
            'B' => 20,
            'C' => 22,
            'D' => 22,
            'E' => 14,
            'F' => 14,
            'G' => 12,
            'H' => 22,
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                if (! $this->flActivo) {
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

                $sheet->freezePane('A'.$this->filaPrimeraDatosExcel);
            },
        ];
    }

    public function title(): string
    {
        return 'Premios cliente UIF';
    }

    public function parametros(int $clienteUifId, object $clienteUif): self
    {
        $this->clienteUifId = $clienteUifId;
        $this->clienteUif = $clienteUif;
        $this->flActivo = true;

        return $this;
    }
}

<?php

namespace App\Exports\Ticket;

use App\Queries\Ticket\TicketQueryInterface;
use App\Support\Configuracion\EmpresaLogoArchivo;
use App\Support\Ticket\AdministracionTicketListadoFiltros;
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

class AdministracionTicketListadoExport implements FromView, ShouldAutoSize, WithColumnFormatting, WithColumnWidths, WithEvents, WithStyles, WithTitle
{
    use Exportable;

    private const COL_ULTIMA = 'N';

    private TicketQueryInterface $ticketQuery;

    /** @var array<string, mixed> */
    private array $filtros = [];

    private bool $flDesdeIndex = false;

    private bool $hayFilaLogos = false;

    private int $filaCabecerasExcel = 2;

    private int $filaPrimeraDatosExcel = 3;

    private int $filaTituloExcel = 1;

    /** @var list<string> */
    private array $rutasLogosExcel = [];

    public function __construct(TicketQueryInterface $ticketQuery)
    {
        $this->ticketQuery = $ticketQuery;
    }

    public function view(): View
    {
        if ($this->flDesdeIndex) {
            $tickets = $this->ticketQuery->leeTicketAdministracion($this->filtros, false);

            $this->rutasLogosExcel = EmpresaLogoArchivo::rutasLogosCabeceraDesdeColeccion(collect());
            $this->hayFilaLogos = count($this->rutasLogosExcel) > 0;
            $this->filaTituloExcel = $this->hayFilaLogos ? 2 : 1;
            $this->filaCabecerasExcel = $this->hayFilaLogos ? 3 : 2;
            $this->filaPrimeraDatosExcel = $this->filaCabecerasExcel + 1;

            return view('exports.ticket.administracion_ticketindex', [
                'ticket' => $tickets,
                'titulo' => 'Administración de tickets',
                'subtitulo' => AdministracionTicketListadoFiltros::formatearResumenExport($this->filtros),
                'reservarFilaLogoExcel' => $this->hayFilaLogos,
                'calculadas' => $this->calculadasExport(),
            ]);
        }

        $this->hayFilaLogos = false;
        $this->filaTituloExcel = 1;
        $this->filaCabecerasExcel = 2;
        $this->filaPrimeraDatosExcel = 3;
        $this->rutasLogosExcel = [];

        return view('exports.ticket.administracion_ticketindex', [
            'ticket' => collect(),
            'titulo' => 'Administración de tickets',
            'subtitulo' => '',
            'reservarFilaLogoExcel' => false,
            'calculadas' => [],
        ]);
    }

    public function columnFormats(): array
    {
        if (! $this->flDesdeIndex) {
            return [];
        }

        $cols = [];
        foreach (range('A', $this->columnaUltima()) as $c) {
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

        $anchos = [
            'A' => 8,
            'B' => 11,
            'C' => 14,
            'D' => 14,
            'E' => 18,
            'F' => 18,
            'G' => 16,
            'H' => 16,
            'I' => 12,
            'J' => 24,
            'K' => 28,
            'L' => 18,
            'M' => 16,
            'N' => 14,
        ];
        $extra = count($this->calculadasExport());
        for ($i = 1; $i <= $extra; $i++) {
            $anchos[chr(ord(self::COL_ULTIMA) + $i)] = 18;
        }

        return $anchos;
    }

    private function columnaUltima(): string
    {
        return chr(ord(self::COL_ULTIMA) + count($this->calculadasExport()));
    }

    /**
     * @return list<array{etiqueta: string, formula: string, valida: bool}>
     */
    private function calculadasExport(): array
    {
        $raw = is_array($this->filtros) ? ($this->filtros['calculadas'] ?? []) : [];

        return AdministracionTicketListadoFiltros::normalizarCalculadas($raw);
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
                $col = $this->columnaUltima();
                $sheet->mergeCells('A'.$filaTit.':'.$col.$filaTit);
                $sheet->getRowDimension($filaTit)->setRowHeight(36);
                $sheet->getStyle('A'.$filaTit.':'.$col.$filaTit)->applyFromArray([
                    'font' => [
                        'bold' => true,
                        'size' => 14,
                        'name' => 'Arial',
                        'color' => ['rgb' => '17202A'],
                    ],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_LEFT,
                        'vertical' => Alignment::VERTICAL_CENTER,
                        'wrapText' => true,
                    ],
                ]);

                $sheet->freezePane('A'.$this->filaPrimeraDatosExcel);

                $primera = $this->filaPrimeraDatosExcel;
                foreach (['J', 'K'] as $col) {
                    $sheet->getStyle($col.$primera.':'.$col.$sheet->getHighestRow())
                        ->getAlignment()
                        ->setWrapText(true)
                        ->setVertical(Alignment::VERTICAL_TOP);
                }
            },
        ];
    }

    public function title(): string
    {
        return 'Administración de tickets';
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
}

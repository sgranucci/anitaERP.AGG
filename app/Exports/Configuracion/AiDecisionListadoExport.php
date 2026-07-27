<?php

namespace App\Exports\Configuracion;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class AiDecisionListadoExport implements FromView, ShouldAutoSize, WithColumnWidths, WithEvents, WithStyles, WithTitle
{
    use Exportable;

    private int $filaCabecerasExcel = 4;

    private int $filaPrimeraDatosExcel = 5;

    /**
     * @param  Collection<int, \App\Models\Ai\AiDecision>  $filas
     * @param  array<string, mixed>  $kpis
     */
    public function __construct(
        private Collection $filas,
        private array $kpis,
        private string $titulo,
        private string $subtitulo,
    ) {}

    public function view(): View
    {
        $filasMeta = 2; // título + generado
        if (trim($this->subtitulo) !== '') {
            $filasMeta++;
        }
        $filasMeta++; // resumen KPIs
        $this->filaCabecerasExcel = $filasMeta + 1;
        $this->filaPrimeraDatosExcel = $this->filaCabecerasExcel + 1;

        return view('exports.configuracion.ai_decisionindex', [
            'filas' => $this->filas,
            'kpis' => $this->kpis,
            'titulo' => $this->titulo,
            'subtitulo' => $this->subtitulo,
        ]);
    }

    public function title(): string
    {
        return 'Gobernanza IA';
    }

    public function columnWidths(): array
    {
        return [
            'A' => 8,
            'B' => 18,
            'C' => 28,
            'D' => 22,
            'E' => 10,
            'F' => 12,
            'G' => 18,
            'H' => 18,
            'I' => 14,
            'J' => 18,
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $ultima = 'J';

                $sheet->mergeCells('A1:'.$ultima.'1');
                $sheet->getStyle('A1')->getFont()->setName('Arial')->setSize(16)->setBold(true)->getColor()->setRGB('17202A');
                $sheet->getRowDimension(1)->setRowHeight(28);

                $filaCab = $this->filaCabecerasExcel;
                $sheet->getStyle('A'.$filaCab.':'.$ultima.$filaCab)->applyFromArray([
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => '85C1E9'],
                    ],
                    'font' => [
                        'name' => 'Arial',
                        'size' => 11,
                        'bold' => true,
                        'color' => ['rgb' => '17202A'],
                    ],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical' => Alignment::VERTICAL_CENTER,
                        'wrapText' => true,
                    ],
                ]);
                $sheet->freezePane('A'.$this->filaPrimeraDatosExcel);
            },
        ];
    }
}

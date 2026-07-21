<?php

namespace App\Exports\Compras;

use App\Models\Compras\Precarga_Comprobante_Recepcion_Error;
use App\Support\Compras\PrecargaRecepcionErrorListadoFiltros;
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
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class PrecargaRecepcionErrorListadoExport implements FromView, ShouldAutoSize, WithColumnFormatting, WithColumnWidths, WithEvents, WithStyles, WithTitle
{
    use Exportable;

    private const COL_ULTIMA = 'J';

    /** @var array<string, mixed> */
    private array $filtros = [];

    private bool $flDesdeIndex = false;

    private int $filaCabecerasExcel = 2;

    private int $filaPrimeraDatosExcel = 3;

    public function parametros(array $filtros): self
    {
        $this->filtros = $filtros;
        $this->flDesdeIndex = true;

        return $this;
    }

    public function view(): View
    {
        $datas = collect();
        if ($this->flDesdeIndex) {
            $query = Precarga_Comprobante_Recepcion_Error::query()
                ->with(['usuario:id,nombre'])
                ->orderByDesc('id');
            if (PrecargaRecepcionErrorListadoFiltros::tieneCriteriosAplicados($this->filtros)) {
                PrecargaRecepcionErrorListadoFiltros::aplicar($query, $this->filtros);
            }
            $datas = $query->get();
        }

        $this->filaCabecerasExcel = 2;
        $this->filaPrimeraDatosExcel = 3;

        return view('exports.compras.precarga_recepcion_errorindex', [
            'datas' => $datas,
        ]);
    }

    public function title(): string
    {
        return 'Errores recepción';
    }

    public function columnFormats(): array
    {
        return [
            'A' => NumberFormat::FORMAT_TEXT,
            'F' => NumberFormat::FORMAT_TEXT,
            'G' => NumberFormat::FORMAT_TEXT,
            'I' => NumberFormat::FORMAT_TEXT,
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 8,
            'B' => 18,
            'C' => 14,
            'D' => 12,
            'E' => 14,
            'F' => 16,
            'G' => 16,
            'H' => 10,
            'I' => 50,
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
                $fila = $this->filaCabecerasExcel;
                $sheet->getStyle('A'.$fila.':'.self::COL_ULTIMA.$fila)->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['rgb' => '17202A'], 'name' => 'Arial', 'size' => 11],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => '85C1E9'],
                    ],
                    'alignment' => ['wrapText' => true, 'vertical' => Alignment::VERTICAL_CENTER],
                ]);
                $sheet->mergeCells('A1:'.self::COL_ULTIMA.'1');
                $sheet->getStyle('A1')->applyFromArray([
                    'font' => ['bold' => true, 'size' => 16, 'name' => 'Arial', 'color' => ['rgb' => '17202A']],
                ]);
                $sheet->freezePane('A'.$this->filaPrimeraDatosExcel);
            },
        ];
    }
}

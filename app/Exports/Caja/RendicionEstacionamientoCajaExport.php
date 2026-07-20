<?php

namespace App\Exports\Caja;

use App\Support\Configuracion\EmpresaLogoArchivo;
use App\Support\Export\ExcelFormatoNumero;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class RendicionEstacionamientoCajaExport implements FromView, WithColumnFormatting, WithColumnWidths, WithEvents, WithStyles, WithTitle
{
    private const COL_ULTIMA = 'L';

    /** Congela también ID y Ticket (columnas A y B): el freeze arranca en C. */
    private const COL_FREEZE = 'C';

    private bool $hayFilaLogos = false;

    private int $filaTituloExcel = 1;

    private int $filaSubtituloExcel = 2;

    private int $filaCabecerasExcel = 3;

    private int $filaPrimeraDatosExcel = 4;

    /** @var list<string> */
    private array $rutasLogosExcel = [];

    public function __construct(
        private Collection $rendiciones,
        private bool $esCsv = false,
    ) {
    }

    public function view(): View
    {
        foreach ($this->rendiciones as $row) {
            $row->nombreempresa = $row->empresa?->nombre ?? '';
        }

        $this->rutasLogosExcel = EmpresaLogoArchivo::rutasLogosCabeceraDesdeColeccion($this->rendiciones);
        $this->hayFilaLogos = count($this->rutasLogosExcel) > 0;
        $this->filaTituloExcel = $this->hayFilaLogos ? 2 : 1;
        $this->filaSubtituloExcel = $this->filaTituloExcel + 1;
        $this->filaCabecerasExcel = $this->filaSubtituloExcel + 1;
        $this->filaPrimeraDatosExcel = $this->filaCabecerasExcel + 1;

        return view('caja.rendicionestacionamiento.listado', [
            'rendiciones' => $this->rendiciones,
            'esExcel' => true,
            'reservarFilaLogoExcel' => $this->hayFilaLogos,
            'formatoNumero' => $this->formatoNumeroEfectivo(),
        ]);
    }

    public function title(): string
    {
        return 'Rend. estacionamiento caja';
    }

    public function columnWidths(): array
    {
        return [
            'A' => 8,
            'B' => 14,
            'C' => 18,
            'D' => 24,
            'E' => 18,
            'F' => 12,
            'G' => 12,
            'H' => 14,
            'I' => 14,
            'J' => 14,
            'K' => 14,
            'L' => 14,
        ];
    }

    /**
     * Importes (H–L) con máscara neutra: sumables y adaptables a la región de la
     * PC que abre el archivo.
     *
     * @return array<string, string>
     */
    public function columnFormats(): array
    {
        $codigo = ExcelFormatoNumero::codigoColumna(ExcelFormatoNumero::preferenciaGlobal(), 2);

        return [
            'H' => $codigo,
            'I' => $codigo,
            'J' => $codigo,
            'K' => $codigo,
            'L' => $codigo,
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
                $col = self::COL_ULTIMA;

                if ($this->hayFilaLogos && count($this->rutasLogosExcel) > 0) {
                    $sheet->getRowDimension(1)->setRowHeight(54);
                    $offsetX = 6;
                    foreach ($this->rutasLogosExcel as $ruta) {
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
                        $drawing->setOffsetX($offsetX);
                        $drawing->setOffsetY(4);
                        $drawing->setWorksheet($sheet);
                        $offsetX += 160;
                    }
                }

                $sheet->mergeCells('A'.$this->filaTituloExcel.':'.$col.$this->filaTituloExcel);
                $sheet->mergeCells('A'.$this->filaSubtituloExcel.':'.$col.$this->filaSubtituloExcel);
                $sheet->getStyle('A'.$this->filaTituloExcel)->getFont()->setName('Arial')->setSize(16)->setBold(true)->getColor()->setRGB('17202A');
                $sheet->getStyle('A'.$this->filaSubtituloExcel)->getFont()->setName('Arial')->setSize(10)->setBold(true)->getColor()->setRGB('444444');
                $sheet->getRowDimension($this->filaTituloExcel)->setRowHeight(28);

                $rangoCab = 'A'.$this->filaCabecerasExcel.':'.$col.$this->filaCabecerasExcel;
                $sheet->getStyle($rangoCab)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('85C1E9');
                $sheet->getStyle($rangoCab)->getFont()->setName('Arial')->setSize(11)->setBold(true)->getColor()->setRGB('17202A');
                $sheet->getStyle($rangoCab)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                // Congela encabezado (filas superiores) + columnas ID y Ticket.
                $sheet->freezePane(self::COL_FREEZE.$this->filaPrimeraDatosExcel);
            },
        ];
    }

    /**
     * XLSX usa la preferencia global (auto adapta a la PC); CSV cae al respaldo
     * de config('export.csv_fallback').
     */
    private function formatoNumeroEfectivo(): string
    {
        $global = ExcelFormatoNumero::preferenciaGlobal();

        return $this->esCsv ? ExcelFormatoNumero::paraCsv($global) : $global;
    }
}

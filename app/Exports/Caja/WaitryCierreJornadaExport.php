<?php

namespace App\Exports\Caja;

use App\Support\Configuracion\EmpresaLogoArchivo;
use App\Support\Export\ExcelFormatoNumero;
use Illuminate\Contracts\View\View;
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

class WaitryCierreJornadaExport implements FromView, WithColumnFormatting, WithColumnWidths, WithEvents, WithStyles, WithTitle
{
    private const COL_ULTIMA = 'L';

    /** Congela también Orden Waitry y Ref. (columnas A y B): el freeze arranca en C. */
    private const COL_FREEZE = 'C';

    private bool $hayFilaLogos = false;

    private int $filaTituloExcel = 1;

    private int $filaSubtituloExcel = 2;

    private int $filaCabecerasExcel = 3;

    private int $filaPrimeraDatosExcel = 4;

    private ?string $rutaLogoExcel = null;

    /**
     * @param  list<array<string, mixed>>  $filas
     * @param  array<string, mixed>  $resumen
     */
    public function __construct(
        private array $filas,
        private array $resumen,
        private string $titulo,
        private string $empresaNombre = '',
        private bool $esCsv = false,
    ) {
    }

    public function view(): View
    {
        $this->rutaLogoExcel = EmpresaLogoArchivo::rutaResuelta($this->empresaNombre);
        $this->hayFilaLogos = is_string($this->rutaLogoExcel) && is_readable($this->rutaLogoExcel);
        $this->filaTituloExcel = $this->hayFilaLogos ? 2 : 1;
        $this->filaSubtituloExcel = $this->filaTituloExcel + 1;
        $this->filaCabecerasExcel = $this->filaSubtituloExcel + 1;
        $this->filaPrimeraDatosExcel = $this->filaCabecerasExcel + 1;

        return view('caja.waitry_cierre_jornada.listado', [
            'filas' => $this->filas,
            'resumen' => $this->resumen,
            'titulo' => $this->titulo,
            'empresaNombre' => $this->empresaNombre,
            'payload' => null,
            'esExcel' => true,
            'reservarFilaLogoExcel' => $this->hayFilaLogos,
            'formatoNumero' => $this->formatoNumeroEfectivo(),
        ]);
    }

    public function title(): string
    {
        return 'Cierre jornada Waitry';
    }

    public function columnWidths(): array
    {
        return [
            'A' => 14,
            'B' => 14,
            'C' => 18,
            'D' => 15,
            'E' => 10,
            'F' => 14,
            'G' => 15,
            'H' => 14,
            'I' => 18,
            'J' => 18,
            'K' => 14,
            'L' => 14,
        ];
    }

    /**
     * Importe Waitry (D), Total Anita (G) y Diferencia (K) con máscara neutra.
     *
     * @return array<string, string>
     */
    public function columnFormats(): array
    {
        $codigo = ExcelFormatoNumero::codigoColumna(ExcelFormatoNumero::preferenciaGlobal(), 2);

        return [
            'D' => $codigo,
            'G' => $codigo,
            'K' => $codigo,
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

                if ($this->hayFilaLogos && is_string($this->rutaLogoExcel)) {
                    $sheet->getRowDimension(1)->setRowHeight(54);
                    $drawing = new Drawing;
                    $drawing->setName('Logo');
                    $drawing->setDescription('Logo empresa');
                    $drawing->setPath($this->rutaLogoExcel);
                    $drawing->setResizeProportional(true);
                    $drawing->setHeight(46);
                    $drawing->setCoordinates('A1');
                    $drawing->setOffsetX(6);
                    $drawing->setOffsetY(4);
                    $drawing->setWorksheet($sheet);
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

                $sheet->freezePane(self::COL_FREEZE.$this->filaPrimeraDatosExcel);
            },
        ];
    }

    private function formatoNumeroEfectivo(): string
    {
        $global = ExcelFormatoNumero::preferenciaGlobal();

        return $this->esCsv ? ExcelFormatoNumero::paraCsv($global) : $global;
    }
}

<?php

namespace App\Exports\Caja\Flash;

use App\Support\Configuracion\EmpresaLogoArchivo;
use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class FlashCajaHistoricoDiarioExport implements FromView, ShouldAutoSize, WithEvents, WithStyles, WithTitle
{
    use Exportable;

    private const COL_ULTIMA = 'K';

    /** @var array<string, mixed> */
    private array $reporte;

    /** @var list<string> */
    private array $rutasLogosExcel = [];

    private int $filaCabecerasExcel = 4;

    private int $filaPrimeraDatosExcel = 5;

    /**
     * @param  array<string, mixed>  $reporte
     */
    public function __construct(array $reporte)
    {
        $this->reporte = $reporte;
    }

    public function view(): View
    {
        $empresa = $this->reporte['empresa'] ?? null;
        if ($empresa !== null) {
            $filaLogo = (object) ['nombreempresa' => $empresa->nombre ?? ''];
            $this->rutasLogosExcel = EmpresaLogoArchivo::rutasLogosCabeceraDesdeColeccion(collect([$filaLogo]));
        }

        $this->filaCabecerasExcel = count($this->rutasLogosExcel) > 0 ? 4 : 3;
        $this->filaPrimeraDatosExcel = $this->filaCabecerasExcel + 1;

        return view('exports.caja.flash.reporte_historico_diario', [
            'reporte' => $this->reporte,
            'reservarFilaLogoExcel' => count($this->rutasLogosExcel) > 0,
        ]);
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            $this->filaCabecerasExcel => [
                'font' => ['bold' => true, 'color' => ['rgb' => '17202A'], 'size' => 11],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => '85C1E9']],
            ],
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                if (count($this->rutasLogosExcel) > 0) {
                    $sheet->getRowDimension(1)->setRowHeight(54);
                    foreach ($this->rutasLogosExcel as $idx => $ruta) {
                        if (! is_readable($ruta)) {
                            continue;
                        }
                        $drawing = new Drawing;
                        $drawing->setPath($ruta);
                        $drawing->setHeight(46);
                        $drawing->setCoordinates('A1');
                        $drawing->setOffsetX(6 + $idx * 160);
                        $drawing->setWorksheet($sheet);
                    }
                }
                $filaTit = count($this->rutasLogosExcel) > 0 ? 2 : 1;
                $sheet->mergeCells('A'.$filaTit.':'.self::COL_ULTIMA.$filaTit);
                $sheet->freezePane('A'.$this->filaPrimeraDatosExcel);
            },
        ];
    }

    public function title(): string
    {
        return 'Detalle diario';
    }
}

<?php

namespace App\Exports\Stock;

use App\Support\Configuracion\EmpresaLogoArchivo;
use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;

class ContaduriaInventarioHojaExport implements FromView, ShouldAutoSize, WithEvents, WithTitle
{
    private bool $hayFilaLogo = false;

    /** @var list<string> */
    private array $rutasLogos = [];

    /**
     * @param  array<string, mixed>  $datos
     */
    public function __construct(
        private readonly string $tituloHoja,
        private readonly string $modo,
        private readonly array $datos,
        private readonly string $titulo,
        private readonly string $subtitulo,
        private readonly string $empresaNombre,
        private readonly string $origen = '',
    ) {}

    public function view(): View
    {
        $coleccionLogo = $this->empresaNombre !== ''
            ? collect([(object) ['nombreempresa' => $this->empresaNombre]])
            : collect();
        $this->rutasLogos = EmpresaLogoArchivo::rutasLogosCabeceraDesdeColeccion($coleccionLogo);
        $this->hayFilaLogo = $this->rutasLogos !== [];

        return view('exports.stock.contaduria_inventario_hoja', [
            'modo' => $this->modo,
            'datos' => $this->datos,
            'titulo' => $this->titulo,
            'titulo_solapa' => $this->tituloHoja,
            'subtitulo' => $this->subtitulo,
            'origen' => $this->origen,
            'reservarFilaLogoExcel' => $this->hayFilaLogo,
        ]);
    }

    public function title(): string
    {
        return $this->tituloHoja;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $ultima = $sheet->getHighestColumn();
                $filasMeta = $this->origen !== '' ? 6 : 5;
                $filaCabecera = ($this->hayFilaLogo ? 1 : 0) + $filasMeta;

                if ($this->origen !== '') {
                    $filaOrigen = $filaCabecera - 1;
                    $sheet->getStyle('A'.$filaOrigen.':'.$ultima.$filaOrigen)
                        ->getAlignment()
                        ->setWrapText(true);
                    $sheet->getRowDimension($filaOrigen)->setRowHeight(32);
                }

                if ($this->hayFilaLogo) {
                    $sheet->getRowDimension(1)->setRowHeight(54);
                    foreach ($this->rutasLogos as $idx => $ruta) {
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
                        $drawing->setOffsetX(6 + $idx * 160);
                        $drawing->setOffsetY(4);
                        $drawing->setWorksheet($sheet);
                    }
                }

                $sheet->getStyle('A'.$filaCabecera.':'.$ultima.$filaCabecera)->applyFromArray([
                    'font' => ['bold' => true, 'name' => 'Arial', 'color' => ['rgb' => '17202A']],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'color' => ['rgb' => '85C1E9'],
                    ],
                ]);
                $sheet->freezePane('A'.($filaCabecera + 1));

                $filaFin = (int) $sheet->getHighestRow();
                $filaDatos = $filaCabecera + 1;
                if ($filaFin < $filaDatos) {
                    return;
                }

                if ($this->modo === 'detalle') {
                    $sheet->getStyle('F'.$filaDatos.':F'.$filaFin)
                        ->getNumberFormat()
                        ->setFormatCode('#,##0.00');
                    $sheet->getStyle('G'.$filaDatos.':H'.$filaFin)
                        ->getNumberFormat()
                        ->setFormatCode(NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);

                    return;
                }

                $sheet->getStyle('B'.$filaDatos.':'.$ultima.$filaFin)
                    ->getNumberFormat()
                    ->setFormatCode(NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);
            },
        ];
    }
}

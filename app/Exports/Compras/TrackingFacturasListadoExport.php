<?php

namespace App\Exports\Compras;

use App\Repositories\Compras\TrackingFacturasRepository;
use App\Support\Configuracion\EmpresaLogoArchivo;
use Illuminate\Contracts\View\View;
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
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class TrackingFacturasListadoExport implements FromView, ShouldAutoSize, WithColumnWidths, WithEvents, WithStyles, WithTitle
{
    use Exportable;

    private const COL_ULTIMA = 'R';

    /** @var array<string, mixed> */
    private array $filtros = [];

    private bool $desdeIndex = false;

    private bool $hayFilaLogos = false;

    private int $filaTitulo = 1;

    private int $filaCabeceras = 2;

    private int $filaPrimeraDatos = 3;

    /** @var list<string> */
    private array $rutasLogos = [];

    public function __construct(
        private readonly TrackingFacturasRepository $repository,
    ) {}

    /**
     * @param  array<string, mixed>  $filtros
     */
    public function parametros(array $filtros): self
    {
        $this->filtros = $filtros;
        $this->desdeIndex = true;

        return $this;
    }

    public function view(): View
    {
        if (! $this->desdeIndex) {
            $this->reiniciarFilas(false);

            return view('exports.compras.trackingfacturasindex', [
                'datas' => collect(),
                'resumen' => null,
                'filtros' => [],
                'reservarFilaLogoExcel' => false,
            ]);
        }

        $datas = $this->repository->leeTrackingFacturas($this->filtros, false);

        $this->rutasLogos = EmpresaLogoArchivo::rutasLogosCabeceraDesdeColeccion($datas);
        $this->reiniciarFilas(count($this->rutasLogos) > 0);

        return view('exports.compras.trackingfacturasindex', [
            'datas' => $datas,
            'resumen' => $this->repository->resumen($this->filtros),
            'filtros' => $this->filtros,
            'reservarFilaLogoExcel' => $this->hayFilaLogos,
        ]);
    }

    public function styles(Worksheet $sheet)
    {
        if (! $this->desdeIndex) {
            return [];
        }

        return [
            $this->filaCabeceras => [
                'font' => ['bold' => true, 'color' => ['rgb' => '17202A'], 'size' => 11, 'name' => 'Arial'],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => '85C1E9']],
            ],
        ];
    }

    public function columnWidths(): array
    {
        if (! $this->desdeIndex) {
            return [];
        }

        return [
            'A' => 9,   // ID
            'B' => 24,  // Empresa
            'C' => 34,  // Proveedor
            'D' => 14,  // CUIT
            'E' => 12,  // Tipo
            'F' => 20,  // Comprobante
            'G' => 13,  // Fecha comprobante
            'H' => 13,  // Fecha de carga
            'I' => 16,  // Origen de la fecha
            'J' => 15,  // Fecha contabilización
            'K' => 11,  // Asiento
            'L' => 11,  // OC
            'M' => 16,  // Importe
            'N' => 16,  // Saldo
            'O' => 16,  // Estado
            'P' => 16,  // Pago
            'Q' => 20,  // Orden de pago
            'R' => 15,  // PDF
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                if (! $this->desdeIndex) {
                    return;
                }

                $sheet = $event->sheet->getDelegate();
                $this->dibujarLogos($sheet);
                $this->estilarTitulo($sheet);

                $sheet->freezePane('A'.$this->filaPrimeraDatos);
            },
        ];
    }

    public function title(): string
    {
        return 'Tracking de facturas';
    }

    private function reiniciarFilas(bool $conLogos): void
    {
        $this->hayFilaLogos = $conLogos;
        $this->filaTitulo = $conLogos ? 2 : 1;
        $this->filaCabeceras = $conLogos ? 3 : 2;
        $this->filaPrimeraDatos = $this->filaCabeceras + 1;
    }

    private function dibujarLogos(Worksheet $sheet): void
    {
        if (! $this->hayFilaLogos) {
            return;
        }

        $sheet->getRowDimension(1)->setRowHeight(54);
        foreach ($this->rutasLogos as $indice => $ruta) {
            if (! is_readable($ruta)) {
                continue;
            }

            $drawing = new Drawing;
            $drawing->setName('Logo');
            $drawing->setDescription('Logo empresa');
            $drawing->setPath($ruta);
            $drawing->setResizeProportional(true);
            $drawing->setHeight(46);
            $drawing->setCoordinates('A1');
            $drawing->setOffsetX(6 + $indice * 160);
            $drawing->setOffsetY(4);
            $drawing->setWorksheet($sheet);
        }
    }

    private function estilarTitulo(Worksheet $sheet): void
    {
        $fila = $this->filaTitulo;
        $rango = 'A'.$fila.':'.self::COL_ULTIMA.$fila;

        $sheet->mergeCells($rango);
        $sheet->getRowDimension($fila)->setRowHeight(30);
        $sheet->getStyle($rango)->applyFromArray([
            'font' => ['bold' => true, 'size' => 16, 'name' => 'Arial', 'color' => ['rgb' => '17202A']],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_LEFT,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ]);
    }
}

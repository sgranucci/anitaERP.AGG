<?php

namespace App\Exports\Compras;

use App\Repositories\Compras\Comprobante_ProveedorRepositoryInterface;
use App\Support\Configuracion\EmpresaLogoArchivo;
use App\Support\Export\ExcelFormatoNumero;
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

class ComprobanteProveedorListadoExport implements FromView, ShouldAutoSize, WithColumnFormatting, WithColumnWidths, WithEvents, WithStyles, WithTitle
{
    use Exportable;

    private const COL_ULTIMA = 'K';

    /** Congela también ID y Empresa (columnas A y B): el freeze arranca en C. */
    private const COL_FREEZE = 'C';

    private ?array $filtros = null;

    private bool $flDesdeIndex = false;

    private bool $esCsv = false;

    private bool $hayFilaLogos = false;

    private int $filaCabecerasExcel = 3;

    private int $filaPrimeraDatosExcel = 4;

    private int $filaTituloExcel = 1;

    private int $filaSubtituloExcel = 2;

    /** @var list<string> */
    private array $rutasLogosExcel = [];

    public function __construct(
        private readonly Comprobante_ProveedorRepositoryInterface $comprobanteRepository,
    ) {}

    public function view(): View
    {
        if ($this->flDesdeIndex) {
            $datas = $this->comprobanteRepository->leeComprobanteProveedor($this->filtros ?? [], false);
            self::enriquecerNombreEmpresa($datas);

            $this->rutasLogosExcel = EmpresaLogoArchivo::rutasLogosCabeceraDesdeColeccion($datas);
            $this->hayFilaLogos = count($this->rutasLogosExcel) > 0;
            $this->filaTituloExcel = $this->hayFilaLogos ? 2 : 1;
            $this->filaSubtituloExcel = $this->filaTituloExcel + 1;
            $this->filaCabecerasExcel = $this->filaSubtituloExcel + 1;
            $this->filaPrimeraDatosExcel = $this->filaCabecerasExcel + 1;

            return view('exports.compras.comprobante_proveedorindex', [
                'datas' => $datas,
                'filtros' => $this->filtros ?? [],
                'esExcel' => true,
                'reservarFilaLogoExcel' => $this->hayFilaLogos,
                'formatoNumero' => $this->formatoNumeroEfectivo(),
            ]);
        }

        $this->hayFilaLogos = false;
        $this->filaTituloExcel = 1;
        $this->filaSubtituloExcel = 2;
        $this->filaCabecerasExcel = 3;
        $this->filaPrimeraDatosExcel = 4;
        $this->rutasLogosExcel = [];

        return view('exports.compras.comprobante_proveedorindex', [
            'datas' => collect(),
            'esExcel' => true,
            'reservarFilaLogoExcel' => false,
            'formatoNumero' => $this->formatoNumeroEfectivo(),
        ]);
    }

    public function columnFormats(): array
    {
        if (! $this->flDesdeIndex) {
            return [];
        }

        return [
            'A' => NumberFormat::FORMAT_TEXT,
            'E' => NumberFormat::FORMAT_TEXT,
            'F' => NumberFormat::FORMAT_TEXT,
            // H = Total: número real con máscara neutra (sumable/adaptable).
            'H' => ExcelFormatoNumero::codigoColumna(ExcelFormatoNumero::preferenciaGlobal(), 2),
        ];
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

        return [
            'A' => 8,
            'B' => 22,
            'C' => 28,
            'D' => 18,
            'E' => 18,
            'F' => 12,
            'G' => 12,
            'H' => 12,
            'I' => 16,
            'J' => 16,
            'K' => 22,
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                if (! $this->flDesdeIndex) {
                    return;
                }

                $sheet = $event->sheet->getDelegate();
                $ult = self::COL_ULTIMA;

                if ($this->hayFilaLogos && count($this->rutasLogosExcel) > 0) {
                    $sheet->getRowDimension(1)->setRowHeight(54);
                    $offsetX = 6;
                    foreach ($this->rutasLogosExcel as $ruta) {
                        if (! is_string($ruta) || ! is_readable($ruta)) {
                            continue;
                        }
                        $drawing = new Drawing();
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

                $filaTit = $this->filaTituloExcel;
                $sheet->mergeCells('A'.$filaTit.':'.$ult.$filaTit);
                $sheet->getRowDimension($filaTit)->setRowHeight(28);
                $sheet->getStyle('A'.$filaTit)->getFont()->setName('Arial')->setSize(16)->setBold(true)->getColor()->setRGB('17202A');

                $filaSub = $this->filaSubtituloExcel;
                $sheet->mergeCells('A'.$filaSub.':'.$ult.$filaSub);
                $sheet->getStyle('A'.$filaSub)->getFont()->setName('Arial')->setSize(10)->setBold(true)->getColor()->setRGB('444444');

                $sheet->freezePane(self::COL_FREEZE.$this->filaPrimeraDatosExcel);
            },
        ];
    }

    public function title(): string
    {
        return 'Comprobantes proveedor';
    }

    /**
     * @param  array|string|null  $filtros
     */
    public function parametros($filtros, bool $esCsv = false): self
    {
        if (is_string($filtros)) {
            $texto = trim($filtros);
            $filtros = array_merge(\App\Support\Compras\ComprobanteProveedorListadoFiltros::filtrosVacios(), [
                'valor' => $texto,
                'busqueda' => $texto,
                'empresa_scope' => 'todas',
            ]);
        }

        $this->filtros = is_array($filtros) ? $filtros : [];
        $this->esCsv = $esCsv;
        $this->flDesdeIndex = true;

        return $this;
    }

    private function formatoNumeroEfectivo(): string
    {
        $global = ExcelFormatoNumero::preferenciaGlobal();

        return $this->esCsv ? ExcelFormatoNumero::paraCsv($global) : $global;
    }

    /** @param \Illuminate\Support\Collection|\Illuminate\Database\Eloquent\Collection $datas */
    private static function enriquecerNombreEmpresa($datas): void
    {
        foreach ($datas as $row) {
            $row->nombreempresa = $row->empresas->nombre ?? '';
        }
    }
}

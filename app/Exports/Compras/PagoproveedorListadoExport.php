<?php

namespace App\Exports\Compras;

use App\Repositories\Compras\PagoproveedorRepositoryInterface;
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

class PagoproveedorListadoExport implements FromView, ShouldAutoSize, WithColumnFormatting, WithColumnWidths, WithEvents, WithStyles, WithTitle
{
    use Exportable;

    private const COL_ULTIMA = 'I';

    private const COL_MONTO = 'F';

    private int $columnasCalculadas = 0;

    private PagoproveedorRepositoryInterface $pagoproveedorRepository;

    /** @var array<string, mixed>|null */
    private $filtros;

    private bool $flDesdeIndex = false;

    private bool $esCsv = false;

    private bool $hayFilaLogos = false;

    private int $filaCabecerasExcel = 2;

    private int $filaPrimeraDatosExcel = 3;

    private int $filaTituloExcel = 1;

    /** @var list<string> */
    private array $rutasLogosExcel = [];

    public function __construct(PagoproveedorRepositoryInterface $pagoproveedorRepository)
    {
        $this->pagoproveedorRepository = $pagoproveedorRepository;
    }

    public function parametros(array $filtros, bool $esCsv = false): self
    {
        $this->filtros = $filtros;
        $this->flDesdeIndex = true;
        $this->esCsv = $esCsv;

        return $this;
    }

    public function view(): View
    {
        $datas = $this->pagoproveedorRepository->leePagoproveedor($this->filtros ?? [], false);

        $this->rutasLogosExcel = EmpresaLogoArchivo::rutasLogosCabeceraDesdeColeccion($datas);
        $this->hayFilaLogos = count($this->rutasLogosExcel) > 0;
        $this->filaTituloExcel = $this->hayFilaLogos ? 2 : 1;
        $this->filaCabecerasExcel = $this->hayFilaLogos ? 3 : 2;
        $this->filaPrimeraDatosExcel = $this->filaCabecerasExcel + 1;

        $this->columnasCalculadas = $this->cantidadCalculadas();

        return view('exports.compras.pagoproveedorindex', [
            'datas' => $datas,
            'reservarFilaLogoExcel' => $this->hayFilaLogos,
            'esExcel' => true,
            'formatoNumero' => $this->formatoNumeroEfectivo(),
            'calculadas' => array_values(is_array($this->filtros['calculadas'] ?? null) ? $this->filtros['calculadas'] : []),
        ]);
    }

    public function columnFormats(): array
    {
        $cols = [];
        foreach (range('A', $this->colUltima()) as $c) {
            $cols[$c] = NumberFormat::FORMAT_TEXT;
        }
        $cols[self::COL_MONTO] = ExcelFormatoNumero::codigoColumna(ExcelFormatoNumero::preferenciaGlobal(), 2);

        return $cols;
    }

    public function styles(Worksheet $sheet)
    {
        return [
            $this->filaCabecerasExcel => [
                'font' => ['bold' => true, 'color' => ['rgb' => '17202A'], 'name' => 'Arial', 'size' => 11],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '85C1E9'],
                ],
            ],
        ];
    }

    public function columnWidths(): array
    {
        $anchos = [
            'A' => 12, 'B' => 18, 'C' => 22, 'D' => 28, 'E' => 36, 'F' => 14, 'G' => 14, 'H' => 40, 'I' => 12,
        ];
        for ($i = 0; $i < $this->cantidadCalculadas(); $i++) {
            $anchos[chr(ord('J') + $i)] = 22;
        }

        return $anchos;
    }

    public function title(): string
    {
        return 'Pagos proveedores';
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                if ($this->hayFilaLogos) {
                    $sheet->getRowDimension(1)->setRowHeight(54);
                    $offsetX = 0;
                    foreach ($this->rutasLogosExcel as $ruta) {
                        if (! is_file($ruta)) {
                            continue;
                        }
                        $drawing = new Drawing();
                        $drawing->setPath($ruta);
                        $drawing->setHeight(48);
                        $drawing->setCoordinates('A1');
                        $drawing->setOffsetX($offsetX);
                        $drawing->setWorksheet($sheet);
                        $offsetX += 120;
                    }
                }
                $sheet->mergeCells('A'.$this->filaTituloExcel.':'.$this->colUltima().$this->filaTituloExcel);
                $sheet->getStyle('A'.$this->filaTituloExcel)->getFont()->setName('Arial')->setSize(16)->setBold(true);
                $sheet->getStyle('A'.$this->filaTituloExcel)->getFont()->getColor()->setRGB('17202A');
                $sheet->getStyle('A'.$this->filaTituloExcel)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
                $ultimaFila = max($this->filaPrimeraDatosExcel, (int) $sheet->getHighestRow());
                $sheet->getStyle(self::COL_MONTO.$this->filaCabecerasExcel.':'.self::COL_MONTO.$ultimaFila)
                    ->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                $sheet->freezePane('A'.$this->filaPrimeraDatosExcel);
            },
        ];
    }

    private function formatoNumeroEfectivo(): string
    {
        $global = ExcelFormatoNumero::preferenciaGlobal();

        return $this->esCsv ? ExcelFormatoNumero::paraCsv($global) : $global;
    }

    private function cantidadCalculadas(): int
    {
        $n = 0;
        $filas = $this->filtros['calculadas'] ?? [];
        if (! is_array($filas)) {
            return 0;
        }
        foreach ($filas as $fila) {
            if (is_array($fila) && trim((string) ($fila['etiqueta'] ?? '')) !== '') {
                $n++;
            }
        }

        return min(2, $n);
    }

    private function colUltima(): string
    {
        return chr(ord('I') + $this->cantidadCalculadas());
    }
}

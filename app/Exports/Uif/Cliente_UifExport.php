<?php

namespace App\Exports\Uif;

use App\Repositories\Uif\Cliente_UifRepositoryInterface;
use App\Support\Configuracion\EmpresaLogoArchivo;
use App\Support\Uif\ClienteUifListadoFiltros;
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
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class Cliente_UifExport implements FromView, ShouldAutoSize, WithColumnFormatting, WithColumnWidths, WithEvents, WithStyles, WithTitle
{
    use Exportable;

    private const COL_ULTIMA = 'K';

    /** Congela ID, Origen y Nombre (A–C). */
    private const COL_FREEZE = 'D';

    private Cliente_UifRepositoryInterface $cliente_uifRepository;

    /** @var array<string, mixed>|string|null */
    private $filtros;

    private bool $flDesdeIndex = false;

    private bool $hayFilaLogos = false;

    private int $filaTituloExcel = 1;

    private int $filaGeneradoExcel = 2;

    private ?int $filaFiltrosExcel = null;

    private int $filaCabecerasExcel = 3;

    private int $filaPrimeraDatosExcel = 4;

    /** @var list<string> */
    private array $rutasLogosExcel = [];

    public function __construct(Cliente_UifRepositoryInterface $cliente_uifrepository)
    {
        $this->cliente_uifRepository = $cliente_uifrepository;
    }

    public function view(): View
    {
        $filtrosArr = is_array($this->filtros) ? $this->filtros : [];
        $subtituloFiltros = $this->flDesdeIndex
            ? ClienteUifListadoFiltros::subtituloFiltros($filtrosArr)
            : '';

        if ($this->flDesdeIndex) {
            $cliente_uifs = $this->cliente_uifRepository->leeCliente_Uif($this->filtros, false);

            $this->rutasLogosExcel = EmpresaLogoArchivo::rutasLogosCabeceraDesdeColeccion($cliente_uifs);
            $this->hayFilaLogos = count($this->rutasLogosExcel) > 0;
            $this->calcularFilasEncabezado($subtituloFiltros);

            return view('exports.uif.cliente_uifindex', [
                'cliente_uifs' => $cliente_uifs,
                'esExcel' => true,
                'reservarFilaLogoExcel' => $this->hayFilaLogos,
                'subtituloFiltros' => $subtituloFiltros,
            ]);
        }

        $this->hayFilaLogos = false;
        $this->rutasLogosExcel = [];
        $this->calcularFilasEncabezado('');

        return view('exports.uif.cliente_uifindex', [
            'cliente_uifs' => collect(),
            'esExcel' => true,
            'reservarFilaLogoExcel' => false,
            'subtituloFiltros' => '',
        ]);
    }

    private function calcularFilasEncabezado(string $subtituloFiltros): void
    {
        $offsetLogo = $this->hayFilaLogos ? 1 : 0;
        $this->filaTituloExcel = $offsetLogo + 1;
        $this->filaGeneradoExcel = $this->filaTituloExcel + 1;
        $fila = $this->filaGeneradoExcel;
        if (trim($subtituloFiltros) !== '') {
            $fila++;
            $this->filaFiltrosExcel = $fila;
        } else {
            $this->filaFiltrosExcel = null;
        }
        $this->filaCabecerasExcel = $fila + 1;
        $this->filaPrimeraDatosExcel = $this->filaCabecerasExcel + 1;
    }

    public function columnFormats(): array
    {
        if (! $this->flDesdeIndex) {
            return [];
        }

        // Todo texto: evita notación científica en DNI / teléfono / ID.
        $cols = [];
        foreach (range('A', self::COL_ULTIMA) as $c) {
            $cols[$c] = NumberFormat::FORMAT_TEXT;
        }

        return $cols;
    }

    public function styles(Worksheet $sheet)
    {
        return [];
    }

    public function columnWidths(): array
    {
        if (! $this->flDesdeIndex) {
            return [];
        }

        return [
            'A' => 10,
            'B' => 16,
            'C' => 34,
            'D' => 10,
            'E' => 16,
            'F' => 28,
            'G' => 20,
            'H' => 16,
            'I' => 14,
            'J' => 14,
            'K' => 28,
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
                $col = self::COL_ULTIMA;

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

                $sheet->mergeCells('A'.$this->filaTituloExcel.':'.$col.$this->filaTituloExcel);
                $sheet->mergeCells('A'.$this->filaGeneradoExcel.':'.$col.$this->filaGeneradoExcel);
                $sheet->getRowDimension($this->filaTituloExcel)->setRowHeight(28);
                $sheet->getStyle('A'.$this->filaTituloExcel)->getFont()->setName('Arial')->setSize(16)->setBold(true)->getColor()->setRGB('17202A');
                $sheet->getStyle('A'.$this->filaGeneradoExcel)->getFont()->setName('Arial')->setSize(10)->setBold(true)->getColor()->setRGB('444444');
                if ($this->filaFiltrosExcel !== null) {
                    $sheet->mergeCells('A'.$this->filaFiltrosExcel.':'.$col.$this->filaFiltrosExcel);
                    $sheet->getRowDimension($this->filaFiltrosExcel)->setRowHeight(36);
                    $sheet->getStyle('A'.$this->filaFiltrosExcel)->getFont()->setName('Arial')->setSize(10)->setBold(true)->getColor()->setRGB('444444');
                    $sheet->getStyle('A'.$this->filaFiltrosExcel)->getAlignment()->setWrapText(true);
                }

                $rangoCab = 'A'.$this->filaCabecerasExcel.':'.$col.$this->filaCabecerasExcel;
                $sheet->getStyle($rangoCab)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('85C1E9');
                $sheet->getStyle($rangoCab)->getFont()->setName('Arial')->setSize(11)->setBold(true)->getColor()->setRGB('17202A');
                $sheet->getStyle($rangoCab)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                $ultimaFila = max($this->filaPrimeraDatosExcel, (int) $sheet->getHighestRow());
                if ($ultimaFila >= $this->filaPrimeraDatosExcel) {
                    $sheet->getStyle('A'.$this->filaPrimeraDatosExcel.':'.$col.$ultimaFila)
                        ->getFont()->setName('Arial')->setSize(10);

                    // ID / Nº doc / Teléfono: texto explícito.
                    foreach (['A', 'E', 'J'] as $colTexto) {
                        $sheet->getStyle($colTexto.$this->filaPrimeraDatosExcel.':'.$colTexto.$ultimaFila)
                            ->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);
                        for ($row = $this->filaPrimeraDatosExcel; $row <= $ultimaFila; $row++) {
                            $cell = $sheet->getCell($colTexto.$row);
                            $raw = $cell->getValue();
                            if ($raw === null || $raw === '') {
                                continue;
                            }
                            $texto = ltrim((string) $raw, "\t'");
                            $cell->setValueExplicit($texto, DataType::TYPE_STRING);
                        }
                    }
                }

                $sheet->freezePane(self::COL_FREEZE.$this->filaPrimeraDatosExcel);
            },
        ];
    }

    public function title(): string
    {
        return 'Clientes UIF';
    }

    /**
     * @param  array<string, mixed>|string|null  $filtros
     */
    public function parametros($filtros)
    {
        $this->filtros = $filtros;
        $this->flDesdeIndex = true;

        return $this;
    }
}

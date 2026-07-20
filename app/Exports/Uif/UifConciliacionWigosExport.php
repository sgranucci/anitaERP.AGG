<?php

namespace App\Exports\Uif;

use App\Support\Configuracion\EmpresaLogoArchivo;
use App\Support\Export\ExcelFormatoNumero;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
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
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class UifConciliacionWigosExport implements FromView, ShouldAutoSize, WithColumnFormatting, WithColumnWidths, WithEvents, WithStyles, WithTitle
{
    use Exportable;

    private const COL_ULTIMA = 'G';

    /** Congela Fecha pago y Fecha emisión (A y B): freeze arranca en C. */
    private const COL_FREEZE = 'C';

    private bool $flActivo = false;

    private bool $esCsv = false;

    private string $titulo = '';

    private string $subtitulo = '';

    /** @var Collection<int, object> */
    private Collection $filas;

    private bool $hayFilaLogos = false;

    private int $filaTituloExcel = 1;

    private int $filaSubtituloExcel = 2;

    private int $filaCabecerasExcel = 3;

    private int $filaPrimeraDatosExcel = 4;

    /** @var list<string> */
    private array $rutasLogosExcel = [];

    public function __construct()
    {
        $this->filas = collect();
    }

    /**
     * @param  Collection<int, object>|iterable<int, object>  $filas
     */
    public function parametros(string $titulo, string $subtitulo, iterable $filas, bool $esCsv = false): self
    {
        $this->titulo = $titulo;
        $this->subtitulo = $subtitulo;
        $this->esCsv = $esCsv;
        $coleccion = $filas instanceof Collection ? $filas : collect($filas);
        $this->filas = $coleccion->map(function ($fila) {
            if (is_object($fila)) {
                $fila->nombreempresa = $this->subtitulo;

                return $fila;
            }

            return $fila;
        });
        $this->rutasLogosExcel = EmpresaLogoArchivo::rutasLogosCabeceraDesdeColeccion($this->filas);
        $this->hayFilaLogos = count($this->rutasLogosExcel) > 0;
        $this->filaTituloExcel = $this->hayFilaLogos ? 2 : 1;
        $this->filaSubtituloExcel = $this->filaTituloExcel + 1;
        $this->filaCabecerasExcel = $this->filaSubtituloExcel + 1;
        $this->filaPrimeraDatosExcel = $this->filaCabecerasExcel + 1;
        $this->flActivo = true;

        return $this;
    }

    public function view(): View
    {
        return view('exports.uif.conciliacion_wigos_unificadoindex', [
            'titulo' => $this->titulo,
            'subtitulo' => $this->subtitulo,
            'filas' => $this->filas,
            'reservarFilaLogoExcel' => $this->hayFilaLogos,
            'esExcel' => true,
            'formatoNumero' => $this->formatoNumeroEfectivo(),
        ]);
    }

    public function columnFormats(): array
    {
        if (! $this->flActivo) {
            return [];
        }

        // C = Monto con máscara neutra (sumable/adaptable); E = Número como texto.
        return [
            'C' => ExcelFormatoNumero::codigoColumna(ExcelFormatoNumero::preferenciaGlobal(), 2),
            'E' => NumberFormat::FORMAT_TEXT,
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [];
    }

    public function columnWidths(): array
    {
        if (! $this->flActivo) {
            return [];
        }

        return [
            'A' => 18,
            'B' => 18,
            'C' => 14,
            'D' => 12,
            'E' => 22,
            'F' => 14,
            'G' => 28,
        ];
    }

    public function title(): string
    {
        return 'Unificado';
    }

    public function registerEvents(): array
    {
        if (! $this->flActivo) {
            return [];
        }

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
                $sheet->getRowDimension($this->filaTituloExcel)->setRowHeight(28);
                $sheet->getStyle('A'.$this->filaTituloExcel)->getFont()->setName('Arial')->setSize(16)->setBold(true)->getColor()->setRGB('17202A');
                $sheet->getStyle('A'.$this->filaTituloExcel)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle('A'.$this->filaSubtituloExcel)->getFont()->setName('Arial')->setSize(10)->setBold(true)->getColor()->setRGB('444444');
                $sheet->getStyle('A'.$this->filaSubtituloExcel)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

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

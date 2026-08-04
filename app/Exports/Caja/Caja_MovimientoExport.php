<?php

namespace App\Exports\Caja;

use App\Queries\Caja\Caja_MovimientoQueryInterface;
use App\Support\Configuracion\EmpresaLogoArchivo;
use App\Support\Export\ExcelFormatoNumero;
use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromView;
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

class Caja_MovimientoExport implements FromView, WithColumnFormatting, WithColumnWidths, WithEvents, WithStyles, WithTitle
{
    /** Congela también ID y Empresa (columnas A y B): el freeze arranca en C. */
    private const COL_FREEZE = 'C';

    private $caja_movimientoQuery;

    /** @var array<string, mixed>|string|null */
    private $filtros = [];

    private bool $esCsv = false;

    private bool $esIguassu = false;

    private bool $hayFilaLogos = false;

    private int $filaTituloExcel = 1;

    private int $filaSubtituloExcel = 2;

    private int $filaCabecerasExcel = 3;

    private int $filaPrimeraDatosExcel = 4;

    /** @var list<string> */
    private array $rutasLogosExcel = [];

    public function __construct(Caja_MovimientoQueryInterface $caja_movimientoquery)
    {
        $this->caja_movimientoQuery = $caja_movimientoquery;
    }

    public function view(): View
    {
        $caja_movimientos = $this->caja_movimientoQuery->leeCaja_Movimiento($this->filtros, 0, false);
        $this->esIguassu = config('app.empresa') === 'Iguassu Travel';

        $this->rutasLogosExcel = EmpresaLogoArchivo::rutasLogosCabeceraDesdeColeccion($caja_movimientos);
        $this->hayFilaLogos = count($this->rutasLogosExcel) > 0;
        $this->filaTituloExcel = $this->hayFilaLogos ? 2 : 1;
        $this->filaSubtituloExcel = $this->filaTituloExcel + 1;
        $this->filaCabecerasExcel = $this->filaSubtituloExcel + 1;
        $this->filaPrimeraDatosExcel = $this->filaCabecerasExcel + 1;

        return view('exports.caja.ingresoegresoindex', [
            'caja_movimiento' => $caja_movimientos,
            'esExcel' => true,
            'reservarFilaLogoExcel' => $this->hayFilaLogos,
            'formatoNumero' => $this->formatoNumeroEfectivo(),
        ]);
    }

    public function title(): string
    {
        return 'Reporte de ingresoegresos';
    }

    /** Columna del monto: I en Iguassu (col. extra Orden de servicio), H en el resto. */
    private function columnaMonto(): string
    {
        return (config('app.empresa') === 'Iguassu Travel') ? 'I' : 'H';
    }

    /** Última columna (Movimientos): J en Iguassu, I en el resto. */
    private function columnaUltima(): string
    {
        return (config('app.empresa') === 'Iguassu Travel') ? 'J' : 'I';
    }

    public function columnWidths(): array
    {
        $anchos = [
            'A' => 8,
            'B' => 22,
            'C' => 12,
            'D' => 12,
            'E' => 20,
            'F' => 20,
            'G' => 24,
        ];
        $anchos[$this->columnaMonto()] = 16;
        $anchos[$this->columnaUltima()] = 40;

        return $anchos;
    }

    /**
     * ID y Número como texto; monto con máscara neutra adaptable.
     *
     * @return array<string, string>
     */
    public function columnFormats(): array
    {
        return [
            'A' => NumberFormat::FORMAT_TEXT,
            'C' => NumberFormat::FORMAT_TEXT,
            $this->columnaMonto() => ExcelFormatoNumero::codigoColumna(ExcelFormatoNumero::preferenciaGlobal(), 2),
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
                $col = $this->columnaUltima();

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

                $sheet->freezePane(self::COL_FREEZE.$this->filaPrimeraDatosExcel);
            },
        ];
    }

    /**
     * @param  array<string, mixed>|string|null  $filtros
     */
    public function parametros($filtros, bool $esCsv = false, $empresaId = 0)
    {
        $this->filtros = $filtros;
        $this->esCsv = $esCsv;
        // Compatibilidad: tercera arg empresa cuando $filtros es string legacy.
        if (! is_array($filtros) && (int) $empresaId > 0) {
            $this->filtros = [
                'valor' => is_string($filtros) ? $filtros : '',
                'busqueda' => is_string($filtros) ? $filtros : '',
                'modo' => 'todos',
                'operador' => 'contiene',
                'empresa_id' => (int) $empresaId,
                'empresa_scope' => 'una',
                'fecha_desde' => '',
                'fecha_hasta' => '',
            ];
        }

        return $this;
    }

    private function formatoNumeroEfectivo(): string
    {
        $global = ExcelFormatoNumero::preferenciaGlobal();

        return $this->esCsv ? ExcelFormatoNumero::paraCsv($global) : $global;
    }
}

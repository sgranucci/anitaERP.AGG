<?php

namespace App\Exports\Ventas;

use App\Services\Ventas\FacturacionLocal\FacturacionLocalCostosLocalReporteService;
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
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class FacturacionLocalCostosLocalReporteExport implements FromView, WithColumnFormatting, WithColumnWidths, WithEvents, WithStyles, WithTitle
{
    use Exportable;

    private const COL_ULTIMA = 'H';

    /** @var array<string, mixed> */
    private array $filtros = [];

    private string $titulo = 'Reporte de costos del local';

    private string $subtitulo = '';

    private bool $esCsv = false;

    private bool $hayFilaLogos = false;

    private int $filaCabecerasExcel = 4;

    private int $filaPrimeraDatosExcel = 5;

    private int $filaTituloExcel = 2;

    /** @var list<string> */
    private array $rutasLogosExcel = [];

    /** @var array<string, mixed> */
    private array $resultado = [];

    public function __construct(
        private readonly FacturacionLocalCostosLocalReporteService $reporteService,
    ) {}

    /**
     * @param  array<string, mixed>  $filtros
     */
    public function parametros(array $filtros, string $titulo, string $subtitulo, bool $esCsv = false): self
    {
        $this->filtros = $filtros;
        $this->titulo = $titulo;
        $this->subtitulo = $subtitulo;
        $this->esCsv = $esCsv;

        return $this;
    }

    public function view(): View
    {
        $this->resultado = $this->reporteService->generar($this->filtros);
        $coleccionLogo = collect([(object) ['nombreempresa' => '']]);
        $this->rutasLogosExcel = EmpresaLogoArchivo::rutasLogosCabeceraDesdeColeccion($coleccionLogo);
        $this->hayFilaLogos = count($this->rutasLogosExcel) > 0;

        $filasMeta = 2;
        if (trim($this->subtitulo) !== '') {
            $filasMeta++;
        }
        if ((int) ($this->resultado['sin_precio'] ?? 0) > 0) {
            $filasMeta++;
        }

        $this->filaTituloExcel = $this->hayFilaLogos ? 2 : 1;
        $this->filaCabecerasExcel = ($this->hayFilaLogos ? 1 : 0) + $filasMeta + 1;
        $this->filaPrimeraDatosExcel = $this->filaCabecerasExcel + 1;

        return view('exports.ventas.facturacion_local_costos_reporteindex', [
            'resultado' => $this->resultado,
            'titulo' => $this->titulo,
            'subtitulo' => $this->subtitulo,
            'reservarFilaLogoExcel' => $this->hayFilaLogos,
            'esExcel' => true,
            'formatoNumero' => $this->formatoNumeroEfectivo(),
            'mostrar_aviso' => (int) ($this->resultado['sin_precio'] ?? 0) > 0,
        ]);
    }

    public function columnFormats(): array
    {
        $importe = ExcelFormatoNumero::codigoColumna($this->formatoNumeroEfectivo(), 2);

        return [
            'A' => NumberFormat::FORMAT_TEXT,
            'B' => NumberFormat::FORMAT_TEXT,
            'C' => NumberFormat::FORMAT_TEXT,
            'D' => NumberFormat::FORMAT_TEXT,
            'E' => NumberFormat::FORMAT_TEXT,
            'F' => $importe,
            'G' => $importe,
            'H' => NumberFormat::FORMAT_TEXT,
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 16,
            'B' => 42,
            'C' => 28,
            'D' => 18,
            'E' => 28,
            'F' => 16,
            'G' => 16,
            'H' => 16,
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            $this->filaCabecerasExcel => [
                'font' => [
                    'bold' => true,
                    'color' => ['rgb' => '17202A'],
                    'size' => 11,
                    'name' => 'Arial',
                ],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'color' => ['rgb' => '85C1E9'],
                ],
            ],
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $colUltima = self::COL_ULTIMA;

                if ($this->hayFilaLogos && count($this->rutasLogosExcel) > 0) {
                    $sheet->getRowDimension(1)->setRowHeight(54);
                    $offsetXp = 6;
                    foreach ($this->rutasLogosExcel as $idx => $ruta) {
                        if (! is_string($ruta) || ! is_readable($ruta)) {
                            continue;
                        }
                        $drawing = new Drawing;
                        $drawing->setPath($ruta);
                        $drawing->setResizeProportional(true);
                        $drawing->setHeight(46);
                        $drawing->setCoordinates('A1');
                        $drawing->setOffsetX($offsetXp + $idx * 160);
                        $drawing->setOffsetY(4);
                        $drawing->setWorksheet($sheet);
                    }
                }

                $filaTit = $this->filaTituloExcel;
                $sheet->mergeCells('A'.$filaTit.':'.$colUltima.$filaTit);
                $sheet->getStyle('A'.$filaTit)->applyFromArray([
                    'font' => ['bold' => true, 'size' => 16, 'name' => 'Arial', 'color' => ['rgb' => '17202A']],
                    'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
                ]);
                $sheet->getRowDimension($filaTit)->setRowHeight(28);

                $filaMeta = $filaTit + 1;
                $ultimaMeta = $this->filaCabecerasExcel - 1;
                for ($fila = $filaMeta; $fila <= $ultimaMeta; $fila++) {
                    $sheet->mergeCells('A'.$fila.':'.$colUltima.$fila);
                    $sheet->getStyle('A'.$fila)->applyFromArray([
                        'font' => ['bold' => true, 'size' => 10, 'name' => 'Arial', 'color' => ['rgb' => '444444']],
                        'alignment' => ['wrapText' => true, 'vertical' => Alignment::VERTICAL_CENTER],
                    ]);
                    $sheet->getRowDimension($fila)->setRowHeight(22);
                }

                $sheet->getStyle('A'.$this->filaCabecerasExcel.':'.$colUltima.$this->filaCabecerasExcel)->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['rgb' => '17202A'], 'size' => 11, 'name' => 'Arial'],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => '85C1E9']],
                ]);

                $filas = $this->resultado['filas'] ?? [];
                $numFilas = count($filas);
                $codigoImporte = ExcelFormatoNumero::codigoColumna($this->formatoNumeroEfectivo(), 2);
                for ($i = 0; $i < $numFilas; $i++) {
                    $filaExcel = $this->filaPrimeraDatosExcel + $i;
                    $fila = $filas[$i];
                    $sheet->setCellValueExplicit('F'.$filaExcel, (float) ($fila['precio_fabrica'] ?? 0), DataType::TYPE_NUMERIC);
                    $sheet->setCellValueExplicit('G'.$filaExcel, (float) ($fila['costo'] ?? 0), DataType::TYPE_NUMERIC);
                    if (! empty($fila['sin_precio'])) {
                        $sheet->getStyle('A'.$filaExcel.':'.$colUltima.$filaExcel)->applyFromArray([
                            'fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => 'FDEBD0']],
                        ]);
                    }
                }
                if ($numFilas > 0) {
                    $filaUltima = $this->filaPrimeraDatosExcel + $numFilas - 1;
                    $sheet->getStyle('F'.$this->filaPrimeraDatosExcel.':G'.$filaUltima)
                        ->getNumberFormat()->setFormatCode($codigoImporte);
                    $sheet->getStyle('F'.$this->filaPrimeraDatosExcel.':G'.$filaUltima)->applyFromArray([
                        'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT],
                    ]);
                }

                $sheet->getStyle('A')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);
                $sheet->freezePane('A'.$this->filaPrimeraDatosExcel);
            },
        ];
    }

    public function title(): string
    {
        return 'Costos del local';
    }

    private function formatoNumeroEfectivo(): string
    {
        $global = ExcelFormatoNumero::preferenciaGlobal();

        return $this->esCsv ? ExcelFormatoNumero::paraCsv($global) : $global;
    }
}

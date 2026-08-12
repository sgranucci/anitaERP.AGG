<?php

declare(strict_types=1);

namespace App\Exports\Contable;

use App\Services\Contable\ReporteDefinibleParidadService;
use App\Support\Configuracion\EmpresaLogoArchivo;
use App\Support\Export\ExcelFormatoNumero;
use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ReporteDefinibleParidadExport implements FromView, WithColumnFormatting, WithColumnWidths, WithEvents, WithStyles
{
    use Exportable;

    /** @var array<string, mixed> */
    private array $resultado = [];

    private bool $soloDiferencias = false;

    private bool $hayFilaLogos = false;

    private bool $esCsv = false;

    private int $filaCabecerasExcel = 4;

    private int $filaPrimeraDatosExcel = 5;

    /** @var list<string> */
    private array $rutasLogos = [];

    /**
     * @param  array<string, mixed>  $resultado
     */
    public function parametros(array $resultado, bool $soloDiferencias = false, bool $esCsv = false): self
    {
        $this->resultado = $resultado;
        $this->soloDiferencias = $soloDiferencias;
        $this->esCsv = $esCsv;

        return $this;
    }

    /**
     * Formato numérico efectivo (auto|ar|intl). El CSV no lleva máscaras: cae al respaldo
     * de config('export.csv_fallback').
     */
    private function formatoNumero(): string
    {
        $global = ExcelFormatoNumero::preferenciaGlobal();

        return $this->esCsv ? ExcelFormatoNumero::paraCsv($global) : $global;
    }

    public function view(): View
    {
        $empresas = ReporteDefinibleParidadService::coleccionEmpresasParaLogos(
            (array) ($this->resultado['parametros']['empresa_ids'] ?? [])
        );
        $this->rutasLogos = EmpresaLogoArchivo::rutasLogosCabeceraDesdeColeccion($empresas);
        $this->hayFilaLogos = $this->rutasLogos !== [];

        $filasMeta = 3; // título + generado + subtítulo
        $offset = $this->hayFilaLogos ? 1 : 0;
        $this->filaCabecerasExcel = $offset + $filasMeta + 1;
        $this->filaPrimeraDatosExcel = $this->filaCabecerasExcel + 1;

        return view('exports.contable.reporte_definible_paridadindex', [
            'resultado' => $this->resultado,
            'filas' => $this->resultado['filas'] ?? [],
            'resumen' => $this->resultado['resumen'] ?? [],
            'parametros' => $this->resultado['parametros'] ?? [],
            'reporte' => $this->resultado['reporte'] ?? null,
            'solo_diferencias' => $this->soloDiferencias,
            'reservarFilaLogoExcel' => $this->hayFilaLogos,
            'excel_formato_numero' => $this->formatoNumero(),
        ]);
    }

    public function columnWidths(): array
    {
        return [
            'A' => 10, 'B' => 46, 'C' => 18, 'D' => 18, 'E' => 18,
            'F' => 16, 'G' => 16, 'H' => 10,
        ];
    }

    public function columnFormats(): array
    {
        $formato = $this->formatoNumero();
        $importe = ExcelFormatoNumero::codigoColumna($formato, 2);

        return [
            'A' => NumberFormat::FORMAT_TEXT,
            'C' => $importe,
            'D' => $importe,
            'E' => $importe,
            'F' => $importe,
            'G' => $importe,
            'H' => ExcelFormatoNumero::esAuto($formato) ? '#,##0.00"%"' : NumberFormat::FORMAT_TEXT,
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            $this->filaCabecerasExcel => [
                'font' => ['bold' => true, 'name' => 'Arial', 'size' => 11, 'color' => ['rgb' => '17202A']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '85C1E9']],
            ],
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event): void {
                $sheet = $event->sheet->getDelegate();
                $colUltima = 'H';
                $offset = $this->hayFilaLogos ? 1 : 0;
                $filaTitulo = $offset + 1;

                if ($this->hayFilaLogos) {
                    $sheet->getRowDimension(1)->setRowHeight(54);
                    $x = 5;
                    foreach ($this->rutasLogos as $ruta) {
                        if (! is_file($ruta)) {
                            continue;
                        }
                        $drawing = new Drawing();
                        $drawing->setPath($ruta);
                        $drawing->setHeight(48);
                        $drawing->setCoordinates('A1');
                        $drawing->setOffsetX($x);
                        $drawing->setWorksheet($sheet);
                        $x += 120;
                    }
                }

                foreach (range($filaTitulo, $filaTitulo + 2) as $fila) {
                    $sheet->mergeCells('A'.$fila.':'.$colUltima.$fila);
                }
                $sheet->getStyle('A'.$filaTitulo)->getFont()->setName('Arial')->setSize(16)->setBold(true)->getColor()->setRGB('17202A');
                $sheet->getRowDimension($filaTitulo)->setRowHeight(28);
                $sheet->getStyle('A'.$filaTitulo.':A'.($filaTitulo + 2))->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_CENTER);
                $sheet->freezePane('A'.$this->filaPrimeraDatosExcel);
            },
        ];
    }
}

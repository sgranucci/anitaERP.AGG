<?php

namespace App\Exports\Contable;

use App\Models\Contable\ReporteContable;
use App\Support\Configuracion\EmpresaLogoArchivo;
use App\Support\Contable\ReporteDefinible\ReporteDefinibleLayoutResolver;
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
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\Hyperlink;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ReporteDefinibleExport implements FromView, WithColumnFormatting, WithColumnWidths, WithEvents, WithStyles, WithTitle
{
    use Exportable;

    /** @var array<string, mixed> */
    private array $filtros = [];

    /** @var array<string, mixed> */
    private array $resultado = [];

    private ?ReporteContable $reporte = null;

    private int $filaCabecerasExcel = 3;

    private int $filaPrimeraDatosExcel = 4;

    private bool $esCsv = false;

    public function parametros(array $filtros, array $resultado, ReporteContable $reporte, bool $esCsv = false): self
    {
        $this->filtros = $filtros;
        $this->resultado = $resultado;
        $this->reporte = $reporte;
        $this->esCsv = $esCsv;

        return $this;
    }

    public function view(): View
    {
        $filasLogo = collect([(object) ['nombreempresa' => '']]);
        $logos = EmpresaLogoArchivo::rutasLogosCabeceraDesdeColeccion($filasLogo);
        $hayLogos = count($logos) > 0;
        // Filas meta de la vista: [logo] + titulo1 + generado + [titulo2]
        $filasMeta = 2 + ($hayLogos ? 1 : 0) + (trim((string) ($this->reporte->titulo2 ?? '')) !== '' ? 1 : 0);
        $this->filaCabecerasExcel = $filasMeta + 1;
        $this->filaPrimeraDatosExcel = $this->filaCabecerasExcel + 1;

        return view('exports.contable.reporte_definibleindex', [
            'reporte' => $this->reporte,
            'resultado' => $this->resultado,
            'filtros' => $this->filtros,
            'hayFilaLogos' => $hayLogos,
            'rutasLogos' => $logos,
            'excel_formato_numero' => $this->formatoNumero(),
        ]);
    }

    /**
     * Formato numérico efectivo (auto|ar|intl), con default en la preferencia global.
     * El CSV no lleva máscaras de formato: cae al respaldo config('export.csv_fallback').
     */
    private function formatoNumero(): string
    {
        $formato = ExcelFormatoNumero::normalizar(
            $this->filtros['excel_formato_numero'] ?? ExcelFormatoNumero::preferenciaGlobal()
        );

        return $this->esCsv ? ExcelFormatoNumero::paraCsv($formato) : $formato;
    }

    /**
     * Columna A: códigos de línea como texto (R001, 111050009) para que Excel no los interprete.
     * Columnas de datos: máscara de importe, o de porcentaje en las columnas que lo son.
     */
    public function columnFormats(): array
    {
        $formato = $this->formatoNumero();
        $mascaraImporte = ExcelFormatoNumero::codigoColumna($formato, 2);
        $esAuto = ExcelFormatoNumero::esAuto($formato);

        $formats = ['A' => NumberFormat::FORMAT_TEXT];

        // A código | B concepto | C… columnas de datos
        $indice = 3;
        foreach ($this->resultado['columnas'] ?? [] as $col) {
            $letra = Coordinate::stringFromColumnIndex($indice);
            $formats[$letra] = $this->esPorcentaje((string) ($col['tipo'] ?? ''))
                ? ($esAuto ? '#,##0.00"%"' : NumberFormat::FORMAT_TEXT)
                : $mascaraImporte;
            $indice++;
        }

        return $formats;
    }

    private function esPorcentaje(string $tipo): bool
    {
        return in_array($tipo, [
            ReporteDefinibleLayoutResolver::TIPO_VAR_PCT,
            ReporteDefinibleLayoutResolver::TIPO_PCT_SOBRE,
        ], true);
    }

    public function title(): string
    {
        return 'Informe '.(string) ($this->reporte->codigo ?? '');
    }

    public function columnWidths(): array
    {
        $widths = ['A' => 12, 'B' => 42];
        $col = 'C';
        foreach ($this->resultado['columnas'] ?? [] as $_) {
            $widths[$col] = 14;
            $col++;
        }

        return $widths;
    }

    public function styles(Worksheet $sheet)
    {
        $fila = $this->filaCabecerasExcel;
        $lastCol = $sheet->getHighestColumn();
        $sheet->getStyle("A{$fila}:{$lastCol}{$fila}")->applyFromArray([
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '85C1E9'],
            ],
            'font' => [
                'bold' => true,
                'color' => ['rgb' => '17202A'],
                'name' => 'Arial',
                'size' => 11,
            ],
        ]);

        return [];
    }

    public function registerEvents(): array
    {
        $filaDatos = &$this->filaPrimeraDatosExcel;
        $filas = &$this->resultado;

        return [
            AfterSheet::class => function (AfterSheet $event) use (&$filaDatos, &$filas) {
                $sheet = $event->sheet->getDelegate();
                $sheet->freezePane('A'.$filaDatos);

                $row = $filaDatos;
                foreach ($filas['filas'] ?? [] as $fila) {
                    $url = $fila['drill_url'] ?? null;
                    if (is_string($url) && $url !== '') {
                        $abs = $this->absolutizarUrl($url);
                        $sheet->setHyperlink('A'.$row, new Hyperlink($abs, 'Abrir mayor plano'));
                    }
                    $row++;
                }
            },
        ];
    }

    private function absolutizarUrl(string $url): string
    {
        if (preg_match('#^https?://#i', $url)) {
            return $url;
        }
        $base = rtrim((string) config('app.url'), '/');
        if ($base === '') {
            return $url;
        }

        return $base.'/'.ltrim($url, '/');
    }
}

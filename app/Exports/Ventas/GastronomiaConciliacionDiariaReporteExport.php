<?php

declare(strict_types=1);

namespace App\Exports\Ventas;

use App\Services\Ventas\Gastronomia\GastronomiaConciliacionDiariaReporteService;
use App\Support\Configuracion\EmpresaLogoArchivo;
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
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Conciliación gastronomía agrupada por PC (identificador_pc / rendg_host) y PV (CAE / CAEA).
 */
final class GastronomiaConciliacionDiariaReporteExport implements FromView, ShouldAutoSize, WithColumnFormatting, WithColumnWidths, WithEvents, WithStyles, WithTitle
{
    use Exportable;

    private const COL_ULTIMA = 'AE';

    private bool $hayFilaLogos = false;

    private int $filasMetaEncabezado = 3;

    private int $filaInicioMeta = 1;

    private int $filaCabecerasExcel = 4;

    private int $filaPrimeraDatosExcel = 5;

    /** @var list<string> */
    private array $rutasLogosExcel = [];

    /** @var list<array<string, mixed>> */
    private array $filas = [];

    private string $titulo = '';

    private string $subtitulo = '';

    /**
     * @param  array<string, mixed>  $informe
     */
    public function __construct(
        private array $informe,
        GastronomiaConciliacionDiariaReporteService $service,
    ) {
        $desde = (string) ($informe['fecha_desde'] ?? '');
        $hasta = (string) ($informe['fecha_hasta'] ?? $desde);
        $this->titulo = 'Conciliación gastronomía ERP / Anita / rendgastro';
        $this->subtitulo = sprintf(
            'Jornada %s → %s · Agrupado por PC (terminal) y PV (CAE / CAEA) · rendgastro por rendg_host · Excluye estacionamiento y facturas fuera de PCs gastronomía',
            $desde,
            $hasta,
        );
        $this->filas = $this->aplanarFilas($informe, $service);
    }

    public function view(): View
    {
        $coleccionLogos = collect($this->filas)->map(static fn (array $f) => (object) [
            'nombreempresa' => $f['empresa_nombre'] ?? '',
        ]);
        $this->rutasLogosExcel = EmpresaLogoArchivo::rutasLogosCabeceraDesdeColeccion($coleccionLogos);
        $this->hayFilaLogos = count($this->rutasLogosExcel) > 0;
        $offsetLogo = $this->hayFilaLogos ? 1 : 0;
        $this->filaInicioMeta = $offsetLogo + 1;
        $this->filaCabecerasExcel = $offsetLogo + $this->filasMetaEncabezado + 1;
        $this->filaPrimeraDatosExcel = $this->filaCabecerasExcel + 1;

        return view('exports.ventas.gastronomia_conciliacion_diaria_reporte', [
            'filas' => $this->filas,
            'titulo' => $this->titulo,
            'subtitulo' => $this->subtitulo,
            'hay_diferencias' => (bool) ($this->informe['hay_diferencias'] ?? false),
            'total_lineas' => count($this->filas),
            'reservarFilaLogoExcel' => $this->hayFilaLogos,
        ]);
    }

    public function columnFormats(): array
    {
        return [
            'J' => NumberFormat::FORMAT_NUMBER_00,
            'K' => NumberFormat::FORMAT_NUMBER_00,
            'L' => NumberFormat::FORMAT_NUMBER_00,
            'M' => NumberFormat::FORMAT_NUMBER_00,
            'N' => NumberFormat::FORMAT_NUMBER_00,
            'O' => NumberFormat::FORMAT_NUMBER_00,
            'P' => NumberFormat::FORMAT_NUMBER_00,
            'Q' => NumberFormat::FORMAT_NUMBER_00,
            'R' => NumberFormat::FORMAT_NUMBER_00,
            'S' => NumberFormat::FORMAT_NUMBER_00,
            'T' => NumberFormat::FORMAT_NUMBER_00,
            'U' => NumberFormat::FORMAT_NUMBER_00,
            'V' => NumberFormat::FORMAT_NUMBER_00,
            'W' => NumberFormat::FORMAT_NUMBER_00,
            'X' => NumberFormat::FORMAT_NUMBER_00,
            'Y' => NumberFormat::FORMAT_NUMBER_00,
            'Z' => NumberFormat::FORMAT_NUMBER_00,
            'AA' => NumberFormat::FORMAT_NUMBER_00,
            'AB' => NumberFormat::FORMAT_NUMBER_00,
            'AC' => NumberFormat::FORMAT_NUMBER_00,
            'AD' => NumberFormat::FORMAT_NUMBER_00,
            'AE' => NumberFormat::FORMAT_NUMBER_00,
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

    public function columnWidths(): array
    {
        return [
            'A' => 8,
            'B' => 22,
            'C' => 11,
            'D' => 14,
            'E' => 10,
            'F' => 10,
            'G' => 10,
            'H' => 10,
            'I' => 10,
            'J' => 12,
            'K' => 12,
            'L' => 12,
            'M' => 12,
            'N' => 12,
            'O' => 12,
            'P' => 12,
            'Q' => 12,
            'R' => 12,
            'S' => 12,
            'T' => 12,
            'U' => 10,
            'V' => 12,
            'W' => 12,
            'X' => 12,
            'Y' => 12,
            'Z' => 14,
            'AA' => 14,
            'AB' => 14,
            'AC' => 14,
            'AD' => 14,
            'AE' => 14,
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
                        $drawing->setName('Logo');
                        $drawing->setDescription('Logo empresa');
                        $drawing->setPath($ruta);
                        $drawing->setResizeProportional(true);
                        $drawing->setHeight(46);
                        $drawing->setCoordinates('A1');
                        $drawing->setOffsetX($offsetXp + $idx * 160);
                        $drawing->setOffsetY(4);
                        $drawing->setWorksheet($sheet);
                    }
                }

                $filaFinMeta = $this->filaInicioMeta + $this->filasMetaEncabezado - 1;
                for ($fila = $this->filaInicioMeta; $fila <= $filaFinMeta; $fila++) {
                    $sheet->mergeCells('A'.$fila.':'.$colUltima.$fila);
                }

                $filaTit = $this->filaInicioMeta;
                $sheet->getRowDimension($filaTit)->setRowHeight(28);
                $sheet->getStyle('A'.$filaTit.':'.$colUltima.$filaTit)->applyFromArray([
                    'font' => ['bold' => true, 'size' => 16, 'name' => 'Arial', 'color' => ['rgb' => '17202A']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER],
                ]);

                for ($fila = $filaTit + 1; $fila <= $filaFinMeta; $fila++) {
                    $sheet->getRowDimension($fila)->setRowHeight($fila === $filaTit + 2 ? 42 : 20);
                    $sheet->getStyle('A'.$fila.':'.$colUltima.$fila)->applyFromArray([
                        'font' => ['bold' => true, 'size' => 10, 'name' => 'Arial', 'color' => ['rgb' => '444444']],
                        'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
                    ]);
                }

                $filaCab = $this->filaCabecerasExcel;
                $sheet->getStyle('A'.$filaCab.':'.$colUltima.$filaCab)->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['rgb' => '17202A'], 'size' => 11, 'name' => 'Arial'],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => '85C1E9']],
                    'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
                ]);

                $sheet->freezePane('A'.$this->filaPrimeraDatosExcel);
            },
        ];
    }

    public function title(): string
    {
        return 'Conciliación PC-PV';
    }

    /**
     * @param  array<string, mixed>  $informe
     */
    public static function contenidoBinario(array $informe): string
    {
        return (new self($informe, app(GastronomiaConciliacionDiariaReporteService::class)))->raw(\Maatwebsite\Excel\Excel::XLSX);
    }

    /**
     * @param  array<string, mixed>  $informe
     */
    public static function nombreArchivo(array $informe): string
    {
        $desde = str_replace('-', '', (string) ($informe['fecha_desde'] ?? ''));
        $hasta = str_replace('-', '', (string) ($informe['fecha_hasta'] ?? $desde));

        return 'conciliacion_gastro_pc_pv_'.$desde.'_'.$hasta.'.xlsx';
    }

    /**
     * @param  array<string, mixed>  $informe
     * @return list<array<string, mixed>>
     */
    private function aplanarFilas(array $informe, GastronomiaConciliacionDiariaReporteService $service): array
    {
        $filasCsv = $service->construirFilasCsv($informe);
        $headers = [
            'empresa_id', 'empresa_nombre', 'fecha_jornada', 'tipo_fila', 'tipo_pv',
            'identificador_pc', 'pv_codigo', 'pv_cae', 'pv_caea',
            'ventas_erp_cae', 'ventas_erp_caea', 'ventas_erp_total',
            'ventas_anita_cae', 'ventas_anita_caea', 'ventas_anita_total',
            'rendgastro_z_portadora', 'rendgastro_caea_campo', 'rendgastro_total',
            'diff_erp_anita', 'diff_erp_rendg', 'estado', 'cant_facturas',
            'nc_erp', 'nc_rendg', 'rendg_neto', 'rendg_legacy_z', 'fc_caea_duplicado',
            'asiento_factura_dia', 'asiento_post_cierre', 'asientos_total', 'diff_rendg_asientos',
        ];

        $out = [];
        foreach ($filasCsv as $row) {
            $assoc = [];
            foreach ($headers as $i => $key) {
                $assoc[$key] = $row[$i] ?? '';
            }
            $out[] = $assoc;
        }

        return $out;
    }
}

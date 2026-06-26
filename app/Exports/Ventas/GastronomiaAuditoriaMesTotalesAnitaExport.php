<?php

declare(strict_types=1);

namespace App\Exports\Ventas;

use App\Support\Configuracion\EmpresaLogoArchivo;
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

final class GastronomiaAuditoriaMesTotalesAnitaExport implements FromView, ShouldAutoSize, WithColumnFormatting, WithColumnWidths, WithEvents, WithStyles, WithTitle
{
    use Exportable;

    private const COL_ULTIMA = 'K';

    private bool $hayFilaLogos = false;

    private int $filasMetaEncabezado = 2;

    private int $filaInicioMeta = 1;

    private int $filaCabecerasExcel = 2;

    private int $filaPrimeraDatosExcel = 3;

    private int $filaSubtituloExcel = 0;

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
    ) {
        $desde = (string) ($informe['fecha_desde'] ?? '');
        $hasta = (string) ($informe['fecha_hasta'] ?? '');
        $this->titulo = 'Auditoría mensual Anita (venta / vengrav / ctamov / rendgastro)';
        $this->subtitulo = sprintf(
            'Jornada %s → %s · Modo %s · Empresas: %s · Excluye FSL/FBI · rendg = Z − NC',
            $desde,
            $hasta,
            (string) ($informe['modo'] ?? 'solo_anita'),
            $this->nombresEmpresas($informe),
        );
        $this->filas = $this->aplanarFilas($informe);
    }

    public function view(): View
    {
        $coleccionLogos = $this->coleccionParaLogos();
        $this->rutasLogosExcel = EmpresaLogoArchivo::rutasLogosCabeceraDesdeColeccion($coleccionLogos);
        $this->hayFilaLogos = count($this->rutasLogosExcel) > 0;
        $this->filasMetaEncabezado = $this->contarFilasMetaEncabezado();
        $offsetLogo = $this->hayFilaLogos ? 1 : 0;
        $this->filaInicioMeta = $offsetLogo + 1;
        $this->filaCabecerasExcel = $offsetLogo + $this->filasMetaEncabezado + 1;
        $this->filaPrimeraDatosExcel = $this->filaCabecerasExcel + 1;
        $this->filaSubtituloExcel = trim($this->subtitulo) !== ''
            ? $this->filaInicioMeta + 2
            : 0;

        return view('exports.ventas.gastronomia_auditoria_mes_totales_anita', [
            'filas' => $this->filas,
            'titulo' => $this->titulo,
            'subtitulo' => $this->subtitulo,
            'hay_alertas' => (bool) ($this->informe['hay_alertas'] ?? false),
            'total_lineas' => count($this->filas),
            'reservarFilaLogoExcel' => $this->hayFilaLogos,
        ]);
    }

    public function columnFormats(): array
    {
        return [
            'A' => NumberFormat::FORMAT_TEXT,
            'B' => NumberFormat::FORMAT_TEXT,
            'C' => NumberFormat::FORMAT_TEXT,
            'D' => NumberFormat::FORMAT_NUMBER_00,
            'E' => NumberFormat::FORMAT_NUMBER_00,
            'F' => NumberFormat::FORMAT_NUMBER_00,
            'G' => NumberFormat::FORMAT_NUMBER_00,
            'H' => NumberFormat::FORMAT_TEXT,
            'I' => NumberFormat::FORMAT_TEXT,
            'J' => NumberFormat::FORMAT_TEXT,
            'K' => NumberFormat::FORMAT_TEXT,
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
            'A' => 10,
            'B' => 28,
            'C' => 12,
            'D' => 14,
            'E' => 14,
            'F' => 14,
            'G' => 14,
            'H' => 10,
            'I' => 10,
            'J' => 10,
            'K' => 36,
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
                    'font' => [
                        'bold' => true,
                        'size' => 16,
                        'name' => 'Arial',
                        'color' => ['rgb' => '17202A'],
                    ],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_LEFT,
                        'vertical' => Alignment::VERTICAL_CENTER,
                    ],
                ]);

                for ($fila = $filaTit + 1; $fila <= $filaFinMeta; $fila++) {
                    $altura = ($this->filaSubtituloExcel > 0 && $fila === $this->filaSubtituloExcel) ? 42 : 20;
                    $sheet->getRowDimension($fila)->setRowHeight($altura);
                    $sheet->getStyle('A'.$fila.':'.$colUltima.$fila)->applyFromArray([
                        'font' => [
                            'bold' => true,
                            'size' => 10,
                            'name' => 'Arial',
                            'color' => ['rgb' => '444444'],
                        ],
                        'alignment' => [
                            'horizontal' => Alignment::HORIZONTAL_LEFT,
                            'vertical' => Alignment::VERTICAL_CENTER,
                            'wrapText' => true,
                        ],
                    ]);
                }

                $filaCab = $this->filaCabecerasExcel;
                $sheet->getStyle('A'.$filaCab.':'.$colUltima.$filaCab)->applyFromArray([
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
                    'alignment' => [
                        'vertical' => Alignment::VERTICAL_CENTER,
                    ],
                ]);

                $sheet->getStyle('K'.$this->filaPrimeraDatosExcel.':K'.$sheet->getHighestRow())
                    ->getAlignment()
                    ->setWrapText(true)
                    ->setVertical(Alignment::VERTICAL_TOP);

                $sheet->freezePane('A'.$this->filaPrimeraDatosExcel);
            },
        ];
    }

    public function title(): string
    {
        return 'Auditoría Anita mensual';
    }

    /**
     * @param  array<string, mixed>  $informe
     */
    public static function contenidoBinario(array $informe): string
    {
        return (new self($informe))->raw(\Maatwebsite\Excel\Excel::XLSX);
    }

    /**
     * @param  array<string, mixed>  $informe
     */
    public static function guardarEnRuta(string $ruta, array $informe): void
    {
        $contenido = self::contenidoBinario($informe);
        if (file_put_contents($ruta, $contenido) === false) {
            throw new \RuntimeException('No se pudo escribir Excel: '.$ruta);
        }
    }

    /**
     * @param  array<string, mixed>  $informe
     */
    public static function nombreArchivo(array $informe): string
    {
        $desde = str_replace('-', '', (string) ($informe['fecha_desde'] ?? ''));
        $hasta = str_replace('-', '', (string) ($informe['fecha_hasta'] ?? ''));

        return 'auditoria_anita_mes_'.$desde.'_'.$hasta.'.xlsx';
    }

    /**
     * @param  array<string, mixed>  $informe
     * @return list<array<string, mixed>>
     */
    private function aplanarFilas(array $informe): array
    {
        $filas = [];

        foreach ($informe['empresas'] ?? [] as $empresa) {
            foreach ($empresa['filas'] ?? [] as $fila) {
                $filas[] = [
                    'empresa_id' => $empresa['empresa_id'] ?? '',
                    'empresa_nombre' => $empresa['empresa_nombre'] ?? '',
                    'nombreempresa' => $empresa['empresa_nombre'] ?? '',
                    'fecha_jornada' => $fila['fecha_jornada'] ?? '',
                    'total_venta_anita' => $fila['total_venta_anita'] ?? 0,
                    'total_vengrav_anita' => $fila['total_vengrav_anita'] ?? 0,
                    'total_ctamov_anita' => $fila['total_ctamov_anita'] ?? 0,
                    'total_rendg_anita' => $fila['total_rendg_anita'] ?? 0,
                    'cant_cabeceras_venta_anita' => $fila['cant_cabeceras_venta_anita'] ?? 0,
                    'huecos_corr_anita' => $fila['huecos_corr_anita'] ?? 0,
                    'estado' => $fila['estado'] ?? '',
                    'observaciones' => $fila['observaciones'] ?? '',
                ];
            }
        }

        return $filas;
    }

    /**
     * @param  array<string, mixed>  $informe
     */
    private function nombresEmpresas(array $informe): string
    {
        $nombres = [];
        foreach ($informe['empresas'] ?? [] as $empresa) {
            $nombres[] = sprintf(
                '%s (id %s)',
                $empresa['empresa_nombre'] ?? '',
                $empresa['empresa_id'] ?? '',
            );
        }

        return implode(', ', $nombres);
    }

    private function contarFilasMetaEncabezado(): int
    {
        $filas = 2;

        if (trim($this->subtitulo) !== '') {
            $filas++;
        }

        if ((bool) ($this->informe['hay_alertas'] ?? false)) {
            $filas++;
        }

        if (count($this->filas) > 0) {
            $filas++;
        }

        return $filas;
    }

    private function coleccionParaLogos(): Collection
    {
        return collect($this->filas)->map(fn (array $f) => (object) [
            'nombreempresa' => (string) ($f['nombreempresa'] ?? ''),
        ]);
    }
}

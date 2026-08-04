<?php

namespace App\Exports\Configuracion;

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
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Excel de una consulta operativa IA (tabla tipada o párrafos).
 */
class AiConsultaOperativaExport implements FromView, ShouldAutoSize, WithColumnFormatting, WithColumnWidths, WithEvents, WithStyles, WithTitle
{
    use Exportable;

    private int $filasMetaEncabezado = 2;

    private int $filaCabecerasExcel = 3;

    private int $filaPrimeraDatosExcel = 4;

    private string $colUltima = 'B';

    /** @var list<string> */
    private array $columnasDebeHaber = [];

    /**
     * @param  array{
     *   interpretacion?: string,
     *   intent?: string,
     *   pregunta?: string|null,
     *   parrafos?: list<string>,
     *   tabla?: array{columnas?: list<array{key: string, label: string}>, filas?: list<array<string,mixed>>}|null,
     *   datos?: array<string,mixed>,
     *   fuente?: string|null
     * }  $payload
     */
    public function __construct(
        private array $payload,
    ) {}

    public function view(): View
    {
        $tabla = is_array($this->payload['tabla'] ?? null) ? $this->payload['tabla'] : null;
        $columnas = is_array($tabla['columnas'] ?? null) ? array_values($tabla['columnas']) : [];
        $filas = is_array($tabla['filas'] ?? null) ? $tabla['filas'] : [];
        $parrafos = array_values(array_filter(
            array_map('strval', $this->payload['parrafos'] ?? []),
            static fn (string $p) => trim($p) !== ''
        ));
        $pregunta = trim((string) ($this->payload['pregunta'] ?? ''));
        $interpretacion = trim((string) ($this->payload['interpretacion'] ?? ''));
        $datos = is_array($this->payload['datos'] ?? null) ? $this->payload['datos'] : [];
        $resumen = $this->armarResumenAmigable($datos);

        $tieneTabla = $columnas !== [];
        $this->filasMetaEncabezado = 2; // título + generado
        if ($interpretacion !== '') {
            $this->filasMetaEncabezado++;
        }
        if ($pregunta !== '') {
            $this->filasMetaEncabezado++;
        }
        // Los párrafos solo van al encabezado cuando hay tabla de datos
        if ($columnas !== []) {
            foreach ($parrafos as $_) {
                $this->filasMetaEncabezado++;
            }
        }
        if ($resumen !== []) {
            foreach ($resumen as $_) {
                $this->filasMetaEncabezado++;
            }
        }

        if ($tieneTabla) {
            $this->filaCabecerasExcel = $this->filasMetaEncabezado + 1;
            $this->filaPrimeraDatosExcel = $this->filaCabecerasExcel + 1;
            $nCols = count($columnas);
            $this->colUltima = $this->colLetter($nCols - 1);
            $this->columnasDebeHaber = [];
            foreach ($columnas as $i => $col) {
                $key = (string) ($col['key'] ?? '');
                if (in_array($key, ['debe', 'haber'], true)) {
                    $this->columnasDebeHaber[] = $this->colLetter($i);
                }
            }
        } else {
            $this->filaCabecerasExcel = $this->filasMetaEncabezado + 1;
            $this->filaPrimeraDatosExcel = $this->filaCabecerasExcel + 1;
            $this->colUltima = 'B';
            $this->columnasDebeHaber = [];
        }

        return view('exports.configuracion.ai_consulta_operativa', [
            'titulo' => 'Consulta operativa IA',
            'interpretacion' => $interpretacion,
            'intent' => (string) ($this->payload['intent'] ?? ''),
            'pregunta' => $pregunta,
            'fuente' => (string) ($this->payload['fuente'] ?? ''),
            'parrafos' => $parrafos,
            'tabla' => $tabla,
            'columnas' => $columnas,
            'filas' => $filas,
            'tieneTabla' => $tieneTabla,
            'resumen' => $resumen,
            'generado' => now()->format('d/m/Y H:i'),
            'colspan' => $tieneTabla ? max(count($columnas), 2) : 2,
        ]);
    }

    public function title(): string
    {
        $intent = (string) ($this->payload['intent'] ?? 'consulta');

        return mb_substr('IA '.$intent, 0, 31);
    }

    public function columnWidths(): array
    {
        $columnas = is_array($this->payload['tabla']['columnas'] ?? null)
            ? array_values($this->payload['tabla']['columnas'])
            : [];
        if ($columnas === []) {
            return [
                'A' => 12,
                'B' => 80,
            ];
        }

        $widths = [];
        foreach ($columnas as $i => $col) {
            $key = (string) ($col['key'] ?? '');
            $widths[$this->colLetter($i)] = match ($key) {
                'fecha' => 12,
                'asiento' => 12,
                'debe', 'haber' => 14,
                'detalle' => 45,
                'proveedor' => 28,
                'cuenta' => 36,
                'centrocosto' => 22,
                default => 18,
            };
        }

        return $widths;
    }

    public function columnFormats(): array
    {
        $out = [];
        foreach ($this->columnasDebeHaber as $col) {
            $out[$col] = NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1;
        }

        return $out;
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font' => [
                    'bold' => true,
                    'size' => 14,
                    'name' => 'Arial',
                    'color' => ['rgb' => '17202A'],
                ],
            ],
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
                $colUltima = $this->colUltima;

                for ($f = 1; $f <= $this->filasMetaEncabezado; $f++) {
                    $sheet->mergeCells('A'.$f.':'.$colUltima.$f);
                }

                $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14)->setName('Arial');
                $sheet->getRowDimension(1)->setRowHeight(26);

                if ($this->filasMetaEncabezado >= 2) {
                    $sheet->getStyle('A2:A'.$this->filasMetaEncabezado)->applyFromArray([
                        'font' => [
                            'bold' => true,
                            'size' => 10,
                            'name' => 'Arial',
                            'color' => ['rgb' => '444444'],
                        ],
                        'alignment' => [
                            'wrapText' => true,
                            'vertical' => Alignment::VERTICAL_CENTER,
                        ],
                    ]);
                }

                $rangoCab = 'A'.$this->filaCabecerasExcel.':'.$colUltima.$this->filaCabecerasExcel;
                $sheet->getStyle($rangoCab)->applyFromArray([
                    'font' => [
                        'bold' => true,
                        'color' => ['rgb' => '17202A'],
                        'size' => 11,
                        'name' => 'Arial',
                    ],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => '85C1E9'],
                    ],
                ]);
                $sheet->freezePane('A'.$this->filaPrimeraDatosExcel);
            },
        ];
    }

    /**
     * @param  array<string,mixed>  $datos
     * @return list<string>
     */
    private function armarResumenAmigable(array $datos): array
    {
        $out = [];
        $lineas = (int) ($datos['lineas_periodo'] ?? $datos['lineas_mostradas'] ?? 0);
        if ($lineas > 0 || array_key_exists('lineas_periodo', $datos)) {
            $out[] = $lineas.' movimiento(s)'
                .(isset($datos['debe_periodo'])
                    ? ' · Debe '.number_format((float) $datos['debe_periodo'], 2, ',', '.')
                    : '')
                .(isset($datos['haber_periodo'])
                    ? ' · Haber '.number_format((float) $datos['haber_periodo'], 2, ',', '.')
                    : '');
        }
        if (! empty($datos['cuentas_incluidas']) && (int) $datos['cuentas_incluidas'] > 1) {
            $out[] = 'Cuentas imputables incluidas: '.(int) $datos['cuentas_incluidas'];
        }
        if (isset($datos['neto'])) {
            $out[] = 'Saldo neto: '.number_format((float) $datos['neto'], 2, ',', '.');
        }

        return $out;
    }

    private function colLetter(int $index): string
    {
        if ($index < 0) {
            return 'A';
        }
        if ($index < 26) {
            return chr(ord('A') + $index);
        }

        return 'A'.chr(ord('A') + ($index - 26));
    }
}

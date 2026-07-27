<?php

namespace App\Exports\Configuracion;

use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Excel de una consulta operativa IA (tabla tipada o párrafos).
 */
class AiConsultaOperativaExport implements FromView, ShouldAutoSize, WithColumnWidths, WithEvents, WithStyles, WithTitle
{
    use Exportable;

    /**
     * @param  array{
     *   interpretacion?: string,
     *   intent?: string,
     *   pregunta?: string|null,
     *   parrafos?: list<string>,
     *   tabla?: array{columnas?: list<array{key: string, label: string}>, filas?: list<array<string,string>>}|null,
     *   datos?: array<string,mixed>,
     *   fuente?: string|null
     * }  $payload
     */
    public function __construct(
        private array $payload,
    ) {}

    public function view(): View
    {
        return view('exports.configuracion.ai_consulta_operativa', [
            'titulo' => 'Consulta operativa IA',
            'interpretacion' => (string) ($this->payload['interpretacion'] ?? ''),
            'intent' => (string) ($this->payload['intent'] ?? ''),
            'pregunta' => (string) ($this->payload['pregunta'] ?? ''),
            'fuente' => (string) ($this->payload['fuente'] ?? ''),
            'parrafos' => array_values(array_map('strval', $this->payload['parrafos'] ?? [])),
            'tabla' => is_array($this->payload['tabla'] ?? null) ? $this->payload['tabla'] : null,
            'datos' => is_array($this->payload['datos'] ?? null) ? $this->payload['datos'] : [],
            'generado' => now()->format('d/m/Y H:i'),
        ]);
    }

    public function title(): string
    {
        return 'Consulta IA';
    }

    public function columnWidths(): array
    {
        $columnas = is_array($this->payload['tabla']['columnas'] ?? null)
            ? $this->payload['tabla']['columnas']
            : [];
        if ($columnas === []) {
            return [
                'A' => 10,
                'B' => 90,
            ];
        }

        $widths = [];
        $letters = range('A', 'Z');
        foreach (array_values($columnas) as $i => $col) {
            if (! isset($letters[$i])) {
                break;
            }
            $key = (string) ($col['key'] ?? '');
            $widths[$letters[$i]] = match ($key) {
                'fecha' => 12,
                'asiento' => 12,
                'debe', 'haber' => 14,
                'detalle' => 40,
                'proveedor' => 28,
                default => 18,
            };
        }

        return $widths;
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
                $columnas = is_array($this->payload['tabla']['columnas'] ?? null)
                    ? $this->payload['tabla']['columnas']
                    : [];
                $parrafos = array_values(array_map('strval', $this->payload['parrafos'] ?? []));
                $tienePregunta = trim((string) ($this->payload['pregunta'] ?? '')) !== '';

                if ($columnas !== []) {
                    // 1 título, 2 meta, 3 pregunta?, luego N párrafos, luego thead
                    $filaThead = 2 + ($tienePregunta ? 1 : 0) + count($parrafos) + 1;
                    $ultimaCol = chr(ord('A') + max(0, count($columnas) - 1));
                    $rango = 'A'.$filaThead.':'.$ultimaCol.$filaThead;
                    $sheet->freezePane('A'.($filaThead + 1));
                    $sheet->getStyle($rango)->applyFromArray([
                        'font' => ['bold' => true, 'color' => ['rgb' => '17202A']],
                        'fill' => [
                            'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                            'startColor' => ['rgb' => '85C1E9'],
                        ],
                    ]);

                    return;
                }

                $filaThead = $tienePregunta ? 5 : 4;
                $sheet->freezePane('A'.($filaThead + 1));
                $sheet->getStyle('A'.$filaThead.':B'.$filaThead)->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['rgb' => '17202A']],
                    'fill' => [
                        'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                        'startColor' => ['rgb' => '85C1E9'],
                    ],
                ]);
            },
        ];
    }
}

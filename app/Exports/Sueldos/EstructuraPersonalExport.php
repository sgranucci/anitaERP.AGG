<?php

namespace App\Exports\Sueldos;

use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class EstructuraPersonalExport implements WithMultipleSheets
{
    use Exportable;

    /**
     * @param  array<string, mixed>  $reporte
     */
    public function __construct(private readonly array $reporte) {}

    public function sheets(): array
    {
        $siglas = $this->reporte['siglas'] ?? [];
        $corte = (string) ($this->reporte['corte_label'] ?? '');
        $generado = 'Generado '.date('d/m/Y H:i').' · Corte '.$corte;

        return [
            $this->hoja('Resumen', $this->filasResumen($generado), ['A' => 28, 'B' => 18, 'C' => 18, 'D' => 16]),
            $this->hoja('Consolidado', $this->filasConsolidado($generado, $siglas), $this->anchosConsolidado(count($siglas))),
            $this->hoja('Rotacion', $this->filasRotacion($generado), [
                'A' => 32, 'B' => 32, 'C' => 14, 'D' => 16, 'E' => 12, 'F' => 14, 'G' => 16,
            ]),
            $this->hoja('Bajas', $this->filasBajas($generado), [
                'A' => 12, 'B' => 36, 'C' => 22, 'D' => 28, 'E' => 28, 'F' => 14, 'G' => 12,
            ]),
            $this->hoja('Base', $this->filasBase($generado), [
                'A' => 12, 'B' => 36, 'C' => 22, 'D' => 28, 'E' => 28, 'F' => 22,
                'G' => 14, 'H' => 12, 'I' => 10, 'J' => 28, 'K' => 24,
            ]),
        ];
    }

    /**
     * @param  list<array<int|string|null>>  $filas
     * @param  array<string, float>  $anchos
     */
    private function hoja(string $titulo, array $filas, array $anchos): FromArray
    {
        return new class($titulo, $filas, $anchos) implements FromArray, WithTitle, WithColumnWidths, WithEvents
        {
            /**
             * @param  list<array<int|string|null>>  $filas
             * @param  array<string, float>  $anchos
             */
            public function __construct(
                private readonly string $titulo,
                private readonly array $filas,
                private readonly array $anchos,
            ) {}

            public function array(): array
            {
                return $this->filas;
            }

            public function title(): string
            {
                return $this->titulo;
            }

            public function columnWidths(): array
            {
                return $this->anchos;
            }

            public function registerEvents(): array
            {
                $filas = $this->filas;

                return [
                    AfterSheet::class => function (AfterSheet $event) use ($filas) {
                        $sheet = $event->sheet->getDelegate();
                        $columnas = max(1, count($filas[2] ?? $filas[0] ?? [1]));
                        $ultima = Coordinate::stringFromColumnIndex($columnas);
                        $sheet->mergeCells('A1:'.$ultima.'1');
                        $sheet->mergeCells('A2:'.$ultima.'2');
                        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16)->getColor()->setRGB('17202A');
                        $sheet->getRowDimension(1)->setRowHeight(28);
                        $sheet->getStyle('A2')->getFont()->setBold(true)->setSize(10)->getColor()->setRGB('444444');
                        $sheet->getStyle('A3:'.$ultima.'3')->applyFromArray([
                            'fill' => [
                                'fillType' => Fill::FILL_SOLID,
                                'startColor' => ['rgb' => '85C1E9'],
                            ],
                            'font' => ['bold' => true, 'color' => ['rgb' => '17202A'], 'name' => 'Arial'],
                            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                        ]);
                        $sheet->freezePane('A4');
                        $ultimaFila = count($filas);
                        for ($fila = 4; $fila <= $ultimaFila; $fila++) {
                            $texto = (string) ($filas[$fila - 1][0] ?? '');
                            if (str_starts_with($texto, 'TOTAL')) {
                                $sheet->getStyle('A'.$fila.':'.$ultima.$fila)->getFont()->setBold(true);
                                $sheet->getStyle('A'.$fila.':'.$ultima.$fila)->getFill()->setFillType(Fill::FILL_SOLID)
                                    ->getStartColor()->setRGB('D6EAF8');
                            }
                        }
                    },
                ];
            }
        };
    }

    /**
     * @return list<array<int|string|null>>
     */
    private function filasResumen(string $generado): array
    {
        $k = $this->reporte['kpis'];
        $filas = [
            ['Estructura de personal'],
            [$generado],
            ['Indicador', 'Valor', '', ''],
            ['Dotación activa', $k['dotacion']],
            ['Presupuesto', $k['presupuesto']],
            ['Desvío vs presupuesto', $k['desvio']],
            ['Bajas del año', $k['bajas_anio']],
            ['Bajas del mes', $k['bajas_mes']],
            ['Altas del mes', $k['altas_mes']],
            ['Antigüedad promedio (años)', $k['antiguedad']],
            ['Edad promedio', $k['edad']],
            ['Rotación del mes %', $k['rotacion_mes']],
            ['Rotación del año %', $k['rotacion_anio']],
            [],
            ['Empresa', 'Sigla', 'Dotación', 'Presupuesto'],
        ];
        foreach ($this->reporte['empresas'] as $empresa) {
            $filas[] = [$empresa['nombre'], $empresa['sigla'], $empresa['dotacion'], $empresa['presupuesto']];
        }

        return $filas;
    }

    /**
     * @param  list<string>  $siglas
     * @return list<array<int|string|null>>
     */
    private function filasConsolidado(string $generado, array $siglas): array
    {
        $cabecera = array_merge(['Centro de costos', 'Dirección'], $siglas, ['Total']);
        if ($this->reporte['tiene_presupuesto']) {
            foreach ($siglas as $sigla) {
                $cabecera[] = $sigla.' ppto';
            }
            $cabecera[] = 'Total ppto';
            $cabecera[] = 'Variación';
        }
        $filas = [
            ['Estructura de personal — Consolidado'],
            [$generado],
            $cabecera,
        ];
        $totalSigla = array_fill_keys($siglas, 0);
        $total = 0;
        $totalPpto = array_fill_keys($siglas, 0);
        $totalPptoGral = 0;
        $hayPpto = false;
        foreach ($this->reporte['consolidado'] as $grupo) {
            foreach ($grupo['filas'] as $fila) {
                $linea = [$fila['nombre'], $grupo['nombre']];
                foreach ($siglas as $sigla) {
                    $n = (int) ($fila['por_sigla'][$sigla] ?? 0);
                    $linea[] = $n;
                    $totalSigla[$sigla] += $n;
                }
                $linea[] = $fila['total'];
                $total += $fila['total'];
                if ($this->reporte['tiene_presupuesto']) {
                    foreach ($siglas as $sigla) {
                        $p = $fila['ppto'][$sigla] ?? null;
                        $linea[] = $p;
                        if ($p !== null) {
                            $totalPpto[$sigla] += (int) $p;
                            $hayPpto = true;
                        }
                    }
                    $linea[] = $fila['ppto_total'];
                    $linea[] = $fila['variacion'];
                    if ($fila['ppto_total'] !== null) {
                        $totalPptoGral += (int) $fila['ppto_total'];
                    }
                }
                $filas[] = $linea;
            }
        }
        $cierre = array_merge(['TOTAL'], [''], array_values($totalSigla), [$total]);
        if ($this->reporte['tiene_presupuesto']) {
            foreach ($siglas as $sigla) {
                $cierre[] = $hayPpto ? $totalPpto[$sigla] : null;
            }
            $cierre[] = $hayPpto ? $totalPptoGral : null;
            $cierre[] = $hayPpto ? $total - $totalPptoGral : null;
        }
        $filas[] = $cierre;

        return $filas;
    }

    /**
     * @return array<string, float>
     */
    private function anchosConsolidado(int $siglas): array
    {
        $anchos = ['A' => 32, 'B' => 32];
        $n = 2 + $siglas + 1 + ($this->reporte['tiene_presupuesto'] ? $siglas + 2 : 0);
        for ($i = 3; $i <= $n; $i++) {
            $anchos[Coordinate::stringFromColumnIndex($i)] = 14;
        }

        return $anchos;
    }

    /**
     * @return list<array<int|string|float|null>>
     */
    private function filasRotacion(string $generado): array
    {
        $filas = [
            ['Estructura de personal — Rotación'],
            [$generado],
            ['Centro de costos', 'Dirección', 'Dotación', 'Dotación 1/1', 'Bajas del año', 'Promedio', 'Rotación %'],
        ];
        foreach ($this->reporte['rotacion'] as $fila) {
            $filas[] = [
                $fila['nombre'], $fila['direccion'], $fila['dotacion'], $fila['inicio_anio'],
                $fila['bajas'], $fila['promedio'], $fila['indice'],
            ];
        }

        return $filas;
    }

    /**
     * @return list<array<int|string|null>>
     */
    private function filasBajas(string $generado): array
    {
        $filas = [
            ['Estructura de personal — Bajas'],
            [$generado.' · '.$this->reporte['periodo_bajas']],
            ['Legajo', 'Nombre', 'Empresa', 'Centro de costos', 'Motivo', 'Fecha', 'Antigüedad'],
        ];
        foreach ($this->reporte['bajas'] as $fila) {
            $filas[] = [
                $fila['legajo'], $fila['nombre'], $fila['empresa'], $fila['cc'],
                $fila['motivo'], $fila['fecha_label'], $fila['anios'],
            ];
        }

        return $filas;
    }

    /**
     * @return list<array<int|string|null>>
     */
    private function filasBase(string $generado): array
    {
        $filas = [
            ['Estructura de personal — Base'],
            [$generado],
            ['Legajo', 'Nombre', 'Empresa', 'Categoría', 'Centro de costos', 'Sindicato', 'Ingreso', 'Antigüedad', 'Sexo', 'Lugar', 'Correo'],
        ];
        foreach ($this->reporte['base'] as $fila) {
            $filas[] = [
                $fila['legajo'], $fila['nombre'], $fila['empresa'], $fila['categoria'], $fila['cc'],
                $fila['sindicato'], $fila['ingreso'], $fila['anios'], $fila['sexo'], $fila['lugar'], $fila['email'],
            ];
        }

        return $filas;
    }
}

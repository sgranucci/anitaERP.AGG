<?php

declare(strict_types=1);

namespace App\Exports\Ventas;

use App\Support\Export\ExcelFormatoNumero;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class GastronomiaRecetasReporteHojaExport implements FromArray, WithColumnFormatting, WithEvents, WithTitle
{
    use Exportable;

    /**
     * @param  list<string>  $encabezados
     * @param  list<list<mixed>>  $filas
     * @param  array<string, int>  $columnasNumericas  letra => decimales
     */
    public function __construct(
        private readonly string $titulo,
        private readonly array $encabezados,
        private readonly array $filas,
        private readonly array $columnasNumericas = [],
        private readonly bool $resaltarTotales = false,
    ) {}

    public function title(): string
    {
        return $this->titulo;
    }

    public function array(): array
    {
        return array_merge([$this->encabezados], $this->filas);
    }

    public function columnFormats(): array
    {
        $formato = ExcelFormatoNumero::preferenciaGlobal();
        $out = [];
        foreach ($this->columnasNumericas as $columna => $decimales) {
            $out[$columna] = ExcelFormatoNumero::codigoColumna($formato, $decimales);
        }

        return $out;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event): void {
                $sheet = $event->sheet->getDelegate();
                $ultima = $sheet->getHighestColumn();
                $ultimaFila = max(1, count($this->filas) + 1);
                $rango = 'A1:'.$ultima.$ultimaFila;
                $sheet->getStyle($rango)->applyFromArray([
                    'font' => ['name' => 'Arial', 'size' => 11, 'color' => ['rgb' => '17202A']],
                    'alignment' => [
                        'vertical' => Alignment::VERTICAL_CENTER,
                        'wrapText' => false,
                    ],
                ]);
                $sheet->getStyle('A1:'.$ultima.'1')->applyFromArray([
                    'font' => ['bold' => true, 'name' => 'Arial', 'size' => 11, 'color' => ['rgb' => '17202A']],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'color' => ['rgb' => '85C1E9'],
                    ],
                    'alignment' => [
                        'vertical' => Alignment::VERTICAL_CENTER,
                        'wrapText' => true,
                    ],
                ]);
                $sheet->getRowDimension(1)->setRowHeight(30);
                $sheet->getDefaultRowDimension()->setRowHeight(18);

                foreach ($this->anchosColumna() as $columna => $ancho) {
                    $sheet->getColumnDimension($columna)->setWidth($ancho);
                }

                foreach (array_keys($this->columnasNumericas) as $columna) {
                    $sheet->getStyle($columna.'2:'.$columna.$ultimaFila)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                }

                $colDetalle = $this->columnaPorEncabezado('Detalle');
                if ($colDetalle !== null) {
                    $sheet->getStyle($colDetalle.'2:'.$colDetalle.$ultimaFila)->getAlignment()->setWrapText(true);
                    for ($fila = 2; $fila <= $ultimaFila; $fila++) {
                        $sheet->getRowDimension($fila)->setRowHeight(32);
                    }
                }

                $sheet->freezePane('A2');
                $sheet->setAutoFilter('A1:'.$ultima.'1');

                if (! $this->resaltarTotales) {
                    return;
                }

                $fila = 2;
                foreach ($this->filas as $datos) {
                    $texto = (string) ($datos[0] ?? '');
                    if (str_starts_with($texto, 'Total ')) {
                        $sheet->getStyle('A'.$fila.':'.$ultima.$fila)->getFont()->setBold(true);
                    }
                    $fila++;
                }
            },
        ];
    }

    /**
     * Ancho según el texto visible (encabezado y valores), con piso para que
     * importes con separador de miles no queden en ####.
     *
     * @return array<string, float>
     */
    private function anchosColumna(): array
    {
        $maximos = [];
        foreach ($this->encabezados as $indice => $encabezado) {
            $maximos[$indice] = mb_strlen((string) $encabezado);
        }

        foreach ($this->filas as $fila) {
            foreach ($fila as $indice => $valor) {
                if (! is_int($indice)) {
                    continue;
                }
                $letra = $this->letraColumna($indice);
                $texto = $this->textoVisible($valor, $letra);
                $largo = mb_strlen($texto);
                if ($largo > ($maximos[$indice] ?? 0)) {
                    $maximos[$indice] = $largo;
                }
            }
        }

        $anchos = [];
        foreach ($maximos as $indice => $largo) {
            $letra = $this->letraColumna($indice);
            $esNumero = isset($this->columnasNumericas[$letra]);
            $encabezado = mb_strtolower((string) ($this->encabezados[$indice] ?? ''));
            $esTextoLargo = str_contains($encabezado, 'descrip')
                || str_contains($encabezado, 'detalle')
                || $encabezado === 'receta'
                || str_contains($encabezado, 'categor');
            $piso = $esNumero ? 16 : 14;
            $techo = match (true) {
                str_contains($encabezado, 'detalle') => 78,
                $esTextoLargo => 46,
                $esNumero => 18,
                default => 28,
            };
            $anchos[$letra] = (float) max($piso, min($techo, $largo + ($esNumero ? 3 : 2)));
        }

        return $anchos;
    }

    private function textoVisible(mixed $valor, string $letra): string
    {
        if ($valor === null || $valor === '') {
            return '';
        }

        if (isset($this->columnasNumericas[$letra]) && is_numeric($valor)) {
            return number_format((float) $valor, $this->columnasNumericas[$letra], ',', '.');
        }

        return (string) $valor;
    }

    private function columnaPorEncabezado(string $buscado): ?string
    {
        foreach ($this->encabezados as $indice => $encabezado) {
            if (mb_strtolower((string) $encabezado) === mb_strtolower($buscado)) {
                return $this->letraColumna($indice);
            }
        }

        return null;
    }

    private function letraColumna(int $indice): string
    {
        $letra = '';
        $n = $indice;
        do {
            $letra = chr(65 + ($n % 26)).$letra;
            $n = intdiv($n, 26) - 1;
        } while ($n >= 0);

        return $letra;
    }
}

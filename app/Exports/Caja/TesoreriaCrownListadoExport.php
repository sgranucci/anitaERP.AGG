<?php

namespace App\Exports\Caja;

use App\Support\Configuracion\EmpresaLogoArchivo;
use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;

class TesoreriaCrownListadoExport implements FromView, WithColumnFormatting, WithColumnWidths, WithEvents, WithTitle
{
    use Exportable;

    /** @var array<string, mixed> */
    private array $armado = [];

    private bool $hayFilaLogos = false;

    private int $filasMeta = 3;

    private int $filaTituloExcel = 1;

    private int $filaCabecerasExcel = 4;

    private int $filaPrimeraDatosExcel = 5;

    /** @var list<string> */
    private array $rutasLogosExcel = [];

    /**
     * @param  array<string, mixed>  $armado
     */
    public function parametros(array $armado): self
    {
        $this->armado = $armado;

        return $this;
    }

    public function view(): View
    {
        $coleccion = $this->armado['coleccion'] ?? collect();
        $this->rutasLogosExcel = EmpresaLogoArchivo::rutasLogosCabeceraDesdeColeccion($coleccion);
        $this->hayFilaLogos = $this->rutasLogosExcel !== [];
        $subtitulo = trim((string) ($this->armado['subtitulo'] ?? ''));
        $this->filasMeta = $subtitulo !== '' ? 4 : 3;
        $offset = $this->hayFilaLogos ? 1 : 0;
        $this->filaTituloExcel = $offset + 1;
        $this->filaCabecerasExcel = $offset + $this->filasMeta + 1;
        $this->filaPrimeraDatosExcel = $this->filaCabecerasExcel + 1;

        return view('exports.caja.tesoreria_crown_listado', [
            'titulo' => $this->armado['titulo'] ?? 'Listado',
            'subtitulo' => $subtitulo,
            'columnas' => $this->armado['columnas'] ?? [],
            'filas' => $this->armado['filas'] ?? [],
            'totalFilas' => count($this->armado['filas'] ?? []),
            'reservarFilaLogoExcel' => $this->hayFilaLogos,
            'mostrarSubtitulo' => $subtitulo !== '',
        ]);
    }

    public function title(): string
    {
        $titulo = (string) ($this->armado['titulo_corto'] ?? $this->armado['titulo'] ?? 'Listado');

        return mb_substr($titulo, 0, 31);
    }

    public function columnWidths(): array
    {
        $anchos = [];
        foreach ($this->armado['columnas'] ?? [] as $i => $columna) {
            $anchos[$this->letra($i)] = (int) ($columna['ancho'] ?? 18);
        }

        return $anchos;
    }

    public function columnFormats(): array
    {
        $formatos = [];
        foreach ($this->armado['columnas'] ?? [] as $i => $columna) {
            $letra = $this->letra($i);
            if (! empty($columna['numero'])) {
                $formatos[$letra] = NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1;
            } elseif (! empty($columna['texto'])) {
                $formatos[$letra] = NumberFormat::FORMAT_TEXT;
            }
        }

        return $formatos;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $ultima = $this->letra(max(0, count($this->armado['columnas'] ?? []) - 1));

                if ($this->hayFilaLogos) {
                    $sheet->getRowDimension(1)->setRowHeight(54);
                    $offset = 8;
                    foreach ($this->rutasLogosExcel as $idx => $ruta) {
                        if (! is_file($ruta)) {
                            continue;
                        }
                        $drawing = new Drawing;
                        $drawing->setName('Logo');
                        $drawing->setPath($ruta);
                        $drawing->setResizeProportional(true);
                        $drawing->setHeight(46);
                        $drawing->setCoordinates('A1');
                        $drawing->setOffsetX($offset + $idx * 120);
                        $drawing->setOffsetY(4);
                        $drawing->setWorksheet($sheet);
                    }
                }

                for ($i = 0; $i < $this->filasMeta; $i++) {
                    $fila = $this->filaTituloExcel + $i;
                    $sheet->mergeCells('A'.$fila.':'.$ultima.$fila);
                }

                $sheet->getRowDimension($this->filaTituloExcel)->setRowHeight(28);
                $sheet->getStyle('A'.$this->filaTituloExcel)->applyFromArray([
                    'font' => ['bold' => true, 'name' => 'Arial', 'size' => 16, 'color' => ['rgb' => '17202A']],
                ]);
                if ($this->filasMeta > 1) {
                    $desde = $this->filaTituloExcel + 1;
                    $hasta = $this->filaTituloExcel + $this->filasMeta - 1;
                    $sheet->getStyle('A'.$desde.':'.$ultima.$hasta)->applyFromArray([
                        'font' => ['bold' => true, 'name' => 'Arial', 'size' => 10, 'color' => ['rgb' => '444444']],
                        'alignment' => ['wrapText' => true],
                    ]);
                }

                $cab = $this->filaCabecerasExcel;
                $sheet->getStyle('A'.$cab.':'.$ultima.$cab)->applyFromArray([
                    'font' => ['bold' => true, 'name' => 'Arial', 'size' => 11, 'color' => ['rgb' => '17202A']],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => '85C1E9'],
                    ],
                ]);
                $sheet->freezePane('A'.$this->filaPrimeraDatosExcel);

                $cantidad = count($this->armado['filas'] ?? []);
                if ($cantidad < 1) {
                    return;
                }
                $filaFin = $this->filaPrimeraDatosExcel + $cantidad - 1;
                foreach ($this->armado['columnas'] ?? [] as $i => $columna) {
                    $letra = $this->letra($i);
                    $rango = $letra.$this->filaPrimeraDatosExcel.':'.$letra.$filaFin;
                    if (! empty($columna['numero'])) {
                        $sheet->getStyle($rango)->getNumberFormat()->setFormatCode('#,##0.00');
                        $sheet->getStyle($rango)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                        for ($fila = $this->filaPrimeraDatosExcel; $fila <= $filaFin; $fila++) {
                            $valor = $sheet->getCell($letra.$fila)->getValue();
                            if ($valor === null || $valor === '') {
                                continue;
                            }
                            $sheet->setCellValueExplicit($letra.$fila, (float) $valor, DataType::TYPE_NUMERIC);
                        }
                    } elseif (! empty($columna['texto'])) {
                        $sheet->getStyle($rango)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);
                        for ($fila = $this->filaPrimeraDatosExcel; $fila <= $filaFin; $fila++) {
                            $valor = $sheet->getCell($letra.$fila)->getValue();
                            $sheet->setCellValueExplicit($letra.$fila, (string) $valor, DataType::TYPE_STRING);
                        }
                    }
                    if (! empty($columna['wrap'])) {
                        $sheet->getStyle($rango)->getAlignment()->setWrapText(true);
                    }
                }
            },
        ];
    }

    private function letra(int $indice): string
    {
        return chr(ord('A') + $indice);
    }
}

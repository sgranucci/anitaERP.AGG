<?php

namespace App\Exports\Stock;

use App\Support\Configuracion\EmpresaLogoArchivo;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ExistenciasDepositoReporteExport implements FromView, ShouldAutoSize, WithColumnFormatting, WithEvents, WithStyles, WithTitle
{
  use Exportable;

  private bool $hayFilaLogos = false;

  private int $filasMetaEncabezado = 2;

  private int $filaInicioMeta = 1;

  private int $filaCabecerasExcel = 2;

  private int $filaPrimeraDatosExcel = 3;

  private int $filaSubtituloExcel = 0;

  private int $totalColumnas = 6;

  private string $colUltima = 'F';

  /** @var list<string> */
  private array $rutasLogosExcel = [];

  /**
   * @param  Collection<int, \App\Models\Stock\Depmae>|iterable  $depositos
   * @param  iterable<int, array<string, mixed>>  $filas
   * @param  array<string, mixed>  $totales
   */
  public function __construct(
    private iterable $depositos,
    private iterable $filas,
    private string $titulo,
    private string $subtitulo = '',
    private array $totales = [],
  ) {
    $this->resolverLayoutColumnas();
  }

  public function view(): View
  {
    $depositos = collect($this->depositos);
    $this->resolverLayoutColumnas();

    $coleccionLogos = collect($this->filas);
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

    return view('exports.stock.existencias_deposito_reporteindex', [
      'depositos' => $depositos,
      'filas' => $this->filas,
      'titulo' => $this->titulo,
      'subtitulo' => $this->subtitulo,
      'totales' => $this->totales,
      'total_columnas' => $this->totalColumnas,
      'reservarFilaLogoExcel' => $this->hayFilaLogos,
      'total_articulos' => count($this->filas),
    ]);
  }

  public function columnFormats(): array
  {
    $formats = [];
    for ($i = 6; $i <= $this->totalColumnas; $i++) {
      $formats[$this->indiceAColumna($i)] = '#,##0.######';
    }

    return $formats;
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
        $colUltima = $this->colUltima;

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

        $sheet->getStyle('A'.$this->filaCabecerasExcel.':'.$colUltima.$this->filaCabecerasExcel)->applyFromArray([
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
        ]);

        $sheet->freezePane('A'.$this->filaPrimeraDatosExcel);

        $colInicioSaldos = $this->indiceAColumna(6);
        $ultimaFila = $sheet->getHighestRow();
        if ($ultimaFila >= $this->filaCabecerasExcel) {
          $sheet->getStyle($colInicioSaldos.$this->filaCabecerasExcel.':'.$colUltima.$ultimaFila)
            ->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_RIGHT)
            ->setVertical(Alignment::VERTICAL_TOP);
        }
      },
    ];
  }

  public function title(): string
  {
    return 'Existencias por depósito';
  }

  private function contarFilasMetaEncabezado(): int
  {
    $filas = 2;
    if (trim($this->subtitulo) !== '') {
      $filas++;
    }
    if (($this->totales['total_articulos'] ?? 0) > 0) {
      $filas++;
    }

    return $filas;
  }

  private function resolverLayoutColumnas(): void
  {
    $depositos = collect($this->depositos);
    $this->totalColumnas = 5 + $depositos->count() + 1;
    $this->colUltima = $this->indiceAColumna($this->totalColumnas);
  }

  private function indiceAColumna(int $indice): string
  {
    $indice = max(1, $indice);
    $columna = '';
    while ($indice > 0) {
      $mod = ($indice - 1) % 26;
      $columna = chr(65 + $mod).$columna;
      $indice = intdiv($indice - 1, 26);
    }

    return $columna;
  }
}

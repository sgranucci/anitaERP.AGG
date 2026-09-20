<?php

namespace App\Exports\Stock;

use App\Models\Stock\Mventa;
use App\Queries\Stock\ArticuloQueryInterface;
use App\Services\Stock\Articulo_MovimientoService;
use App\Support\Stock\ArticuloCombinacionFotoSupport;
use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Excel Stock por OT en formato artesanal Ferli/Tomahawk
 * (título + cabecera compacta + talles usados + PS/QM/N/TT + situación/OT/depósito + TOTAL).
 */
class StockOtExport implements FromView, WithColumnFormatting, WithMapping, WithStyles, WithColumnWidths, WithEvents, WithTitle
{
    use Exportable;

    private $desdearticulo_id;

    private $hastaarticulo_id;

    private $desdelinea_id;

    private $hastalinea_id;

    private $desdecategoria_id;

    private $hastacategoria_id;

    private $estado;

    private $mventa_id;

    private $desdelote;

    private $hastalote;

    private $imprimeFoto;

    private $estadoOt;

    private $apertura;

    private $deposito_id;

    private ArticuloQueryInterface $articuloQuery;

    private Articulo_MovimientoService $articulo_movimientoService;

    /** @var list<array<string, mixed>> */
    private array $filasDatos = [];

    /** @var list<int> */
    private array $medidasColumnas = [];

    private int $filaCabecerasExcel = 2;

    private int $filaPrimeraDatosExcel = 3;

    private int $totalColumnas = 14;

    private string $colUltima = 'N';

    private string $tituloHoja = 'Stock por OT';

    public function __construct(
        ArticuloQueryInterface $articuloquery,
        Articulo_MovimientoService $articulo_movimientoservice
    ) {
        $this->articuloQuery = $articuloquery;
        $this->articulo_movimientoService = $articulo_movimientoservice;
    }

    public function view(): View
    {
        $ret = generaRangoArticulo($this->desdearticulo_id, $this->hastaarticulo_id, $this->articuloQuery);
        $desdeArticuloRango = $ret['desdearticulorango'];
        $hastaArticuloRango = $ret['hastaarticulorango'];

        $nombremarca = 'Todas las marcas';
        if ($this->mventa_id != 0) {
            $marca = Mventa::find($this->mventa_id);
            $nombremarca = $marca ? $marca->nombre : '--';
        }

        $data = $this->articulo_movimientoService->generaDatosRepStockOt(
            $this->estado,
            $this->mventa_id,
            $desdeArticuloRango,
            $hastaArticuloRango,
            $this->desdelinea_id,
            $this->hastalinea_id,
            $this->desdecategoria_id,
            $this->hastacategoria_id,
            $this->desdelote,
            $this->hastalote,
            $this->estadoOt,
            $this->apertura,
            $this->deposito_id
        );

        if ($this->imprimeFoto === 'CON_FOTO') {
            foreach ($data as &$fila) {
                $fila['foto_path'] = ArticuloCombinacionFotoSupport::rutaAbsoluta(
                    $fila['foto'] ?? null,
                    $fila['sku'] ?? null,
                    $fila['codigo'] ?? null
                );
            }
            unset($fila);
        }

        $this->filasDatos = is_array($data) ? $data : (method_exists($data, 'all') ? $data->all() : []);
        $this->medidasColumnas = $this->resolverMedidasColumnas($this->filasDatos);
        $this->totalColumnas = ($this->imprimeFoto === 'CON_FOTO' ? 1 : 0)
            + 3 // LINEA ART DESCRIPCION
            + count($this->medidasColumnas)
            + 7; // PS QM N TT PRECIO SITUACION OT DEPOSITO
        $this->colUltima = $this->indiceAColumna($this->totalColumnas);
        $this->filaCabecerasExcel = 2;
        $this->filaPrimeraDatosExcel = 3;
        $this->tituloHoja = 'Stock por OT — '.$nombremarca;

        return view('exports.stock.reportestockot.reportestockot', [
            'data' => $this->filasDatos,
            'imprimefoto' => $this->imprimeFoto,
            'titulo' => $this->tituloHoja,
            'medidas_columnas' => $this->medidasColumnas,
            'total_columnas' => $this->totalColumnas,
        ]);
    }

    public function columnFormats(): array
    {
        return [];
    }

    public function map($row): array
    {
        return [];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => [
                'font' => [
                    'bold' => true,
                    'color' => ['rgb' => '17202A'],
                    'size' => 14,
                    'name' => 'Arial',
                ],
            ],
            $this->filaCabecerasExcel => [
                'font' => [
                    'bold' => true,
                    'color' => ['rgb' => '17202A'],
                    'size' => 10,
                    'name' => 'Arial',
                ],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'color' => ['rgb' => 'D5D8DC'],
                ],
            ],
        ];
    }

    public function columnWidths(): array
    {
        $widths = [];
        $offset = 0;
        if ($this->imprimeFoto === 'CON_FOTO') {
            $widths['A'] = 12;
            $offset = 1;
        }
        $widths[$this->indiceAColumna(1 + $offset)] = 16; // LINEA
        $widths[$this->indiceAColumna(2 + $offset)] = 12; // ART
        $widths[$this->indiceAColumna(3 + $offset)] = 22; // DESC
        $col = 4 + $offset;
        foreach ($this->medidasColumnas as $_) {
            $widths[$this->indiceAColumna($col)] = 4;
            $col++;
        }
        $widths[$this->indiceAColumna($col++)] = 6;  // PS
        $widths[$this->indiceAColumna($col++)] = 5;  // QM
        $widths[$this->indiceAColumna($col++)] = 4;  // N
        $widths[$this->indiceAColumna($col++)] = 8;  // TT
        $widths[$this->indiceAColumna($col++)] = 10; // PRECIO
        $widths[$this->indiceAColumna($col++)] = 18; // SITUACION
        $widths[$this->indiceAColumna($col++)] = 12; // OT
        $widths[$this->indiceAColumna($col)] = 10;   // DEPOSITO

        return $widths;
    }

    public function registerEvents(): array
    {
        $filas = &$this->filasDatos;
        $imprimeFoto = &$this->imprimeFoto;
        $filaDatos = &$this->filaPrimeraDatosExcel;
        $colUltima = &$this->colUltima;
        $filaCab = &$this->filaCabecerasExcel;

        return [
            AfterSheet::class => function (AfterSheet $event) use (&$filas, &$imprimeFoto, &$filaDatos, &$colUltima, &$filaCab) {
                $sheet = $event->sheet->getDelegate();

                $sheet->mergeCells('A1:'.$colUltima.'1');
                $sheet->getRowDimension(1)->setRowHeight(22);
                $sheet->getStyle('A1')->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);

                $sheet->freezePane('A'.$filaDatos);
                $sheet->getStyle('A:'.$colUltima)->getAlignment()
                    ->setVertical(Alignment::VERTICAL_CENTER);

                $ultimaFila = $sheet->getHighestRow();
                // TOTAL en negrita
                if ($ultimaFila >= $filaDatos) {
                    $sheet->getStyle('A'.$ultimaFila.':'.$colUltima.$ultimaFila)->getFont()->setBold(true);
                }

                foreach ($filas as $idx => $fila) {
                    if (empty($fila['en_produccion'])) {
                        continue;
                    }
                    $excelRow = $filaDatos + $idx;
                    $sheet->getStyle('A'.$excelRow.':'.$colUltima.$excelRow)
                        ->getFont()
                        ->getColor()
                        ->setARGB(Color::COLOR_RED);
                }

                if ($imprimeFoto !== 'CON_FOTO' || $filas === []) {
                    return;
                }

                $sheet->getColumnDimension('A')->setWidth(12);
                foreach ($filas as $idx => $fila) {
                    $excelRow = $filaDatos + $idx;
                    $path = $fila['foto_path'] ?? null;
                    if (! is_string($path) || $path === '' || ! is_file($path)) {
                        continue;
                    }
                    $sheet->getRowDimension($excelRow)->setRowHeight(70);
                    try {
                        $drawing = new Drawing;
                        $drawing->setName('foto-ot-'.$excelRow);
                        $drawing->setDescription((string) ($fila['sku'] ?? ''));
                        $drawing->setPath($path);
                        $drawing->setHeight(62);
                        $drawing->setCoordinates('A'.$excelRow);
                        $drawing->setOffsetX(2);
                        $drawing->setOffsetY(2);
                        $drawing->setWorksheet($sheet);
                    } catch (\Throwable $e) {
                        // sin foto si el archivo no es imagen válida
                    }
                }
            },
        ];
    }

    public function title(): string
    {
        return 'Stock por OT';
    }

    public function parametros(
        $estado,
        $mventa_id,
        $desdearticulo_id,
        $hastaarticulo_id,
        $desdelinea_id,
        $hastalinea_id,
        $desdecategoria_id,
        $hastacategoria_id,
        $desdelote,
        $hastalote,
        $imprimefoto,
        $estadoot,
        $apertura,
        $deposito_id
    ) {
        $this->estado = $estado;
        $this->mventa_id = $mventa_id;
        $this->desdearticulo_id = $desdearticulo_id;
        $this->hastaarticulo_id = $hastaarticulo_id;
        $this->desdelinea_id = $desdelinea_id;
        $this->hastalinea_id = $hastalinea_id;
        $this->desdecategoria_id = $desdecategoria_id;
        $this->hastacategoria_id = $hastacategoria_id;
        $this->desdelote = $desdelote;
        $this->hastalote = $hastalote;
        $this->imprimeFoto = $imprimefoto;
        $this->estadoOt = $estadoot;
        $this->apertura = $apertura;
        $this->deposito_id = $deposito_id;

        return $this;
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     * @return list<int>
     */
    private function resolverMedidasColumnas(array $filas): array
    {
        $min = null;
        $max = null;
        foreach ($filas as $fila) {
            foreach ([$fila['medidas'] ?? [], $fila['modulo'] ?? []] as $lista) {
                foreach ($lista as $m) {
                    $cant = (float) ($m['cantidad'] ?? 0);
                    if (abs($cant) < 0.0001) {
                        continue;
                    }
                    $medida = (int) ($m['medida'] ?? 0);
                    if ($medida <= 0) {
                        continue;
                    }
                    $min = $min === null ? $medida : min($min, $medida);
                    $max = $max === null ? $medida : max($max, $medida);
                }
            }
        }
        if ($min === null || $max === null) {
            $min = (int) config('consprod.DESDE_MEDIDA', 35);
            $max = (int) config('consprod.HASTA_MEDIDA', 40);
        }

        $out = [];
        for ($i = $min; $i <= $max; $i++) {
            $out[] = $i;
        }

        return $out;
    }

    private function indiceAColumna(int $indice): string
    {
        $columna = '';
        while ($indice > 0) {
            $resto = ($indice - 1) % 26;
            $columna = chr(65 + $resto).$columna;
            $indice = intdiv($indice - 1, 26);
        }

        return $columna;
    }
}

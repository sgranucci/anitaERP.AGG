<?php

declare(strict_types=1);

namespace App\Exports\Contable;

use App\Support\Configuracion\EmpresaLogoArchivo;
use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class GastronomiaDiarioPuntoventaExport implements FromView, WithColumnWidths, WithEvents, WithStyles, WithTitle
{
    private bool $hayFilaLogos = false;

    private int $filaTituloExcel = 1;

    /** Fila 1 del thead (títulos PV). */
    private int $filaCabeceraPvExcel = 4;

    /** Fila 2 del thead (medios / Neto / IVA / NC). */
    private int $filaCabeceraMediosExcel = 5;

    private int $filaPrimeraDatosExcel = 6;

    private int $cantidadColumnas = 1;

    /** @var list<string> */
    private array $rutasLogosExcel = [];

    /**
     * @param  array<string, mixed>  $resultado
     */
    public function __construct(
        private array $resultado,
    ) {
    }

    public function view(): View
    {
        $paraLogos = collect([(object) [
            'nombreempresa' => (string) ($this->resultado['empresa_nombre'] ?? ''),
        ]]);
        $this->rutasLogosExcel = EmpresaLogoArchivo::rutasLogosCabeceraDesdeColeccion($paraLogos);
        $this->hayFilaLogos = count($this->rutasLogosExcel) > 0;
        $offset = $this->hayFilaLogos ? 1 : 0;
        // logos? + título + subtítulo + resumen + cabecera PV + cabecera medios
        $this->filaTituloExcel = $offset + 1;
        $this->filaCabeceraPvExcel = $offset + 4;
        $this->filaCabeceraMediosExcel = $offset + 5;
        $this->filaPrimeraDatosExcel = $this->filaCabeceraMediosExcel + 1;

        $matriz = self::matrizAncha($this->resultado);
        $this->cantidadColumnas = max(1, (int) ($matriz['cantidad_columnas'] ?? 1));

        return view('contable.cierre_turno_gastronomia.diario_puntoventa_listado', [
            'resultado' => $this->resultado,
            'esExcel' => true,
            'reservarFilaLogoExcel' => $this->hayFilaLogos,
            'matriz' => $matriz,
        ]);
    }

    /**
     * Matriz a lo ancho: FECHA + por cada PV (medios + Venta + Neto + IVA + NC), una fila por jornada.
     *
     * @param  array<string, mixed>  $resultado
     * @return array{
     *   bloques_pv: list<array<string, mixed>>,
     *   filas: list<array<string, mixed>>,
     *   cantidad_columnas: int,
     *   labels_fila2: list<string>
     * }
     */
    public static function matrizAncha(array $resultado): array
    {
        /** @var array<int, array{puntoventa_id: int, pv_codigo: string, pv_nombre: string, medios: array<int, array{cuentacaja_id: int, codigo: string, nombre: string}>}> $pvMap */
        $pvMap = [];

        foreach ($resultado['dias'] ?? [] as $dia) {
            foreach ($dia['puntoventas'] ?? [] as $pv) {
                $pvId = (int) ($pv['puntoventa_id'] ?? 0);
                if ($pvId <= 0) {
                    $pvId = crc32((string) ($pv['pv_codigo'] ?? '').'|'.(string) ($pv['pv_nombre'] ?? ''));
                }
                if (! isset($pvMap[$pvId])) {
                    $pvMap[$pvId] = [
                        'puntoventa_id' => $pvId,
                        'pv_codigo' => (string) ($pv['pv_codigo'] ?? '—'),
                        'pv_nombre' => (string) ($pv['pv_nombre'] ?? '—'),
                        'medios' => [],
                    ];
                }
                foreach ($pv['medios'] ?? [] as $medio) {
                    $ccId = (int) ($medio['cuentacaja_id'] ?? 0);
                    if ($ccId <= 0) {
                        $ccId = crc32((string) ($medio['codigo'] ?? '').'|'.(string) ($medio['nombre'] ?? ''));
                    }
                    if (! isset($pvMap[$pvId]['medios'][$ccId])) {
                        $pvMap[$pvId]['medios'][$ccId] = [
                            'cuentacaja_id' => $ccId,
                            'codigo' => (string) ($medio['codigo'] ?? ''),
                            'nombre' => (string) ($medio['nombre'] ?? ''),
                        ];
                    }
                }
            }
        }

        uasort($pvMap, static function (array $a, array $b): int {
            return strcmp($a['pv_codigo'], $b['pv_codigo']);
        });

        $bloquesPv = [];
        $labelsFila2 = [];
        $cantidadColumnas = 1; // FECHA
        /** @var array<int, array{cuentacaja_id: int, codigo: string, nombre: string}> $mediosUnion */
        $mediosUnion = [];

        foreach ($pvMap as $pv) {
            $medios = array_values($pv['medios']);
            usort($medios, static fn (array $a, array $b): int => strcmp(
                (string) ($a['codigo'] ?? ''),
                (string) ($b['codigo'] ?? ''),
            ));

            $labelsMedios = [];
            foreach ($medios as $medio) {
                $ccId = (int) ($medio['cuentacaja_id'] ?? 0);
                if (! isset($mediosUnion[$ccId])) {
                    $mediosUnion[$ccId] = $medio;
                }
                $codigo = trim((string) ($medio['codigo'] ?? ''));
                $nombre = trim((string) ($medio['nombre'] ?? ''));
                // Encabezado: nombre de la cuenta de caja (fallback al código).
                $label = $nombre !== '' ? $nombre : ($codigo !== '' ? $codigo : 'Medio');
                $labelsMedios[] = $label;
                $labelsFila2[] = $label;
            }
            $labelsFila2[] = 'Venta';
            $labelsFila2[] = 'Neto';
            $labelsFila2[] = 'IVA';
            $labelsFila2[] = 'NC';

            $colsBloque = count($medios) + 4; // medios + Venta + Neto + IVA + NC
            $bloquesPv[] = [
                'puntoventa_id' => $pv['puntoventa_id'],
                'pv_codigo' => $pv['pv_codigo'],
                'pv_nombre' => $pv['pv_nombre'],
                'titulo' => self::tituloPv($pv['pv_codigo'], $pv['pv_nombre']),
                'medios' => $medios,
                'cantidad_columnas' => $colsBloque,
                'labels_medios' => $labelsMedios,
                'es_total_dia' => false,
            ];
            $cantidadColumnas += $colsBloque;
        }

        // Bloque final: totales del día (unión de medios + Neto + IVA + NC).
        $mediosTotales = array_values($mediosUnion);
        usort($mediosTotales, static fn (array $a, array $b): int => strcmp(
            (string) ($a['nombre'] ?? $a['codigo'] ?? ''),
            (string) ($b['nombre'] ?? $b['codigo'] ?? ''),
        ));
        $labelsMediosTotales = [];
        foreach ($mediosTotales as $medio) {
            $codigo = trim((string) ($medio['codigo'] ?? ''));
            $nombre = trim((string) ($medio['nombre'] ?? ''));
            $label = $nombre !== '' ? $nombre : ($codigo !== '' ? $codigo : 'Medio');
            $labelsMediosTotales[] = $label;
            $labelsFila2[] = $label;
        }
        $labelsFila2[] = 'Venta';
        $labelsFila2[] = 'Neto';
        $labelsFila2[] = 'IVA';
        $labelsFila2[] = 'NC';
        $colsTotalDia = count($mediosTotales) + 4;
        $bloqueTotalDia = [
            'puntoventa_id' => 0,
            'pv_codigo' => '',
            'pv_nombre' => '',
            'titulo' => 'TOTAL DÍA',
            'medios' => $mediosTotales,
            'cantidad_columnas' => $colsTotalDia,
            'labels_medios' => $labelsMediosTotales,
            'es_total_dia' => true,
        ];
        $bloquesPv[] = $bloqueTotalDia;
        $cantidadColumnas += $colsTotalDia;

        $filas = [];
        foreach ($resultado['dias'] ?? [] as $dia) {
            /** @var array<int, array<string, mixed>> $pvPorId */
            $pvPorId = [];
            foreach ($dia['puntoventas'] ?? [] as $pv) {
                $pvId = (int) ($pv['puntoventa_id'] ?? 0);
                if ($pvId <= 0) {
                    $pvId = crc32((string) ($pv['pv_codigo'] ?? '').'|'.(string) ($pv['pv_nombre'] ?? ''));
                }
                $pvPorId[$pvId] = $pv;
            }

            $valores = [];
            /** @var array<int, float> $netoMediosDia */
            $netoMediosDia = [];
            $ventaBrutaDia = 0.0;
            $ventaNetoDia = 0.0;
            $ventaIvaDia = 0.0;
            $ncDia = 0.0;

            foreach ($bloquesPv as $bloque) {
                if (! empty($bloque['es_total_dia'])) {
                    continue;
                }

                $pvId = (int) $bloque['puntoventa_id'];
                $pvDia = $pvPorId[$pvId] ?? null;

                if ($pvDia === null) {
                    foreach ($bloque['medios'] as $_) {
                        $valores[] = null;
                    }
                    $valores[] = null; // Venta
                    $valores[] = null; // Neto
                    $valores[] = null; // IVA
                    $valores[] = null; // NC
                    continue;
                }

                /** @var array<int, float> $netoPorMedio */
                $netoPorMedio = [];
                foreach ($pvDia['medios'] ?? [] as $medio) {
                    $ccId = (int) ($medio['cuentacaja_id'] ?? 0);
                    if ($ccId <= 0) {
                        $ccId = crc32((string) ($medio['codigo'] ?? '').'|'.(string) ($medio['nombre'] ?? ''));
                    }
                    $neto = (float) ($medio['neto'] ?? 0);
                    $netoPorMedio[$ccId] = $neto;
                    $netoMediosDia[$ccId] = ($netoMediosDia[$ccId] ?? 0.0) + $neto;
                }

                foreach ($bloque['medios'] as $medioDef) {
                    $ccId = (int) $medioDef['cuentacaja_id'];
                    $valores[] = array_key_exists($ccId, $netoPorMedio)
                        ? round($netoPorMedio[$ccId], 2)
                        : 0.0;
                }
                $brutoPv = round((float) ($pvDia['venta_bruta'] ?? 0), 2);
                $netoPv = round((float) ($pvDia['venta_neto'] ?? 0), 2);
                $ivaPv = round((float) ($pvDia['venta_iva'] ?? 0), 2);
                $ncPv = round((float) ($pvDia['total_nc'] ?? 0), 2);
                $valores[] = $brutoPv;
                $valores[] = $netoPv;
                $valores[] = $ivaPv;
                $valores[] = $ncPv;
                $ventaBrutaDia += $brutoPv;
                $ventaNetoDia += $netoPv;
                $ventaIvaDia += $ivaPv;
                $ncDia += $ncPv;
            }

            // Columnas TOTAL DÍA (medios + Neto + IVA + NC).
            foreach ($mediosTotales as $medioDef) {
                $ccId = (int) $medioDef['cuentacaja_id'];
                $valores[] = round((float) ($netoMediosDia[$ccId] ?? 0), 2);
            }
            $totalesDia = $dia['totales'] ?? [];
            $valores[] = round((float) ($totalesDia['venta_bruta'] ?? $ventaBrutaDia), 2);
            $valores[] = round((float) ($totalesDia['venta_neto'] ?? $ventaNetoDia), 2);
            $valores[] = round((float) ($totalesDia['venta_iva'] ?? $ventaIvaDia), 2);
            $valores[] = round((float) ($totalesDia['total_nc'] ?? $ncDia), 2);

            $filas[] = [
                'fecha' => (string) ($dia['fecha_jornada_fmt'] ?? ''),
                'valores' => $valores,
            ];
        }

        return [
            'bloques_pv' => array_values($bloquesPv),
            'filas' => $filas,
            'cantidad_columnas' => $cantidadColumnas,
            'labels_fila2' => $labelsFila2,
        ];
    }

    /**
     * Compatibilidad con llamadas previas del controller PDF.
     *
     * @param  array<string, mixed>  $resultado
     * @return list<array<string, mixed>>
     */
    public static function aplanarFilas(array $resultado): array
    {
        $matriz = self::matrizAncha($resultado);
        $out = [];
        foreach ($matriz['filas'] as $fila) {
            $out[] = [
                'fecha' => $fila['fecha'],
                'valores' => $fila['valores'],
            ];
        }

        return $out;
    }

    private static function tituloPv(string $codigo, string $nombre): string
    {
        $codigo = trim($codigo);
        $nombre = trim($nombre);
        if ($codigo === '' || $codigo === '—') {
            return $nombre !== '' ? $nombre : 'Punto de venta';
        }
        if ($nombre === '' || $nombre === '—' || strcasecmp($codigo, $nombre) === 0) {
            return 'PV '.$codigo;
        }

        return 'PV '.$codigo.' — '.$nombre;
    }

    public function title(): string
    {
        return 'Diario PV gastro';
    }

    public function columnWidths(): array
    {
        $widths = ['A' => 12];
        for ($i = 2; $i <= max(2, $this->cantidadColumnas); $i++) {
            $widths[Coordinate::stringFromColumnIndex($i)] = 14;
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
                $colUltima = Coordinate::stringFromColumnIndex(max(1, $this->cantidadColumnas));
                $ultimaFila = $sheet->getHighestRow();

                if ($this->hayFilaLogos) {
                    $sheet->getRowDimension(1)->setRowHeight(54);
                    $offsetX = 5;
                    foreach ($this->rutasLogosExcel as $ruta) {
                        if (! is_file($ruta)) {
                            continue;
                        }
                        $drawing = new Drawing();
                        $drawing->setPath($ruta);
                        $drawing->setHeight(48);
                        $drawing->setCoordinates('A1');
                        $drawing->setOffsetX($offsetX);
                        $drawing->setWorksheet($sheet);
                        $offsetX += 90;
                    }
                }

                // Localizar encabezado real (el HTML de dos tablas puede desplazar filas).
                $filaCabPv = $this->filaCabeceraPvExcel;
                for ($r = 1; $r <= $ultimaFila; $r++) {
                    if (trim((string) $sheet->getCell('A'.$r)->getValue()) === 'FECHA') {
                        $filaCabPv = $r;
                        break;
                    }
                }
                $filaCabMedios = $filaCabPv + 1;
                $filaDatos = $filaCabPv + 2;
                $filaTitulo = max(1, $filaCabPv - 3);

                $sheet->mergeCells('A'.$filaTitulo.':'.$colUltima.$filaTitulo);
                if ($filaTitulo + 1 < $filaCabPv) {
                    $sheet->mergeCells('A'.($filaTitulo + 1).':'.$colUltima.($filaTitulo + 1));
                }
                if ($filaTitulo + 2 < $filaCabPv) {
                    $sheet->mergeCells('A'.($filaTitulo + 2).':'.$colUltima.($filaTitulo + 2));
                }

                $sheet->getStyle('A'.$filaTitulo)->getFont()
                    ->setName('Arial')->setSize(14)->setBold(true)->getColor()->setRGB('17202A');
                if ($filaTitulo + 1 < $filaCabPv) {
                    $sheet->getStyle('A'.($filaTitulo + 1))->getFont()
                        ->setName('Arial')->setSize(10)->setBold(true)->getColor()->setRGB('444444');
                }
                $sheet->getRowDimension($filaTitulo)->setRowHeight(26);

                // Encabezado doble (títulos PV + medios) en celeste.
                $rangoCab = 'A'.$filaCabPv.':'.$colUltima.$filaCabMedios;
                $sheet->getStyle($rangoCab)->getFill()
                    ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('85C1E9');
                $sheet->getStyle($rangoCab)->getFont()
                    ->setName('Arial')->setSize(9)->setBold(true)->getColor()->setRGB('17202A');
                $sheet->getStyle($rangoCab)->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                    ->setVertical(Alignment::VERTICAL_CENTER)
                    ->setWrapText(true);
                $sheet->getStyle($rangoCab)->getBorders()->getAllBorders()
                    ->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('5DADE2');

                $sheet->getRowDimension($filaCabPv)->setRowHeight(28);
                $sheet->getRowDimension($filaCabMedios)->setRowHeight(30);

                // Resaltar bloque TOTAL DÍA.
                $colInicioTotal = null;
                $colFinTotal = null;
                for ($c = 1; $c <= max(1, $this->cantidadColumnas); $c++) {
                    $coord = Coordinate::stringFromColumnIndex($c).$filaCabPv;
                    $val = trim((string) $sheet->getCell($coord)->getValue());
                    if ($val !== 'TOTAL DÍA') {
                        continue;
                    }
                    $colInicioTotal = $c;
                    $colFinTotal = max(1, $this->cantidadColumnas);
                    foreach ($sheet->getMergeCells() as $range) {
                        if (str_starts_with($range, $coord.':')) {
                            [, $fin] = Coordinate::rangeBoundaries($range);
                            $colFinTotal = (int) $fin[0];
                            break;
                        }
                    }
                    break;
                }
                if ($colInicioTotal !== null && $colFinTotal !== null) {
                    $rangoTotalCab = Coordinate::stringFromColumnIndex($colInicioTotal).$filaCabPv
                        .':'.Coordinate::stringFromColumnIndex($colFinTotal).$filaCabMedios;
                    $sheet->getStyle($rangoTotalCab)->getFill()
                        ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('5DADE2');
                    if ($ultimaFila >= $filaDatos) {
                        $rangoTotalDatos = Coordinate::stringFromColumnIndex($colInicioTotal).$filaDatos
                            .':'.Coordinate::stringFromColumnIndex($colFinTotal).$ultimaFila;
                        $sheet->getStyle($rangoTotalDatos)->getFont()->setBold(true);
                        $sheet->getStyle($rangoTotalDatos)->getFill()
                            ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('D6EAF8');
                    }
                }

                // Congela FECHA (col A) + las dos filas de encabezado.
                $sheet->freezePane('B'.$filaDatos);

                if ($ultimaFila >= $filaDatos) {
                    $sheet->getStyle('A'.$filaDatos.':A'.$ultimaFila)
                        ->getFont()->setBold(true)->setName('Arial');
                    $sheet->getStyle('A'.$filaDatos.':A'.$ultimaFila)
                        ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    if ($this->cantidadColumnas > 1) {
                        $sheet->getStyle('B'.$filaDatos.':'.$colUltima.$ultimaFila)
                            ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                        $sheet->getStyle('B'.$filaDatos.':'.$colUltima.$ultimaFila)
                            ->getFont()->setName('Arial')->setSize(9);
                    }
                }
            },
        ];
    }
}

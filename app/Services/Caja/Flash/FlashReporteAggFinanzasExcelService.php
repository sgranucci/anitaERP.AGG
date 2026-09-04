<?php

namespace App\Services\Caja\Flash;

use App\Models\Caja\Flash\FlashCaja;
use App\Models\Configuracion\Empresa;
use App\Support\Caja\Flash\FlashCajaLFlashCalculoSupport;
use App\Support\Caja\Flash\FlashCajaReporteSupport;
use App\Support\Caja\Flash\FlashReporteAggPerfilVistaSupport;
use App\Support\Configuracion\EmpresaLogoArchivo;
use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use RuntimeException;
use stdClass;

/**
 * Excel acotado del Flash AGG para el perfil Finanzas (pedido Betania / equipo finanzas).
 */
class FlashReporteAggFinanzasExcelService
{
    private const COL_ULTIMA = 'I';

    /** @var array<string, float> */
    private const ANCHOS = [
        'A' => 12,
        'B' => 18,
        'C' => 18,
        'D' => 16,
        'E' => 16,
        'F' => 16,
        'G' => 16,
        'H' => 20,
        'I' => 16,
    ];

    public function __construct(
        private FlashReporteAggExcelService $excelCompleto,
    ) {}

    /**
     * @return array{path: string, nombre: string, mime: string, dias: int, empresas: list<string>, tabla_resumen?: list<list<array{texto: string, negrita: bool, rojo: bool, encabezado: bool}>>, perfil_vista: string}
     */
    public function generar(Carbon $desde, Carbon $hasta): array
    {
        $mapa = $this->excelCompleto->mapaEmpresas();
        if ($mapa === []) {
            throw new RuntimeException('No hay empresas AGG (Biyemas / Kandiko / Rebisco) para armar el Flash Finanzas.');
        }

        $columnas = FlashReporteAggPerfilVistaSupport::columnasFinanzas();
        /** @var list<array{titulo: string, empresa_nombre: string|null, filas: list<array<string, float|int|string>>}> $hojas */
        $hojas = [];
        $nombres = [];
        $diasConDatos = 0;

        $filasConsolFinanzas = $this->filasFinanzasEmpresa($desde, $hasta, 0, true, array_map('intval', array_keys($mapa)));
        $diasConDatos = max($diasConDatos, $this->contarDiasConActividad($filasConsolFinanzas));
        $hojas[] = [
            'titulo' => 'Consolidados',
            'empresa_nombre' => null,
            'filas' => $filasConsolFinanzas,
        ];

        foreach ($mapa as $empresaId => $meta) {
            $empresa = Empresa::query()->find($empresaId);
            $filasFinanzas = $this->filasFinanzasEmpresa($desde, $hasta, (int) $empresaId, false);
            $diasConDatos = max($diasConDatos, $this->contarDiasConActividad($filasFinanzas));
            $titulo = (string) ($empresa->nombre ?? $meta['hoja'] ?? 'Empresa '.$empresaId);
            $nombres[] = $titulo;
            $hojas[] = [
                'titulo' => $titulo,
                'empresa_nombre' => $titulo,
                'filas' => $filasFinanzas,
            ];
        }

        $ss = new Spreadsheet;
        $ss->removeSheetByIndex(0);
        $idx = 0;
        foreach ($hojas as $hoja) {
            $sheet = $ss->createSheet($idx);
            $sheet->setTitle($this->tituloHojaSeguro((string) $hoja['titulo'], $idx));
            $this->escribirHoja(
                $sheet,
                $columnas,
                $hoja['filas'],
                $desde,
                $hasta,
                $hoja['empresa_nombre'],
            );
            $idx++;
        }
        $ss->setActiveSheetIndex(0);

        $nombre = sprintf(
            'Flash Report AGG Finanzas al %s.xlsx',
            $hasta->format('d.m.Y')
        );
        $dir = $this->directorioTemporal();
        $path = $dir.'/flash-reporte-agg-finanzas-'.uniqid('', true).'.xlsx';
        (new Xlsx($ss))->save($path);
        $ss->disconnectWorksheets();

        return [
            'path' => $path,
            'nombre' => $nombre,
            'mime' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'dias' => $diasConDatos,
            'empresas' => $nombres,
            'tabla_resumen' => $this->resumenMail($filasConsolFinanzas, $hasta),
            'perfil_vista' => FlashReporteAggPerfilVistaSupport::FINANZAS,
        ];
    }

    /**
     * @param  list<int>|null  $empresaIds
     * @return list<array<string, float|int|string>>
     */
    private function filasFinanzasEmpresa(
        Carbon $desde,
        Carbon $hasta,
        int $empresaId,
        bool $consolidado,
        ?array $empresaIds = null,
    ): array {
        $empresa = $consolidado ? null : Empresa::query()->find($empresaId);
        $ids = $consolidado
            ? ($empresaIds ?? [])
            : [$empresaId];

        $reporte = FlashCajaReporteSupport::armarHistorico(
            collect(),
            $empresa,
            $desde->format('Y-m-d'),
            $hasta->format('Y-m-d'),
            true,
            $ids,
        );
        $porDia = [];
        foreach ($reporte['filas_diarias'] ?? [] as $fila) {
            $iso = (string) ($fila['fecha_iso'] ?? '');
            if ($iso === '') {
                continue;
            }
            $porDia[(int) Carbon::parse($iso)->day] = $fila;
        }

        $out = [];
        $cursor = $desde->copy()->startOfDay();
        while ($cursor->lte($hasta)) {
            $dia = (int) $cursor->day;
            $metricas = $porDia[$dia] ?? $this->metricasVaciasDelDia($cursor, $consolidado ? 0 : $empresaId);
            $out[] = FlashReporteAggPerfilVistaSupport::filaFinanzasDesdeMetricas($metricas, $cursor);
            $cursor->addDay();
        }

        return $this->quitarDiasVaciosAlFinal($out);
    }

    /**
     * @return array<string, mixed>
     */
    private function metricasVaciasDelDia(Carbon $fecha, int $empresaId): array
    {
        $parametro = $empresaId > 0
            ? FlashCajaLFlashCalculoSupport::cargarParametro($empresaId, $fecha->format('Ym'))
            : null;
        $indice = $empresaId > 0
            ? FlashCajaLFlashCalculoSupport::cargarIndice($empresaId, $fecha->format('Y-m-d'))
            : null;
        $flash = new FlashCaja([
            'empresa_id' => $empresaId,
            'fecha' => $fecha->format('Y-m-d'),
        ]);

        return FlashCajaLFlashCalculoSupport::enriquecerConBudgetYSeason(
            FlashCajaLFlashCalculoSupport::metricasDesdeFlash($flash),
            $parametro,
            $indice,
            $fecha,
            true,
        );
    }

    /**
     * @param  list<array<string, float|int|string>>  $filas
     * @return list<array<string, float|int|string>>
     */
    /**
     * @param  list<array<string, float|int|string>>  $filas  Filas ya en formato finanzas
     * @return list<array<string, float|int|string>>
     */
    private function quitarDiasVaciosAlFinal(array $filas): array
    {
        for ($i = count($filas) - 1; $i >= 0; $i--) {
            if ($this->filaFinanzasConActividad($filas[$i])) {
                return array_slice($filas, 0, $i + 1);
            }
        }

        return $filas;
    }

    /**
     * @param  array<string, float|int|string>  $f
     */
    private function filaFinanzasConActividad(array $f): bool
    {
        return (float) ($f['coin_in'] ?? 0) != 0.0
            || (float) ($f['drop'] ?? 0) != 0.0
            || (float) ($f['win_online'] ?? 0) != 0.0
            || (float) ($f['win_financiero'] ?? 0) != 0.0
            || (float) ($f['ventas_bingo'] ?? 0) != 0.0
            || (float) ($f['ventas_parking'] ?? 0) != 0.0
            || (float) ($f['ventas_gastronomia'] ?? 0) != 0.0
            || (float) ($f['ventas_vending'] ?? 0) != 0.0;
    }

    /**
     * @param  list<array<string, float|int|string>>  $filas
     */
    private function contarDiasConActividad(array $filas): int
    {
        $n = 0;
        foreach ($filas as $f) {
            if ($this->filaFinanzasConActividad($f)) {
                $n++;
            }
        }

        return $n;
    }

    /**
     * @param  array<string, string>  $columnas
     * @param  list<array<string, float|int|string>>  $filas
     */
    private function escribirHoja(
        Worksheet $sheet,
        array $columnas,
        array $filas,
        Carbon $desde,
        Carbon $hasta,
        ?string $empresaNombre,
    ): void {
        $rutasLogos = $this->rutasLogosParaHoja($empresaNombre);
        $hayLogos = $rutasLogos !== [];
        $fila = 1;

        if ($hayLogos) {
            $sheet->getRowDimension(1)->setRowHeight(54);
            foreach ($rutasLogos as $idx => $ruta) {
                if (! is_readable($ruta)) {
                    continue;
                }
                $drawing = new Drawing;
                $drawing->setPath($ruta);
                $drawing->setResizeProportional(true);
                $drawing->setHeight(46);
                $drawing->setCoordinates('A1');
                $drawing->setOffsetX(6 + $idx * 160);
                $drawing->setOffsetY(4);
                $drawing->setWorksheet($sheet);
            }
            $fila = 2;
        }

        $etiquetaEmpresa = $empresaNombre !== null && $empresaNombre !== ''
            ? $empresaNombre
            : 'Consolidados AGG ('.(string) config('app.empresa').')';

        $sheet->setCellValue('A'.$fila, 'Flash Report AGG — vista Finanzas');
        $sheet->mergeCells('A'.$fila.':'.self::COL_ULTIMA.$fila);
        $sheet->getStyle('A'.$fila)->applyFromArray([
            'font' => ['bold' => true, 'size' => 16, 'name' => 'Arial', 'color' => ['rgb' => '17202A']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension($fila)->setRowHeight(28);
        $fila++;

        $sheet->setCellValue('A'.$fila, 'Generado '.now()->format('d/m/Y H:i'));
        $sheet->mergeCells('A'.$fila.':'.self::COL_ULTIMA.$fila);
        $sheet->getStyle('A'.$fila)->applyFromArray([
            'font' => ['bold' => true, 'size' => 10, 'name' => 'Arial', 'color' => ['rgb' => '444444']],
        ]);
        $fila++;

        $sheet->setCellValue('A'.$fila, 'Empresa: '.$etiquetaEmpresa);
        $sheet->mergeCells('A'.$fila.':'.self::COL_ULTIMA.$fila);
        $sheet->getStyle('A'.$fila)->applyFromArray([
            'font' => ['bold' => true, 'size' => 10, 'name' => 'Arial', 'color' => ['rgb' => '444444']],
        ]);
        $fila++;

        $sheet->setCellValue(
            'A'.$fila,
            sprintf(
                'Período %s al %s · Coin in, drop, win online/financiero, ventas bingo, parking, gastronomía y vending',
                $desde->format('d/m/Y'),
                $hasta->format('d/m/Y')
            )
        );
        $sheet->mergeCells('A'.$fila.':'.self::COL_ULTIMA.$fila);
        $sheet->getStyle('A'.$fila)->applyFromArray([
            'font' => ['bold' => true, 'size' => 10, 'name' => 'Arial', 'color' => ['rgb' => '444444']],
            'alignment' => ['wrapText' => true],
        ]);
        $sheet->getRowDimension($fila)->setRowHeight(32);
        $fila++;

        $filaCabecera = $fila;
        $colKeys = array_keys($columnas);
        $colIdx = 1;
        foreach ($columnas as $titulo) {
            $coord = Coordinate::stringFromColumnIndex($colIdx).$filaCabecera;
            $sheet->setCellValue($coord, $titulo);
            $colIdx++;
        }
        $sheet->getStyle('A'.$filaCabecera.':'.self::COL_ULTIMA.$filaCabecera)->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => '17202A'], 'size' => 11, 'name' => 'Arial'],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '85C1E9'],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
                'wrapText' => true,
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => 'CCCCCC'],
                ],
            ],
        ]);
        $sheet->getRowDimension($filaCabecera)->setRowHeight(30);

        $filaExcel = $filaCabecera + 1;
        $filaPrimeraDatos = $filaExcel;
        foreach ($filas as $filaDatos) {
            $colIdx = 1;
            foreach ($colKeys as $clave) {
                $coord = Coordinate::stringFromColumnIndex($colIdx).$filaExcel;
                $valor = $filaDatos[$clave] ?? '';
                $sheet->setCellValue($coord, $valor);
                if ($clave !== 'fecha' && is_numeric($valor)) {
                    $sheet->getStyle($coord)->getNumberFormat()->setFormatCode('#,##0.00');
                    $sheet->getStyle($coord)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                }
                $colIdx++;
            }
            $filaExcel++;
        }

        $filaTotal = $filaExcel;
        $totales = FlashReporteAggPerfilVistaSupport::totalesFinanzas($filas);
        $sheet->setCellValue('A'.$filaTotal, 'Total');
        $colIdx = 2;
        foreach (array_slice($colKeys, 1) as $clave) {
            $coord = Coordinate::stringFromColumnIndex($colIdx).$filaTotal;
            $sheet->setCellValue($coord, $totales[$clave] ?? 0);
            $sheet->getStyle($coord)->getNumberFormat()->setFormatCode('#,##0.00');
            $sheet->getStyle($coord)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $colIdx++;
        }
        $sheet->getStyle('A'.$filaTotal.':'.self::COL_ULTIMA.$filaTotal)->applyFromArray([
            'font' => ['bold' => true, 'name' => 'Arial', 'color' => ['rgb' => '17202A']],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'F5F5F5'],
            ],
        ]);

        if ($filaTotal >= $filaPrimeraDatos) {
            $sheet->getStyle('A'.$filaPrimeraDatos.':'.self::COL_ULTIMA.$filaTotal)->applyFromArray([
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['rgb' => 'CCCCCC'],
                    ],
                ],
                'font' => ['name' => 'Arial', 'size' => 10],
            ]);
        }

        foreach (self::ANCHOS as $col => $ancho) {
            $sheet->getColumnDimension($col)->setWidth($ancho);
        }
        $sheet->freezePane('A'.$filaPrimeraDatos);
    }

    /**
     * @return list<string>
     */
    private function rutasLogosParaHoja(?string $empresaNombre): array
    {
        if ($empresaNombre !== null && $empresaNombre !== '') {
            $row = new stdClass;
            $row->nombreempresa = $empresaNombre;

            return EmpresaLogoArchivo::rutasLogosCabeceraDesdeColeccion([$row]);
        }

        return EmpresaLogoArchivo::rutasLogosCabeceraDesdeColeccion(collect());
    }

    /**
     * @param  list<array<string, float|int|string>>  $filasConsol
     * @return list<list<array{texto: string, negrita: bool, rojo: bool, encabezado: bool}>>
     */
    private function resumenMail(array $filasConsol, Carbon $hasta): array
    {
        $totales = FlashReporteAggPerfilVistaSupport::totalesFinanzas($filasConsol);
        $ultimo = $filasConsol !== [] ? $filasConsol[array_key_last($filasConsol)] : [];
        $fmt = static fn (float $n): string => number_format($n, 2, ',', '.');

        $celda = static function (string $texto, bool $negrita = false, bool $encabezado = false): array {
            return [
                'texto' => $texto,
                'negrita' => $negrita,
                'rojo' => false,
                'encabezado' => $encabezado,
            ];
        };

        $columnas = FlashReporteAggPerfilVistaSupport::columnasFinanzas();
        unset($columnas['fecha']);

        $cabecera = [$celda('Consolidado', true, true)];
        foreach ($columnas as $titulo) {
            $cabecera[] = $celda($titulo, true, true);
        }

        $dia = [$celda('Día '.$hasta->format('d/m'), true)];
        $mtd = [$celda('MTD', true)];
        foreach (array_keys($columnas) as $clave) {
            $dia[] = $celda($fmt((float) ($ultimo[$clave] ?? 0)));
            $mtd[] = $celda($fmt((float) ($totales[$clave] ?? 0)), true);
        }

        return [$cabecera, $dia, $mtd];
    }

    private function tituloHojaSeguro(string $nombre, int $idx): string
    {
        $nombre = preg_replace('/[\\\\\\/\\?\\*\\[\\]:]/', '', $nombre) ?? $nombre;
        $nombre = trim(mb_substr($nombre, 0, 31));
        if ($nombre === '') {
            $nombre = 'Hoja '.($idx + 1);
        }

        return $nombre;
    }

    private function directorioTemporal(): string
    {
        $candidatos = [
            storage_path('app/tmp'),
            storage_path('app'),
            sys_get_temp_dir(),
        ];
        foreach ($candidatos as $dir) {
            if (! is_dir($dir)) {
                @mkdir($dir, 0775, true);
            }
            if (is_dir($dir) && is_writable($dir)) {
                return $dir;
            }
        }

        throw new RuntimeException('No hay directorio temporal escribible para el Flash Finanzas.');
    }
}

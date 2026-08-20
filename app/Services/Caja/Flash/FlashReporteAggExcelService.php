<?php

namespace App\Services\Caja\Flash;

use App\Models\Caja\Flash\FlashCaja;
use App\Models\Configuracion\Empresa;
use App\Support\Caja\Flash\FlashCajaLFlashCalculoSupport;
use App\Support\Caja\Flash\FlashCajaReporteSupport;
use App\Support\Caja\Flash\FlashReporteAggMapeoSupport;
use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use RuntimeException;

/**
 * Completa la plantilla oficial Flash Report AGG a partir de flash_caja.
 */
class FlashReporteAggExcelService
{
    public const FILA_DATOS_INICIO = 9;

    public const FILA_PRESENTACION_INICIO = 7;

    public const DIAS_MAX = 31;

    /**
     * @return array{path: string, nombre: string, mime: string, dias: int, empresas: list<string>}
     */
    public function generar(Carbon $desde, Carbon $hasta): array
    {
        $plantilla = $this->rutaPlantilla();
        if (! is_file($plantilla)) {
            throw new RuntimeException('No está la plantilla Flash Report AGG: '.$plantilla);
        }

        $mapa = $this->mapaEmpresas();
        if ($mapa === []) {
            throw new RuntimeException('No hay empresas AGG (Biyemas / Kandiko / Rebisco) para armar el Flash Report.');
        }

        $spreadsheet = IOFactory::load($plantilla);
        $diasConDatos = 0;
        $nombres = [];

        foreach ($mapa as $empresaId => $hojas) {
            $empresa = Empresa::query()->find($empresaId);
            $reporte = FlashCajaReporteSupport::armarHistorico(
                collect(),
                $empresa,
                $desde->format('Y-m-d'),
                $hasta->format('Y-m-d'),
                true,
                [$empresaId],
            );
            $porDia = $this->indexarPorDia($reporte['filas_diarias'] ?? []);
            $filasDatos = $this->filasDatosDelMes($desde, $hasta, $empresaId, $porDia);
            $diasConDatos = max($diasConDatos, count(array_filter(
                $filasDatos,
                fn (array $f) => (int) ($f['C'] ?? 0) > 0 || (float) ($f['AU'] ?? 0) != 0.0
            )));

            $this->escribirHojaDatos(
                $spreadsheet,
                (string) $hojas['datos'],
                $filasDatos,
                $desde,
                $hasta,
                $empresa,
            );
            $this->escribirHojaPresentacion(
                $spreadsheet,
                (string) $hojas['hoja'],
                $filasDatos,
                $reporte,
                $desde,
                $hasta,
            );
            $nombres[] = (string) ($empresa->nombre ?? $hojas['hoja']);
        }

        $reporteConsol = FlashCajaReporteSupport::armarHistorico(
            collect(),
            null,
            $desde->format('Y-m-d'),
            $hasta->format('Y-m-d'),
            true,
            array_map('intval', array_keys($mapa)),
        );
        $porDiaConsol = $this->indexarPorDia($reporteConsol['filas_diarias'] ?? []);
        $filasConsol = $this->filasDatosDelMes($desde, $hasta, 0, $porDiaConsol, true);
        $this->escribirHojaDatos(
            $spreadsheet,
            'Datos Consolidados',
            $filasConsol,
            $desde,
            $hasta,
            null,
        );
        $this->escribirHojaPresentacion(
            $spreadsheet,
            'Resumen',
            $filasConsol,
            $reporteConsol,
            $desde,
            $hasta,
        );

        $nombre = sprintf(
            'Flash Report AGG al %s.xlsx',
            $hasta->format('d.m.Y')
        );
        $dir = storage_path('app/tmp');
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $path = $dir.'/flash-reporte-agg-'.uniqid('', true).'.xlsx';

        $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
        // La plantilla oficial tiene fórmulas que PhpSpreadsheet no puede resolver
        // (p. ej. Resumen!AZ26 → "internal error"). Excel las calcula al abrir.
        $writer->setPreCalculateFormulas(false);
        $writer->save($path);
        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);

        return [
            'path' => $path,
            'nombre' => $nombre,
            'mime' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'dias' => $diasConDatos,
            'empresas' => $nombres,
        ];
    }

    /**
     * @return array<int, array{hoja: string, datos: string}>
     */
    public function mapaEmpresas(): array
    {
        $cfg = config('caja.flash_reporte_agg.empresas', []);
        $out = [];
        foreach ($cfg as $id => $hojas) {
            $empresaId = (int) $id;
            if ($empresaId <= 0 || ! is_array($hojas)) {
                continue;
            }
            if (Empresa::query()->whereKey($empresaId)->exists()) {
                $out[$empresaId] = [
                    'hoja' => (string) ($hojas['hoja'] ?? ''),
                    'datos' => (string) ($hojas['datos'] ?? ''),
                ];
            }
        }

        if ($out !== []) {
            return $out;
        }

        $alias = [
            'biyemas' => ['hoja' => 'Biyemas S.A.', 'datos' => 'Datos Biyemas'],
            'kandiko' => ['hoja' => 'Kandiko S.A', 'datos' => 'Datos Kandiko'],
            'rebisco' => ['hoja' => 'Rebisco S.A.', 'datos' => 'Datos Rebisco'],
        ];
        foreach (Empresa::query()->orderBy('id')->get(['id', 'nombre']) as $empresa) {
            $nombre = mb_strtolower((string) $empresa->nombre);
            foreach ($alias as $clave => $hojas) {
                if (str_contains($nombre, $clave)) {
                    $out[(int) $empresa->id] = $hojas;
                }
            }
        }

        return $out;
    }

    public function rutaPlantilla(): string
    {
        $cfg = (string) config('caja.flash_reporte_agg.plantilla', '');
        if ($cfg !== '' && is_file($cfg)) {
            return $cfg;
        }

        return resource_path('templates/caja/flash/plantilla-flash-agg.xlsx');
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     * @return array<int, array<string, mixed>>
     */
    private function indexarPorDia(array $filas): array
    {
        $out = [];
        foreach ($filas as $fila) {
            $iso = (string) ($fila['fecha_iso'] ?? '');
            if ($iso === '') {
                continue;
            }
            $out[(int) Carbon::parse($iso)->day] = $fila;
        }

        return $out;
    }

    /**
     * @param  array<int, array<string, mixed>>  $porDia
     * @return list<array<string, float|int|string>>
     */
    private function filasDatosDelMes(
        Carbon $desde,
        Carbon $hasta,
        int $empresaId,
        array $porDia,
        bool $consolidado = false,
    ): array {
        $out = [];
        $cursor = $desde->copy()->startOfDay();
        while ($cursor->lte($hasta)) {
            $dia = (int) $cursor->day;
            if (isset($porDia[$dia])) {
                $out[] = FlashReporteAggMapeoSupport::filaDatos($porDia[$dia], $cursor);
            } else {
                $metricas = $this->metricasVaciasDelDia($cursor, $consolidado ? 0 : $empresaId);
                $out[] = FlashReporteAggMapeoSupport::filaDatos($metricas, $cursor);
            }
            $cursor->addDay();
        }

        return $out;
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
     */
    private function escribirHojaDatos(
        Spreadsheet $spreadsheet,
        string $nombreHoja,
        array $filas,
        Carbon $desde,
        Carbon $hasta,
        ?Empresa $empresa,
    ): void {
        $sheet = $this->hoja($spreadsheet, $nombreHoja);
        if ($sheet === null) {
            return;
        }

        $sheet->setCellValue('A1', now()->translatedFormat('D M j H:i:s T Y'));
        $sheet->setCellValue('A2', 'Consolidated Income');
        $sheet->setCellValue(
            'A3',
            'Desde '.$desde->format('d/m/y').' hasta '.$hasta->format('d/m/y').' '
        );
        $sheet->setCellValue(
            'A4',
            $empresa !== null
                ? 'Empresas: '.(int) $empresa->id.' '.(string) $empresa->nombre
                : 'Empresas: consolidado AGG'
        );
        $sheet->setCellValue('A5', 'Through day: '.$hasta->format('j'));

        for ($i = 0; $i < self::DIAS_MAX; $i++) {
            $excelRow = self::FILA_DATOS_INICIO + $i;
            if (! isset($filas[$i])) {
                $this->limpiarRangoDatos($sheet, $excelRow);
                continue;
            }
            foreach ($filas[$i] as $col => $valor) {
                $sheet->setCellValue($col.$excelRow, $valor);
            }
        }
    }

    /**
     * @param  list<array<string, float|int|string>>  $filas
     * @param  array<string, mixed>  $reporte
     */
    private function escribirHojaPresentacion(
        Spreadsheet $spreadsheet,
        string $nombreHoja,
        array $filas,
        array $reporte,
        Carbon $desde,
        Carbon $hasta,
    ): void {
        $sheet = $this->hoja($spreadsheet, $nombreHoja);
        if ($sheet === null) {
            return;
        }

        if ($nombreHoja !== 'Resumen') {
            $sheet->setCellValue('A3', FlashReporteAggMapeoSupport::tituloMes($desde));
        }

        for ($i = 0; $i < self::DIAS_MAX; $i++) {
            $excelRow = self::FILA_PRESENTACION_INICIO + $i;
            if (! isset($filas[$i])) {
                $sheet->setCellValue('BP'.$excelRow, null);
                continue;
            }
            $sheet->setCellValue('BP'.$excelRow, $filas[$i]['A']);
        }

        $mesAnt = is_array($reporte['comparativo_mes_ant'] ?? null) ? $reporte['comparativo_mes_ant'] : [];
        $anioAnt = is_array($reporte['comparativo_anio_ant'] ?? null) ? $reporte['comparativo_anio_ant'] : [];

        $sheet->setCellValue('A44', FlashReporteAggMapeoSupport::tituloMesLargo($desde->copy()->subMonthNoOverflow()));
        $this->escribirTotalesComparativo($sheet, 45, 46, $mesAnt);

        $sheet->setCellValue('A53', FlashReporteAggMapeoSupport::tituloMes($desde->copy()->subYear()));
        $this->escribirTotalesComparativo($sheet, 54, 55, $anioAnt);
    }

    /**
     * @param  array<string, mixed>  $periodo
     */
    private function escribirTotalesComparativo(Worksheet $sheet, int $filaTotal, int $filaMtd, array $periodo): void
    {
        $total = is_array($periodo['total_final'] ?? null) ? $periodo['total_final'] : [];
        $mtd = is_array($periodo['mtd_average'] ?? null) ? $periodo['mtd_average'] : [];
        if ($total === []) {
            return;
        }

        $this->escribirResumenMetricas($sheet, $filaTotal, $total, 'Total final     ');
        if ($mtd !== []) {
            $this->escribirResumenMetricas($sheet, $filaMtd, $mtd, 'MTD Average');
        }
    }

    /**
     * @param  array<string, mixed>  $m
     */
    private function escribirResumenMetricas(Worksheet $sheet, int $fila, array $m, string $etiqueta): void
    {
        $sheet->setCellValue('A'.$fila, $etiqueta);
        $sheet->setCellValue('C'.$fila, (int) ($m['custom'] ?? 0));
        $sheet->setCellValue('E'.$fila, (float) ($m['slot_coin_in'] ?? 0));
        $sheet->setCellValue('F'.$fila, (float) ($m['slot_drop'] ?? 0));
        $sheet->setCellValue('G'.$fila, (float) ($m['slot_ol_win'] ?? 0));
        $sheet->setCellValue('M'.$fila, (float) ($m['rul_coin_in'] ?? 0));
        $sheet->setCellValue('N'.$fila, (float) ($m['rul_drop'] ?? 0));
        $sheet->setCellValue('O'.$fila, (float) ($m['rul_ol_win'] ?? 0));
        $sheet->setCellValue('AE'.$fila, (float) ($m['win_financial'] ?? 0));
        $sheet->setCellValue('AG'.$fila, (int) ($m['bingo_carton'] ?? 0));
        $sheet->setCellValue('AH'.$fila, (float) ($m['bingo_venta'] ?? 0));
        $sheet->setCellValue('AI'.$fila, (float) ($m['bingo_win'] ?? 0));
        $sheet->setCellValue('AL'.$fila, (float) ($m['ayb'] ?? 0));
        $sheet->setCellValue('AN'.$fila, (float) ($m['estac'] ?? 0));
        $sheet->setCellValue('AU'.$fila, (float) ($m['revenues'] ?? 0));
    }

    private function limpiarRangoDatos(Worksheet $sheet, int $fila): void
    {
        foreach (['A', 'B', 'C', 'D', 'E', 'F', 'G', 'L', 'M', 'N', 'O', 'AD', 'AE', 'AG', 'AH', 'AI', 'AL', 'AN', 'AU'] as $col) {
            $sheet->setCellValue($col.$fila, null);
        }
    }

    private function hoja(Spreadsheet $spreadsheet, string $nombre): ?Worksheet
    {
        try {
            return $spreadsheet->getSheetByName($nombre);
        } catch (\Throwable $e) {
            return null;
        }
    }
}

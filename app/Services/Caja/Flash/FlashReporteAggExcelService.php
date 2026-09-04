<?php

namespace App\Services\Caja\Flash;

use App\Models\Caja\Flash\FlashCaja;
use App\Models\Configuracion\Empresa;
use App\Support\Caja\Flash\FlashCajaLFlashCalculoSupport;
use App\Support\Caja\Flash\FlashCajaReporteSupport;
use App\Support\Caja\Flash\FlashReporteAggMapeoSupport;
use App\Support\Caja\Flash\FlashReporteAggPerfilVistaSupport;
use App\Support\Caja\Flash\FlashReporteAggXlsxPatchSupport;
use Carbon\Carbon;
use RuntimeException;

/**
 * Completa la plantilla oficial Flash Report AGG a partir de flash_caja.
 */
class FlashReporteAggExcelService
{
    public const FILA_DATOS_INICIO = 9;

    public const FILA_PRESENTACION_INICIO = 7;

    public const DIAS_MAX = 31;

    public const HOJA_PRESENTACION_MAESTRA = 'Biyemas S.A.';

    /**
     * BP de un día sin fila: no puede quedar vacío.
     * En Marcela, A = SI(BP = Datos!A; …) y C/G = SI(BP = A; …).
     * Vacío = vacío da verdadero y las filas 29–31 leen Total final / MTD;
     * COUNT(C7:C37) pasa a 31, el INDEX de Recaudación toma el promedio
     * y el SUM del mes se cae con #N/A.
     */
    public const MARCA_DIA_SIN_DATOS = '-';

    /**
     * @return array{path: string, nombre: string, mime: string, dias: int, empresas: list<string>, imagen_path?: string, tabla_resumen?: list<list<array{texto: string, negrita: bool, rojo: bool, encabezado: bool}>>, perfil_vista?: string}
     */
    public function generar(Carbon $desde, Carbon $hasta, string $perfilVista = FlashReporteAggPerfilVistaSupport::COMPLETA): array
    {
        $perfilVista = FlashReporteAggPerfilVistaSupport::normalizar($perfilVista);
        if (FlashReporteAggPerfilVistaSupport::esFinanzas($perfilVista)) {
            $archivo = app(FlashReporteAggFinanzasExcelService::class)->generar($desde, $hasta);
            $archivo['perfil_vista'] = FlashReporteAggPerfilVistaSupport::FINANZAS;

            return $archivo;
        }

        return $this->generarCompleto($desde, $hasta);
    }

    /**
     * @return array{path: string, nombre: string, mime: string, dias: int, empresas: list<string>, imagen_path?: string, tabla_resumen?: list<list<array{texto: string, negrita: bool, rojo: bool, encabezado: bool}>>, perfil_vista: string}
     */
    private function generarCompleto(Carbon $desde, Carbon $hasta): array
    {
        $plantilla = $this->rutaPlantilla();
        if (! is_file($plantilla)) {
            throw new RuntimeException('No está la plantilla Flash Report AGG: '.$plantilla);
        }

        $mapa = $this->mapaEmpresas();
        if ($mapa === []) {
            throw new RuntimeException('No hay empresas AGG (Biyemas / Kandiko / Rebisco) para armar el Flash Report.');
        }

        $porHoja = [];
        $diasConDatos = 0;
        $nombres = [];
        $filasRef = [];

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
            $porHoja[(string) $hojas['datos']] = $this->celdasHojaDatos(
                $filasDatos,
                $desde,
                $hasta,
                $empresa,
                $reporte,
            );
            if ($filasDatos !== []) {
                $filasRef = $filasDatos;
            }
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
        $porHoja['Datos Consolidados'] = $this->celdasHojaDatos(
            $filasConsol,
            $desde,
            $hasta,
            null,
            $reporteConsol,
        );
        if ($filasRef === [] && $filasConsol !== []) {
            $filasRef = $filasConsol;
        }
        $porHoja[self::HOJA_PRESENTACION_MAESTRA] = $this->celdasHojaPresentacionMaestra($filasRef, $desde);

        $nombre = sprintf(
            'Flash Report AGG al %s.xlsx',
            $hasta->format('d.m.Y')
        );
        $dir = $this->directorioTemporal();
        $path = $dir.'/flash-reporte-agg-'.uniqid('', true).'.xlsx';
        $imagenPath = $dir.'/flash-reporte-agg-tabla-'.uniqid('', true).'.png';

        $extra = (new FlashReporteAggXlsxPatchSupport)->rellenar($plantilla, $path, $porHoja, $imagenPath);

        $out = [
            'path' => $path,
            'nombre' => $nombre,
            'mime' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'dias' => $diasConDatos,
            'empresas' => $nombres,
            'tabla_resumen' => $extra['tabla_resumen'] ?? [],
            'perfil_vista' => FlashReporteAggPerfilVistaSupport::COMPLETA,
        ];
        if (is_file($imagenPath)) {
            $out['imagen_path'] = $imagenPath;
        }

        return $out;
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

        throw new RuntimeException('No hay directorio temporal escribible para armar el Flash Report AGG.');
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

        return $this->quitarDiasVaciosAlFinal($out);
    }

    /**
     * @param  list<array<string, float|int|string>>  $filas
     * @return list<array<string, float|int|string>>
     */
    private function quitarDiasVaciosAlFinal(array $filas): array
    {
        while ($filas !== [] && $this->filaDatosSinMovimiento($filas[array_key_last($filas)])) {
            array_pop($filas);
        }

        return array_values($filas);
    }

    /**
     * @param  list<array<string, float|int|string>>  $filas
     */
    private function throughDay(array $filas, Carbon $hasta): string
    {
        if ($filas === []) {
            return $hasta->format('j');
        }

        $ultima = $filas[array_key_last($filas)]['B'] ?? '';
        if (is_string($ultima) && preg_match('/(\d{1,2})\//', $ultima, $m)) {
            return (string) (int) $m[1];
        }

        return $hasta->format('j');
    }

    /**
     * @param  array<string, float|int|string>  $fila
     */
    private function filaDatosSinMovimiento(array $fila): bool
    {
        return (int) ($fila['C'] ?? 0) === 0
            && abs((float) ($fila['G'] ?? 0)) < 0.00001
            && abs((float) ($fila['O'] ?? 0)) < 0.00001;
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
     * @param  array<string, mixed>  $reporte
     * @return array<string, float|int|string|null>
     */
    private function celdasHojaDatos(
        array $filas,
        Carbon $desde,
        Carbon $hasta,
        ?Empresa $empresa,
        array $reporte,
    ): array {
        $celdas = [
            'A1' => now()->translatedFormat('D M j H:i:s T Y'),
            'A2' => 'Consolidated Income',
            'A3' => 'Desde '.$desde->format('d/m/y').' hasta '.$hasta->format('d/m/y').' ',
            'A4' => $empresa !== null
                ? 'Empresas: '.(int) $empresa->id.' '.(string) $empresa->nombre
                : 'Empresas: consolidado AGG',
            'A5' => 'Through day: '.$this->throughDay($filas, $hasta),
        ];

        // Filas 6–8: títulos oficiales (HLOOKUP Electronic / máscara Tabla).
        $celdas = array_merge($celdas, FlashReporteAggMapeoSupport::encabezadosHojaDatos());

        $budgetMes = is_array($reporte['budget_mes'] ?? null)
            ? $reporte['budget_mes']
            : FlashCajaLFlashCalculoSupport::budgetDesdeParametro(null);
        $celdas = array_merge(
            $celdas,
            FlashReporteAggMapeoSupport::celdasDenominadoresBudgetFila8($budgetMes)
        );

        for ($i = 0; $i < self::DIAS_MAX; $i++) {
            $excelRow = self::FILA_DATOS_INICIO + $i;
            if (! isset($filas[$i])) {
                continue;
            }
            foreach ($filas[$i] as $col => $valor) {
                if ($valor === '' || $valor === null) {
                    continue;
                }
                $celdas[$col.$excelRow] = $valor;
            }
        }

        return array_merge($celdas, $this->celdasConsolidadosDatos($filas, $reporte, $desde));
    }

    /**
     * @param  list<array<string, float|int|string>>  $filas
     * @return array<string, float|int|string|null>
     */
    private function celdasHojaPresentacionMaestra(array $filas, Carbon $desde): array
    {
        $celdas = [
            'A3' => FlashReporteAggMapeoSupport::tituloMes($desde),
            'A44' => FlashReporteAggMapeoSupport::tituloMesLargo($desde->copy()->subMonthNoOverflow()),
            'A53' => FlashReporteAggMapeoSupport::tituloMes($desde->copy()->subYear()),
        ];

        for ($i = 0; $i < self::DIAS_MAX; $i++) {
            $excelRow = self::FILA_PRESENTACION_INICIO + $i;
            if (! isset($filas[$i])) {
                $celdas['BP'.$excelRow] = self::MARCA_DIA_SIN_DATOS;
                continue;
            }
            $celdas['BP'.$excelRow] = $filas[$i]['A'];
        }

        return $celdas;
    }

    /**
     * Bloque Total / MTD / comparativos de las hojas Datos (como el l-flash de Marcela).
     * Las VLOOKUP de presentación buscan estas etiquetas en A9:BB145.
     *
     * @param  list<array<string, float|int|string>>  $filas
     * @param  array<string, mixed>  $reporte
     * @return array<string, float|int|string|null>
     */
    private function celdasConsolidadosDatos(array $filas, array $reporte, Carbon $desde): array
    {
        if ($filas === []) {
            return [];
        }

        $filasBloque = FlashReporteAggMapeoSupport::filasConsolidados(
            self::FILA_DATOS_INICIO + count($filas) - 1
        );
        $celdas = [];
        $total = is_array($reporte['total_final'] ?? null) ? $reporte['total_final'] : [];
        $mtd = is_array($reporte['mtd_average'] ?? null) ? $reporte['mtd_average'] : [];
        $mesAnt = is_array($reporte['comparativo_mes_ant'] ?? null) ? $reporte['comparativo_mes_ant'] : [];
        $anioAnt = is_array($reporte['comparativo_anio_ant'] ?? null) ? $reporte['comparativo_anio_ant'] : [];
        $mesAntTotal = is_array($mesAnt['total_final'] ?? null) ? $mesAnt['total_final'] : [];
        $mesAntMtd = is_array($mesAnt['mtd_average'] ?? null) ? $mesAnt['mtd_average'] : [];
        $anioAntTotal = is_array($anioAnt['total_final'] ?? null) ? $anioAnt['total_final'] : [];
        $anioAntMtd = is_array($anioAnt['mtd_average'] ?? null) ? $anioAnt['mtd_average'] : [];

        $this->agregarFilaComparativo(
            $celdas,
            $filasBloque['total_final'],
            $total,
            FlashReporteAggMapeoSupport::ETIQUETA_TOTAL_FINAL,
            $desde,
        );
        $this->agregarFilaComparativo(
            $celdas,
            $filasBloque['mtd_average'],
            $mtd,
            FlashReporteAggMapeoSupport::ETIQUETA_MTD_AVERAGE,
            $desde,
        );

        $celdas['A'.$filasBloque['titulo_mes_ant']] = FlashReporteAggMapeoSupport::tituloComparativoMesAnt($desde);
        $this->agregarFilaComparativo(
            $celdas,
            $filasBloque['total_mes_ant'],
            $mesAntTotal,
            FlashReporteAggMapeoSupport::ETIQUETA_TOTAL_MES_ANT,
            $desde,
        );
        $this->agregarFilaComparativo(
            $celdas,
            $filasBloque['mtd_mes_ant'],
            $mesAntMtd,
            FlashReporteAggMapeoSupport::ETIQUETA_MTD_MES_ANT,
            $desde,
        );
        $this->agregarFilaVariacion(
            $celdas,
            $filasBloque['prom_pct_mes_ant'],
            $mtd,
            $mesAntMtd,
            FlashReporteAggMapeoSupport::ETIQUETA_PROM_PCT_MES_ANT,
            true,
        );
        $this->agregarFilaVariacion(
            $celdas,
            $filasBloque['prom_monto_mes_ant'],
            $mtd,
            $mesAntMtd,
            FlashReporteAggMapeoSupport::ETIQUETA_PROM_MONTO_MES_ANT,
            false,
        );

        $celdas['A'.$filasBloque['titulo_anio_ant']] = FlashReporteAggMapeoSupport::tituloComparativoAnioAnt($desde);
        $this->agregarFilaComparativo(
            $celdas,
            $filasBloque['total_anio_ant'],
            $anioAntTotal,
            FlashReporteAggMapeoSupport::ETIQUETA_TOTAL_ANIO_ANT,
            $desde,
        );
        $this->agregarFilaComparativo(
            $celdas,
            $filasBloque['mtd_anio_ant'],
            $anioAntMtd,
            FlashReporteAggMapeoSupport::ETIQUETA_MTD_ANIO_ANT,
            $desde,
        );
        $this->agregarFilaVariacion(
            $celdas,
            $filasBloque['prom_pct_anio_ant'],
            $mtd,
            $anioAntMtd,
            FlashReporteAggMapeoSupport::ETIQUETA_PROM_PCT_ANIO_ANT,
            true,
        );
        $this->agregarFilaVariacion(
            $celdas,
            $filasBloque['prom_monto_anio_ant'],
            $mtd,
            $anioAntMtd,
            FlashReporteAggMapeoSupport::ETIQUETA_PROM_MONTO_ANIO_ANT,
            false,
        );

        return $celdas;
    }

    /**
     * @param  array<string, float|int|string|null>  $celdas
     * @param  array<string, mixed>  $metricas
     */
    private function agregarFilaComparativo(
        array &$celdas,
        int $fila,
        array $metricas,
        string $etiqueta,
        Carbon $fecha,
    ): void {
        $valores = FlashReporteAggMapeoSupport::filaDatos($metricas, $fecha);
        $valores['A'] = $etiqueta;
        $valores['B'] = ' ';
        foreach ($valores as $col => $valor) {
            if ($valor === '' || $valor === null) {
                continue;
            }
            $celdas[$col.$fila] = $valor;
        }
    }

    /**
     * @param  array<string, float|int|string|null>  $celdas
     * @param  array<string, mixed>  $actual
     * @param  array<string, mixed>  $base
     */
    private function agregarFilaVariacion(
        array &$celdas,
        int $fila,
        array $actual,
        array $base,
        string $etiqueta,
        bool $porcentaje,
    ): void {
        $celdas['A'.$fila] = $etiqueta;
        $celdas['B'.$fila] = ' ';
        $valores = $porcentaje
            ? FlashReporteAggMapeoSupport::filaPromedioDiarioPct($actual, $base)
            : FlashReporteAggMapeoSupport::filaPromedioDiarioMonto($actual, $base);
        foreach ($valores as $col => $valor) {
            $celdas[$col.$fila] = $valor;
        }
    }
}

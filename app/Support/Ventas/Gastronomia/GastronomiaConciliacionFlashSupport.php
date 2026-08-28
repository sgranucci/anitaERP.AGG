<?php

declare(strict_types=1);

namespace App\Support\Ventas\Gastronomia;

use App\Support\Caja\AnitaSync\RendicionGastronomiaAnitaRendgastroSupport;
use Illuminate\Support\Facades\Log;

/**
 * Control flash (caja Informix): flash_ayb / flash_estac vs rendgastro neto por unidad de negocio.
 *
 * Informix no discrimina vending: flash_ayb = AyB + vending (el ERP los guarda separados
 * y el export a Anita los suma). FLASH-GASTRO compara flash_ayb contra gastro+vending.
 */
final class GastronomiaConciliacionFlashSupport
{
    public function __construct(
        private readonly RendicionGastronomiaAnitaRendgastroSupport $rendgastroSupport,
        private readonly GastronomiaConciliacionVendingRendgSupport $vendingSupport,
    ) {
    }

    /**
     * @param  list<array<string, mixed>>  $filasDia
     * @param  array{flash_ayb: float, flash_estac: float, total_flash: float}|null  $flashPrecargado
     * @param  string|null  $fechaFlashEtiqueta  Si el cuadro es de otra jornada (ej. día anterior), se anota en la descripción.
     * @return list<array<string, mixed>>
     */
    public function armarControl(
        int $empresaId,
        string $fechaJornada,
        array $filasDia,
        bool $jornadaAbierta,
        float $tolerancia,
        ?array $flashPrecargado = null,
        ?string $fechaFlashEtiqueta = null,
    ): array {
        unset($jornadaAbierta);

        $totalesErp = $this->totalesErpGastroEstacionamiento($filasDia);
        $rendgPorUnidad = $this->cargarRendgPorUnidadNegocio($empresaId, $fechaJornada);
        $vending = $this->cargarVendingParaFlashAyb($empresaId, $fechaJornada, $filasDia);

        $flashAyb = round((float) ($flashPrecargado['flash_ayb'] ?? 0), 2);
        $flashEstac = round((float) ($flashPrecargado['flash_estac'] ?? 0), 2);

        return [
            $this->armarFilaSegmento(
                'gastro',
                $totalesErp,
                $rendgPorUnidad,
                $flashAyb,
                $tolerancia,
                $fechaFlashEtiqueta,
                $vending,
            ),
            $this->armarFilaSegmento(
                'estacionamiento',
                $totalesErp,
                $rendgPorUnidad,
                $flashEstac,
                $tolerancia,
                $fechaFlashEtiqueta,
                ['erp' => 0.0, 'rendg' => 0.0, 'incluido_en_ayb' => false],
            ),
        ];
    }

    /**
     * @param  array{
     *   ventas_erp_gastro: float,
     *   ventas_erp_estacionamiento: float
     * }  $totalesErp
     * @param  array{gastro: float|null, estacionamiento: float|null}  $rendgPorUnidad
     * @param  array{erp: float, rendg: float, incluido_en_ayb: bool}  $vending
     * @return array<string, mixed>
     */
    private function armarFilaSegmento(
        string $segmento,
        array $totalesErp,
        array $rendgPorUnidad,
        float $flashValor,
        float $tolerancia,
        ?string $fechaFlashEtiqueta = null,
        array $vending = ['erp' => 0.0, 'rendg' => 0.0, 'incluido_en_ayb' => false],
    ): array {
        $esGastro = $segmento === 'gastro';
        $incluyeVending = $esGastro && (bool) ($vending['incluido_en_ayb'] ?? false);
        $vendingErp = $incluyeVending ? round((float) ($vending['erp'] ?? 0), 2) : 0.0;
        $vendingRendg = $incluyeVending ? round((float) ($vending['rendg'] ?? 0), 2) : 0.0;

        $ventasErpBase = $esGastro
            ? (float) $totalesErp['ventas_erp_gastro']
            : (float) $totalesErp['ventas_erp_estacionamiento'];
        $rendgNetoBase = $esGastro
            ? $rendgPorUnidad['gastro']
            : $rendgPorUnidad['estacionamiento'];

        $ventasErp = round($ventasErpBase + $vendingErp, 2);
        $rendgNeto = $rendgNetoBase === null
            ? null
            : round((float) $rendgNetoBase + $vendingRendg, 2);

        $flashAyb = $esGastro ? $flashValor : 0.0;
        $flashEstac = $esGastro ? 0.0 : $flashValor;

        $sinActividad = abs($ventasErp) <= $tolerancia
            && ($rendgNeto === null || abs((float) $rendgNeto) <= $tolerancia)
            && abs($flashValor) <= $tolerancia;

        $diffErpFlash = $ventasErp <= $tolerancia && $flashValor <= $tolerancia
            ? null
            : round($ventasErp - $flashValor, 2);
        $diffRendgFlash = $rendgNeto === null
            ? null
            : round((float) $rendgNeto - $flashValor, 2);

        $estado = $sinActividad
            ? '—'
            : ($rendgNeto === null
                ? 'SIN RENDG'
                : ($this->cuadranFlashRendg((float) $rendgNeto, $flashValor, $tolerancia) ? 'OK' : 'DIF'));

        $sufijoFecha = $fechaFlashEtiqueta !== null && $fechaFlashEtiqueta !== ''
            ? ' [jornada flash '.$fechaFlashEtiqueta.']'
            : '';

        $descripcion = $esGastro
            ? ($incluyeVending
                ? 'Flash gastro: flash_ayb Anita (AyB+vending, sin discriminar) vs ERP/Rendg gastro+vending'
                : 'Flash gastro: flash_ayb (Informix caja) vs rendgastro salón (AyB)')
            : 'Flash estacionamiento: flash_estac (Informix caja) vs rendgastro estacionamiento';

        return [
            'tipo_fila' => $esGastro ? 'control_flash_gastro' : 'control_flash_estacionamiento',
            'circuito' => 'FLASH',
            'identificador_pc' => $esGastro ? 'FLASH-GASTRO' : 'FLASH-ESTAC',
            'tipo_pv' => 'EMPRESA',
            'pv_codigo' => '—',
            'descripcion_pc' => $descripcion.$sufijoFecha,
            'pv_cae' => '—',
            'pv_caea' => '—',
            'ventas_erp_cae' => 0.0,
            'ventas_erp_caea' => 0.0,
            'ventas_erp' => $ventasErp,
            'ventas_erp_gastro_base' => $esGastro ? round($ventasErpBase, 2) : null,
            'ventas_anita_cae' => 0.0,
            'ventas_anita_caea' => 0.0,
            'ventas_anita' => null,
            'rendgastro_z' => $rendgNeto,
            'rendgastro_neto' => $rendgNeto,
            'rendgastro_gastro_base' => $esGastro && $rendgNetoBase !== null ? round((float) $rendgNetoBase, 2) : null,
            'vending_erp' => $incluyeVending ? $vendingErp : null,
            'vending_rendg' => $incluyeVending ? $vendingRendg : null,
            'flash_ayb_incluye_vending' => $incluyeVending,
            'flash_ayb' => $flashAyb,
            'flash_estac' => $flashEstac,
            'total_flash' => $flashValor,
            'diff_erp_anita' => null,
            'diff_erp_rendg' => null,
            'diff_erp_flash' => $diffErpFlash,
            'diff_anita_flash' => null,
            'diff_rendg_flash' => $diffRendgFlash,
            'estado' => $estado,
            'cantidad_facturas_erp' => null,
            'es_control_flash' => true,
            'segmento_flash' => $segmento,
            'fecha_flash' => $fechaFlashEtiqueta,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $filasDia
     * @return array{erp: float, rendg: float, incluido_en_ayb: bool}
     */
    private function cargarVendingParaFlashAyb(int $empresaId, string $fechaJornada, array $filasDia): array
    {
        if (! $this->flashAybIncluyeVending()) {
            return ['erp' => 0.0, 'rendg' => 0.0, 'incluido_en_ayb' => false];
        }

        $vendingErp = $this->vendingErpDesdeFilasOConsulta($empresaId, $fechaJornada, $filasDia);
        $vendingRendg = $this->vendingRendgDelDia($empresaId, $fechaJornada);

        return [
            'erp' => $vendingErp,
            'rendg' => $vendingRendg,
            'incluido_en_ayb' => true,
        ];
    }

    private function flashAybIncluyeVending(): bool
    {
        return (bool) config(
            'gastronomia.conciliacion_diaria_reporte.control_flash_ayb_incluye_vending',
            true,
        );
    }

    /**
     * @param  list<array<string, mixed>>  $filasDia
     */
    private function vendingErpDesdeFilasOConsulta(int $empresaId, string $fechaJornada, array $filasDia): float
    {
        foreach ($filasDia as $fila) {
            if (($fila['tipo_fila'] ?? '') === 'total_vending') {
                return round((float) ($fila['ventas_erp'] ?? 0), 2);
            }
        }

        try {
            $map = $this->vendingSupport->totalesMaquinavendingErpPorJornada(
                $empresaId,
                $fechaJornada,
                $fechaJornada,
            );

            return round((float) ($map[$fechaJornada] ?? 0), 2);
        } catch (\Throwable $e) {
            Log::warning('gastronomia.conciliacion_diaria_reporte.flash_vending_erp_fallo', [
                'empresa_id' => $empresaId,
                'fecha_jornada' => $fechaJornada,
                'msg' => $e->getMessage(),
            ]);

            return 0.0;
        }
    }

    private function vendingRendgDelDia(int $empresaId, string $fechaJornada): float
    {
        $fechaEntera = (int) str_replace('-', '', $fechaJornada);
        if ($empresaId <= 0 || $fechaEntera <= 0) {
            return 0.0;
        }

        try {
            $cabeceras = $this->rendgastroSupport->listarCabecerasEmpresaFechaDetalle($empresaId, $fechaEntera);
            $totales = $this->vendingSupport->ventaAnitaVendingDesdeRendg($empresaId, $cabeceras);

            return round((float) ($totales['total'] ?? 0), 2);
        } catch (\Throwable $e) {
            Log::warning('gastronomia.conciliacion_diaria_reporte.flash_vending_rendg_fallo', [
                'empresa_id' => $empresaId,
                'fecha_jornada' => $fechaJornada,
                'msg' => $e->getMessage(),
            ]);

            return 0.0;
        }
    }

    /**
     * @param  list<array<string, mixed>>  $filasDia
     * @return array{ventas_erp_gastro: float, ventas_erp_estacionamiento: float}
     */
    private function totalesErpGastroEstacionamiento(array $filasDia): array
    {
        $gastroErp = 0.0;
        $estacErp = 0.0;
        $tieneTotalEstac = false;

        foreach ($filasDia as $fila) {
            $tipo = (string) ($fila['tipo_fila'] ?? '');
            if ($tipo === 'total_gastro') {
                $gastroErp = round((float) ($fila['ventas_erp'] ?? 0), 2);
            }
            if ($tipo === 'total_estacionamiento') {
                $tieneTotalEstac = true;
                $estacErp = round((float) ($fila['ventas_erp'] ?? 0), 2);
            }
        }

        if (! $tieneTotalEstac) {
            foreach ($filasDia as $fila) {
                if (($fila['tipo_fila'] ?? '') !== 'estacionamiento_pv') {
                    continue;
                }
                $estacErp = round($estacErp + (float) ($fila['ventas_erp'] ?? 0), 2);
            }
        }

        return [
            'ventas_erp_gastro' => $gastroErp,
            'ventas_erp_estacionamiento' => $estacErp,
        ];
    }

    /**
     * @return array{gastro: float|null, estacionamiento: float|null}
     */
    private function cargarRendgPorUnidadNegocio(int $empresaId, string $fechaJornada): array
    {
        $fechaEntera = (int) str_replace('-', '', $fechaJornada);
        if ($empresaId <= 0 || $fechaEntera <= 0) {
            return ['gastro' => null, 'estacionamiento' => null];
        }

        try {
            $totales = $this->rendgastroSupport->totalesNetoRendgPorUnidadNegocio($empresaId, $fechaEntera);

            return [
                'gastro' => $totales['gastro'],
                'estacionamiento' => $totales['estacionamiento'],
            ];
        } catch (\Throwable $e) {
            Log::warning('gastronomia.conciliacion_diaria_reporte.flash_rendg_fallo', [
                'empresa_id' => $empresaId,
                'fecha_jornada' => $fechaJornada,
                'msg' => $e->getMessage(),
            ]);

            return ['gastro' => null, 'estacionamiento' => null];
        }
    }

    private function cuadranFlashRendg(float $rendgNeto, float $flashValor, float $tolerancia): bool
    {
        return abs($rendgNeto - $flashValor) <= $tolerancia;
    }
}

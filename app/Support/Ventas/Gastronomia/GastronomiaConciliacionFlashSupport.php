<?php

declare(strict_types=1);

namespace App\Support\Ventas\Gastronomia;

use App\Support\Caja\AnitaSync\RendicionGastronomiaAnitaRendgastroSupport;
use Illuminate\Support\Facades\Log;

/**
 * Control flash (caja Informix): flash_ayb / flash_estac vs rendgastro neto por unidad de negocio.
 */
final class GastronomiaConciliacionFlashSupport
{
    public function __construct(
        private readonly RendicionGastronomiaAnitaRendgastroSupport $rendgastroSupport,
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
            ),
            $this->armarFilaSegmento(
                'estacionamiento',
                $totalesErp,
                $rendgPorUnidad,
                $flashEstac,
                $tolerancia,
                $fechaFlashEtiqueta,
            ),
        ];
    }

    /**
     * @param  array{
     *   ventas_erp_gastro: float,
     *   ventas_erp_estacionamiento: float
     * }  $totalesErp
     * @param  array{gastro: float|null, estacionamiento: float|null}  $rendgPorUnidad
     * @return array<string, mixed>
     */
    private function armarFilaSegmento(
        string $segmento,
        array $totalesErp,
        array $rendgPorUnidad,
        float $flashValor,
        float $tolerancia,
        ?string $fechaFlashEtiqueta = null,
    ): array {
        $esGastro = $segmento === 'gastro';

        $ventasErp = $esGastro
            ? (float) $totalesErp['ventas_erp_gastro']
            : (float) $totalesErp['ventas_erp_estacionamiento'];
        $rendgNeto = $esGastro
            ? $rendgPorUnidad['gastro']
            : $rendgPorUnidad['estacionamiento'];

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

        return [
            'tipo_fila' => $esGastro ? 'control_flash_gastro' : 'control_flash_estacionamiento',
            'circuito' => 'FLASH',
            'identificador_pc' => $esGastro ? 'FLASH-GASTRO' : 'FLASH-ESTAC',
            'tipo_pv' => 'EMPRESA',
            'pv_codigo' => '—',
            'descripcion_pc' => ($esGastro
                ? 'Flash gastro: flash_ayb (Informix caja) vs rendgastro salón (AyB)'
                : 'Flash estacionamiento: flash_estac (Informix caja) vs rendgastro estacionamiento'
            ).$sufijoFecha,
            'pv_cae' => '—',
            'pv_caea' => '—',
            'ventas_erp_cae' => 0.0,
            'ventas_erp_caea' => 0.0,
            'ventas_erp' => $ventasErp,
            'ventas_anita_cae' => 0.0,
            'ventas_anita_caea' => 0.0,
            'ventas_anita' => null,
            'rendgastro_z' => $rendgNeto,
            'rendgastro_neto' => $rendgNeto,
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

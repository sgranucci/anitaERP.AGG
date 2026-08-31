<?php

declare(strict_types=1);

namespace App\Services\Contable\CanonMunicipal;

use App\Support\Contable\CanonMunicipal\CanonMunicipalCalendarioSupport;
use App\Support\Contable\CanonMunicipal\CanonMunicipalCruceSupport;
use App\Support\Contable\CanonMunicipal\CanonMunicipalFichaSupport;
use App\Support\Contable\CanonMunicipal\CanonMunicipalListadoFiltros;
use Illuminate\Support\Facades\Cache;

final class CanonMunicipalReporteService
{
    private const CACHE_TTL = 2700;

    public function __construct(
        private readonly CanonMunicipalCruceSupport $cruceSupport,
    ) {
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>
     */
    public function generarOCache(array $filtros): array
    {
        $cached = $this->leerCache($filtros);
        if ($cached !== null) {
            return $cached;
        }

        $resultado = $this->generar($filtros);
        $this->guardarCache($filtros, $resultado);

        return $resultado;
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>
     */
    public function generar(array $filtros): array
    {
        $empresaId = (int) ($filtros['empresa_id'] ?? 0);
        $ficha = CanonMunicipalFichaSupport::resolver($empresaId);
        if ($ficha === null) {
            return [
                'mensaje_config' => 'La empresa no tiene configuración de canon municipal activa. Carguela en Configuración.',
                'cuadra' => false,
                'puede_emitir_nota' => false,
                'filas' => [],
                'resumen' => [],
                'ficha' => null,
            ];
        }

        $desde = (string) ($filtros['fecha_desde'] ?? '');
        $hasta = (string) ($filtros['fecha_hasta'] ?? '');
        $cruce = $this->cruceSupport->cruzar($empresaId, $desde, $hasta);

        $alicuota = (float) ($ficha['alicuota'] ?? 0.04);
        if ($alicuota <= 0) {
            $alicuota = 0.04;
        }

        $filas = [];
        $canonTotalFilas = 0.0;
        foreach ($cruce['filas'] as $fila) {
            $venta = (float) $fila['venta_flash'];
            $canon = round($venta * $alicuota, 2);
            $canonTotalFilas += $canon;
            $filas[] = array_merge($fila, [
                'venta' => $venta,
                'canon' => $canon,
            ]);
        }

        $totalVentas = (float) $cruce['total_flash'];
        $canonTotal = round($totalVentas * $alicuota, 2);
        // El total de la nota es la suma de filas (nunca un valor cargado a mano).
        $canonTotalFilas = round($canonTotalFilas, 2);

        $resumen = [
            'total_flash' => $totalVentas,
            'total_posicion' => (float) $cruce['total_posicion'],
            'diferencia' => (float) $cruce['diferencia'],
            'canon_4' => $canonTotalFilas,
            'canon_sobre_total' => $canonTotal,
            'alicuota' => $alicuota,
            'dias_con_venta' => (int) $cruce['dias_con_venta'],
            'dias_rango' => (int) $cruce['dias_rango'],
            'tolerancia' => CanonMunicipalCalendarioSupport::TOLERANCIA,
            'desvios' => $cruce['desvios'],
        ];

        $cuadra = (bool) $cruce['cuadra'];

        return [
            'mensaje_config' => null,
            'cuadra' => $cuadra,
            'puede_emitir_nota' => $cuadra,
            'filas' => $filas,
            'resumen' => $resumen,
            'ficha' => $ficha,
            'periodo_texto' => CanonMunicipalListadoFiltros::formatearPeriodoTexto($filtros),
            'fecha_desde' => $desde,
            'fecha_hasta' => $hasta,
        ];
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>|null
     */
    public function leerCache(array $filtros): ?array
    {
        if (! CanonMunicipalListadoFiltros::tieneCriteriosAplicados($filtros)) {
            return null;
        }
        $pack = Cache::get(CanonMunicipalListadoFiltros::claveCacheResultado($filtros));
        if (! is_array($pack) || ($pack['firma'] ?? '') !== CanonMunicipalListadoFiltros::firma($filtros)) {
            return null;
        }
        if (! isset($pack['filas'], $pack['resumen'])) {
            return null;
        }

        return $pack;
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @param  array<string, mixed>  $resultado
     */
    public function guardarCache(array $filtros, array $resultado): void
    {
        if (! CanonMunicipalListadoFiltros::tieneCriteriosAplicados($filtros)) {
            return;
        }
        Cache::put(
            CanonMunicipalListadoFiltros::claveCacheResultado($filtros),
            array_merge($resultado, [
                'firma' => CanonMunicipalListadoFiltros::firma($filtros),
            ]),
            self::CACHE_TTL
        );
    }
}

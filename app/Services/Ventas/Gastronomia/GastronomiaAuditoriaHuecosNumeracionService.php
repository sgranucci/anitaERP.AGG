<?php

declare(strict_types=1);

namespace App\Services\Ventas\Gastronomia;

use App\Models\Configuracion\Empresa;
use App\Models\Ventas\Puntoventa;
use App\Models\Ventas\Venta;
use App\Support\Ventas\Gastronomia\GastronomiaNumeracionHuecosSupport;
use App\Support\Ventas\Gastronomia\GastronomiaVentasSoloErpSupport;
use App\Support\Ventas\TipotransaccionCodigoAfipSupport;
use Carbon\Carbon;
use Carbon\CarbonPeriod;

/**
 * Auditoría de huecos en numeración de comprobantes gastronomía (ERP y Anita).
 */
final class GastronomiaAuditoriaHuecosNumeracionService
{
    public function __construct(
        private readonly GastronomiaControlCorrelatividadAnitaErpService $correlatividadService,
    ) {
    }

    /**
     * Resumen compacto por jornada y empresa (ERP ↔ Anita + huecos ERP).
     *
     * @return array{
     *   fecha_jornada: string,
     *   huecos_corr_erp: int,
     *   solo_erp: int,
     *   solo_anita: int,
     *   huecos: list<array<string, mixed>>
     * }
     */
    public function resumenJornadaEmpresa(int $empresaId, string $fechaJornada): array
    {
        $huecos = $this->huecosErpJornadaEmpresa($empresaId, $fechaJornada, null);
        $res = GastronomiaVentasSoloErpSupport::esJornada($empresaId, $fechaJornada)
            ? []
            : ($this->correlatividadService->ejecutar($fechaJornada, [$empresaId])['resumen'] ?? []);

        return [
            'fecha_jornada' => $fechaJornada,
            'huecos_corr_erp' => count($huecos),
            'solo_erp' => (int) ($res['solo_erp'] ?? 0),
            'solo_anita' => (int) ($res['solo_anita'] ?? 0),
            'dif_monto' => (int) ($res['dif_monto'] ?? 0),
            'huecos' => $huecos,
        ];
    }

    /**
     * Barrido de huecos en un rango de jornadas (secuencia continua por PV en el mes).
     *
     * @param  list<int>  $empresaIds
     * @return array{
     *   fecha_desde: string,
     *   fecha_hasta: string,
     *   hay_huecos: bool,
     *   resumen: array<string, int>,
     *   empresas: list<array<string, mixed>>
     * }
     */
    public function auditarRango(
        string $fechaDesde,
        string $fechaHasta,
        array $empresaIds,
        ?string $codigoPuntoventaFiltro = null,
        bool $incluirAnita = true,
        bool $forzarCacheAnita = false,
    ): array {
        unset($incluirAnita, $forzarCacheAnita);

        $desde = Carbon::parse($fechaDesde)->toDateString();
        $hasta = Carbon::parse($fechaHasta)->toDateString();
        if ($desde > $hasta) {
            throw new \InvalidArgumentException('fecha-desde no puede ser posterior a fecha-hasta.');
        }

        $resumenGlobal = [
            'huecos_erp' => 0,
            'numeros_faltantes_erp' => 0,
            'huecos_anita' => 0,
            'numeros_faltantes_anita' => 0,
            'puntos_venta_con_huecos_erp' => 0,
            'jornadas_con_huecos_erp' => 0,
        ];

        $empresasInforme = [];
        $pvConHuecos = [];

        foreach ($empresaIds as $empresaId) {
            $empresaId = (int) $empresaId;
            if ($empresaId <= 0) {
                continue;
            }

            $empresa = Empresa::query()->find($empresaId);
            $huecosErp = [];
            $porJornada = [];
            $porPuntoventa = [];
            $statsPv = [];

            foreach (CarbonPeriod::create($desde, $hasta) as $dia) {
                $fechaJornada = $dia->toDateString();
                $huecosJornada = $this->huecosErpJornadaEmpresa($empresaId, $fechaJornada, $codigoPuntoventaFiltro);

                if ($huecosJornada === []) {
                    continue;
                }

                $resumenGlobal['jornadas_con_huecos_erp']++;
                $faltantesJornada = 0;

                foreach ($huecosJornada as $row) {
                    $row['fecha_jornada'] = $fechaJornada;
                    $row['origen'] = 'erp';
                    $clave = $this->claveHueco($row);
                    $huecosErp[$clave] = $row;
                    $faltantesJornada += (int) ($row['cantidad'] ?? 0);

                    $pvCodigo = (string) ($row['pv_codigo'] ?? '');
                    $pvConHuecos[$empresaId.'|'.$pvCodigo] = true;
                    $statsPv[$pvCodigo] = ($statsPv[$pvCodigo] ?? 0) + (int) ($row['cantidad'] ?? 0);
                }

                $porJornada[] = [
                    'fecha_jornada' => $fechaJornada,
                    'huecos_corr_erp' => count($huecosJornada),
                    'numeros_faltantes_erp' => $faltantesJornada,
                    'huecos' => array_values($huecosJornada),
                    'solo_erp' => 0,
                    'solo_anita' => 0,
                ];
            }

            foreach ($statsPv as $pvCodigo => $faltantes) {
                $porPuntoventa[] = [
                    'pv_codigo' => $pvCodigo,
                    'empresa_id' => $empresaId,
                    'faltantes_erp' => $faltantes,
                    'tramos_erp' => count(array_filter(
                        $huecosErp,
                        static fn (array $h) => (string) ($h['pv_codigo'] ?? '') === $pvCodigo,
                    )),
                ];
            }

            usort($porPuntoventa, static fn (array $a, array $b) => strcmp((string) $a['pv_codigo'], (string) $b['pv_codigo']));
            usort($porJornada, static fn (array $a, array $b) => strcmp((string) $a['fecha_jornada'], (string) $b['fecha_jornada']));

            $huecosLista = array_values($huecosErp);
            $resumenGlobal['huecos_erp'] += count($huecosLista);
            $resumenGlobal['numeros_faltantes_erp'] += array_sum(array_column($huecosLista, 'cantidad'));

            $empresasInforme[] = [
                'empresa_id' => $empresaId,
                'empresa_nombre' => (string) ($empresa->nombre ?? 'Empresa '.$empresaId),
                'por_puntoventa' => $porPuntoventa,
                'por_jornada' => $porJornada,
                'huecos_erp' => $huecosLista,
                'huecos_anita' => [],
            ];
        }

        $resumenGlobal['puntos_venta_con_huecos_erp'] = count($pvConHuecos);
        $hayHuecos = $resumenGlobal['huecos_erp'] > 0;

        return [
            'fecha_desde' => $desde,
            'fecha_hasta' => $hasta,
            'hay_huecos' => $hayHuecos,
            'resumen' => $resumenGlobal,
            'empresas' => $empresasInforme,
        ];
    }

    /**
     * @param  array<string, mixed>  $informe
     */
    public function hayHuecos(array $informe): bool
    {
        if (($informe['hay_huecos'] ?? false) === true) {
            return true;
        }

        foreach ($informe['empresas'] ?? [] as $empresa) {
            foreach ($empresa['dias'] ?? [] as $dia) {
                $huecos = $dia['huecos_numeracion'] ?? null;
                if (is_array($huecos) && (int) ($huecos['huecos_corr_erp'] ?? 0) > 0) {
                    return true;
                }
            }
            if (($empresa['huecos_rango']['hay_huecos'] ?? false) === true) {
                return true;
            }
        }

        return false;
    }

    /**
     * Huecos ERP de una fecha de comprobante, por punto de venta y código AFIP.
     * La jornada no parte la serie: un número existente en otra jornada no es hueco.
     *
     * @return list<array<string, mixed>>
     */
    private function huecosErpJornadaEmpresa(int $empresaId, string $fechaJornada, ?string $codigoPuntoventaFiltro): array
    {
        $query = Puntoventa::query()
            ->where('empresa_id', $empresaId)
            ->where('modofacturacion', '!=', 'M')
            ->orderBy('codigo');

        if ($codigoPuntoventaFiltro !== null && trim($codigoPuntoventaFiltro) !== '') {
            $query->where('codigo', trim($codigoPuntoventaFiltro));
        }

        $huecos = [];
        foreach ($query->get() as $puntoventa) {
            $emitidos = Venta::query()
                ->select(['venta.codigo_afip', 'venta.codigo'])
                ->where('puntoventa_id', (int) $puntoventa->id)
                ->whereDate('fecha', $fechaJornada)
                ->where('codigo_afip', '>', 0)
                ->whereHas('gastronomiaEmision')
                ->get();

            if ($emitidos->isEmpty()) {
                continue;
            }

            $tipos = [];
            foreach ($emitidos as $venta) {
                $codigoAfip = TipotransaccionCodigoAfipSupport::codigoAfipDesdeVentaGrabada(
                    (int) $venta->codigo_afip,
                    (string) $venta->codigo,
                );
                if ($codigoAfip > 0) {
                    $tipos[$codigoAfip] = $codigoAfip;
                }
            }

            foreach ($tipos as $codigoAfip) {
                foreach (GastronomiaNumeracionHuecosSupport::huecosSeriePorFechaComprobante(
                    (int) $puntoventa->id,
                    (int) $codigoAfip,
                    $fechaJornada,
                ) as $row) {
                    $row['pv_codigo'] = (string) $puntoventa->codigo;
                    $row['empresa_id'] = $empresaId;
                    $row['codigo_afip'] = (int) $codigoAfip;
                    $row['fecha'] = $fechaJornada;
                    $huecos[] = $row;
                }
            }
        }

        return $huecos;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function claveHueco(array $row): string
    {
        return implode('|', [
            (string) ($row['empresa_id'] ?? ''),
            (string) ($row['pv_codigo'] ?? ''),
            (string) ($row['codigo_afip'] ?? ''),
            (string) ($row['fecha_jornada'] ?? ''),
            (string) ($row['desde'] ?? ''),
            (string) ($row['hasta'] ?? ''),
        ]);
    }
}

<?php

declare(strict_types=1);

namespace App\Services\Ventas\Gastronomia;

use App\Models\Configuracion\Empresa;
use App\Models\Ventas\JornadaGastronomia;
use App\Support\Ventas\Gastronomia\GastronomiaConciliacionPorPcSupport;
use App\Support\Ventas\Gastronomia\GastronomiaFacturacionAuditoriaCtamovSupport;
use App\Support\Ventas\Gastronomia\GastronomiaVentasSoloErpSupport;
use Carbon\Carbon;
use Carbon\CarbonPeriod;

/**
 * Auditoría integral por día:
 * facturación ERP ↔ Anita ↔ rendgastro (totales PC/día, CAE+CAEA) ↔ asientos ERP ↔ ctamov Anita.
 */
final class GastronomiaFacturacionAuditoriaIntegralService
{
    public function __construct(
        private readonly GastronomiaConciliacionPorPcSupport $conciliacionPorPcSupport,
        private readonly GastronomiaChequeoVentasAnitaErpService $chequeoService,
        private readonly GastronomiaAuditoriaHuecosNumeracionService $huecosNumeracionService,
    ) {
    }

    /**
     * @param  list<int>  $empresasIds
     * @return array<string, mixed>
     */
    public function generar(
        string $fechaDesde,
        string $fechaHasta,
        array $empresasIds,
        float $tolerancia = 0.02,
        ?string $codigoPuntoventaFiltro = null,
    ): array {
        $desde = Carbon::parse($fechaDesde)->toDateString();
        $hasta = Carbon::parse($fechaHasta)->toDateString();
        if ($desde > $hasta) {
            throw new \InvalidArgumentException('fecha-desde no puede ser posterior a fecha-hasta.');
        }

        $empresas = [];
        foreach ($empresasIds as $empresaId) {
            $empresaId = (int) $empresaId;
            if ($empresaId <= 0) {
                continue;
            }

            $empresa = Empresa::query()->find($empresaId);
            $dias = [];

            foreach (CarbonPeriod::create($desde, $hasta) as $dia) {
                $fechaJornada = $dia->toDateString();
                if ($this->esJornadaPreMigracion($empresaId, $fechaJornada)) {
                    continue;
                }
                $dias[] = $this->armarDia($empresaId, $fechaJornada, $tolerancia, $codigoPuntoventaFiltro);
            }

            $empresas[] = [
                'empresa_id' => $empresaId,
                'empresa_nombre' => (string) ($empresa->nombre ?? 'Empresa '.$empresaId),
                'empresa_codigo' => (int) ($empresa->codigo ?? $empresaId),
                'dias' => $dias,
                'huecos_rango' => $this->huecosNumeracionService->auditarRango(
                    $desde,
                    $hasta,
                    [$empresaId],
                    $codigoPuntoventaFiltro,
                    true,
                    false,
                ),
            ];
        }

        return [
            'fecha_desde' => $desde,
            'fecha_hasta' => $hasta,
            'tolerancia' => $tolerancia,
            'empresas' => $empresas,
            'hay_diferencias' => $this->hayDiferencias(['empresas' => $empresas]),
            'hay_huecos_numeracion' => $this->huecosNumeracionService->hayHuecos(['empresas' => $empresas]),
        ];
    }

    /**
     * @param  array<string, mixed>  $informe
     */
    public function hayDiferencias(array $informe): bool
    {
        foreach ($informe['empresas'] ?? [] as $empresa) {
            foreach ($empresa['dias'] ?? [] as $dia) {
                foreach ($dia['filas'] ?? [] as $fila) {
                    if (in_array($fila['tipo_fila'] ?? '', ['pv_cae', 'pv_caea'], true)) {
                        continue;
                    }
                    if (in_array($fila['estado'] ?? '', ['DIF', 'SIN RENDG'], true)) {
                        return true;
                    }
                }
                if (($dia['contable_empresa']['estado'] ?? '') === 'DIF') {
                    return true;
                }
                $montosCabecera = $dia['montos_cabecera'] ?? [];
                if (($montosCabecera['estado'] ?? '') === 'DIF') {
                    return true;
                }
                if (! ($montosCabecera['ventas_solo_erp'] ?? false)
                    && (($montosCabecera['conteo']['diferencia'] ?? 0) > 0
                        || ($montosCabecera['conteo']['solo_erp'] ?? 0) > 0)) {
                    return true;
                }
                $huecos = $dia['huecos_numeracion'] ?? null;
                if (is_array($huecos) && (int) ($huecos['huecos_corr_erp'] ?? 0) > 0) {
                    return true;
                }
            }
            $huecosRango = $empresa['huecos_rango'] ?? null;
            if (is_array($huecosRango) && ($huecosRango['hay_huecos'] ?? false) === true) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<array<int|string|float|null>>
     */
    public function construirFilasCsv(array $informe): array
    {
        $filas = [];
        foreach ($informe['empresas'] ?? [] as $empresa) {
            foreach ($empresa['dias'] ?? [] as $dia) {
                foreach ($dia['filas'] ?? [] as $fila) {
                    $filas[] = [
                        $empresa['empresa_id'],
                        $empresa['empresa_nombre'],
                        $dia['fecha_jornada'],
                        $fila['puntoventa'] ?? '',
                        $fila['tipo_fila'] ?? '',
                        $fila['cant_facturas'] ?? 0,
                        $fila['ventas_erp'] ?? 0,
                        $fila['ventas_anita'] ?? 0,
                        $fila['rendg_z'] ?? '',
                        $fila['asientos_erp'] ?? 0,
                        $fila['ctamov_anita'] ?? 0,
                        $fila['diff_erp_anita'] ?? '',
                        $fila['diff_erp_rendg'] ?? '',
                        $fila['diff_asiento_ctamov'] ?? '',
                        $fila['estado'] ?? '',
                    ];
                }
            }
        }

        return $filas;
    }

    public function guardarCsv(string $ruta, array $informe): void
    {
        $handle = fopen('php://temp', 'r+');
        if ($handle === false) {
            throw new \RuntimeException('No se pudo crear buffer CSV.');
        }

        fputcsv($handle, [
            'empresa_id', 'empresa_nombre', 'fecha_jornada', 'clave', 'tipo_fila',
            'cant_facturas', 'ventas_erp', 'ventas_anita', 'rendg_z',
            'asientos_erp', 'ctamov_anita',
            'diff_erp_anita', 'diff_erp_rendg', 'diff_asiento_ctamov', 'estado',
        ], ';');

        foreach ($this->construirFilasCsv($informe) as $fila) {
            fputcsv($handle, $fila, ';');
        }

        rewind($handle);
        $contenido = stream_get_contents($handle);
        fclose($handle);

        if ($contenido === false || file_put_contents($ruta, $contenido) === false) {
            throw new \RuntimeException('No se pudo escribir CSV: '.$ruta);
        }
    }

    /**
     * @return array{fecha_jornada: string, jornada_cerrada: bool, contabilidad_por_factura: bool, filas: list<array<string, mixed>>, contable_empresa: array<string, mixed>}
     */
    private function armarDia(
        int $empresaId,
        string $fechaJornada,
        float $tolerancia,
        ?string $codigoPuntoventaFiltro,
    ): array {
        $jornadaAbierta = ! $this->jornadaEstaCerrada($empresaId, $fechaJornada);
        $conciliacion = $this->conciliacionPorPcSupport->conciliacionDiaCompleta(
            $empresaId,
            $fechaJornada,
            $tolerancia,
            $jornadaAbierta,
        );

        $filas = [];
        foreach ($conciliacion['filas_pc'] as $filaPc) {
            if (! $this->pasaFiltroPv($filaPc, $codigoPuntoventaFiltro)) {
                continue;
            }
            $filas[] = $this->mapearFilaOperativa($filaPc, 'pc');
        }

        foreach ($conciliacion['filas_totales'] as $filaTotal) {
            $filas[] = $this->mapearFilaOperativa($filaTotal, (string) ($filaTotal['tipo_fila'] ?? 'total'));
        }

        $empresa = Empresa::query()->find($empresaId);
        $empresaCodigo = (int) ($empresa->codigo ?? $empresaId);
        $cuentas = GastronomiaFacturacionAuditoriaCtamovSupport::cuentasVentasConciliacion($empresaId);
        $contabilidadPorFactura = GastronomiaFacturacionAuditoriaCtamovSupport::contabilidadPorFacturaHabilitada();

        $asientosCierre = GastronomiaFacturacionAuditoriaCtamovSupport::asientosCierreJornada($empresaId, $fechaJornada);
        $auditCierre = GastronomiaFacturacionAuditoriaCtamovSupport::auditarAsientosCierreVsCtamov(
            $asientosCierre,
            $empresaCodigo,
            $cuentas['codigos_cuenta'],
            $tolerancia,
        );

        $diffCierreCtamov = round((float) ($auditCierre['total_erp'] ?? 0) - (float) ($auditCierre['total_ctamov'] ?? 0), 2);
        $estadoContableEmpresa = $this->resolverEstadoContableCierre(
            $auditCierre,
            $diffCierreCtamov,
            $tolerancia,
            $contabilidadPorFactura,
        );

        $contableEmpresa = [
            'modo' => $contabilidadPorFactura ? 'por_factura' : 'cierre_agrupado',
            'asientos_cierre' => count($asientosCierre),
            'asientos_erp_ventas' => (float) ($auditCierre['total_erp'] ?? 0),
            'ctamov_anita_ventas' => (float) ($auditCierre['total_ctamov'] ?? 0),
            'diff_asiento_ctamov' => $diffCierreCtamov,
            'ctamov_ok' => (int) ($auditCierre['ok'] ?? 0),
            'ctamov_dif' => (int) ($auditCierre['dif'] ?? 0),
            'sin_ctamov' => (int) ($auditCierre['sin_ctamov'] ?? 0),
            'estado' => $estadoContableEmpresa,
            'detalle' => $auditCierre['detalle'] ?? [],
        ];

        if (! $contabilidadPorFactura) {
            $filas[] = [
                'tipo_fila' => 'contable_empresa',
                'puntoventa' => 'CIERRE',
                'identificador_pc' => 'CIERRE-WAITRY',
                'descripcion' => 'Asientos cierre Waitry (empresa/día) ↔ ctamov Anita',
                'asientos_erp' => (float) ($auditCierre['total_erp'] ?? 0),
                'ctamov_anita' => (float) ($auditCierre['total_ctamov'] ?? 0),
                'diff_asiento_ctamov' => $diffCierreCtamov,
                'ctamov_ok' => (int) ($auditCierre['ok'] ?? 0),
                'ctamov_dif' => (int) ($auditCierre['dif'] ?? 0),
                'sin_ctamov' => (int) ($auditCierre['sin_ctamov'] ?? 0),
                'asientos_cierre' => count($asientosCierre),
                'estado_operativo' => '—',
                'estado_contable' => $estadoContableEmpresa,
                'estado' => $estadoContableEmpresa === 'OK' ? 'OK' : 'DIF',
            ];
        }

        return [
            'fecha_jornada' => $fechaJornada,
            'jornada_cerrada' => ! $jornadaAbierta,
            'contabilidad_por_factura' => $contabilidadPorFactura,
            'filas' => $filas,
            'contable_empresa' => $contableEmpresa,
            'totales_salon' => $conciliacion['totales_salon'],
            'montos_cabecera' => GastronomiaVentasSoloErpSupport::esJornada($empresaId, $fechaJornada)
                ? ['conteo' => ['ok' => 0, 'diferencia' => 0, 'solo_erp' => 0, 'solo_anita' => 0], 'delta_totales' => ['total' => 0.0, 'gravado' => 0.0, 'iva' => 0.0, 'exento' => 0.0], 'estado' => 'N/A', 'ventas_solo_erp' => true]
                : $this->auditarMontosCabeceraPorJornada(
                    $empresaId,
                    $fechaJornada,
                    $tolerancia,
                    $codigoPuntoventaFiltro,
                ),
            'huecos_numeracion' => $this->huecosNumeracionService->resumenJornadaEmpresa($empresaId, $fechaJornada),
        ];
    }

    /**
     * Concilia factura a factura: total, gravado, IVA y exento en cabecera Anita.
     *
     * @return array{conteo: array<string, int>, delta_totales: array<string, float>, estado: string}
     */
    private function auditarMontosCabeceraPorJornada(
        int $empresaId,
        string $fechaJornada,
        float $tolerancia,
        ?string $codigoPuntoventaFiltro,
    ): array {
        $combinaciones = $this->chequeoService->listarCombinacionesPvJornada(
            $fechaJornada,
            $fechaJornada,
            $empresaId,
            $codigoPuntoventaFiltro,
        );

        $conteo = ['ok' => 0, 'diferencia' => 0, 'solo_erp' => 0, 'solo_anita' => 0];
        $delta = ['total' => 0.0, 'gravado' => 0.0, 'iva' => 0.0, 'exento' => 0.0];

        foreach ($combinaciones as $combo) {
            $resultado = $this->chequeoService->chequear(
                (int) $combo['puntoventa_id'],
                (string) $combo['fecha_jornada'],
                $tolerancia,
                true,
            );

            $res = $resultado['resumen'];
            foreach ($conteo as $k => $_) {
                $conteo[$k] += (int) ($res['conteo'][$k] ?? 0);
            }

            foreach ($delta as $campo => $_) {
                $delta[$campo] += (float) ($res['delta_totales'][$campo] ?? 0);
            }
        }

        foreach ($delta as $campo => $valor) {
            $delta[$campo] = round($valor, 2);
        }

        $estado = ($conteo['diferencia'] > 0 || $conteo['solo_erp'] > 0) ? 'DIF' : 'OK';

        return [
            'conteo' => $conteo,
            'delta_totales' => $delta,
            'estado' => $estado,
        ];
    }

    /**
     * @param  array<string, mixed>  $fila
     * @return array<string, mixed>
     */
    private function mapearFilaOperativa(array $fila, string $tipoFila): array
    {
        $pvCae = (string) ($fila['pv_cae'] ?? '');
        $pvCaea = (string) ($fila['pv_caea'] ?? '—');
        $identificadorPc = (string) ($fila['identificador_pc'] ?? '');
        $puntoventa = match ($tipoFila) {
            'pc' => $pvCae.($pvCaea !== '—' ? '+'.$pvCaea : ''),
            'total_salon' => 'TOTAL-SALON',
            'total_dia' => 'TOTAL-DIA',
            'post_cierre_caea' => 'POST-CIERRE '.($fila['pv_codigo'] ?? $pvCaea),
            default => $identificadorPc !== '' ? $identificadorPc : '—',
        };

        $estado = (string) ($fila['estado'] ?? '—');
        $esTotalDia = $tipoFila === 'total_dia' || ($fila['tipo_fila'] ?? '') === 'total_dia';

        return [
            'tipo_fila' => $tipoFila,
            'identificador_pc' => $identificadorPc,
            'puntoventa' => $puntoventa,
            'pv_cae' => $pvCae,
            'pv_caea' => $pvCaea,
            'descripcion' => (string) ($fila['descripcion_pc'] ?? ''),
            'cant_facturas' => (int) ($fila['cantidad_facturas_erp'] ?? 0),
            'ventas_erp' => (float) ($fila['ventas_erp'] ?? 0),
            'ventas_erp_cae' => (float) ($fila['ventas_erp_cae'] ?? 0),
            'ventas_erp_caea' => (float) ($fila['ventas_erp_caea'] ?? 0),
            'ventas_anita' => (float) ($fila['ventas_anita'] ?? 0),
            'rendg_z' => $fila['rendgastro_z'] ?? null,
            'diff_erp_anita' => (float) ($fila['diff_erp_anita'] ?? 0),
            'diff_erp_rendg' => $fila['diff_erp_rendg'] ?? null,
            'estado_operativo' => $estado,
            'estado_contable' => 'CIERRE_AGRUPADO',
            'estado' => $estado,
            'es_total_dia' => $esTotalDia,
        ];
    }

    /**
     * @param  array<string, mixed>  $filaPc
     */
    private function pasaFiltroPv(array $filaPc, ?string $codigoPuntoventaFiltro): bool
    {
        if ($codigoPuntoventaFiltro === null || trim($codigoPuntoventaFiltro) === '') {
            return true;
        }

        $filtro = trim($codigoPuntoventaFiltro);

        return trim((string) ($filaPc['pv_cae'] ?? '')) === $filtro
            || trim((string) ($filaPc['pv_caea'] ?? '')) === $filtro;
    }

    /**
     * @param  array<string, mixed>  $auditCierre
     */
    private function resolverEstadoContableCierre(
        array $auditCierre,
        float $diffTotal,
        float $tolerancia,
        bool $contabilidadPorFactura,
    ): string {
        if ($contabilidadPorFactura) {
            return '—';
        }

        if (($auditCierre['sin_ctamov'] ?? 0) > 0 || ($auditCierre['dif'] ?? 0) > 0) {
            return 'DIF';
        }

        if (abs($diffTotal) > $tolerancia) {
            return 'DIF';
        }

        return 'OK';
    }

    private function esJornadaPreMigracion(int $empresaId, string $fechaJornada): bool
    {
        $map = config('gastronomia.conciliacion_diaria_reporte.fecha_jornada_desde_por_empresa', []);
        $min = trim((string) ($map[$empresaId] ?? ''));
        if ($min === '') {
            return false;
        }

        return $fechaJornada < Carbon::parse($min)->toDateString();
    }

    private function jornadaEstaCerrada(int $empresaId, string $fechaJornada): bool
    {
        $jornada = JornadaGastronomia::query()
            ->where('empresa_id', $empresaId)
            ->whereDate('fecha_jornada', $fechaJornada)
            ->orderByDesc('id')
            ->first(['estado']);

        if ($jornada === null) {
            return false;
        }

        return (string) ($jornada->estado ?? '') === JornadaGastronomia::ESTADO_CERRADA;
    }
}

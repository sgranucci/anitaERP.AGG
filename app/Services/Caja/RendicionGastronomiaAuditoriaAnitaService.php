<?php

namespace App\Services\Caja;

use App\Models\Ventas\JornadaGastronomia;
use App\Support\Ventas\Gastronomia\GastronomiaConciliacionEstadoSupport;
use App\Support\Ventas\Gastronomia\GastronomiaConciliacionEstacionamientoSupport;
use App\Support\Ventas\Gastronomia\GastronomiaConciliacionPorPcSupport;
use Carbon\Carbon;

/**
 * Concilia facturación bruta del día (ERP) con rendg_total_z en rendgastro (Anita bridge).
 * Por PC (CAE+CAEA vs rendg host) y total día — no por PV sucursal aislado (CAEA por caída ARCA).
 */
final class RendicionGastronomiaAuditoriaAnitaService
{
    public function __construct(
        private readonly RendicionGastronomiaAnitaSyncService $anitaSyncService,
        private readonly GastronomiaConciliacionPorPcSupport $conciliacionPorPcSupport,
        private readonly GastronomiaConciliacionEstacionamientoSupport $estacionamientoSupport,
    ) {
    }

    /**
     * @return array{
     *   fecha_jornada: string,
     *   empresa_id: int,
     *   tolerancia: float,
     *   filas: list<array<string, mixed>>,
     *   resumen: array<string, mixed>
     * }
     */
    public function auditarFechaJornada(
        int $empresaId,
        string $fechaJornada,
        float $tolerancia = 0.02,
        ?string $codigoPuntoventaFiltro = null,
    ): array {
        if (! $this->anitaSyncService->sincronizacionHabilitada()) {
            throw new \RuntimeException('RENDICION_GASTRONOMIA_SINCRONIZAR_ANITA está deshabilitado.');
        }

        $fechaJornada = Carbon::parse($fechaJornada)->toDateString();
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
            $filas[] = $this->mapearFila($filaPc, 'pc');
        }

        foreach ($conciliacion['filas_totales'] as $filaTotal) {
            $filas[] = $this->mapearFila($filaTotal, (string) ($filaTotal['tipo_fila'] ?? 'total'));
        }

        $estacionamiento = $this->estacionamientoSupport->filasAuditoriaIntegrada(
            $empresaId,
            $fechaJornada,
            $tolerancia,
            $jornadaAbierta,
        );
        foreach ($estacionamiento['filas'] as $filaEst) {
            if (! $this->pasaFiltroPvEstacionamiento($filaEst, $codigoPuntoventaFiltro)) {
                continue;
            }
            $filas[] = $this->mapearFila($filaEst, 'estacionamiento_pv');
        }
        if ($estacionamiento['fila_total'] !== null
            && ($codigoPuntoventaFiltro === null || trim($codigoPuntoventaFiltro) === '')) {
            $filas[] = $this->mapearFila($estacionamiento['fila_total'], 'total_estacionamiento');
        }

        $conteo = [
            'ok' => 0,
            'dif_venta' => 0,
            'dif_rendg' => 0,
            'dif_ambos' => 0,
            'sin_rendg' => 0,
            'diferencia' => 0,
        ];
        foreach ($filas as $fila) {
            $estado = (string) ($fila['estado'] ?? '');
            $estadoAnita = (string) ($fila['estado_anita'] ?? '');
            $estadoRendg = (string) ($fila['estado_rendg'] ?? '');

            if ($estado === 'OK') {
                $conteo['ok']++;
            } elseif ($estado === 'DIF venta') {
                $conteo['dif_venta']++;
                $conteo['diferencia']++;
            } elseif ($estado === 'DIF rendg') {
                $conteo['dif_rendg']++;
                $conteo['diferencia']++;
            } elseif ($estado === 'DIF ambos') {
                $conteo['dif_ambos']++;
                $conteo['diferencia']++;
            } elseif ($estado === 'SIN RENDG') {
                $conteo['sin_rendg']++;
                $conteo['diferencia']++;
            } elseif ($estadoAnita === 'DIF' || in_array($estadoRendg, ['DIF', 'SIN RENDG'], true)) {
                $conteo['diferencia']++;
            }
        }

        $totalDia = null;
        foreach ($filas as $fila) {
            if (($fila['tipo_fila'] ?? '') === 'total_dia') {
                $totalDia = $fila;
                break;
            }
        }
        if ($totalDia === null) {
            foreach ($filas as $fila) {
                if (($fila['tipo_fila'] ?? '') === 'total_salon') {
                    $totalDia = $fila;
                    break;
                }
            }
        }

        $requiereAlerta = false;
        foreach ($filas as $fila) {
            $tipoFila = (string) ($fila['tipo_fila'] ?? '');
            if (! in_array($tipoFila, ['pc', 'estacionamiento_pv'], true)) {
                continue;
            }
            if (GastronomiaConciliacionEstadoSupport::requiereAlertaRendg(
                (string) ($fila['estado_rendg'] ?? ''),
            )) {
                $requiereAlerta = true;
                break;
            }
            if (in_array((string) ($fila['estado'] ?? ''), ['DIF rendg', 'SIN RENDG', 'DIF'], true)) {
                $requiereAlerta = true;
                break;
            }
        }
        if (! $requiereAlerta && $totalDia !== null) {
            $requiereAlerta = GastronomiaConciliacionEstadoSupport::requiereAlertaRendg(
                (string) ($totalDia['estado_rendg'] ?? ''),
            );
        }

        return [
            'fecha_jornada' => $fechaJornada,
            'empresa_id' => $empresaId,
            'tolerancia' => $tolerancia,
            'filas' => $filas,
            'total_dia' => $totalDia,
            'resumen' => [
                'puntoventas' => count($filas),
                'conteo' => $conteo,
                'requiere_alerta' => $requiereAlerta,
                'filtro_erp' => 'venta.fechajornada + venta_gastronomia_emision por PC (CAE+CAEA)',
                'filtro_anita_venta' => 'cabecera venta Informix (ven_monto) emparejada por comprobante',
                'filtro_anita_rendg' => 'rendgastro neto por rendg_host (Z portadora − NC por PC); total día salón + post-cierre + estacionamiento',
                'estacionamiento' => $estacionamiento['totales'],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $fila
     * @return array<string, mixed>
     */
    private function mapearFila(array $fila, string $tipoFila): array
    {
        $pvCae = (string) ($fila['pv_cae'] ?? '');
        $pvCaea = (string) ($fila['pv_caea'] ?? '—');
        $identificadorPc = (string) ($fila['identificador_pc'] ?? '');

        $puntoventa = match ($tipoFila) {
            'pc' => $identificadorPc.' ('.$pvCae.($pvCaea !== '—' ? '+'.$pvCaea : '').')',
            'estacionamiento_pv' => 'ESTAC '.trim((string) ($fila['pv_codigo'] ?? $pvCae)),
            'total_salon' => 'TOTAL-SALON',
            'total_estacionamiento' => 'TOTAL-ESTACIONAMIENTO',
            'total_dia' => 'TOTAL-DIA',
            'post_cierre_caea' => 'POST-CIERRE '.((string) ($fila['pv_codigo'] ?? $pvCaea)),
            default => $identificadorPc,
        };

        $ventasAnita = array_key_exists('ventas_anita', $fila) && $fila['ventas_anita'] === null
            ? null
            : (float) ($fila['ventas_anita'] ?? 0);

        return [
            'tipo_fila' => $tipoFila,
            'identificador_pc' => $identificadorPc,
            'puntoventa' => $puntoventa,
            'pv_cae' => $pvCae,
            'pv_caea' => $pvCaea,
            'estado' => (string) ($fila['estado'] ?? '—'),
            'estado_anita' => (string) ($fila['estado_anita'] ?? '—'),
            'estado_rendg' => (string) ($fila['estado_rendg'] ?? '—'),
            'cantidad_facturas_erp' => (int) ($fila['cantidad_facturas_erp'] ?? 0),
            'cantidad_nc_erp' => (int) ($fila['cantidad_nc_erp'] ?? 0),
            'erp_z' => (float) ($fila['ventas_erp_neto'] ?? $fila['ventas_erp'] ?? 0),
            'erp_bruto' => (float) ($fila['ventas_erp_bruto'] ?? $fila['ventas_erp'] ?? 0),
            'erp_nc' => (float) ($fila['notas_credito_erp'] ?? 0),
            'erp_cae' => (float) ($fila['ventas_erp_cae'] ?? 0),
            'erp_caea' => (float) ($fila['ventas_erp_caea'] ?? 0),
            'ventas_anita' => $ventasAnita,
            'anita_z' => $fila['rendgastro_neto'] ?? $fila['rendgastro_z'] ?? null,
            'anita_z_bruto' => $fila['rendgastro_z_bruto'] ?? null,
            'anita_nc' => (float) ($fila['notas_credito_rendg'] ?? 0),
            'diff_anita' => $ventasAnita === null ? null : ($fila['diff_erp_anita'] ?? null),
            'diff_z' => $fila['diff_erp_rendg'] ?? null,
            'diff_nc' => null,
            'mensaje' => (string) ($fila['descripcion_pc'] ?? ''),
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
     * @param  array<string, mixed>  $filaEst
     */
    private function pasaFiltroPvEstacionamiento(array $filaEst, ?string $codigoPuntoventaFiltro): bool
    {
        if ($codigoPuntoventaFiltro === null || trim($codigoPuntoventaFiltro) === '') {
            return true;
        }

        $filtro = trim($codigoPuntoventaFiltro);

        return trim((string) ($filaEst['pv_cae'] ?? $filaEst['pv_codigo'] ?? '')) === $filtro;
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

    public function resolverJornada(int $empresaId, string $fechaJornada): ?JornadaGastronomia
    {
        return JornadaGastronomia::query()
            ->where('empresa_id', $empresaId)
            ->whereDate('fecha_jornada', $fechaJornada)
            ->orderByDesc('id')
            ->first();
    }
}

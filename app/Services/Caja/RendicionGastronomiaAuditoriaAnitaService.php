<?php

namespace App\Services\Caja;

use App\Models\Ventas\JornadaGastronomia;
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

        $conteo = ['ok' => 0, 'diferencia' => 0, 'sin_anita' => 0, 'sin_ventas_erp' => 0];
        foreach ($filas as $fila) {
            $estado = (string) ($fila['estado'] ?? '');
            if ($estado === 'OK') {
                $conteo['ok']++;
            } elseif ($estado === 'DIF') {
                $conteo['diferencia']++;
            } elseif ($estado === 'SIN RENDG') {
                $conteo['sin_anita']++;
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

        $estadoDecisivo = (string) ($totalDia['estado'] ?? '');
        $requiereAlerta = in_array($estadoDecisivo, ['DIF', 'SIN RENDG'], true);
        if (! $requiereAlerta) {
            foreach ($filas as $fila) {
                if (($fila['tipo_fila'] ?? '') === 'pc'
                    && in_array((string) ($fila['estado'] ?? ''), ['DIF', 'SIN RENDG'], true)) {
                    $requiereAlerta = true;
                    break;
                }
            }
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
                'filtro_anita' => 'rendgastro rendg_host (Z portadora + neto CAEA por PC); total día salón + post-cierre',
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
            'total_salon' => 'TOTAL-SALON',
            'total_dia' => 'TOTAL-DIA',
            'post_cierre_caea' => 'POST-CIERRE '.((string) ($fila['pv_codigo'] ?? $pvCaea)),
            default => $identificadorPc,
        };

        return [
            'tipo_fila' => $tipoFila,
            'identificador_pc' => $identificadorPc,
            'puntoventa' => $puntoventa,
            'pv_cae' => $pvCae,
            'pv_caea' => $pvCaea,
            'estado' => (string) ($fila['estado'] ?? '—'),
            'cantidad_facturas_erp' => (int) ($fila['cantidad_facturas_erp'] ?? 0),
            'cantidad_nc_erp' => 0,
            'erp_z' => (float) ($fila['ventas_erp'] ?? 0),
            'erp_cae' => (float) ($fila['ventas_erp_cae'] ?? 0),
            'erp_caea' => (float) ($fila['ventas_erp_caea'] ?? 0),
            'anita_z' => $fila['rendgastro_z'] ?? null,
            'anita_nc' => null,
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

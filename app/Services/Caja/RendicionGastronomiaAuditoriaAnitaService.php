<?php

namespace App\Services\Caja;

use App\Models\Caja\Estacionamiento\JornadaEstacionamiento;
use App\Models\Caja\RendicionEstacionamientoCaja;
use App\Models\Caja\RendicionGastronomiaCaja;
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

        $presentacionCaja = $this->resolverPresentacionCaja($empresaId, $fechaJornada, $filas);
        $clasificacion = $this->clasificarAlerta($requiereAlerta, $presentacionCaja, $filas);

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
                'clasificacion_alerta' => $clasificacion,
                'presentacion_caja' => $presentacionCaja,
                'avisos' => $presentacionCaja['avisos'],
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

    /**
     * Z en rendgastro se recalcula al presentar la rendición tipo jornada en Caja.
     * Sin esa presentación, Δ rendg suele ser falso positivo (Z=0 / NC sueltas).
     *
     * @param  list<array<string, mixed>>  $filas
     * @return array{
     *   gastro_aplica: bool,
     *   gastro_presentada: bool|null,
     *   gastro_jornada_id: int|null,
     *   gastro_estado_ventas: string|null,
     *   estac_aplica: bool,
     *   estac_presentada: bool|null,
     *   estac_jornada_id: int|null,
     *   estac_estado_ventas: string|null,
     *   pendiente: bool,
     *   avisos: list<string>
     * }
     */
    private function resolverPresentacionCaja(int $empresaId, string $fechaJornada, array $filas): array
    {
        $hayGastro = false;
        $hayEstac = false;
        foreach ($filas as $fila) {
            $tipo = (string) ($fila['tipo_fila'] ?? '');
            if (in_array($tipo, ['pc', 'total_salon', 'total_gastro', 'post_cierre_caea'], true)) {
                $hayGastro = true;
            }
            if (in_array($tipo, ['estacionamiento_pv', 'total_estacionamiento'], true)) {
                $hayEstac = true;
            }
        }

        $jornadaGastro = $this->resolverJornada($empresaId, $fechaJornada);
        $gastroEstado = $jornadaGastro !== null ? (string) ($jornadaGastro->estado ?? '') : null;
        $gastroId = $jornadaGastro !== null ? (int) $jornadaGastro->id : null;
        $gastroAplica = $hayGastro || ($jornadaGastro !== null && $gastroEstado === JornadaGastronomia::ESTADO_CERRADA);
        $gastroPresentada = null;
        if ($gastroAplica && $gastroId !== null && $gastroEstado === JornadaGastronomia::ESTADO_CERRADA) {
            $gastroPresentada = RendicionGastronomiaCaja::query()
                ->where('jornada_gastronomia_id', $gastroId)
                ->where('tipo', RendicionGastronomiaCaja::TIPO_JORNADA)
                ->exists();
        }

        $jornadaEstac = JornadaEstacionamiento::query()
            ->where('empresa_id', $empresaId)
            ->whereDate('fecha_jornada', $fechaJornada)
            ->orderByDesc('id')
            ->first(['id', 'estado']);
        $estacEstado = $jornadaEstac !== null ? (string) ($jornadaEstac->estado ?? '') : null;
        $estacId = $jornadaEstac !== null ? (int) $jornadaEstac->id : null;
        $estacAplica = $hayEstac || ($jornadaEstac !== null && $estacEstado === JornadaEstacionamiento::ESTADO_CERRADA);
        $estacPresentada = null;
        if ($estacAplica && $estacId !== null && $estacEstado === JornadaEstacionamiento::ESTADO_CERRADA) {
            $estacPresentada = RendicionEstacionamientoCaja::query()
                ->where('jornada_estacionamiento_id', $estacId)
                ->where('tipo', RendicionEstacionamientoCaja::TIPO_JORNADA)
                ->exists();
        }

        $avisos = [];
        if ($gastroAplica && $gastroEstado === JornadaGastronomia::ESTADO_ABIERTA) {
            $avisos[] = 'Gastronomía: la jornada de ventas sigue abierta; rendgastro Z aún no es comparable.';
        } elseif ($gastroAplica && $gastroPresentada === false) {
            $avisos[] = 'Gastronomía: la jornada aún no está presentada en Caja. '
                .'En Anita el Z suele quedar en 0 hasta esa presentación; los DIF rendg pueden ser esperables.';
        }

        if ($estacAplica && $estacEstado === JornadaEstacionamiento::ESTADO_ABIERTA) {
            $avisos[] = 'Estacionamiento: la jornada de ventas sigue abierta; rendgastro Z aún no es comparable.';
        } elseif ($estacAplica && $estacPresentada === false) {
            $avisos[] = 'Estacionamiento: la jornada aún no está presentada en Caja. '
                .'En Anita el Z suele quedar en 0 hasta esa presentación; los DIF rendg pueden ser esperables.';
        }

        $pendiente = ($gastroAplica && $gastroPresentada === false)
            || ($estacAplica && $estacPresentada === false)
            || ($gastroAplica && $gastroEstado === JornadaGastronomia::ESTADO_ABIERTA)
            || ($estacAplica && $estacEstado === JornadaEstacionamiento::ESTADO_ABIERTA);

        return [
            'gastro_aplica' => $gastroAplica,
            'gastro_presentada' => $gastroPresentada,
            'gastro_jornada_id' => $gastroId,
            'gastro_estado_ventas' => $gastroEstado,
            'estac_aplica' => $estacAplica,
            'estac_presentada' => $estacPresentada,
            'estac_jornada_id' => $estacId,
            'estac_estado_ventas' => $estacEstado,
            'pendiente' => $pendiente,
            'avisos' => $avisos,
        ];
    }

    /**
     * @param  array{
     *   pendiente: bool,
     *   gastro_presentada: bool|null,
     *   gastro_estado_ventas: string|null,
     *   estac_presentada: bool|null,
     *   estac_estado_ventas: string|null
     * }  $presentacionCaja
     * @param  list<array<string, mixed>>  $filas
     */
    private function clasificarAlerta(bool $requiereAlerta, array $presentacionCaja, array $filas): string
    {
        if (! $requiereAlerta) {
            return 'ok';
        }

        $hayDifGastro = false;
        $hayDifEstac = false;
        foreach ($filas as $fila) {
            $tipo = (string) ($fila['tipo_fila'] ?? '');
            if (! in_array($tipo, ['pc', 'estacionamiento_pv'], true)) {
                continue;
            }
            $esDif = GastronomiaConciliacionEstadoSupport::requiereAlertaRendg(
                (string) ($fila['estado_rendg'] ?? ''),
            ) || in_array((string) ($fila['estado'] ?? ''), ['DIF rendg', 'SIN RENDG', 'DIF'], true);
            if (! $esDif) {
                continue;
            }
            if ($tipo === 'pc') {
                $hayDifGastro = true;
            } else {
                $hayDifEstac = true;
            }
        }

        $difInexplicado = false;
        if ($hayDifGastro && ! $this->difExplicablePorCajaPendiente(
            $presentacionCaja['gastro_presentada'] ?? null,
            $presentacionCaja['gastro_estado_ventas'] ?? null,
            JornadaGastronomia::ESTADO_ABIERTA,
        )) {
            $difInexplicado = true;
        }
        if ($hayDifEstac && ! $this->difExplicablePorCajaPendiente(
            $presentacionCaja['estac_presentada'] ?? null,
            $presentacionCaja['estac_estado_ventas'] ?? null,
            JornadaEstacionamiento::ESTADO_ABIERTA,
        )) {
            $difInexplicado = true;
        }

        if (! $difInexplicado && ! empty($presentacionCaja['pendiente'])) {
            return 'aviso_caja_pendiente';
        }

        return 'alerta';
    }

    private function difExplicablePorCajaPendiente(
        ?bool $presentada,
        ?string $estadoVentas,
        string $estadoAbierta,
    ): bool {
        if ($estadoVentas === $estadoAbierta) {
            return true;
        }

        return $presentada === false;
    }
}

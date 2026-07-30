<?php

declare(strict_types=1);

namespace App\Support\Ventas\Gastronomia;

use App\Models\Ventas\MaquinavendingRendicion;
use App\Models\Ventas\Puntoventa;
use App\Support\Caja\AnitaSync\RendicionGastronomiaAnitaRendgastroSupport;
use App\Support\Ventas\MaquinavendingRendicionAnitaContextBuilder;

/**
 * Rendiciones rendgastro de máquinas vending (PV manual Maquina N, host descriptivo en Informix).
 */
final class GastronomiaConciliacionVendingRendgSupport
{
    /** @var array<int, array<int, Puntoventa>> */
    private array $pvVendingPorSucursalCache = [];

    public function __construct(
        private readonly RendicionGastronomiaAnitaRendgastroSupport $rendgastroSupport,
    ) {
    }

    public function esCabeceraVending(object $fila, int $empresaId): bool
    {
        if ($this->rendgastroSupport->esCabeceraPostCierreWaitry($fila)) {
            return false;
        }

        if ($this->rendgastroSupport->esCabeceraEstacionamiento($fila, $empresaId)) {
            return false;
        }

        if ($this->rendgastroSupport->esCabeceraAgregadosCaea($fila)) {
            return false;
        }

        if (! MaquinavendingRendicionAnitaContextBuilder::esHostVending((string) ($fila->rendg_host ?? ''))) {
            return false;
        }

        $sucursal = (int) ($fila->rendg_sucursal ?? 0);

        return $sucursal > 0 && isset($this->puntoventasVendingPorSucursal($empresaId)[$sucursal]);
    }

    /**
     * Cuadre jornada: incluye legacy (host VENDING NRO.*, suc ≥ 1200) aunque no exista PV en ERP.
     */
    public function esCabeceraVendingCuadreJornada(object $fila, int $empresaId): bool
    {
        if ($this->esCabeceraVending($fila, $empresaId)) {
            return true;
        }

        if ($this->rendgastroSupport->esCabeceraPostCierreWaitry($fila)
            || $this->rendgastroSupport->esCabeceraEstacionamiento($fila, $empresaId)
            || $this->rendgastroSupport->esCabeceraAgregadosCaea($fila)) {
            return false;
        }

        $host = mb_strtoupper(trim((string) ($fila->rendg_host ?? '')));
        if ($host === '' || ! str_starts_with($host, 'VENDING NRO')) {
            return false;
        }

        $sucursal = (int) ($fila->rendg_sucursal ?? 0);

        return $sucursal >= 1200;
    }

    /**
     * RMV Z vending en rendgastro Anita (rendg_total_z − rendg_tot_nc por PV). Solo datos del cache/bridge.
     *
     * @param  list<object>  $cabecerasRendgDia
     * @return array{
     *   total: float,
     *   por_pv: list<array{
     *     pv_sucursal: int,
     *     pv_codigo: string,
     *     rmv_z: float,
     *     rmv_nc: float,
     *     neto: float,
     *     rendg_nro_oper: int|null
     *   }>
     * }
     */
    public function ventaAnitaVendingDesdeRendg(int $empresaId, array $cabecerasRendgDia): array
    {
        $pvPorSucursal = $this->puntoventasVendingPorSucursal($empresaId);

        /** @var array<int, list<object>> $porSucursal */
        $porSucursal = [];
        foreach ($cabecerasRendgDia as $fila) {
            if (! $this->esCabeceraVendingCuadreJornada($fila, $empresaId)) {
                continue;
            }
            $sucursal = (int) ($fila->rendg_sucursal ?? 0);
            if ($sucursal <= 0) {
                continue;
            }
            $porSucursal[$sucursal][] = $fila;
        }

        $porPv = [];
        $total = 0.0;

        foreach ($porSucursal as $sucursal => $grupo) {
            $neto = $this->rendgastroSupport->netoGrupoHost($grupo);
            $portadora = $this->rendgastroSupport->elegirPortadora($grupo);
            $z = round((float) ($portadora->rendg_total_z ?? 0), 2);
            $nc = round($this->rendgastroSupport->sumaNcCabeceras($grupo), 2);
            $pv = $pvPorSucursal[$sucursal] ?? null;

            $porPv[] = [
                'pv_sucursal' => $sucursal,
                'pv_codigo' => $pv !== null ? (string) ($pv->codigo ?? (string) $sucursal) : (string) $sucursal,
                'rmv_z' => $z,
                'rmv_nc' => $nc,
                'neto' => $neto,
                'rendg_nro_oper' => $portadora !== null ? (int) ($portadora->rendg_nro_oper ?? 0) : null,
            ];
            $total += $neto;
        }

        usort($porPv, static fn (array $a, array $b): int => $a['pv_sucursal'] <=> $b['pv_sucursal']);

        return [
            'total' => round($total, 2),
            'por_pv' => $porPv,
        ];
    }

    /**
     * @return array<string, float>
     */
    public function totalesMaquinavendingErpPorJornada(int $empresaId, string $desde, string $hasta): array
    {
        $rows = MaquinavendingRendicion::query()
            ->where('empresa_id', $empresaId)
            ->whereDate('fecha_jornada', '>=', $desde)
            ->whereDate('fecha_jornada', '<=', $hasta)
            ->selectRaw('DATE(fecha_jornada) as fecha_jornada')
            ->selectRaw('SUM(total_ventas) as neto')
            ->groupByRaw('DATE(fecha_jornada)')
            ->get();

        $map = [];
        foreach ($rows as $row) {
            $fecha = substr((string) ($row->fecha_jornada ?? ''), 0, 10);
            if ($fecha === '') {
                continue;
            }
            $map[$fecha] = round((float) ($row->neto ?? 0), 2);
        }

        return $map;
    }

    /**
     * @return array{
     *   filas: list<array<string, mixed>>,
     *   totales: array{ventas_erp: float, rendgastro_z: float, cantidad: int}
     * }
     */
    public function filasReporte(
        int $empresaId,
        string $fechaJornada,
        float $tolerancia,
        bool $jornadaAbierta,
    ): array {
        if ($jornadaAbierta || ! $this->conciliaVendingJornada($fechaJornada)) {
            return ['filas' => [], 'totales' => ['ventas_erp' => 0.0, 'rendgastro_z' => 0.0, 'cantidad' => 0]];
        }

        $fechaEntera = (int) str_replace('-', '', $fechaJornada);
        $cabeceras = $this->rendgastroSupport->listarCabecerasEmpresaFechaDetalle($empresaId, $fechaEntera);
        $pvPorSucursal = $this->puntoventasVendingPorSucursal($empresaId);
        $erpPorSucursal = $this->totalesErpPorSucursal($empresaId, $fechaJornada);

        /** @var array<int, list<object>> $porSucursal */
        $porSucursal = [];
        foreach ($cabeceras as $fila) {
            if (! $this->esCabeceraVending($fila, $empresaId)) {
                continue;
            }
            $sucursal = (int) ($fila->rendg_sucursal ?? 0);
            if ($sucursal <= 0) {
                continue;
            }
            $porSucursal[$sucursal][] = $fila;
        }

        $sucursales = array_unique(array_merge(array_keys($porSucursal), array_keys($erpPorSucursal)));
        sort($sucursales);

        $filas = [];
        $sumErp = 0.0;
        $sumRendg = 0.0;

        foreach ($sucursales as $sucursal) {
            $pv = $pvPorSucursal[$sucursal] ?? null;
            if ($pv === null) {
                continue;
            }

            $grupo = $porSucursal[$sucursal] ?? [];
            $rendgZ = $grupo !== []
                ? $this->rendgastroSupport->netoGrupoHost($grupo)
                : 0.0;
            $erpTotal = round((float) ($erpPorSucursal[$sucursal] ?? 0), 2);

            if ($erpTotal <= $tolerancia && $rendgZ <= $tolerancia) {
                continue;
            }

            $portadora = $grupo !== [] ? $this->rendgastroSupport->elegirPortadora($grupo) : null;
            $codigoPv = (string) ($pv->codigo ?? '');
            $diffRendg = round($erpTotal - $rendgZ, 2);

            $filas[] = GastronomiaConciliacionEstadoSupport::aplicarEstadosEnFila([
                'tipo_fila' => 'vending_pv',
                'circuito' => 'VENDING',
                'tipo_pv' => 'VENDING',
                'identificador_pc' => 'VENDING-'.$codigoPv,
                'pv_codigo' => $codigoPv,
                'descripcion_pc' => trim((string) ($pv->nombre ?? 'Maquina vending'))
                    .($portadora !== null ? ' (rendg '.trim((string) ($portadora->rendg_host ?? 'VENDING')).')' : ''),
                'pv_cae' => '—',
                'pv_caea' => '—',
                'ventas_erp_cae' => 0.0,
                'ventas_erp_caea' => 0.0,
                'ventas_erp' => $erpTotal,
                'ventas_anita_cae' => 0.0,
                'ventas_anita_caea' => 0.0,
                'ventas_anita' => $rendgZ,
                'rendgastro_z' => $rendgZ,
                'rendgastro_z_cae' => null,
                'rendgastro_caea' => null,
                'rendgastro_nro_oper' => $portadora !== null ? (int) ($portadora->rendg_nro_oper ?? 0) : null,
                'rendg_sucursal' => $sucursal,
                'diff_erp_anita' => round($erpTotal - $rendgZ, 2),
                'diff_erp_rendg' => $diffRendg,
                'cantidad_facturas_erp' => 0,
                'es_vending_pv' => true,
            ], $tolerancia);

            $sumErp += $erpTotal;
            $sumRendg += $rendgZ;
        }

        return [
            'filas' => $filas,
            'totales' => [
                'ventas_erp' => round($sumErp, 2),
                'rendgastro_z' => round($sumRendg, 2),
                'cantidad' => count($filas),
            ],
        ];
    }

    /**
     * @return array<int, float>
     */
    private function totalesErpPorSucursal(int $empresaId, string $fechaJornada): array
    {
        $map = [];
        $rendiciones = MaquinavendingRendicion::query()
            ->where('empresa_id', $empresaId)
            ->whereDate('fecha_jornada', $fechaJornada)
            ->with(['maquinavending.puntoventa'])
            ->get();

        foreach ($rendiciones as $rendicion) {
            $sucursal = MaquinavendingRendicionAnitaContextBuilder::codigoPuntoventaEntero(
                $rendicion->maquinavending?->puntoventa?->codigo,
            );
            if ($sucursal <= 0) {
                continue;
            }
            $map[$sucursal] = round(($map[$sucursal] ?? 0.0) + (float) $rendicion->total_ventas, 2);
        }

        return $map;
    }

    /**
     * @deprecated Use filasReporte; mantiene clave totales.rendgastro_z por compatibilidad interna.
     * @param  array{rendgastro_z: float, ventas_erp?: float, cantidad: int}  $totalesVending
     * @return array<string, mixed>
     */
    public function filaTotalVending(array $totalesVending, bool $jornadaAbierta, float $tolerancia = 0.02): array
    {
        $erp = round((float) ($totalesVending['ventas_erp'] ?? 0), 2);
        $rendgZ = round((float) ($totalesVending['rendgastro_z'] ?? 0), 2);
        $diff = $jornadaAbierta ? null : round($erp - $rendgZ, 2);

        return GastronomiaConciliacionEstadoSupport::aplicarEstadosEnFila([
            'tipo_fila' => 'total_vending',
            'circuito' => 'VENDING',
            'identificador_pc' => 'TOTAL-VENDING',
            'tipo_pv' => 'TOTAL',
            'pv_codigo' => '—',
            'descripcion_pc' => 'Total vending (rendiciones ERP vs rendgastro)',
            'pv_cae' => '',
            'pv_caea' => '',
            'ventas_erp_cae' => 0.0,
            'ventas_erp_caea' => 0.0,
            'ventas_erp' => $erp,
            'ventas_anita_cae' => 0.0,
            'ventas_anita_caea' => 0.0,
            'ventas_anita' => $rendgZ,
            'rendgastro_z' => $jornadaAbierta ? null : $rendgZ,
            'rendgastro_neto' => $jornadaAbierta ? null : $rendgZ,
            'diff_erp_anita' => $diff ?? 0.0,
            'diff_erp_rendg' => $diff,
            'estado' => $jornadaAbierta || ($erp <= $tolerancia && $rendgZ <= $tolerancia) ? '—' : '',
            'cantidad_facturas_erp' => (int) ($totalesVending['cantidad'] ?? 0),
            'es_total' => true,
            'es_vending_total' => true,
            'jornada_abierta' => $jornadaAbierta,
        ], $tolerancia);
    }

    /** @return array<int, Puntoventa> */
    public function puntoventasVendingPorSucursal(int $empresaId): array
    {
        if (isset($this->pvVendingPorSucursalCache[$empresaId])) {
            return $this->pvVendingPorSucursalCache[$empresaId];
        }

        $map = [];
        $puntoventas = Puntoventa::query()
            ->where('empresa_id', $empresaId)
            ->where('modofacturacion', 'M')
            ->where(function ($q) {
                $q->where('nombre', 'like', 'Maquina %')
                    ->orWhere('nombre', 'like', 'Maquina%')
                    ->orWhere('nombre', 'like', 'Máquina %')
                    ->orWhere('nombre', 'like', 'Máquina%');
            })
            ->whereRaw('LOWER(nombre) NOT LIKE ?', ['%estacionamiento%'])
            ->whereRaw('LOWER(nombre) NOT LIKE ?', ['%estac.%'])
            ->get();

        foreach ($puntoventas as $pv) {
            $sucursal = (int) preg_replace('/\D+/', '', (string) $pv->codigo);
            if ($sucursal > 0) {
                $map[$sucursal] = $pv;
            }
        }

        return $this->pvVendingPorSucursalCache[$empresaId] = $map;
    }

    public function conciliaVendingJornada(string $fechaJornada): bool
    {
        $desde = trim((string) config('gastronomia.conciliacion_diaria_reporte.vending_jornada_desde', ''));
        if ($desde === '') {
            return true;
        }

        return $fechaJornada >= $desde;
    }
}
